from pathlib import Path
import subprocess, tempfile, shutil, json, socket, time, urllib.request, urllib.error

project = Path(__file__).resolve().parents[1]
repo = project if (project/'mikanBox/lib/device-auth.php').exists() else project.parent/'mikanBox-sqlite'
with tempfile.TemporaryDirectory(prefix='mikanbox-migration-security-') as temp:
    site = Path(temp)
    for filename in subprocess.check_output(['git','ls-files'], cwd=repo, text=True).splitlines():
        if filename == 'index.php' or filename.startswith('mikanBox/') or filename == '.htaccess':
            source = repo / filename
            if source.is_file():
                dest = site / filename
                dest.parent.mkdir(parents=True, exist_ok=True)
                shutil.copy2(source, dest)
    data = site / 'mikanData'
    data.mkdir()
    (data / 'posts').mkdir()
    (data / 'components').mkdir()
    (data / 'settings.json').write_text(json.dumps({'mcp_api_key':'AUDIT_FAKE_KEY_ONLY','password_hash':'AUDIT_FAKE_PASSWORD_HASH','site_name':'Audit'}))
    (data / 'posts' / 'private.json').write_text(json.dumps({'status':'draft','title':'Private','content_md':'AUDIT_PRIVATE_DRAFT'}))
    subprocess.run(['php','-r','require $argv[1]."/mikanBox/config.php";', str(site)],check=True,stdout=subprocess.DEVNULL)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1',0)); port = sock.getsockname()[1]
    with tempfile.TemporaryFile() as logs:
        server = subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(site),str(site/'index.php')],stdout=logs,stderr=logs)
        try:
            base = f'http://127.0.0.1:{port}'
            def request(path):
                try: return urllib.request.urlopen(base+path,timeout=2)
                except urllib.error.HTTPError as error: return error
            for attempt in range(80):
                try:
                    response=request('/mikanBox/import/oldsystem_imported/settings.json')
                    assert response.status in [403,404]
                    break
                except OSError:
                    if attempt == 79: raise
                    time.sleep(.05)
            assert request('/mikanBox/import/oldsystem_imported/posts/private.json').status in [403,404]
            assert request('/mikanData/private-import/').status in [403,404]
            backups=list((data/'private-import').glob('oldsystem-*/settings.json'))
            assert len(backups)==1 and 'AUDIT_FAKE_KEY_ONLY' in backups[0].read_text()
            # Repair historical copies without overwriting current DB content.
            legacy=site/'mikanBox/import/oldsystem_imported'; legacy.mkdir()
            (legacy/'.htaccess').write_text('Require all granted')
            (legacy/'settings.json').write_text(json.dumps({'mcp_api_key':'OLD_EXPOSED_KEY'}))
            subprocess.run(['php','-r','require $argv[1]."/mikanBox/config.php"; if (!empty(loadSettings()["mcp_api_key"])) exit(1); if ((loadData(POSTS_DIR,"private")["content_md"] ?? "") !== "AUDIT_PRIVATE_DRAFT") exit(2);',str(site)],check=True,stdout=subprocess.DEVNULL)
            assert not legacy.exists()
            secured=list((data/'private-import').glob('oldsystem_imported-*'))
            assert len(secured)==1 and not (secured[0]/'.htaccess').exists()
            assert list(secured[0].glob('.htaccess.saved-*'))
            assert request('/mikanBox/import/oldsystem_imported/settings.json').status in [403,404]
            print('SQLite migration: protected backup, legacy repair, key revocation and private content preserved')
        finally:
            server.terminate();server.wait(timeout=5)
