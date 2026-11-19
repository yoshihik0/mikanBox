// ZIP files in the browser, in all three editions: MikanZip writes one (stored entries, no compression),
// MikanZip.open reads one lazily, mikanDownload saves a Blob. Canonical in shared/zip.js (shared/sync.sh
// copies it to the PHP editions' lib/ and to the Cloud edition as public/mikanbox/cloud-zip.js).
(() => {
  'use strict';
  const table = new Uint32Array(256).map((_, n) => { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; return c >>> 0; });
  const crc32 = bytes => { let c = 0xFFFFFFFF; for (let i = 0; i < bytes.length; i++) c = table[(c ^ bytes[i]) & 255] ^ (c >>> 8); return (c ^ 0xFFFFFFFF) >>> 0; };
  window.MikanZip = function () {
    const parts = [], central = [], encoder = new TextEncoder();
    let offset = 0, count = 0;
    const now = new Date(), time = (now.getHours() << 11) | (now.getMinutes() << 5) | (now.getSeconds() >> 1), date = ((now.getFullYear() - 1980) << 9) | ((now.getMonth() + 1) << 5) | now.getDate();
    this.add = (name, bytes) => {
      const path = encoder.encode(name.replace(/^\/+/, '')), crc = crc32(bytes);
      const local = new DataView(new ArrayBuffer(30));
      [[0, 0x04034b50, 4], [4, 20, 2], [6, 0x0800, 2], [8, 0, 2], [10, time, 2], [12, date, 2], [14, crc, 4], [18, bytes.length, 4], [22, bytes.length, 4], [26, path.length, 2], [28, 0, 2]]
        .forEach(([at, value, size]) => size === 4 ? local.setUint32(at, value, true) : local.setUint16(at, value, true));
      const entry = new DataView(new ArrayBuffer(46));
      [[0, 0x02014b50, 4], [4, 20, 2], [6, 20, 2], [8, 0x0800, 2], [10, 0, 2], [12, time, 2], [14, date, 2], [16, crc, 4], [20, bytes.length, 4], [24, bytes.length, 4], [28, path.length, 2], [30, 0, 2], [32, 0, 2], [34, 0, 2], [36, 0, 2], [38, 0, 4], [42, offset, 4]]
        .forEach(([at, value, size]) => size === 4 ? entry.setUint32(at, value, true) : entry.setUint16(at, value, true));
      parts.push(local.buffer, path, bytes); central.push(entry.buffer, path);
      offset += 30 + path.length + bytes.length; count++;
    };
    this.blob = () => {
      const size = central.reduce((total, part) => total + (part.byteLength ?? part.length), 0), end = new DataView(new ArrayBuffer(22));
      [[0, 0x06054b50, 4], [4, 0, 2], [6, 0, 2], [8, count, 2], [10, count, 2], [12, size, 4], [16, offset, 4], [20, 0, 2]]
        .forEach(([at, value, sz]) => sz === 4 ? end.setUint32(at, value, true) : end.setUint16(at, value, true));
      return new Blob([...parts, ...central, end.buffer], { type: 'application/zip' });
    };
  };
  /**
   * Reads a ZIP file lazily: only its directory is read at first, each entry when asked for, so a large
   * backup is never held whole. Stored and deflated entries are accepted; sizes and CRCs are checked.
   */
  window.MikanZip.open = async file => {
    const tail = new Uint8Array(await file.slice(Math.max(0, file.size - 65557)).arrayBuffer()), base = Math.max(0, file.size - 65557);
    const tv = new DataView(tail.buffer);
    let eocd = -1;
    for (let i = tail.length - 22; i >= 0; i--) if (tv.getUint32(i, true) === 0x06054b50) { eocd = i; break; }
    if (eocd < 0) throw new Error('not-zip');
    const count = tv.getUint16(eocd + 10, true), size = tv.getUint32(eocd + 12, true), offset = tv.getUint32(eocd + 16, true);
    const dir = new Uint8Array(await file.slice(offset, offset + size).arrayBuffer()), dv = new DataView(dir.buffer), decoder = new TextDecoder();
    const entries = new Map();
    for (let i = 0, at = 0; i < count; i++) {
      if (dv.getUint32(at, true) !== 0x02014b50) throw new Error('damaged');
      const method = dv.getUint16(at + 10, true), crc = dv.getUint32(at + 16, true), packed = dv.getUint32(at + 20, true), length = dv.getUint32(at + 24, true);
      const nameLength = dv.getUint16(at + 28, true), extra = dv.getUint16(at + 30, true), comment = dv.getUint16(at + 32, true), local = dv.getUint32(at + 42, true);
      const name = decoder.decode(dir.subarray(at + 46, at + 46 + nameLength)).replaceAll('\\', '/');
      at += 46 + nameLength + extra + comment;
      if (name.endsWith('/') || name.split('/').includes('..') || name.startsWith('/')) continue;
      entries.set(name, { method, crc, packed, length, local });
    }
    void base;
    return {
      names: () => [...entries.keys()],
      has: name => entries.has(name),
      read: async name => {
        const entry = entries.get(name); if (!entry) return null;
        const head = new DataView(await file.slice(entry.local, entry.local + 30).arrayBuffer());
        if (head.getUint32(0, true) !== 0x04034b50) throw new Error('damaged');
        const start = entry.local + 30 + head.getUint16(26, true) + head.getUint16(28, true), packed = file.slice(start, start + entry.packed);
        let bytes;
        if (entry.method === 0) bytes = new Uint8Array(await packed.arrayBuffer());
        else if (entry.method === 8) bytes = new Uint8Array(await new Response(packed.stream().pipeThrough(new DecompressionStream('deflate-raw'))).arrayBuffer());
        else throw new Error('unsupported');
        if (bytes.length !== entry.length || crc32(bytes) !== entry.crc) throw new Error('damaged');
        return bytes;
      },
    };
  };
  window.mikanDownload = (blob, name) => {
    const url = URL.createObjectURL(blob), link = document.createElement('a');
    link.href = url; link.download = name; document.body.append(link); link.click(); link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 60000);
  };
})();
