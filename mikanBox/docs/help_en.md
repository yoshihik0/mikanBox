<nav class="sidebar">

## Table of Contents

- <a href="#concept">Concept &amp; Features</a>
- <a href="#design-philosophy">Design Philosophy</a>
- <a href="#security-policy">Security Policy</a>
- <a href="#setup-dist">Setup &amp; Distribution</a>
- <a href="#overview">Overview</a>
- <a href="#common">Common</a>
- <a href="#page-mgmt">Pages</a>
- <a href="#page-edit">Page Edit</a>
- <a href="#visual-edit">Visual Editing</a>
- <a href="#site">Site</a>
- <a href="#design-mgmt">Design</a>
- <a href="#design-edit">Design editor</a>
- <a href="#media-mgmt">Media</a>
- <a href="#markdown">Markdown</a>
- <a href="#components">Components (custom tags)</a>
- <a href="#tags">Available tags</a>
- <a href="#init-comps">Initial components</a>
- <a href="#data-embedding">Embedding and loading data, and the API</a>
- <a href="#output-formats">Output formats (Markdown version and data)</a>
- <a href="#catalog-pager">Create a paginated list with catalog data</a>
- <a href="#podcast">Podcast publishing</a>
- <a href="#site-mapfeed">Sitemap, RSS, and API</a>
- <a href="#ai-guide">Publish AI-generated designs</a>
- <a href="#announcement">Announcement</a>
- <a href="#license">License</a>

</nav>
<main>

<div class="manual-editions" role="group" aria-label="Edition"><strong>Edition</strong> <button type="button" data-edition-choice="flat">flat</button> <button type="button" data-edition-choice="sqlite">sqlite</button> <button type="button" data-edition-choice="cloud">cloud</button> <button type="button" data-edition-choice="all">all</button> <label><input type="checkbox" id="manual-details"> Show details</label></div>

<style>
.manual-version {
  font-size: 0.5em;
  font-weight: 400;
  white-space: nowrap;
}
.type-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.75em;
  font-weight: 600;
  white-space: nowrap;
  line-height: 1.6;
}
.badge-sqlite { background:#fef3c7; color:#92400e; border:1px solid #fde68a; }
.badge-flat { background:#dbeafe; color:#1e40af; border:1px solid #bfdbfe; }
.badge-page { background:#c9e8d7; color:#2d6a4f; border:1px solid #a8d4be; }
.badge-part { background:#dde3ea; color:#1e293b; border:1px solid #c4cdd6; }
.badge-ai { background:#fce7f3; color:#9d174d; border:1px solid #fbcfe8; }
body[data-edition="flat"] nav.sidebar a.cloud,
body[data-edition="sqlite"] nav.sidebar a.cloud {
  display: none;
}
.mat-icon {
  vertical-align: -0.2em;
  font-size: 1em;
}

# 🍊mikanBox User Manual <small class="manual-version">flat・SQLite v2.30.5 / Cloud v0.21.3</small>

🍊mikanBox comes in three editions: **🍊mikanBox** (the SQLite edition), which uses an SQLite database; **🍊mikanBox flat** (the flat edition), which uses JSON files without a database; and **🍊mikanBox Cloud** (the Cloud edition), which uses Cloudflare for both management and publication. Their basic usage is shared, so this help covers all three. Use the buttons at the top of the page (flat / sqlite / cloud / all) to select an edition. Badges identify content that differs by edition.

## Concept & Features {#concept}

🍊mikanBox is an ultra-lightweight, component-based CMS for the AI era, designed to build and operate websites of a few to a few dozen pages quickly and securely. Choose 🍊mikanBox flat (the JSON edition), which is also convenient for building static sites locally; 🍊mikanBox (the SQLite edition), for comfortably managing larger sites; or 🍊mikanBox Cloud, which runs on Cloudflare.

- File-based (JSON) — no database required; just place it on a server running PHP {.flat}
- SQLite (single-file database) — all data is stored in one SQLite database file; just place it on a server running PHP {.sqlite}
- Runs on Cloudflare — edit your site and publish a static site on Cloudflare. No R2 is needed for images, so you can start on the free tier with a Cloudflare account {.cloud}
- Modeless UI — complete work on one screen without page transitions
- Markdown support — edit content easily and use it as an information archive
- Category filters — filter pages and images by category and use categories as workspaces
- Display images by filename alone — insert images without worrying about file paths
- Component structure — combine page templates and parts
- Component-level CSS — write CSS for a small scope without worrying about interference
- Place AI-generated code directly — run a site without designing it by hand
- AI agent integration (MCP) — AI can understand the site structure and design direction and read and write files directly
- Manage DESIGN.md — manage instructions for AI on the site and share them with AI
- Multimodal AI — AI can generate images and send them directly to the media folder
- Static (SSG), dynamic, or both — mix static and dynamic content {.flat .sqlite}
- Greater security by separating the public site from the management site {.cloud}
- Check for tampering and easily review operation history
- DB Less DB — embed data in pages and output it through an API; also use it as a headless CMS
- Podcasts — automatically generate RSS for podcast distribution

---

## Design Philosophy {#design-philosophy}

### 🍊mikanBox flat: For Small Sites {.flat}

🍊mikanBox flat is not intended for sites with thousands of pages. A small site whose information needs updating does not necessarily need a heavy database system. By deliberately avoiding a database, it offers:

- Easy installation
- A simple structure that is easy to learn
- Easy backup and server migration

🍊mikanBox flat is also easy to run on your own computer. Open the site folder as an AI working folder and AI can read and write the JSON files directly, create the site, and upload it to a hosting server or Cloudflare. Because only exported static files need to be placed on the public server, you can keep the admin panel and work-in-progress data on your computer.

### 🍊mikanBox: For Sites with More Pages {.sqlite}

🍊mikanBox (the SQLite edition) also supports somewhat larger sites. Its single-file SQLite database retains most of the simplicity:

- Easy installation
- Easy backup and server migration
- Support for sites with more pages
- Multi-user editing and an approval system
- Editing history

### 🍊mikanBox Cloud: Run on Cloudflare {.cloud}

🍊mikanBox Cloud is for running a site on Cloudflare. Articles and settings are stored in D1 and edited from registered browsers. Separate Workers handle management and publication, and the public Worker serves the built static files.

- Run on fast, secure Cloudflare
- Manage a static site online with multiple editors
- Visitors do not use the admin Worker or D1 after publication
- No R2 required, so you can start on the free tier without registering a payment method

### Flexible Design

Pages and components are associated with their own CSS, making the structure easy to understand and avoiding interference. You can create flexible site designs by combining components.

- Pages and components can have CSS and JavaScript
- Scope CSS to prevent interference
- Nest components
- Easily create the equivalent of a WordPress theme, including with AI
- Media paths are resolved automatically
- Easily embed data in pages
- Load and use data from other pages and sites
- Store data in various forms for many uses

### AI-Friendly

- Use MCP to let AI remotely control everything from writing articles to design
- Manage AI instructions such as DESIGN.md and let AI read them, or have AI generate a design system from a design
- Compact, simple code is easy for AI to understand, making it easy to create pages and designs using the data structure
- Paste in AI-generated pages and publish elaborate designs with little additional work
- Use `{{EXT_MD:url}}` to source external Markdown, such as GitHub files, so an AI agent can update a repository and thereby update the site without opening the admin panel {.flat .sqlite}
- The small codebase is easy for AI to understand, so AI can modify the source to add features instead of using plugins

---

## Security Policy {#security-policy}

### Reduce Stored Information and Public Entry Points

🍊mikanBox is intended for small informational sites. It has no visitor registration or payment features, so you can minimize the information your site holds by not collecting personal data. It has no system for installing feature plugins or themes and keeps external library dependencies low.

### Admin Access and Authentication

The admin panel includes CSRF protection against writes from other sites. In a public environment, HTTP access to the admin panel redirects to HTTPS to protect communications. Repeated incorrect passwords or device registration codes temporarily restrict further attempts.

- **flat password authentication.** When installed on a server, someone with the setup token sets the admin password. The password is stored as a hash. See [Initial Setup and Sign-in](#first-setup). {.flat}
- **SQLite and Cloud device authentication.** Devices are authenticated instead of using a password. The key is stored in the browser, and only its hash remains on the server. Issue a single-use code to add another device. A lost device can be disabled from another registered device. See [Devices and Users](#site-users). {.sqlite .cloud}

### Separate Editing and Publication

With the flat and SQLite editions, you can edit and preview on your computer, export a static site locally, and send the files to a hosting server or Cloudflare. Open the local folder as an AI working folder to let AI create content including images, build pages, and transfer files to the public destination. Only exported files are placed on the public server, so the admin panel and work-in-progress data stay private and PHP does not run for visitors. See [Static Site Generation](#site-ssg). {.flat .sqlite}

In the Cloud edition, access to the admin panel, MCP, and D1 is confined to the admin Worker; a separate public Worker serves static files at a different address. {.cloud}

#### Example: Authenticate the Admin Side on the Same Server {#php-separated-publishing .flat .sqlite}

If the admin panel is at `example.com/blog/mikanBox/admin.php` and the site is published under `/blog/`, configure web-server authentication for `/blog/mikanBox/`. Public pages and the sibling `/blog/media/` folder can be served without authentication.

To separate publication under `example.com/pub/`, use `/blog/` only for administration and preview, protect the entire host or path with authentication, and serve only static files from `/pub/`. Keep the admin MCP on the admin side as well.

| Purpose | Public address | Example file locations |
| --- | --- | --- |
| Administration and preview | `https://example.com/blog/` | `index.php` and `mikanBox/` in `/srv/www/blog/` |
| Public pages | `https://example.com/pub/` | Static build output in `/srv/www/pub/` |
| Media | `https://example.com/pub/media/` | Uploaded files in `/srv/www/pub/media/` |

Select “Generate static pages on the server,” set the output directory to `../pub` and the public root URL to `https://example.com/pub`, and select [Place media in the output media/ directory](#ssg-media-in-output). If `pub.example.com` serves the same public folder, set the public root URL to `https://pub.example.com`.

Disable PHP and CGI execution and directory listings on the public side. Give admin-side PHP permission to write to the public and media destinations, and give the public side the read access needed to serve them. Keep the admin program, data, and setup token outside the public serving area. Apply the media `.htaccess` to SVGs or an equivalent `Content-Security-Policy: sandbox`. In a signed-out browser, check that public pages and images open and that the admin side requires authentication. Media placed on the public side is served as soon as it is uploaded.

### Protect Data, Uploads, and AI Integration

- **Data folder.** The PHP editions protect the data folder with Apache access-denial settings and the 🍊mikanBox entry point. If a warning appears in the admin panel, change the configuration so the folder cannot be read over the web. The flat edition uses JSON files; the SQLite edition separates SQL statements from input values with prepared statements. {.flat .sqlite}
- **Uploads.** Scripts and similar content are removed from SVGs on upload. JS and CSS are also checked for format and content requirements. Check the source and contents of code before using it on public pages. See [Media](#media-mgmt).
- **Admin MCP.** An API key is shown only once when issued; only its hash is stored on the server. Use separate keys for each AI tool so you can identify their activity in the operation log and disable only keys that are no longer needed. You can also disable admin MCP entirely when it is not in use. See [MCP API Key](#site-mcp-key).

### Verify Update Sources and Trace Changes

PHP system updates and Cloud GitHub updates resolve the commit pointed to by a public release tag before fetching it. Updates from the Cloud admin panel verify the official release tag and commit and check the distribution files' SHA-256 before applying them. In the 🍊mikanBox development repositories, configured Git hooks check for secrets before a push.

[Recent Activity](#site-audit) shows changes to pages, components, media, and settings made through the admin panel, MCP, and other interfaces. Run [Check for Tampering](#site-integrity) to compare public files and stored data with their recorded state and investigate differences that cannot be explained by recorded operations. In the PHP editions, program files are checked too.

---

## Setup & Distribution {#setup-dist}

This section explains how to install 🍊mikanBox and access the admin panel. After installation, update it under **Site → Version** in the admin panel (see [Updating](#setup-update)).

### Requirements {#requirements .flat .sqlite}

- PHP 8.0 or later (for a new installation, use the latest supported PHP version). The SQLite edition also needs the SQLite3 extension, which is standard on many hosting services.
- Apache with `.htaccess` enabled, to prevent browser access to the data folder. The admin panel is not designed to run on nginx or similar servers, though they can serve exported static files.
- HTTPS. It is not needed when running as `localhost` on your computer.

### Install a PHP Edition on a Server {#php-install .flat .sqlite}

#### 1. Obtain the Distribution

From the GitHub releases page ([mikanBox-flat releases](https://github.com/yoshihik0/mikanBox-flat/releases) for flat, or [mikanBox releases](https://github.com/yoshihik0/mikanBox/releases) for SQLite), download the latest **Source code (zip)** and extract it.

The extracted folder contains:

| Name | Contents | Place on server? |
|---|---|---|
| `index.php` | Entry point for public pages | Yes |
| `mikanBox/` | Admin panel and program | Yes |
| `setup-token/` | File for the setup token | Yes, for initial setup |
| `README.md` and others | Documentation and license | Not required |

`mikanData/`, which holds articles and settings, and `media/`, which holds images and other files, are not included. They are created automatically on first access.

#### 2. Write a Setup Token

The setup token prevents someone else from becoming the administrator immediately after installation. It is used once during initial setup.

1. Create a random string of at least 20 characters. A string consisting of repeated identical characters cannot be used. A password generator is a reliable way to create one.
2. Open `setup-token/setup-token.txt` and write the string on one line below the explanatory lines beginning with `#`. Save it as UTF-8 without a BOM.
3. Keep a copy of the string; you will enter it in the admin panel.

#### 3. Upload to the Server

Upload `index.php`, the `mikanBox` folder, and the `setup-token` folder to the same location on the server using FTP or another method. Place them in the document root for a site at the domain root, or in a `blog` folder for `https://example.com/blog/`.

- You may rename the admin folder to a less guessable name. If you do, change `$core_dir` near the top of `index.php` to the same name.
- Write permission: PHP must be able to write to the location containing `index.php`, where it creates `mikanData` and `media`.

#### 4. Open the Admin Panel and Complete Initial Setup

Open `https://example.com/mikanBox/admin.php` in a browser, using the renamed folder if applicable. On first access, `mikanData` is created with sample pages and components, followed by the initial setup screen.

- **flat** — Enter the setup token and an admin password of at least seven characters ([Set the First Password](#flat-setup)). {.flat}
- **SQLite** — Enter the setup token, username, display name, and device name to register this device ([Register the First Device](#device-setup)). {.sqlite}

After setup, `setup-token.txt` contains only its explanatory lines again. The token is no longer used. A site-root `.htaccess` is created if one does not already exist.

#### 5. Check That the Data Folder Is Protected

Each time the admin panel opens, it checks whether files inside the data folder are readable from a browser. If they are, a warning appears at the top of the screen. Settings, drafts, MCP API keys, and other information may be exposed in this state, so fix it before publishing.

- Check that the server uses Apache and has `.htaccess` enabled. Consult your hosting control panel or server administrator.
- If `.htaccess` cannot be used, configure the server to deny access to the data folder (`mikanData`), or move to a server where `.htaccess` is enabled.
- Reopen the admin panel and confirm that the warning has disappeared.

#### 6. First Steps After Installation

1. Under [Site Settings](#site-settings) on the Site screen, enter and save the site name. Change the admin panel language under [Language](#site-lang).
2. Review the sample pages on the Pages screen. The home page and most samples are **Public (Dynamic)** and visible to everyone immediately after installation. Edit the home page (ID `index`) and delete or make drafts of samples you do not need. The embeddable `sample_md` starts as a draft.
3. Check the publication status of pages you intend to publish. **Public (Static)** pages do not become files until you press **Build Static Site** ([Static Site Generation](#site-ssg)).
4. To operate the site from AI, issue an [MCP API key](#site-mcp-key).
5. To edit with other people, issue an invitation code under [Devices and Users](#site-users). {.sqlite}

#### Run on Your Computer {#php-local .flat .sqlite}

You can also run mikanBox on your computer without installing it on a server. Only PHP is required. You can build locally and send only exported static files to the public server.

1. Extract the distribution. `setup-token` is not used.
2. In a terminal, go to the folder containing `index.php` and run:

```sh
php -v                            # Check that it is 8.0 or later
php -S localhost:8000 index.php
```

3. Open `http://localhost:8000/mikanBox/admin.php` in a browser. A setup token is not required when accessing it from the same computer. In flat, set a password; in SQLite, register with a username and display name.
4. Press Ctrl+C in the terminal to stop.

Do not omit `index.php` at the end of the command. If omitted, the data folder becomes readable from a browser and a warning appears in the admin panel. This simple server is for use on your own computer. To share the site, [export a static site](#site-ssg) and publish it.

If you open the folder in an AI coding tool, AI can read and write page and component data directly. {.flat}

### Initial Setup and Sign-in {#first-setup .flat .sqlite}

#### Set the First Password {#flat-setup .flat}

Enter the setup token and a password of at least seven characters in the admin panel. On your own computer, only the password is needed. Thereafter, sign in with that password. Change it under [Change Password](#site-password) on the Site screen.

#### Register the First Device {#device-setup .sqlite}

The SQLite edition has no password; a registered browser serves as the key. Open the admin panel in **the browser you normally use**, enter the setup token, username (ASCII letters, digits, `_`, and `-`), display name, and device name, and press **Register this device**. You can then open the admin panel in that browser. To use another browser or computer, [add a device](#add-device).

#### Upgrade from 2.8.x {#upgrade-to-devices .sqlite .detail}

The old password and security-question answer are deleted during the update. Usernames, display names, pages, designs, and MCP API keys are retained.

1. Before updating, prepare a setup token in `setup-token/setup-token.txt` from the distribution.
2. After updating, open the admin panel and register the first device using the token, an existing username, and a device name.
3. Issue [addition codes](#add-device) for other users from a registered device.

If you used shared URLs for pages awaiting approval, send the new URLs after updating.

#### If All Devices Are Lost {#device-recovery .sqlite}

1. Write a new setup token to `setup-token/setup-token.txt` and upload it. A previously used value cannot be reused.
2. Open **Recovery (all devices lost)** on the sign-in screen and enter the new token, an existing username, and a device name.

Recovery invalidates all previous devices, unused codes, shared preview URLs, and MCP API keys. Issue replacements as needed.

### Install and Publish the Cloud Edition {#cloud-install .cloud}

The Cloud edition creates an admin panel (admin Worker), public site (public Worker), and database for articles and settings (D1) on Cloudflare. You need:

- A Cloudflare account (you can start on the free plan)
- Node.js 22 or later, npm, and a terminal on your working computer
- Proposed addresses for the admin panel and public site. You can start with `workers.dev` addresses and switch to custom domains later.

There are two installation methods. To update an existing site, use [Version (Cloud)](#site-version-cloud).

#### Ask AI to Install

1. Open an AI coding tool such as Claude Code in an empty working folder.
2. Ask it to install on Cloudflare from `https://github.com/yoshihik0/mikanBox-cloud`.
3. Follow its instructions to sign in to Cloudflare and press **Authorize** on the authorization screen. Answer when asked about custom domains and names for the admin panel and public site.
4. When installation is complete, AI gives you the admin address and the location of the file containing the setup token (`SETUP_TOKEN`). Open the admin panel in **the browser you normally use**, enter the token, and [register the first device](#cloud-auth). Do not paste the token into chat. Registering in the AI tool's built-in browser would register that browser as the device.
5. Save an API token under [Cloudflare Publish Settings](#cloudflare-publish-settings) on the Site screen. The field explains how to obtain one.
6. Press **Build Static Site** on the Pages screen and leave it open until the build finishes. Open the public site and verify that public pages and images appear and that drafts and pages awaiting approval cannot be opened.

#### Install with Commands {#cloud-install-commands .cloud .detail}

Prepare Cloudflare and GitHub accounts, an internet connection, Node.js 22 or later, npm, and a terminal. If using a custom domain, first make sure it can be connected in Cloudflare.

1. Download and extract **Source code (zip)** for the desired version from [Cloud releases](https://github.com/yoshihik0/mikanBox-cloud/releases), then go to the extracted folder in a terminal.
2. Install the required tools with `npm ci`.
3. Sign in to Cloudflare with `npx wrangler login`. Check the target account on the authorization screen and press **Authorize**. Back in the terminal, confirm the target with `npx wrangler whoami`.
4. Create a D1 database with `npx wrangler d1 create mikanbox-cloud` and note the displayed ID.
5. Copy `wrangler.production.example.json` to `wrangler.production.json` (admin) and `wrangler.public.example.json` to `wrangler.public.json` (public), then change the [configuration values](#cloud-install-config).
6. Confirm the publication target and deploy a placeholder with `npx wrangler deploy --config wrangler.public.json`. **Run this only once for a new installation.** Running it on an existing site replaces the public site with the placeholder.
7. Use the [secret-file creation command](#cloud-install-secrets) to save installation-specific `JWT_SECRET` and `SETUP_TOKEN` values in `.env.production.secrets`.
8. Prepare D1 with `npx wrangler d1 migrations apply DB --remote --config wrangler.production.json`.
9. Deploy the admin panel with `npm run deploy:cloud`.
10. Open the admin `/admin` in your usual browser and enter the `SETUP_TOKEN` from `.env.production.secrets` to [register the first device](#cloud-auth).
11. Save an API token with Workers Scripts Edit permission limited to the target account under [Cloudflare Publish Settings](#cloudflare-publish-settings).
12. Press **Build Static Site** and leave the screen open until it finishes. Verify that public pages and images appear and that drafts and pages awaiting approval cannot be opened.

Record the admin and public Worker names, Cloudflare account ID, and D1 ID. Configuration and secret files are needed for updates and recovery. Keep them safe and do not publish them on GitHub or elsewhere.

##### Configuration Values {#cloud-install-config}

| Field | Value |
|---|---|
| `account_id` in both files and admin `vars.CLOUDFLARE_ACCOUNT_ID` | Cloudflare account ID displayed by `whoami` |
| Admin `name` and `vars.CLOUDFLARE_WORKER_NAME` | Admin Worker name |
| Public `name` and admin `vars.CLOUDFLARE_PUBLIC_WORKER_NAME` | Public Worker name, different from the admin Worker name |
| Admin `vars.CLOUDFLARE_D1_ID` and `d1_databases[0].database_id` | D1 ID recorded in step 4 |
| Admin `vars.PUBLIC_SITE_URL` | Public site's HTTPS address, without a path |

With `workers.dev`, for example, the admin panel might be `https://admin.example-sub.workers.dev/admin` and the public site `https://mikan.example-sub.workers.dev/`. The subdomain is part of the public address. Do not use part of your email address or login ID.

##### Command to Create the Secrets File {#cloud-install-secrets}

Create two cryptographically random values and save them to a file. The command stops if the file already exists.

```sh
node -e "const fs=require('node:fs'),c=require('node:crypto'); fs.writeFileSync('.env.production.secrets', 'JWT_SECRET='+c.randomBytes(32).toString('hex')+'\nSETUP_TOKEN='+c.randomBytes(32).toString('hex')+'\n', {flag:'wx',mode:0o600})"
```

##### Change the Public Site URL {#cloud-change-public-url}

1. Connect the new domain to the public Worker.
2. Change `PUBLIC_SITE_URL` in the admin `wrangler.production.json`.
3. Redeploy the admin Worker with `npm run deploy:cloud`.
4. Press **Build Static Site** in the admin panel.
5. Check pages, images, sitemap, and RSS at the new URL. Migrate external links and search results as needed.

Changing the admin panel URL may also require devices to be authenticated again.

<a id="cloud-device-auth"></a>

### Device Authentication {#cloud-auth .cloud}

The Cloud edition has no password; a registered browser serves as the key. Open the admin panel (`/admin`) in **the browser you normally use**, enter the installation `SETUP_TOKEN`, username, display name, and device name, and press **Register this device**. Enter `SETUP_TOKEN` directly in the admin panel. A value used to register cannot be reused. To use another browser or computer, [add a device](#add-device).

#### If All Devices Are Lost {#cloud-device-recovery .cloud}

1. Set the admin Worker's `SETUP_TOKEN` to a new value. To do this with a command, run `npx wrangler secret put SETUP_TOKEN --config wrangler.production.json` in the installation folder and enter the new value. {.detail}
2. Use **Recovery** on the sign-in screen to register a device with the new token and an existing username.

Previous devices and unused codes become unusable, and MCP API keys are deleted. Issue replacements as needed.

### Add a Device or Invite Someone {#add-device .sqlite .cloud}

In a registered browser, open [Devices and Users](#site-users) on the Site screen.

| Goal | Action | Code validity |
|---|---|---|
| Add another browser or computer of your own | Issue a code with **Add a device** and enter it on the new device's sign-in screen | 3 hours |
| Invite someone else | Issue and share a code with **Invite someone**. The invitee chooses a username and display name | 6 hours |

Each code can be used only once. Before clearing browser cookies, make sure you have another usable device. If a device is lost, disable it from another registered device.

Register using your usual browser and profile. If you register in private mode, device information may be lost when the window closes.

### Updating {#setup-update}

Check **Version** on the Site screen in the admin panel to see whether a new version is available.

- **flat and SQLite** — Press **Update** under Version to replace only the program. Articles, settings, and images are untouched ([Version](#site-version-php)). {.flat .sqlite}
- **Cloud** — Review the changes on Site and press **Update** ([Version (Cloud)](#site-version-cloud)). Before updating, export and save your data under Data Management. {.cloud}

### Signing In to Multiple Sites {#multi-site-login .flat .sqlite}

When multiple sites are installed on the same domain, sign in separately to each. If your sign-in expires while editing, sign in on another tab, then save again on the original screen.

#### Details: Passwords Saved in the Browser {.flat .detail}

When saving a password in your browser, the storage site ID under **Change Password** on the Site screen helps distinguish which site it belongs to.

### Share Designs or Content with Others {.flat .sqlite}

When giving someone a copy of a site, prepare it so the recipient can complete initial setup themselves.

- **flat** — Clear `password_hash` in the copy's `mikanData/settings.json`. The recipient [sets their own password](#flat-setup). {.flat}
- **SQLite** — Place an empty `reset-accounts.txt` in the copy's `mikanData`. Existing users and devices are removed on the next access. The recipient [registers the first device](#device-setup) with their own token. {.sqlite}

If using MCP, the recipient issues their own [MCP API key](#site-mcp-key).

---

## Overview {#overview}

🍊mikanBox builds and manages a site through four sections.

### [description]{.material-symbols-outlined .mat-icon}Pages

Manage the pages that make up the site. Set a URL (ID), title, and SEO information, and write the body in Markdown or HTML.

### [save]{.material-symbols-outlined .mat-icon}Site

Manage site-wide settings and administration, including system updates, tampering checks, and data backups.

### [widgets]{.material-symbols-outlined .mat-icon}Design

Manage three component types: [Page]{.type-badge .badge-page} for a page's overall design, [Part]{.type-badge .badge-part} for shared elements, and [AI Instructions]{.type-badge .badge-ai} for instructions to AI agents. Create headers, footers, navigation, and other shared elements as parts to use them anywhere. This section primarily manages design elements (components).

### [image]{.material-symbols-outlined .mat-icon}Media

Manage primarily images: inspect, search, resize, and copy filenames for insertion. JS and CSS files uploaded as files are also managed here. The flat and SQLite editions also support video and audio uploads.

#### Side Menu

The left side of the screen has icons for the four sections: [[description]{.material-symbols-outlined} Pages]{.m-btn .m-btn-gray}, [[save]{.material-symbols-outlined} Site]{.m-btn .m-btn-gray}, [[widgets]{.material-symbols-outlined} Design]{.m-btn .m-btn-gray}, and [[image]{.material-symbols-outlined} Media]{.m-btn .m-btn-gray}. Press one to open its screen and move among them as you edit. A feature of 🍊mikanBox is that you can work by scrolling within one screen.

[[edit]{.material-symbols-outlined} Edit]{.m-btn .m-btn-gray} is added only while editing a page or design. Press its **×** on the right to close the editor and return to the list.

---

## Common {#common}

These elements appear at the top of each section, described in screen order.

#### Admin Panel Tab

The browser tab displays “🍊 + site name.” The 🍊 identifies the admin panel.

#### Preview {.flat .sqlite}

At the top right of the screen. Opens the site's home page in a new tab.

#### Logout {.flat}

Signs out of the system.

#### Help

Next to each section heading. Opens the official help page in a new tab.

#### Ask AI {#ask-ai}

Next to each section heading. Ask an external AI about operation or settings with the public help relevant to the open section as a reference.

1. Press **Ask AI** and enter your question.
2. Check the question and references in the displayed prompt.
3. Press **Copy Prompt**.
4. Press **Open ChatGPT** or **Open Claude**, paste the prompt into the AI input field, and send it.

For questions about mechanisms or limitations, the prompt includes an instruction to consult the specification in the same language from the public help. Do not enter API keys, passwords, personal information, or similar information in your question.

### Filtering by Category

Registered categories appear as a cloud of tags at the top of the screen. Use them to filter page and media lists. Categories are set in the page editor; each page can have multiple categories.

You can continue editing while filtered, using a category as a workspace. This makes even information-rich sites comfortable to edit. The selected category also applies when exporting data.

Categories beginning with `_`, such as `_nav` and `_sample`, mark categories used for site structure. They appear dimmed after ordinary categories in the list. See [Category](#page-edit-category) in the page editor for details.

#### All{.m-btn .m-btn-gray}

Clears the filter and shows all pages.

#### Category Name{.m-btn .m-btn-gray}

Click to show only pages with that category. The same filter applies to the media list. Files can be categorized using a `category_` filename prefix. See [Media category filter state](#media-category-state) for filenames and clearing the filter.

#### [add]{.material-symbols-outlined} New{.m-btn .m-btn-gray}

Enter a new category name directly to add it. It appears in the cloud immediately, even if no pages use it yet.

#### [delete]{.material-symbols-outlined} Delete{.m-btn .m-btn-gray}

Switches to deletion mode, showing an **×** on each category tag. Click the **×** or category name to remove that category from all pages at once. When pages use it, the number of affected pages is displayed.

---

## Pages {#page-mgmt}

This screen lists and manages your pages.

#### [add]{.material-symbols-outlined} Create New{.m-btn .m-btn-blue}

Creates a page and opens the page editor. When a category filter is active, the editor opens with that category already selected.

#### Filter by output format

Filter the page list by output format. You can combine this with category filters and search. The filter remains in place when you open or close the editor.

- All output formats
- HTML
- HTML + Markdown
- Markdown
- Data
- mikanBox DB

#### Page order

Choose how to sort the page list. The default, Display order, puts the home page (`index`) first, followed by pages awaiting approval. The remaining pages are ordered by ascending sort order, then most recent update, then ID. Other choices sort all pages by the selected criterion.

- Display order (default)
- Newest update first
- Oldest update first
- By ID
- By title

#### Keyword search

Search page IDs, titles, body text, and descriptions. You can also search while filtering by category or output format.

### Page list

#### Edit / Preview

Each page row has three icon buttons.

- [[edit]{.material-symbols-outlined}]{.m-btn .m-btn-blue} **Edit** — Opens <a href="#page-edit">Page Edit</a>.
- [[edit_square]{.material-symbols-outlined}]{.m-btn .m-btn-blue} **Visual Edit** — Opens <a href="#visual-edit">Visual Edit</a>. Available only for pages that output HTML; disabled for other formats.
- [[language]{.material-symbols-outlined}]{.m-btn .m-btn-blue} **Open the public page** — Opens the published page in a new tab. Disabled for unpublished pages (drafts and pages awaiting approval).

Use [Preview](#page-preview) in the page editor to check work in progress. See [Page Edit](#page-edit) for details.

In the PHP editions, [[language]{.material-symbols-outlined}]{.m-btn .m-btn-blue} is available for pages set to Public (Dynamic) or Public (Static). {.flat .sqlite}

##### Globe icon in Cloud {.cloud}

| Status | Published site | Globe | What opens |
|---|---|---|---|
| Published | Built with the latest changes | Blue | Latest published page |
| Published | No file | Gray | Cannot open |
| Published | File exists, with changes not yet published | Gray | Currently published page |
| Draft or pending approval | Whether or not a file exists | Gray | Cannot open |

#### Publication status {#page-list-status}

Choosing a status saves it immediately. Set pages you do not want to publish to Draft.

In SQLite and Cloud, you can set a page under review to Pending approval. {.sqlite .cloud}

In SQLite, you can share a [preview URL](#page-review-url) for a page awaiting approval. {.sqlite}

In the PHP editions, Public (Dynamic) displays the saved content. Switching a page to Public (Static) in the list builds it and writes it to the output directory. Changing it from Public (Static) to another status immediately deletes its generated file from the output directory. {.flat .sqlite}

In Cloud, [build](#static-build-button) after changing the status to publish or remove a page on the public site. A published file remains until the next build even if its page is changed to Draft or Pending approval. {.cloud}

For details, see [Publication status](#page-edit-status) under Page Edit.

#### ID

The identifier (slug) used in the page URL. For example, `news` produces a URL such as `/news`.

See [ID](#page-edit-id) under Page Edit.

#### Title

The page title. Click it to open the editor. Pages with output other than HTML show a format label to the right: `+MD` for HTML + Markdown, `MD` for Markdown, or `JSON`, `JS`, and so on for data formats.

See [Title](#page-edit-title) under Page Edit.

#### Updated

Shows the date of the page's update timestamp. The initial value is its creation time; it changes to the current time when the page is first published.

See [Updated](#page-editor-date) under Page Edit.

#### Order

The page's priority in navigation and other lists. Lower numbers appear earlier in `NAV_LINKS`, `NAV_CARDS`, and this page list. Numbers below zero are excluded from `NAV_LINKS` and `NAV_CARDS` lists.

See [Order](#page-edit-sort-order) under Page Edit.

#### Category

Shows the page's categories. They can be used for filtering, for example with `{{NAV_LINKS:category}}`. Categories beginning with `_` are used for site administration and appear dimmed.

See [Category](#page-edit-category) under Page Edit.

### Pagination

When the list spans multiple pages, page numbers and counts appear below it. Use [Previous]{.m-btn .m-btn-gray} and [Next]{.m-btn .m-btn-gray} to move between pages. Change the number shown per page in <a href="#site-settings">Site Settings</a>.

### Build static site {#static-build-button}

#### [auto_awesome]{.material-symbols-outlined} Build Static Site{.m-btn .m-btn-blue}

Build the static site to apply edits to pages set to Public (Static). {.flat .sqlite}

Build the static site to apply page edits to the public site. {.cloud}

Information below the button includes:

- Whether there are unpublished changes and the number of affected pages, or whether the public site is up to date.
- The date and time of the last build.
- The publication destination (the output folder in export mode).

Changes only to components or site settings are not included in the page count.

Cloud sends changed files to the public Worker. Keep the screen open until the build finishes. If asked to configure the public Worker, check [Connecting the public Worker](#public-worker). {.cloud}

---
## Page Edit {#page-edit}

Use this screen to edit a page's content and settings. It opens when you click **Create New**, a page title, or its edit button.

The SQLite and Cloud editions restrict editing of pages opened by another editor. {.sqlite .cloud}

The fields displayed depend on the selected [output format](#page-edit-output-format). Fields that vary by format state the applicable formats below their headings. Fields without such a note appear for every format.

| Field | HTML | HTML + Markdown | Markdown | Data formats and mikanBox DB |
|---|:-:|:-:|:-:|:-:|
| Design Component | Yes | Yes | — | — |
| Markdown version footer components | — | Yes | Yes | — |
| Expand tags | — | — | — | Yes (except mikanBox DB) |
| Thumbnail / OGP Image | Yes | Yes | Yes | — |
| Keywords | Yes | Yes | Yes | — |
| Page description | Yes | Yes | Yes | — |
| Content | Yes | Yes | Yes | Yes |
| Raw HTML | Yes | Yes | — | — |
| Custom CSS | Yes | Yes | — | — |
| Page JavaScript | Yes | Yes | — | — |

#### Updated {#page-editor-date}

Initially this is the creation date and time. It automatically changes to the date and time when the page is first published. It is retained when republishing a withdrawn page or editing a published page. You can change it manually here.

#### History {.sqlite .cloud}

Each save automatically retains up to ten previous versions, selectable by date and time and, on multi-user sites, editor name. Selecting an old revision loads it into the form and displays a **Viewing Past Revision** warning banner. Press [[save]{.material-symbols-outlined} Save]{.m-btn .m-btn-blue} to restore its contents as the current version. Select the first option, **Latest Version (Current)**, again to return to the latest contents.

In the SQLite edition, this appears only when saved history exists. {.sqlite}

#### [edit_square]{.material-symbols-outlined} Visual edit{.m-btn .m-btn-blue}

Opens [Visual Editing](#visual-edit). It is available only for pages that output HTML; for other formats it is gray and disabled.

In visual editing, you can also edit destination pages by following links written with the public site's address. You can navigate to draft HTML pages too. {.cloud}

#### Title {#page-edit-title}

The page title is required. Components can output it with `{{TITLE}}` or `{{FULL_TITLE}}` (in the form “Page title - Site name”).

#### Status {#page-edit-status}

Choose the publication status:

- **Draft** — Private; only administrators can access it.
- **Pending approval** — Private, but an unguessable review share URL (`?preview=token`, valid for seven days) is generated automatically and displayed here. Give the URL to a reviewer to let them check the content without signing in to the admin panel. {.sqlite}
- **Pending approval** — Private; only signed-in editors can preview it. This label indicates work status and has the same viewing permissions as a draft. {.cloud}
- **Public (Dynamic)** — Displayed dynamically using PHP. {.flat .sqlite}
- **Public (Static)** — Published as a static HTML file using the directory and file settings under Static Site Generation (SSG) on the Site screen. {.flat .sqlite}
- **Public** — A build creates files according to the output format (HTML, Markdown, JSON, and so on) and serves them as static files. {.cloud}

Changing **Public (Static)** to another status and saving deletes the generated static HTML file. {.flat .sqlite}

If a published page is changed to Draft or Pending Approval, it is removed from the public site when you [build](#static-build-button). {.cloud}

#### Preview Share URL (for internal review) {#page-review-url .sqlite}

Shown only when Status is Pending Approval. The URL appears in a text box.

Press [[content_copy]{.material-symbols-outlined} Copy]{.m-btn .m-btn-gray} to copy it to the clipboard. For a new page, the URL becomes valid after the first save.

The URL expires after seven days. Once expired, reopen this screen to find a new URL and share it again. The same URL is displayed throughout the same day. Review pages are configured not to send referrers, appear in search engines, or be cached.

#### Memo

A private note visible only to administrators. Use it for reminders or handoff notes. It is not displayed on the site or public API. AI can read it for context through authenticated admin MCP.

#### ID {#page-edit-id}

The page identifier (slug) used in its URL. ASCII letters, digits, hyphens, and underscores are allowed. A dot (`.`) is not allowed; the extension comes from the [output format](#output-formats).

An ID may contain `/`, but not at the beginning or end or consecutively. A `/` represents a hierarchy. For example, `news/2024` produces a URL like `site URL/news/2024`. IDs that overlap system paths cannot be used.

In the flat edition, changing the JSON folder structure also changes the ID. {.flat}

If you save with an ID already in use, a confirmation appears. Choose **Change ID** to return to the ID field without losing your input. Choose **Overwrite** to replace the page at that ID with the content being edited. If you changed an existing page's ID, the page at the original ID is removed.

**Keep the previous page as a draft** is selected by default. If left selected, overwriting keeps the destination page as a draft under a different ID. For example, the saved copy of `news` becomes `news-bk`, or `news-bk-2` if that is already used. Clear the checkbox to overwrite without creating a draft.

The home page ID is `index`. To replace the home page, change the current page's ID first, then give the new page the ID `index`.

In the PHP editions, the installation's admin and data folders and names such as `media`, `api`, and `setup-token` are reserved. {.flat .sqlite}

In the Cloud edition, names including `admin`, `mcp`, and `public-mcp` are also reserved. {.cloud}

#### Output format {#page-edit-output-format}

Choose one output format: HTML, HTML + Markdown, Markdown, or data (JSON, XML, CSV, text, JavaScript, CSS, or [mikanBox DB](#mikanbox-db)). HTML is the default. The actual output URL, such as `https://example.com/news-list.json`, appears immediately below.

The selected output format changes which settings are available; unused settings are hidden (see the table at the start of [Page Edit](#page-edit)). For details, see [Output Formats](#output-formats). The `404` page uses HTML.

**This setting controls the output format. Even if you enter Markdown in the content field, choose HTML if you want it converted to and displayed as HTML.**

For example, publish a list of recent articles as JSON and load it with JavaScript from pages to update the list without editing the content of the page displaying it. In Cloud, a build is required to reflect JSON changes on the public site.

#### Category (comma-separated) {#page-edit-category}

Enter page categories freely, separated by commas. Categories currently in use appear below the field. Category list pages are not created automatically. If needed, create a page and place `{{NAV_LINKS:category}}` or `{{NAV_CARDS:category:component ID}}` on it.

Categories beginning with `_`, such as `_nav`, `_sample`, and `_temp`, mark classifications used to operate the site. They are distinguished from content categories such as `coffee` by one character, and you can assign any number. They work in explicit selectors such as `{{NAV_LINKS:_nav}}`, but are excluded from [`@same`](#navigation), `{{CATEGORY}}`, and the `categories` field of the [Markdown version](#markdown-version). They are included in `{{CATEGORY:all}}`. Names beginning with `@` are reserved to distinguish special selectors such as `@same` and cannot be used as categories.

#### Design Component

Shown only for HTML and HTML + Markdown output formats.

Select the page component used for the page layout. Components whose Type is Page in Design Edit are available.

##### Do not load tracking tags on this page {#page-edit-no-tracking}

Shown only when tracking tags are configured in Site Settings and the output format is HTML or HTML + Markdown. Select this to omit tracking tags on this page only. See [Common Loading](#common-loading) in Site Settings for configuration.

#### Markdown version footer components

Shown only for HTML + Markdown and Markdown output formats.

Enter the IDs of components to append to the [Markdown version](#markdown-version), separated by commas in the order they should appear. Leave blank to append nothing. Only parts can be used; page components and AI instructions cannot.

Use this to add related information or similar content to Markdown.

#### Expand tags

Shown only for JSON, XML, CSV, text, JavaScript, and CSS output formats; not shown for mikanBox DB.

Choose whether to expand `{{ }}` tags in the content. It is enabled by default for JSON, XML, CSV, and text, and disabled by default for JavaScript and CSS. Values are escaped for the format ([Data Formats](#data-formats)).

#### Order {#page-edit-sort-order}

Controls the order in navigation and similar lists. Lower numbers come first. Pages with values below zero do not appear in page lists.

#### Thumbnail / OGP Image

Shown only for HTML, HTML + Markdown, and Markdown output formats.

Specify the image filename, such as `ogp.jpg`, to display in social shares and card lists. It is output as `og:image` in a `meta` tag. Use `{{OGP_IMAGE}}` in components.

#### Keywords

Shown only for HTML, HTML + Markdown, and Markdown output formats.

Enter search keywords (meta keywords) separated by commas. Components output them with `{{KEYWORDS}}`.

#### Description (meta description)

Shown only for HTML, HTML + Markdown, and Markdown output formats.

Set a description of the page. It is output with `{{DESCRIPTION}}` and used as the introduction shown under the title in search results.

#### Content

Write the page's main content in Markdown or HTML; the two can be mixed. Complex AI-generated HTML can be pasted directly and used. See the guide at the bottom of the editor for supported Markdown and custom tags, or see [Markdown](#markdown) and [Components (Tags)](#components).

This field appears for every output format. Its heading changes with the format, such as **Content (Markdown or HTML)**, **Content (Markdown)**, or **Content (JSON)**. For data formats, the content is output as entered ([Data Formats](#data-formats)).

##### Raw HTML

Shown only for HTML and HTML + Markdown output formats.

Select this to display the content as HTML without Markdown conversion. Use it when pasting a complete HTML page generated by AI or similar content directly.

**To use complete HTML as-is**

1. Set Design Component to **None (use the content HTML directly)**.
2. Paste the complete HTML into Content.
3. Select **Raw HTML**.

To use the site's shared header and footer as well, choose a page component such as `_layout` in step 1. The pasted HTML is inserted at the content position in that layout.

If tracking tags are configured under Common Loading in Site Settings, they are automatically inserted even on pages using complete HTML directly (inside `head` and immediately after the opening `body` tag). Select **Do not load tracking tags on this page** only on pages where they should be omitted.

For automatic completion of site-wide information such as the description and OGP image, see the details below.

###### Details: What Is Added to Pasted HTML {.detail}

Missing elements are supplied as follows. Items already present in the HTML are not overwritten.

- Missing `html`, `head`, and `body` elements are supplied.
- If missing from `head`, a UTF-8 character encoding declaration, mobile viewport setting, and title are added. The title takes the form “Page name - Site name.”
- If missing from `head`, a description (`description`) and OGP image (`og:image`) are supplied. Values from Description and Thumbnail / OGP Image in Page Edit are used if present; otherwise the shared site description and shared OGP image in Site Settings are used. If neither has a value, nothing is added.

#### Custom CSS

Shown only for HTML and HTML + Markdown output formats.

Write styles for this page only. They are automatically scoped, so they do not affect other pages or areas outside the content. Basic information tags such as `{{SITE_URL}}` and `{{PAGE_URL:ID}}` can be expanded in CSS.

To deliberately style elements outside the scope, such as the layout, wrap the entire selector in `:global(...)`. For example, `:global(.hero_box) { display: none; }` applies a style to `.hero_box` outside the scope. Ordinary selectors continue to apply only within this page's content.

#### Page JavaScript

Shown only for HTML and HTML + Markdown output formats.

Write JavaScript without `<script>` tags. The field opens expanded when it contains code. The CMS automatically inserts the script at the end of the page body, normally just before `</body>`, or at the end of HTML without a closing tag. This page's code is output once, after component JavaScript. It works in dynamic display, preview, and static HTML.

CSS scoping does not apply to JavaScript. Markdown conversion, custom tag expansion, and automatic URL completion are not performed in this field; the code is used as entered. `<script>` written directly in HTML content remains available. Specify any necessary load order, including external library dependencies, in the code.

#### [save]{.material-symbols-outlined} Save{.m-btn .m-btn-blue}

Saves all page content.

Changes to published pages do not appear on the public site until you [build](#static-build-button). {.cloud}

If the same page is updated from another screen or AI (MCP) while you edit it, saving is stopped. Copy any input you need to keep, reload the screen, and check the latest content. {.cloud}

#### [visibility]{.material-symbols-outlined} Preview{#page-preview .m-btn .m-btn-blue}

Displays the current edits in a new tab without saving. A note beside the button explains that the edits are displayed but not saved. This works with all output formats and statuses, including new pages. Pages that output HTML are displayed as pages; Markdown-only pages, data formats, and mikanBox DB display the actual output text or JSON in UTF-8. The preview is isolated from the admin panel: scripts run, but cannot access the panel. To open the actual site address, use [[language]{.material-symbols-outlined}]{.m-btn .m-btn-blue} in the page list (see the table in <a href="#page-mgmt">Pages</a>).

In Cloud, the current edits are retained for preview for 30 minutes. Up to four previews per person are retained, and only their creator can open them. Links written with the public site URL open the public site. Use [Visual Editing](#visual-edit) to navigate between draft HTML pages. {.cloud}

#### [arrow_back]{.material-symbols-outlined} Back to List{.m-btn .m-btn-gray}

Returns to the page list. If there are unsaved changes, the following choices appear:

- [[save]{.material-symbols-outlined} Save and Close]{.m-btn .m-btn-blue} — Save and return to the list.
- [[delete]{.material-symbols-outlined} Discard and Close]{.m-btn .m-btn-red} — Discard changes and return to the list.
- [Cancel]{.m-btn .m-btn-gray} — Continue editing.

#### [delete]{.material-symbols-outlined} Delete{.m-btn .m-btn-red}

Permanently deletes the page. This cannot be undone.

In the PHP editions, deleting a page removes it from the public site immediately. For Public (Static), it also deletes the static file in the current output destination. {.flat .sqlite}

In Cloud, a deleted published page is removed from the public site when you [build](#static-build-button). The Delete button is not shown for the home page (`index`). {.cloud}

#### Markdown Syntax and Tag Guide

Open the brief [Markdown](#markdown) guide and [tag guide](#tags) from the bottom of the screen.

---

## Visual Editing {#visual-edit}

Edit the body and page CSS while viewing the page. Click [[edit_square]{.material-symbols-outlined} Visual Edit]{.m-btn .m-btn-blue} in the page list or editor. Available for HTML and HTML + Markdown pages.

### Bottom-right toolbar {#visual-dock}

The toolbar at the bottom right contains the visual editing controls. They appear in the following order, divided into three groups. Toggle buttons change color when on.

#### Page and page settings

- [[menu_open]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Switch page (⌘/Ctrl+K)** — Open the page list to open another page or create one. See <a href="#visual-pages">Open or create another page</a>.
- [[dashboard]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Open the admin page list (g l)** — Open the admin page list in a new tab.
- [[tune]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Open this page's settings in the admin screen (g e)** — Open the page editor in a new tab to change its title, status, and other settings.

On the keyboard, press `g`, then `l` or `e` to open the corresponding screen. Pressing `g` displays a short hint.

#### Undo and history

- [[undo]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Undo (⌘/Ctrl+Z)** — Undo the last body or CSS save. Hover over the button to see how many saves can be undone. Disabled when there is nothing to undo.
- [[redo]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Redo (⌘/Ctrl+Shift+Z)** — Redo a save that was undone.
- [[history]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Edit history (go back to a state before an editing session)** — Open the history list. See <a href="#visual-save">Saving and history</a>.

#### View and edit

- [[devices]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Check at different screen widths** — Switch between phone, tablet, and desktop widths. See <a href="#visual-viewport">Check different screen widths</a>.
- [[edit]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Click to edit (on/off)** — When on, click body text to edit it in place. When off, you can browse and test links and buttons. Each click displays “Click to edit: on (click text to change it)” or “Click to edit: off (browse only)”. The setting is remembered the next time you open the editor. See <a href="#visual-inline">Edit text in place</a>.
- [[search]{.material-symbols-outlined} Inspect]{.m-btn .m-btn-gray} **Inspect** — Click a page element to inspect its selectors and CSS rules and choose where to edit its body or CSS. See <a href="#visual-inspect">Inspect and edit elements and CSS</a>.
- [[edit_note]{.material-symbols-outlined} Body]{.m-btn .m-btn-gray} **Body** — Edit the entire body as Markdown in the panel at the bottom. See <a href="#visual-md-panel">Edit in the Body panel</a>.
- [[palette]{.material-symbols-outlined} CSS]{.m-btn .m-btn-gray} **CSS** — Edit this page's CSS in the panel at the bottom. See <a href="#visual-css-panel">Edit page CSS</a>.

### Edit text in place {#visual-inline}

Click body text to edit it in place. To edit text in a link or button, **Shift-click** it.

A small dark-blue inline editing bar appears near the input. On the left it shows “Cmd/Ctrl+Enter to save, Esc to cancel”. The buttons on the right are, from left to right:

- [[add_row_above]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Add a paragraph above** — Add a paragraph before the current one, or an item above when editing a list.
- [[add_row_below]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Add a paragraph below** — Add a paragraph after the current one, or an item below when editing a list.
- [[delete]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Delete this block** — Delete the current paragraph or list item.
- [[edit_note]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Edit this part in "Body"** — Open the Body panel with your input carried over.
- [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Cancel (Esc)** — Discard the input.
- [[check]{.material-symbols-outlined}]{.m-btn .m-btn-blue} **Save (⌘/Ctrl+Enter)** — Save the input.

Input is saved as text. Use the Body panel to add bold text or links, or to rewrite paragraphs.

### Edit in the Body panel {#visual-md-panel}

**Option-click (Alt-click)** a section to open only that section in the Body panel at the bottom. You can also select an element with [[search]{.material-symbols-outlined} Inspect]{.m-btn .m-btn-gray} and click its path. To edit the whole body, click [[edit_note]{.material-symbols-outlined} Body]{.m-btn .m-btn-gray} at the bottom right.

The panel contains the following, from top to bottom.

#### Panel header

From left to right: “This page's body (Markdown)”, the hint “Changes show on the page as you type (save with ⌘/Ctrl+Enter)”, a status indicator, and two buttons. Changes appear on the page as you type. The status reads “● Unsaved” when changes have not been saved.

- [[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue} **Save (⌘/Ctrl+Enter)** — Save the body.
- [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Close** — Close the panel.

#### Markdown quick actions

This dark-blue bar above the input applies Markdown syntax to the selected text or current line. From left to right:

- [[title]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Heading (## → ### → #### → none)** — Cycle through heading levels.
- [[format_bold]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Bold (⌘/Ctrl+B)**
- [[format_italic]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Italic (⌘/Ctrl+I)**
- [[link]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Link (⌘/Ctrl+K)**
- [[format_list_bulleted]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Bulleted list**
- [[format_list_numbered]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Numbered list**
- [[format_quote]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Quote**
- [[code]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Code (inline)**
- [[image]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Insert an image (pick from the list; paste or drag also works)** — Choose an image from Media. Add new images using “Add” in the list, by pasting, or by dragging into the input (PNG, JPEG, GIF, or WebP; up to 10 MB and 40 megapixels).

#### Selection bar

When a section is selected in the Body panel, this bar appears below the quick actions. It shows the selected location (tag hierarchy and body line number) on the left, and the following buttons on the right. It does not appear when the whole body is open.

- [[subdirectory_arrow_right]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Edit an inner element (pick by number if there are several)** — Move to an element inside the selected container. The button shows the number of inner elements; choose from a numbered list if there are several.
- [[arrow_back]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Back out to the parent element** — Return to the enclosing element.
- [[add_row_above]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Add a paragraph above** — Add a paragraph before the selection (an element inside HTML).
- [[add_row_below]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Add a paragraph below** — Add a paragraph after the selection.
- [[content_copy]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Duplicate this block (adds the same content below)** — Duplicate the selection immediately below it.
- [[delete]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Delete this block** — Delete the selection. Press twice to confirm.
- [[undo]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Undo the last add / duplicate / delete** — Undo the last such operation. The input's undo cannot undo these operations, so use this button.
- [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Stop showing only the selection and show everything** — Return to the whole body.

When you select content generated by a custom tag (`{{…}}`), such as navigation cards, a note appears below the bar. Change the card titles and images in the linked pages' settings.

#### Input

Enter the Markdown for the selection, or for the whole body if nothing is selected. Write syntax directly when the buttons above the input do not provide it.

### Edit page CSS {#visual-css-panel}

Click [[palette]{.material-symbols-outlined} CSS]{.m-btn .m-btn-gray} at the bottom right to open the page CSS panel. Select an element with [[search]{.material-symbols-outlined} Inspect]{.m-btn .m-btn-gray}, then click [[palette]{.material-symbols-outlined}]{.m-btn .m-btn-gray} in the small popup to choose and open a related rule (<a href="#visual-inspect-pop">popup buttons</a>). CSS changes also appear on the page as you type.

The panel has the same layout as the Body panel, with these differences:

#### Panel header

The title is “This page's CSS”. The buttons are the same as in the Body panel: [[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue} **Save (⌘/Ctrl+Enter)** and [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Close**.

#### CSS rule selection bar

This appears above the input only when a rule is selected. The selector and “n / total” appear on the left, and the following buttons on the right. There is no quick action bar.

- [[arrow_upward]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Previous matching rule** — Move to the previous rule; disabled at the first.
- [[arrow_downward]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Next matching rule** — Move to the next rule; disabled at the last.
- [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Stop showing only the selection and show everything** — Return to all page CSS.

#### Input

Enter the selected rule, or all CSS if nothing is selected.

#### Popup buttons when inspecting an element {#visual-inspect-pop}

Selecting an element with [[search]{.material-symbols-outlined} Inspect]{.m-btn .m-btn-gray} opens a small popup listing its selectors. Each row contains a selector, the number of matching elements, and three buttons. Clicking a selector also opens the body.

- [[edit_note]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Edit the body here** — Open the element's body in the Body panel. Disabled for elements that are not body blocks.
- [[palette]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Choose a CSS rule (ones that apply to this element or mention its name)** — Show related page CSS rules in a numbered list. Choose one to open it in the CSS panel.
- [[content_copy]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Copy** — Copy the selector.

In the rules list, use [[arrow_back]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Back to the selector list** to return.

### Inspect and edit elements and CSS {#visual-inspect}

Use [[search]{.material-symbols-outlined} Inspect]{.m-btn .m-btn-gray} to select an element on the page and open its body or an applicable CSS rule in an editing panel:

**Step 1.** Click [[search]{.material-symbols-outlined} Inspect]{.m-btn .m-btn-gray} at the bottom right. The button changes color while on, and elements under the pointer are outlined. Links and buttons do not work during inspection.

**Step 2.** Click the element to edit. A popup lists “Selectors for `<tag>`”, one per row, tracing outward through its ancestors. Each row also shows the number of matching elements.

**Step 3.** Choose the button for what you want to edit. Inspect turns off automatically.

- [[edit_note]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Edit the body here** — Open the element selected in the Body panel. Clicking the selector does the same. Disabled for elements outside the body, such as headers and footers.
- [[palette]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Choose a CSS rule** — List rules that apply to the element or mention its class or ID. Choose a rule to open only that rule in the CSS panel. The numbered list includes line numbers and excerpts. Use [[arrow_back]{.material-symbols-outlined}]{.m-btn .m-btn-gray} to return to selectors.
- [[content_copy]{.material-symbols-outlined}]{.m-btn .m-btn-gray} **Copy** — Copy the selector.

**Step 4.** Edit in the panel. Changes appear as you type. In the CSS panel, use [[arrow_upward]{.material-symbols-outlined}]{.m-btn .m-btn-gray} and [[arrow_downward]{.material-symbols-outlined}]{.m-btn .m-btn-gray} to move between matching rules. Save with [[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue} or ⌘/Ctrl+Enter. See <a href="#visual-md-panel">Edit in the Body panel</a> and <a href="#visual-css-panel">Edit page CSS</a>.

Depending on the selection, a note may appear below the popup:

- The element is outside the page body (such as a header or footer). Page CSS applies only inside the body; use a component's CSS.
- This is not a body block (for example, it is part of the layout), so its body cannot be edited here. CSS can still be inspected.
- This is the page title, not the body. Follow the link below the note to change Title in page settings.

Press Esc to stop. If the popup is open, Esc closes it; press Esc again to turn Inspect off. You can also inspect elements while checking screen widths with [[devices]{.material-symbols-outlined}]{.m-btn .m-btn-gray}; the width preview stays open.

### Saving and history {#visual-save}

Save Body or CSS edits with the panel's [[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue} or ⌘/Ctrl+Enter. Changes to statically published pages do not appear on the public site until you <a href="#static-build-button">build</a>.

- Even if the live display cannot be updated, you can save when the panel says saving still works.
- If the body or CSS changes in another screen while you are editing, saving stops and a warning appears. Copy your input before reopening the page.
- Use [[undo]{.material-symbols-outlined}]{.m-btn .m-btn-gray} and [[redo]{.material-symbols-outlined}]{.m-btn .m-btn-gray} at the bottom right to undo or redo up to three recent body or CSS saves.
- Use [[history]{.material-symbols-outlined}]{.m-btn .m-btn-gray} to open Edit history. It lists the state immediately before the first save after opening the page, once per session, with date and time. Up to ten entries are kept. Select a date and confirm with Yes to restore it; the page reloads. You can undo the restore with [[undo]{.material-symbols-outlined}]{.m-btn .m-btn-gray}. Close with [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray}.

In Cloud, you cannot save while another user is editing the page. {.cloud}

### Check different screen widths {#visual-viewport}

Click [[devices]{.material-symbols-outlined}]{.m-btn .m-btn-gray} at the bottom right to open a width bar at the top. You can keep editing at the selected width. Its buttons, from left to right:

- [[smartphone]{.material-symbols-outlined} 375]{.m-btn .m-btn-gray} **Show at 375** — Phone width.
- [[tablet_mac]{.material-symbols-outlined} 768]{.m-btn .m-btn-gray} **Show at 768** — Tablet width.
- [[desktop_windows]{.material-symbols-outlined} 1200]{.m-btn .m-btn-gray} **Show at 1200** — Desktop width.
- [[width_full]{.material-symbols-outlined} Full width]{.m-btn .m-btn-gray} **Finish checking widths (full width)** — Return to the original view.

A note on the right explains that you can click text to edit in place or Alt-click an element to edit it in the Body panel without leaving this view. [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} at the far right also closes the width preview.

To test links and buttons, turn off click-to-edit using [[edit]{.material-symbols-outlined}]{.m-btn .m-btn-gray} at the bottom right.

Check page changes produced by JavaScript in [Page Edit preview](#page-preview) or on the public site.

### Open or create another page {#visual-pages}

In Cloud, links written with public-site URLs can also open their destination in Visual Editing, including draft HTML pages. Clicking a link while checking widths switches the page being edited. {.cloud}

Click [[menu_open]{.material-symbols-outlined}]{.m-btn .m-btn-gray} (⌘/Ctrl+K) at the bottom right to open the page panel. From top to bottom:

- **Search pages (title or ID)** — Enter a title or ID to filter the list. Close the panel with [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} at the right.
- [[add]{.material-symbols-outlined} New page]{.m-btn .m-btn-gray} **Create a new page** — Open fields for the page ID and title.
- [[content_copy]{.material-symbols-outlined} Duplicate this page]{.m-btn .m-btn-gray} **Duplicate the page that is open now** — Open fields for the page ID and title.
- **Page list** — Select a page to open it in Visual Editing. Unsaved input prompts for confirmation. Use [[content_copy]{.material-symbols-outlined}]{.m-btn .m-btn-gray} on a row to duplicate that page. Selecting the current page closes the panel.
- **All pages (list screen)** — Open the page selection screen.

The new page and duplication fields contain:

- **Page ID (becomes the URL)** — For example, `about` or `news/2026`. Leave blank to assign one automatically. For a duplicate, the suggested ID adds `-copy` to the original.
- **Title** — For a duplicate, the suggested title adds “ (copy)” to the original.
- [[check]{.material-symbols-outlined} Create]{.m-btn .m-btn-blue} **Create** (or **Duplicate**) — Create the page as a draft.
- [[close]{.material-symbols-outlined} Cancel]{.m-btn .m-btn-gray} **Cancel (Esc)** — Close the fields and return to the page list.

New pages are drafts. Change their publication status in Page Edit to publish them.

---
## Site {#site}

This screen brings together site-wide settings and tools for operation and maintenance. Admin Memo, Version, and Check for tampering are always visible; open the other sections by selecting their headings. The descriptions below follow their screen order.

### When to use each section

| Purpose | Section | When to use it |
|---|---|---|
| Daily operation | [Admin Memo](#site-memo) | To leave handover or work notes |
| | [Site Settings](#site-settings) | To set the site name after installation or add tracking tags |
| | [Language](#site-lang) | To change the admin screen language |
| | [CSV Import](#csv-import) | To add tabular data to pages |
| Publishing | [Static Site Generation](#site-ssg) (flat and SQLite) | To choose the output location and format for static HTML |
| | [Cloudflare publication settings](#cloudflare-publish-settings) (Cloud) | Once after installation, to enable delivery to the public site |
| Updates and maintenance | [Version (flat and SQLite)](#site-version-php), [Version (Cloud)](#site-version-cloud) | When a new version is available |
| | [Data Management](#site-data-mgmt) | For regular backups, migration from another site, or downloading the public site |
| Accounts and connections | [Devices and Users](#site-users) (SQLite and Cloud) | To add a device, invite someone, or disable a lost device |
| | [Change Password](#site-password) (flat) | To change the password |
| | [MCP API Key](#site-mcp-key) | To let AI operate the site |
| Inspection | [Check for tampering](#site-integrity) | To look for unfamiliar changes |
| | [Recent operations](#site-audit) | To see who changed what and when |

### Admin Memo {#site-memo}

Leave site administration notes here. They are always displayed at the top of the Site screen. They are not included in public pages or the public API. AI can also read them through authenticated admin MCP.

#### Memo field

Enter handover information or work notes freely.

#### [save]{.material-symbols-outlined} Save{.m-btn .m-btn-gray}

Save the memo.

### Version (flat and SQLite editions) {#site-version-php .flat .sqlite}

Update the 🍊mikanBox program to a new version. The installed version and the latest official GitHub release are shown side by side. Only the program is replaced; articles, settings, images, the database, and static output are untouched.

#### Current version / Latest version

If the latest version cannot be retrieved, “Could not retrieve” is displayed.

#### Update{.m-btn .m-btn-blue}

Shown only when a new version is available. Select and confirm to replace program files, reload the screen, and return to your previous position.

If the update fails, the previous state is restored automatically. {.detail}

If automatic restoration fails, an error appears and a backup remains at `update-backups/.pending-*` on the server. Do not delete it; ask the server administrator to restore it. {.detail}

#### Reinstall

Shown only when already on the latest version. Reinstalls the same program version. Use this if an interrupted update left the program incomplete. Articles, settings, and images are untouched.

#### Check again

Fetch the latest version information again. The result appears at the bottom right.

#### GitHub

Open this edition's repository in a new tab.

#### Restore

Shown only when the previous version is saved. Return to it if a problem appears after updating.

### Version (Cloud edition) {#site-version-cloud .cloud}

Update the admin screen (admin Worker). The current version and the latest GitHub release are shown. Updating does not delete articles or images, but first export and save them using [Data Management](#site-data-mgmt).

#### Current version / Latest version

Shows the current version and latest Cloud release.

#### Check again

Fetch the latest version information again. After updating, reopen the screen and check the current version.

#### Update {#manual-update .m-btn .m-btn-blue}

From v0.21.0 onward, you can update from the admin screen using the API token saved in Cloudflare publication settings.

1. Under Site, check the latest version and changes, then select Update.
2. Progress is displayed. Keep the screen open until it finishes. If the connection is interrupted, select Resume update.
3. When complete, reload the screen and check the current version, articles, images, and settings.

If the update button is absent and you are asked to configure a token, save a token with Workers Scripts Edit permission for the admin Worker in Cloudflare publication settings. Devices added using a code can update three days after registration.

The update changes the admin screen and database changes required by that version. To deliver changes to public pages or images, use Build Static Site.

Updating from the admin screen requires the standard `DB` and `ASSETS` bindings on the admin Worker and a separate public Worker. Sites with custom bindings, or releases without update files, must use the fallback procedure below. One-click updates alone do not require D1 Edit permission on the API token. {.detail}

#### Cloud release

Open the public GitHub releases in a new tab.

#### Updating for the first time from an older version

For versions before v0.21.0, update once using the download procedure below or configured [GitHub Actions](#github-actions). Afterwards, Update is available under Site. Check each release's notes for any preparation required by intermediate versions.

#### Download and update

1. Download and extract **Source code (zip)** from the latest [release page](https://github.com/yoshihik0/mikanBox-cloud/releases).
2. Put the `wrangler.production.json` used at installation in the extracted folder. Use the current site's configuration, not sample values for a new installation. If it is missing, check the existing Worker configuration in Cloudflare and recreate it before proceeding.
3. To ask AI, open a new AI conversation in the extracted folder, provide the existing admin screen URL, and ask it to update the site to the latest version. If prompted to log in to Cloudflare, select **Authorize** with the same account as the existing site.

#### Update using commands {.cloud .detail}

Run the following in order in the extracted folder. Use `whoami` to confirm that the account is the same as the existing site's account before running the final two commands.

```sh
npm ci
npx wrangler login
npx wrangler whoami
npx wrangler d1 migrations apply DB --remote --config wrangler.production.json
npx wrangler deploy --config wrangler.production.json --keep-vars
```

Do not recreate `JWT_SECRET` or `SETUP_TOKEN` during an update. Registered devices may stop working. Do not redeploy the public `wrangler.public.json` either: this replaces the public site with its initial placeholder page. When finished, reopen the admin screen and check the version, articles, and images.

#### Update with GitHub Actions {#github-actions .cloud .detail}

To update with GitHub Actions, prepare `.github/workflows/update-cloud.yml` in your own Cloud edition repository. Initially, register these two values under **Settings → Secrets and variables → Actions → New repository secret** in your repository.

| Name | Value |
|---|---|
| `CLOUDFLARE_API_TOKEN` | A Cloudflare API token restricted to the target account with **Workers Scripts Edit** and **D1 Edit**. Additional zone permissions may be needed depending on custom domains and routes. |
| `MIKANBOX_UPDATE_CONFIG` | The complete current site's `wrangler.production.json`. Check the account, admin Worker, D1, routes, and custom domains. |

Register the API token directly in GitHub Secrets. Do not put the API token, `JWT_SECRET`, or `SETUP_TOKEN` in the configuration JSON.

1. Open **Actions → Update mikanBox Cloud** in your repository.
2. Select **Run workflow**.
3. Confirm **Success** in the results. Reopen the admin screen and check the version, articles, and images.

The workflow fetches the latest official public release, including public prereleases. If you change the admin Worker's bindings or routes, update the configuration registered in GitHub too. When a correction to the workflow itself is released, incorporate it into your repository.

#### Interrupted updates and rollback {#cloud-update-recovery .cloud .detail}

If the connection is lost, reopen the admin screen and select Resume update if it appears. If it fails, check the screen message or failed GitHub Actions step. The D1 update may already have completed. Correct permissions or configuration and try again. Applied migrations are not applied again.

Even if the admin screen cannot be opened, you can check the deployed version in the admin Worker's deployment history in the Cloudflare dashboard. Before rolling back, confirm that the previous version works with the current D1, then switch in deployment history. Switching the Worker alone does not restore D1 structure or data. If you need to [restore D1](#cloud-d1-restore) to match the previous version, edits saved after the restoration point will also be undone.

### Check for tampering {#site-integrity}

Check for changes to files or stored data that are absent from the operation log. Use this when you notice an unfamiliar change in appearance, see a colored entry in [Recent operations](#site-audit), or carry out a regular inspection.

#### Check for tampering{.m-btn .m-btn-gray}

The check runs only when pressed. Results appear below the button. If there are no problems, the time checked is displayed. It checks the following (not images, audio, or video):

- **Public files** — Compares files with the record made when they were exported (published in Cloud), and reports changed or missing files. PHP editions also report unfamiliar added files. Cloud cannot find unfamiliar files added to the public site.
- **Program** — PHP editions only. Compares the installed version with the official release on GitHub. If GitHub is unavailable, it compares with the record made at update or approval. Cloud's program is the deployed Worker itself and is not checked.
- **Stored data** — Compares pages, components, and site settings with their last changes in the operation log. Pages created before logging began cannot be compared and are excluded.

The results also include entries marked in color as unusual operations in the last 30 days.

#### This is my change (approve){#site-integrity-accept .m-btn .m-btn-gray}

Results can include your own changes, such as files uploaded through FTP or direct edits to D1. Selecting this makes the current state the new baseline, so the same items will not be reported next time. Colored entries also lose their highlighting. Approval is recorded under important operations in the log.

If an intrusion is present in the state you approve, it becomes part of the baseline. Confirm the changes are yours before selecting this.

### Static Site Generation (SSG) {#site-ssg .flat .sqlite}

Configure export of pages set to “Public (static)” as static HTML files. They display quickly without executing PHP, and you can publish without placing PHP files or data in the public location. Static and dynamically published pages can coexist. Set the destination and format here; for routine exports, you can also use [Build Static Site](#static-build-button) in the page list.

No files are written inside the admin folder, data folder, `media`, or `setup-token`. Pages targeting those locations are reported as errors and not exported. {.detail}

#### Static site creation method

- **Generate static pages on the server** — Export directly to the public location on the server, normally the site root.
- **Create an upload folder (when running locally)** — Prepare a site created on your computer for upload to another server. HTML and `media/` are exported together to a dedicated destination. The destination cannot overlap the site root, 🍊mikanBox itself, or an existing media folder.

#### Output directory

Specify the destination as a path relative to the site root. `blog` means `blog/` directly under the root; `../` means one level above. Leave blank to export directly under the root. For an upload folder, the default is `export`.

#### Output structure / URL format

Choose the HTML file format.

- **Directory (folder/index.html)** — URLs are `/page-ID/`. These match dynamic publication URLs, so switching does not break links.
- **Filename (filename.html)** — URLs are `/page-ID.html`, giving a simple file structure.

The default is directory for server generation and filename for an upload folder. This affects HTML only; Markdown and data formats are exported as `ID.extension` in either case.

#### Link style

Choose how page and image links are written in an upload folder.

- **Relative links (portable, recommended)** — Relative to the output location, keeping links intact when the folder moves.
- **Fixed public URL** — Fixed to the specified public URL.

#### Public root URL (for SSG)

Enter the root of the public site, such as `https://example.com/site`. If the public URL is known, as with Cloudflare Pages or Netlify, enter it here.

- **Fixed public URL** — Required; used for page links, `sitemap.xml`, and `rss.xml`.
- **Relative links** — Optional; used only to generate `sitemap.xml` and `rss.xml`. Neither is generated if blank.

#### Place media in the output media/ folder {#ssg-media-in-output .flat .sqlite}

Use this when separating the public location from the admin screen (see [an example of protecting the admin side on the same server](#php-separated-publishing)). Under server generation, enter the output directory and public root URL, select this option, and build. Existing media moves to `media/` directly under the output destination, and future uploads go there too.

For example, to change the public location from `/blog/` to `/pub/`, set the output to `../pub` and the public root URL to `https://example.com/pub`. The destination `media/` must be empty or absent. Clear the option and save to return media to its original location. An upload folder includes the current media regardless of this setting. {.detail}

Place source and destination on the same filesystem and give the admin PHP permission to move them. Admin previews also load images from `media/` under the public root URL. {.detail}

##### Specify storage and delivery URL separately {#php-media-location .flat .sqlite .detail}

To use a dedicated media folder or delivery location, create `local-config.php` in the admin folder and specify the storage location and delivery URL. This takes precedence over “Place media in the output media/ folder”. Move existing files to the new location first.

```php
<?php
define('MIKANBOX_MEDIA_DIR', '/srv/mikanbox/assets');
define('MIKANBOX_MEDIA_URL', 'https://example.com/media/');
```

Server-side static generation uses the specified delivery URL. Upload folders and public-site export ZIPs include files from the storage location. HTML `srcset` images and video `poster` attributes also refer to the included files.

#### [auto_awesome]{.material-symbols-outlined} Save and Build Static Site{.m-btn .m-btn-blue}

Save settings and export all pages set to “Public (static)” as static HTML. The screen stays in place and a notification shows the number of files generated. When pages are added or changed, published files remain unchanged until you rebuild.

Pages with `/` in their IDs (such as `news/2024`) are exported into actual folders inside the output destination. `sitemap.xml`, `rss.xml`, and `podcast.xml` are generated when a public root URL is configured. They exclude the 404 page and data-format pages. See [Sitemap, RSS & API](#site-mapfeed) for details. {.detail}
### Language {#site-lang}

Set the language of the admin screen. This does not affect the language of public pages.

#### Select language

Choose “Auto (browser setting)”, “日本語 (Japanese)”, or “English”. Auto follows your browser's language setting.

#### [save]{.material-symbols-outlined} Save{.m-btn .m-btn-blue}

Save the language setting.

### CSV Import {#csv-import}

Convert a CSV created in spreadsheet software or elsewhere into data that can be pasted into a page (`DATAROW` blocks). Use this for similarly structured data such as product lists and event schedules.

#### Choose file

Select a CSV file. Use alphanumeric field names in the first row. The encoding (UTF-8 / Shift-JIS) is detected automatically.

#### [content_copy]{.material-symbols-outlined} Convert & Copy{.m-btn .m-btn-gray}

Convert each row into a `{{DATAROW:sequence-number}}` block and copy it to the clipboard. Paste it into the page body in the editor and save.

If you publish with the output format set to “mikanBox DB”, you can read this data as an API at `site-URL/api/page-ID.json` ([mikanBox DB](#mikanbox-db)). In the PHP editions, you can keep a data-only page as a draft and refer to it with `{{POST_MD:page-ID#row-ID:field}}` or `{{NAV_CARDS}}`. In the Cloud edition, the public site cannot refer to draft or pending data, so publish and build it.

<a id="site-backup"></a>
### Data Management {#site-data-mgmt}

Save or migrate your site's content here. Use it for the following:

- **Regular backups** — Save a ZIP with “Save” and keep it somewhere safe.
- **Migration from another site or edition, or restoration from a backup** — Use “Read” to read an exported ZIP or WordPress data.
- **Taking a copy of the public site** — Use “Export the public site” to make a ZIP of the published pages.

The top of the section shows the number of pages, components, and media files and the total media size (also the number of users and database size in SQLite and Cloud). Large sites take time, so keep the screen open until the operation finishes.

#### Storage {.cloud}

Pages, history, and settings are stored in the D1 database; media files are stored on the public site. These can be used within the free tier. A warning appears when D1 usage exceeds 450 MB. Media is limited to 10 MB per file and 10,000 files. The public site is the only place media is stored, so use Save to save JSON and images regularly.

#### Save {#data-export}

Save site content as a ZIP or other file.

##### Contents

Choose one. If unsure, choose “JSON and media”.

| Contents | Details |
|---|---|
| JSON and media | JSON for settings, pages, and components, and media such as images, in one ZIP. |
| JSON only | A ZIP containing only JSON for settings, pages, and components. |
| Media only | A ZIP containing only media files. |
| Database (SQLite and Cloud editions) | A database file for SQLite or a D1 SQL file for Cloud. Includes page history. |

##### Include page history {.sqlite .cloud}

Include the latest 10 history entries for each page. This is off by default.

##### Match the filtered lists

When the page list is filtered by category, output format, or search, select this to export only those pages and the layouts, parts, and media they use. Use it to transfer selected pages to another site. Linked pages and pages in navigation are not included. This does not affect database exports.

Parts embedded inside other parts are included. Media files whose delivery URLs are written directly as strings in JavaScript are also included. URLs assembled by JavaScript at runtime do not add files.

##### [download]{.material-symbols-outlined} Download{.m-btn .m-btn-gray}

Download the selected content. It includes unpublished content such as drafts and admin notes, so keep it safe. Users, devices, passwords, MCP API keys, and the site ID are not included in the export ZIP. The ZIP format is the same across all three editions, so it can be imported into another edition.

In the Cloud edition, images are collected from the public site. If the public files differ from the records, the export stops; build the site and save again. “Database” exports all of D1, including settings, accounts, and media information, as one SQL file. It does not include image files. To select it, register a token with D1 edit permission in Cloudflare publication settings. {.cloud}

#### Read {#data-import}

Import exported files or data from another site into this site.

##### Choose files

Select files to import. You can select multiple files at once; their contents are identified and JSON and images are imported. Unreadable files are ignored. You can select:

- ZIP files saved by Save (including ZIPs from other editions or older versions)
- ZIP files containing `posts/` and `components/`
- Media files such as images, or ZIPs containing only those files
- WordPress XML exported via “Tools > Export”. Posts and pages are imported; published items remain published, pending items remain pending, and all others become drafts.

##### When data with the same ID exists

Choose one of three behaviors for existing IDs or names. The default is “Do not overwrite”.

- **Do not overwrite (add only what is missing)** — Add only items not present on the current site. Settings are not imported. Useful for importing parts from another site.
- **Overwrite** — Replace matching IDs or names with the file's contents. Items absent from the file remain. Pages before replacement are kept in history (SQLite and Cloud).
- **Replace everything (what the file lacks is deleted)** — Make the current site match the file. Use this to restore a backup. Confirmation is required before execution.

Only types included in the file (pages, components, images) change. Settings are imported only when overwriting or replacing everything; users, devices, site ID, and MCP API key stay unchanged. Invalid IDs, such as reserved names, are skipped and shown in the results.

##### [upload]{.material-symbols-outlined} Read{.m-btn .m-btn-gray}

Import the selected files. Results appear at the bottom right without reloading the screen. Build the site to reflect imported pages on the public site.

In the Cloud edition, importing images sends them directly to the public site (building the entire site). If sending fails, the images are not imported; import the same files again. {.cloud}

#### Restore D1 to a specified time {#cloud-d1-restore .cloud}

If you make a mistake, for example, restore the entire database to a specified time within Cloudflare's retention period (the most recent seven days on the free plan). To use this, register a token with D1 edit permission in Cloudflare publication settings.

Only D1 contents are restored: pages, components, settings, users, devices, and media information. Media files and the public site are not restored. After restoring, build the site and check pages and images. Devices registered after that time will no longer work and may need to be registered again.

##### Time to restore

Enter the date and time to restore.

##### [history]{.material-symbols-outlined} Restore D1 to specified time{.m-btn .m-btn-gray}

Restore the database to the entered time.

#### Export the public site {#site-export}

Download the current public site as a ZIP. Use this to move it to another server as a static site or keep a copy of its published state. In the PHP editions, dynamically published pages are also included as files. In the Cloud edition, published files are collected from the public site.

##### Convert to relative paths

Write links between pages and image locations relative to the files. Links remain valid if the site's address or folder changes. If not selected, paths start with the site address.

##### [download]{.material-symbols-outlined} Download the public site{.m-btn .m-btn-gray}

Download the public site as a ZIP. Server files such as `.htaccess` are not included.
### Site Settings {#site-settings}

Set the site name, defaults used when a page has no settings of its own, and tracking tags added to every page. Set the site name first after installation.

#### Site Name

The name of the site. Output it with `{{SITE_NAME}}`. `{{FULL_TITLE}}` uses the form “Page title - Site name” and is also used as the title in bookmarks and search results.

#### Site ID (for AI)

An unchangeable ID created automatically for each site. It helps AI identify the correct site when connected to more than one.

#### Site URL {.flat .sqlite}

The URL AI uses to identify the site. Leave it blank to detect the site's address automatically. This is separate from the SSG output and publication locations. Enter a URL only if you want to give AI a different address.

#### Site URL {.cloud}

Shows the public URL as read-only.

#### Site Environment

Choose production, staging, development, local, or unspecified. AI can check this environment alongside the site ID, name, and URL. It indicates the site's purpose and does not restrict operations or access.

#### Site Description

Used as the page description when a page has none. Output it with `{{SITE_DESCRIPTION}}`.

#### Common Keywords

Used when a page has no keywords of its own.

#### Global OGP Image

Used as the image shared on social media when a page has no OGP image. Output it with `{{SITE_OGP_IMAGE}}`. The recommended size is 1200 × 630 px.

#### Pages per page (in list)

Choose how many entries appear on each page of the Pages list (default: 30).

#### Media items per page (in list)

Choose how many entries appear on each page of the Media list (default: 100).

#### Registration Categories (comma-separated) {.flat .sqlite}

Register category options for filtering and editing pages.

#### Allow external Markdown {.flat .sqlite}

Allow Markdown documents from other sites to be fetched with `{{EXT_MD:url}}`. See [Embedding Markdown documents from other sites](#external-markdown) for the default and permitted URLs.

#### Shared loading {#common-loading}

Load tracking tags, such as analytics tags, on every page without adding them separately to each page. To use a Google tag or Tag Manager, enter and save the Tracking ID. Rebuild static pages to apply the change. To exclude a particular page, select [“Do not load tracking tags on this page”](#page-edit-no-tracking) when editing it.

##### Tracking ID

Enter a Google tag ID (`G-…`) or a Tag Manager container ID (`GTM-…`).

##### Enter code directly (advanced)

Open this to use custom tracking code. Fields appear for head code and optional code immediately after the body starts. The entered code is inserted as is and takes priority over the tracking ID. Tracking tags written directly in content or components cannot be excluded per page.

#### [save]{.material-symbols-outlined} Save{.m-btn .m-btn-blue}

Save the Site Settings.
### Cloudflare Publish Settings {#cloudflare-publish-settings .cloud}

Configure the Cloudflare connection used to publish pages and images from the admin panel to the public site. Set it up once after installation and before the first build. **Build Static Site** cannot publish until this is configured.

#### How to Obtain a Cloudflare API Token

Open this for instructions on issuing a token in Cloudflare. Publication requires Workers Scripts Edit permission. Add D1 edit permission if you also use D1 export or point-in-time restore under Data Management.

#### Cloudflare Account ID

The destination Cloudflare account ID is already filled in. Leave it as is.

#### Worker Name

The admin Worker name specified during installation is already filled in. Leave it as is. To change the public Worker, see [Connecting the Public Worker](#public-worker).

#### Cloudflare API Token

Enter the API token you issued. The saved value is encrypted and cannot be displayed again. If you revoke the token in Cloudflare or change the site's `JWT_SECRET`, register a valid token again.

#### Save{.m-btn .m-btn-gray}

Saves the connection settings. The adjacent indicator shows whether the connection is configured.

#### Connecting the Public Worker {#public-worker .cloud .detail}

Set the public Worker name and public site address in `vars.CLOUDFLARE_PUBLIC_WORKER_NAME` and `vars.PUBLIC_SITE_URL` in the admin `wrangler.production.json`. Use separate Workers for administration and publication. Redeploy the admin Worker if you change these settings. See [Configuration Values](#cloud-install-config).

If the public Worker is undergoing a gradual deployment, consolidate traffic to one version in Cloudflare before building.

### Devices and Users {#site-users .sqlite .cloud}

Manage devices (browsers) that can access the admin panel and editor accounts. Use this to add another computer of your own, invite someone, or stop a lost device. Editor names are recorded in edit history. Passwords are not used. Users are responsible for managing their own devices, including screen locks.

#### Users and Devices

Each user's devices are shown with device name, registration date, last use, and expiration. Your current device is marked accordingly. If someone registers a new device, a notice appears the next time you open the admin panel. Stop any device you do not recognize.

##### Display Name

This input appears only for your own user. Enter the name displayed in edit history and elsewhere. The username cannot be changed.

##### Save{.m-btn .m-btn-gray}

Saves the display name.

##### Stop{.m-btn .m-btn-red}

Shown for each device. Use it for a lost or no longer needed device. It stops the device and invalidates unused codes issued by it. The stopped device is signed out immediately and needs a new addition code to be used again. Stopping your current device signs you out.

A device added with a code cannot stop other devices or delete users for three days after registration. This prevents a device added with a stolen code from locking out the owner. {.detail}

In Cloud, the device also cannot change other users or perform D1 point-in-time restoration during those three days. {.cloud .detail}

##### Delete{.m-btn .m-btn-red}

Shown for each other user. Deletes the user, their devices, and unused codes. This cannot be undone. You cannot delete the signed-in user or the last remaining user.

#### [add]{.material-symbols-outlined} Add a device

Allows another browser or computer of your own to access the site. Open this to display the button for issuing a code. Open the admin panel on the new device and enter the code.

##### [add]{.material-symbols-outlined} Issue a code{.m-btn .m-btn-blue}

Issues an addition code. It is valid for three hours and can be used once. It is displayed only immediately after issuance and cannot be viewed later.

##### [add]{.material-symbols-outlined} Issue an invite code{.m-btn .m-btn-blue}

Found under **Invite someone**. Use it to invite someone to edit with you. Issue the invitation code and give it to them. The invitee enters the code and device name on the sign-in screen, chooses a user ID and display name, and registers. The ID may contain ASCII letters, digits, underscores, and hyphens. The code is valid for six hours and can be used once.

#### Unused codes

Lists issued codes that have not yet been used.

##### Cancel{.m-btn .m-btn-gray}

Shown for each code. Invalidates it, for example if it was sent to the wrong person.

#### Unused devices expire after

Choose 30, 90, or 180 days before a device that has not been used automatically expires. Each use extends the period. Add an expired device again using a code.

##### Save{.m-btn .m-btn-gray}

Saves the validity period.

If all devices are lost, recover using a setup token ([SQLite recovery](#device-recovery)). {.sqlite}

If all devices are lost, recover using `SETUP_TOKEN` ([Cloud recovery](#cloud-device-recovery)). {.cloud}

#### Device Authentication Behind a Proxy {#device-auth-proxy .sqlite .detail}

After ten failed device registration or recovery attempts from the same IP address, attempts from that IP are blocked for 15 minutes. Behind a proxy, all users may appear to share an IP. If multiple users are restricted together, ask the web server administrator to configure trusted proxies and pass each user's IP in `REMOTE_ADDR`. No admin panel setting is needed.

### Change Password {#site-password .flat}

Change the admin panel sign-in password.

#### Site ID for password saving

A read-only field that helps the browser distinguish this site's password. It prevents confusion between passwords saved in the browser when multiple sites share a domain.

#### Current Password

Enter the current password.

#### New Password

Enter a new password of at least seven characters.

#### [save]{.material-symbols-outlined} Save{.m-btn .m-btn-blue}

Changes and saves the password. Changing it here while signed in does not change MCP API keys.

If you forget the password, open `mikanData/settings.json` on the server using FTP or another method and clear the value of `password_hash`. On the next access, the initial password setup screen appears; a setup token is also required on a server. Resetting the password this way invalidates MCP API keys, so issue them again under [MCP API Key](#site-mcp-key).

### MCP API Key {#site-mcp-key}

Issue and manage API keys that allow AI agents such as Claude Code to operate this site through MCP. Use this when you want AI to create pages or modify designs.

#### Enable MCP

A checkbox. Clear it and save to disable admin MCP while retaining the keys. Re-enable it to connect with the same keys. Disabling it while not in use improves security. Public help MCP remains available while it is disabled.

#### Save{.m-btn .m-btn-gray}

Saves the Enable MCP setting.

#### [add]{.material-symbols-outlined} Add an API key {#mcp-key-issue}

Open this to display the key issuance fields. Give each key a name; up to 20 keys can be issued. Separate keys for each tool or machine identify which key performed an action in the activity log and let you disable only a problematic key.

##### Key name (AI tool, machine name, etc.)

Enter the name of the AI tool or machine that will use the key, such as “Claude Code / MacBook.”

##### [auto_awesome]{.material-symbols-outlined} Issue key{.m-btn .m-btn-blue}

Issues a new key and displays it in the API key field below. Other keys are unaffected.

##### Connection URL

The URL for this site's admin MCP.

###### [content_copy]{.material-symbols-outlined} Copy{.m-btn .m-btn-gray}

Copies the connection URL to the clipboard. Paste it directly into the client configuration.

##### API Key

Displayed only immediately after issuance. It cannot be displayed again later.

###### [content_copy]{.material-symbols-outlined} Copy{.m-btn .m-btn-gray}

Copies the API key to the clipboard. Paste it promptly into the connecting tool's configuration, such as `.mcp.json`. If lost, disable the key and issue a new one.

#### Issued API keys

Lists issued keys by name, issue date, and last use. Until first use, it indicates that the key has not yet been used.

##### Disable{.m-btn .m-btn-gray}

Shown for each key. Disable keys that are no longer needed or may have leaked. AI tools using that key can no longer connect; other keys remain usable.

#### Connecting

Register the connection URL in the AI application's Remote MCP (Streamable HTTP) feature and specify the API key in a header: `Authorization: Bearer issued-API-key` or `X-API-Key: issued-API-key`. Never put the API key in the URL or paste it into the OAuth field of a Claude custom connector. Public help MCP requires no authentication and can be registered with its URL alone.

Treat API keys like passwords; do not write them in public repositories or shared files. An AI with a key can also write JavaScript for pages and components, so give keys only to AI you trust as much as an administrator.

On connection, AI reads all registered [AI Instructions]{.type-badge .badge-ai} and uses them as site-specific instructions. AI instructions do not include instructions concerning MCP keys, login, site settings, the server, or the program itself. {.detail}

#### When API Keys Become Invalid

- When the key is disabled in the list
- In SQLite, after recovery using a setup token or handoff with `reset-accounts.txt` {.sqlite}
- In flat, after clearing `password_hash` and setting the password again {.flat}
- In Cloud, after [device recovery](#cloud-device-recovery) {.cloud}

Ordinary password changes, stopping devices, and deleting users do not change keys. {.flat .sqlite}

#### What Admin MCP Can Do {.detail}

Use it to edit or delete pages and components, edit AI instructions, preview, and upload media. In SQLite and Cloud, page save history is also available.

#### Using MCP in Cloud {.cloud .detail}

Specify pages by page ID. Media uploads are limited to 10 MB per file. If a file with the same name exists, a sequential suffix (`_1`, `_2`, and so on) is added. After generating static HTML, [build in the admin panel](#static-build-button) to deliver it to the public side.

#### Let AI Read Public Help {#cloud-public-help}

To ask AI about usage or settings, register the public help URL in the AI application's Remote MCP (Streamable HTTP). The URL alone is enough to connect. AI searches and reads the public help.

- For flat and SQLite, append `/mcp` to the URL of the site where mikanBox is installed. {.flat .sqlite}
- For Cloud, append `/public-mcp` to the admin Worker URL. To read in a browser, open `/public-help.html?language=ja` on the same Worker. {.cloud}

In a WebMCP-compatible browser, an in-browser agent in the admin panel can also search public help. WebMCP is not available on static Cloud public pages. {.cloud}

### Recent security activity {#site-audit}

This is the history of actions performed on the site. Use it to check when, by whom, and what was done and spot unfamiliar changes. Investigate changes absent from the log with [Check for Tampering](#site-integrity). The log does not affect public page display.

#### Tabs

Records appear newest first in three tabs: **Important**, **Site edits**, and **Failed sign-ins**.

- **Important** — Enabling or disabling MCP; issuing or disabling MCP keys; restoring data (importing with “replace everything”); [accepting an integrity check](#site-integrity-accept); and program updates (in Cloud, recorded on the first access after updating). PHP editions also record restoration to an earlier version. SQLite and Cloud also record device registration and stopping, code issuance, user deletion, and recovery.
- **Site edits** — Adding, changing, or deleting pages, components, media, and site settings, and importing data. Actions are recorded whether made in the admin panel, visual editor, or MCP. For site settings, only changed field names are recorded, not values.
- **Failed sign-ins** — Failed password or other authentication attempts.

#### Highlighting and Unusual Activity

Records matching either of the following are highlighted with a pale color. There is no setting for this.

- The combination of actor and IP range has never appeared in earlier records.
- The actor is blank or unknown.

For example, if you previously operated the site only from home and work, an action from another location is highlighted. Once you have confirmed that it was yours, press **This is my change (accept)** in the integrity check result to clear highlighting through that point.

#### What Each Record Contains {.detail}

The date and time, action type, actor (signed-in username, or “MCP” for MCP), target (page, component, or media ID, or setting name), and masked IP. Passwords, keys, tokens, content, setting values, and full IP addresses are not stored.

#### Retention and Grouping {.detail}

The most recent 50 important actions, 50 failed sign-ins, and 500 site edits are retained. Consecutive saves of the same item by the same person within ten minutes are grouped into one line. Saves that only change the update date without changing content are not recorded.

#### What Is Not Recorded

- Public page views. Only write operations are recorded.
- Changes made directly to server files without the admin panel or MCP, such as FTP uploads. Use the integrity check to detect them. {.flat .sqlite}
- Changes made directly to D1 through the Cloudflare dashboard or wrangler. A record remains, but the actor is unknown and left blank. {.cloud}

---

## Design {#design-mgmt}

Use this screen to view and manage components. Page layouts, shared parts, and AI instructions are managed as components.

#### [add]{.material-symbols-outlined} Create New{.m-btn .m-btn-blue}

Create a component. Clicking opens the design editor.

#### Filter components

Filter the list by component type. You can combine this with search.

- All
- Page
- Part
- AI instructions
- Standard components — Show only IDs beginning with `_`.

#### Keyword search

Search component IDs and notes. The list filters as you type. You can combine this with the type filter.

### Component list

The list is ordered [AI instructions]{.type-badge .badge-ai}, [Page]{.type-badge .badge-page}, then [Part]{.type-badge .badge-part}. Within each type, `_global_head`, `_header`, and `_footer` appear first, followed by the others in ID order.

#### Actions (Edit)

- [[edit]{.material-symbols-outlined} Edit]{.m-btn .m-btn-blue} — Open the design editor. You can also click the ID.

See [Design editor](#design-edit) for details.

#### ID

The component's identifier is the name used to call it from other pages and components. IDs beginning with `_` mark standard components. You normally omit this prefix from components you create.

See [ID](#design-edit-id) in the design editor section.

#### Type

Displays [Part]{.type-badge .badge-part}, [Page]{.type-badge .badge-page}, or [AI instructions]{.type-badge .badge-ai}.

See [Type](#design-edit-type) in the design editor section.

#### Tag name

This is an embedding tag such as `{{COMPONENT:header}}`. Click to copy it. Paste it into a page body or another component's HTML to call the component. Select page components under “Design Component” in the page editor.

---

## Design editor {#design-edit}

Edit a component's content and settings. This screen opens when you click “Create New”, a component ID, or its edit button.

In the SQLite edition, editing is restricted while another editor is working on the component. {.sqlite}

The fields displayed depend on the selected [type](#design-edit-type). Fields that vary by type state so below their heading. Fields without such a note appear for every type.

| Field | Part | Page | AI instructions |
|---|:-:|:-:|:-:|
| ID, type, note | Yes | Yes | Yes |
| HTML / Markdown | HTML | HTML | Markdown |
| CSS | Yes | Yes | Yes (optional) |
| Scope CSS | Yes | Yes | — |
| JavaScript | Yes | Yes | — |
| Markdown syntax and tag guide | Yes | Yes | — |

#### ID {#design-edit-id}

The name used to call the component from another page or part with `{{COMPONENT:ID}}`. A leading `_` marks a standard component; normally omit it for components you create.

- PHP editions accept ASCII letters, digits, hyphens, and underscores. You can use `/` as a separator and dots within each segment. AI instruction IDs receive a `.md` suffix when needed. {.flat .sqlite}
- Cloud accepts ASCII letters, digits, hyphens, underscores, and a trailing `.md`. Enter `.md` yourself when you want it on an AI instruction ID. {.cloud}

#### Type {#design-edit-type}

Choose one of three component roles. The selection changes the fields below.

| Type | Purpose | Usage |
|---|---|---|
| [Part]{.type-badge .badge-part} | Headers, navigation, and similar parts | Paste `{{COMPONENT:ID}}` into the body or another part |
| [Page]{.type-badge .badge-page} | Layout for an entire page | Mark the body position with `{{CONTENT}}` in HTML, then select it under “Design Component” in the page editor |
| [AI instructions]{.type-badge .badge-ai} | Design and operational rules | Write instructions in a document such as `DESIGN.md` |

#### Note

A note for the admin screen. Authenticated management MCP clients can also read it. It does not appear on public pages.

#### HTML / Markdown

The field heading changes to “HTML” or “Markdown” according to the type.

- Part / Page — Write the structure in HTML. Other components can be embedded. For Page, use `{{CONTENT}}` to mark the body position.
- AI instructions — Write instructions for AI in Markdown or HTML. They do not appear on public pages.

See the guide at the bottom of the screen or [available tags](#tags) for custom tags.

#### CSS

Write the component's styles. For AI instructions, this is an optional field for sample styles such as buttons and may be left empty. Basic information tags such as `{{SITE_URL}}` and `{{PAGE_URL:ID}}` are available in CSS.

Page components can use `{{HEAD_CSS}}` to specify the insertion point for combined CSS.

#### Scope CSS

Shown only for Part and Page types.

When on, CSS applies only within the component (the default). Turn it off to apply CSS site-wide. To apply a particular rule outside the component while scoping remains on, wrap its selector, for example `:global(.hero_box)`.

#### JavaScript

Shown only for Part and Page types. The field is expanded if it contains code.

Write code without `<script>` tags. Code from the same component is output only once per page, even if the component appears multiple times, so initial execution occurs once. Custom tags are not expanded in this field.

#### [save]{.material-symbols-outlined} Save{.m-btn .m-btn-blue}

Save all component content. To apply changes to statically published pages, [build](#static-build-button).

#### [arrow_back]{.material-symbols-outlined} Back to List{.m-btn .m-btn-gray}

Return to the component list. If there are unsaved changes, these choices appear:

- [[save]{.material-symbols-outlined} Save and Close]{.m-btn .m-btn-blue} — Save and return to the list.
- [[delete]{.material-symbols-outlined} Discard and Close]{.m-btn .m-btn-red} — Discard changes and return to the list.
- [Cancel]{.m-btn .m-btn-gray} — Continue editing.

#### [delete]{.material-symbols-outlined} Delete{.m-btn .m-btn-red}

Shown only when editing an existing component.

Permanently delete the component. This cannot be undone. Check pages that used the deleted component.

#### Markdown syntax and tag guide

Shown only for Part and Page types.

Open a short [Markdown](#markdown) guide and the [tag guide](#tags) at the bottom of the screen.

---

## Media {#media-mgmt}

View and manage uploaded files. Upload images, JS, and CSS for use on pages. The PHP editions also support audio and video uploads.

#### Filter by file type

Filter the list by file type. You can combine this with search and category filtering.

- All types
- Images
- Audio {.flat .sqlite}
- Video {.flat .sqlite}
- JS
- CSS

#### Keyword search

Search for part of a filename. The list filters as you type. You can combine this with type and category filters.

### Upload

#### Choose a file

Choose files to upload. You can also add them by dragging and dropping them into the Media area.

#### [upload]{.material-symbols-outlined} Upload{.m-btn .m-btn-blue}

Add the selected files. If a file with the same name exists, a sequence number (`_1`, `_2`, and so on) is appended.

When a category filter is active, the category name is automatically prefixed to image and other filenames, for example `blog_photo.jpg`. This does not apply to JS, CSS, or names already beginning with ASCII alphanumeric characters followed by `_`.

##### Cloud uploads and publication {#cloud-media-delivery .cloud}

Uploaded files are sent to the public site. **Files used on draft pages are also published as soon as they are uploaded.** Files successfully sent to the public destination appear in the list.

If the site has never been published, the first upload builds the entire site. Keep the screen open until it finishes.

If asked to resend other files, build the site and retry the upload.

#### Upload Options

Open “Upload Options” below the button to see supported formats and processing settings. Settings are saved in the browser and also apply to drag-and-drop uploads.

##### Supported formats

The supported formats are JPEG, PNG, GIF, WebP, and SVG for images, and JS and CSS for code. The PHP editions also accept MP3, M4A, and MP4. JS and CSS run on public pages. Add files only after checking their source and contents.

##### Limit longest side to ( ) px

Limit the longest side of an image. Initially on, it reduces images over 2,000px. You can change the value. It does not apply to GIF or SVG.

##### Convert to WebP

Save JPEG and PNG images as WebP.

Turn off processing before adding images you want to distribute at their original size or in their original format.

##### Details: Upload and processing limits {.detail}

- JS and CSS must be UTF-8 and no larger than 10 MB per file.
- In the PHP editions, upload limits and available image processing also depend on server settings. {.flat .sqlite}
- Cloud supports up to 10 MB per file and 10,000 files. Image processing applies to images with a longest side of at most 16,000px and at most 40 million pixels. {.cloud}

#### Category filter state {#media-category-state}

Shown above the list only when a category is selected at the top of the admin screen. It indicates that the list is filtered to files prefixed with the category name, such as `blog_photo.jpg`. Files beginning with `g_` are global and, like JS and CSS, appear in every category.

##### [visibility_off]{.material-symbols-outlined} Do not filter by category{.m-btn .m-btn-orange}

Temporarily clear the filter and show all media.

##### [filter_list]{.material-symbols-outlined} Apply category filter{.m-btn .m-btn-blue}

Shown only while filtering is disabled. Apply the category filter again.

### File list

Files appear as cards, newest updates first.

#### Thumbnail, filename, and format

Each card shows an image thumbnail (or a file-type icon for non-images), filename, format, and dimensions. Click the filename to copy it.

To use an image, specify `![Description](filename.png)` in Markdown or `media/filename` in HTML.

#### [delete]{.material-symbols-outlined} Delete{.m-btn .m-btn-red}

Remove a file. Deletion cannot be undone.

In Cloud, deleting a published file builds the site to remove it. Other saved changes to public pages are also published at that time. {.cloud}

#### Copy URL{.m-btn .m-btn-gray}

Shown only for JS and CSS files. Copy the file URL to the clipboard.

In Cloud, the copied URL is for the management side. To share the public URL, append `/media/filename` to the public site address. {.cloud}

#### Copy embed tag{.m-btn .m-btn-gray}

Shown only for JS and CSS files. Copy a `<script>` or `<link>` tag to the clipboard for pasting into a page body or component HTML.

#### Resize

Shown only for JPEG, PNG, and WebP files.

Open “Resize” to show width (W) and height (H) fields and [[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue}. Enter a width or height and save. Entering only one preserves the aspect ratio. In Cloud, images can only be reduced, and the resized image is sent to the public destination. {.cloud}

#### Rename

Open “Rename” to show a filename field and [[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue}. Enter a new name without changing the extension. Select “Update Links” to update references in pages and components as well.

To switch references on static pages, [build](#static-build-button) after the change. In Cloud, the old URL no longer in use is removed at the next build. {.cloud}

##### Details: Updating references when renaming {.detail}

“Update Links” covers filenames used in bodies, CSS, JavaScript, components, and OGP images. Check external links and URLs constructed by JavaScript separately.

In SQLite and Cloud, the operation stops if a page whose references would be updated is being edited. Finish editing and retry. {.sqlite .cloud}

#### Convert to WebP{.m-btn .m-btn-blue}

Shown only for JPEG and PNG files. Convert to WebP and update references in pages and components because the extension changes. Conversion stops if a WebP file with the target name already exists. Build after conversion to switch references on static pages.

In Cloud, the converted WebP is sent to the public destination, and the old URL no longer in use is removed at the next build. {.cloud}

### When media changes reach the public site {#cloud-media-timing .cloud}

In Cloud, the publication timing depends on the media operation:

- **Delete a published file** — Immediately builds the whole site to remove it. Other saved page changes not yet built are also published.
- **Upload** — On an already published site, sends only that file to the public site immediately. Other pages do not change.
- **Resize** — Immediately sends the reduced image to the public site, replacing the image of the same name. Other pages do not change.
- **Rename or convert to WebP** — Updates references in pages and components immediately. Changes to public pages and removal of old files take effect at the next build. A converted WebP image is sent to the public site immediately.

### Pagination

When the list spans multiple pages, page numbers and item counts appear below it. Navigate with [Previous]{.m-btn .m-btn-gray} and [Next]{.m-btn .m-btn-gray}. Change the number displayed in <a href="#site-settings">site-wide settings</a>.

---
## Markdown {#markdown}

You can use Markdown syntax, such as headings and lists, in the page body. A guide to supported syntax appears at the bottom of the page editor.

### Markdown and HTML

Markdown and HTML can be mixed. A line beginning with one of the following tags (ignoring leading whitespace) is treated as HTML, and Markdown syntax on that line is not converted.

`a` `address` `article` `aside` `blockquote` `body` `button` `canvas` `caption` `col` `colgroup` `dd` `details` `dialog` `div` `dl` `dt` `fieldset` `figcaption` `figure` `footer` `form` `h1`–`h6` `head` `header` `hr` `html` `iframe` `legend` `li` `link` `main` `menu` `meta` `nav` `noscript` `ol` `optgroup` `option` `p` `pre` `script` `section` `select` `source` `span` `style` `summary` `table` `tbody` `td` `tfoot` `th` `thead` `title` `tr` `ul` `video` `audio`

This applies to both opening and closing tags, as well as `<!DOCTYPE html>` at the beginning of a line and comments with a space immediately after `<!--`, such as `<!-- comment -->`. In a multiline HTML block, Markdown syntax such as bold is not converted until a blank line or a line containing only a closing tag. Code blocks and tables are processed before HTML blocks.

When pasting a complex HTML layout, turn on “Raw HTML” in the page editor to treat the body as HTML without Markdown conversion. 🍊mikanBox custom tags are still expanded.

### Paragraphs

**Text separated by blank lines** becomes paragraphs, output as `<p>content</p>`. You can use Markdown syntax such as bold and inline HTML tags inside paragraphs. Line breaks inside a paragraph become `<br>`. A `<br>` is not inserted immediately before or after an HTML tag.

### Headings

A line starting with `## Heading` is treated as a heading and output as `<h2>Heading</h2>`. **A half-width or full-width space is required after the `#` characters.**

Use one to six `#` characters. Since the `<h1>` tag represented by `#` is usually used for the page title, use `## Heading` or `### Subheading` in the body.

Markdown: `## Heading`
HTML: `<h2>Heading</h2>`

### Lists

Use `-`, `*`, or `+` for bullet lists. For both bulleted and numbered lists, **a half-width or full-width space is required after the marker**.

Markdown: `- List item`
HTML: `<ul><li>List item</li></ul>`

Markdown: `* List item`
HTML: `<ul><li>List item</li></ul>`

Markdown: `1. Numbered list item`
HTML: `<ol><li>Numbered list item</li></ol>`

### Blockquotes

Use `>` for a blockquote. The space after `>` is optional. Consecutive quoted lines are combined into one blockquote.

Markdown: `> Quote`
HTML: `<blockquote>Quote</blockquote>`

### Horizontal rules

Write three or more `-`, `*`, or `_` characters without spaces on a line to create a horizontal rule.

Markdown: `---`, `***`, or `___`
HTML: `<hr>`

### Code blocks

Enclose code between lines of at least three backticks (&#96;) or tildes (`~`). Up to three leading half-width spaces are allowed. The closing line must use the same character at least as many times as the opening line. Markdown, HTML, and `{{tags}}` inside the block are not converted and are displayed as written. The HTML output is `<pre><code>…</code></pre>`.

- Add a single language name using ASCII letters, numbers, hyphens, and underscores after the opening fence (for example, `php`, `json`, or `html`) to add a class beginning with `language-`, such as `<code class="language-php">`. Use this with CSS or a highlighting library to style each language.
- To include a code block fenced with three backticks inside a code block, for example on a page explaining Markdown, use four or more backticks or tildes for the outer fence. An inner fence shorter than the outer fence will not close it.
- If you omit the closing fence, everything from the opening fence to the end is treated as code.

### Inline elements

Markdown: `**Bold**`
HTML: `<strong>Bold</strong>`

Markdown: `*Italic*`
HTML: `<em>Italic</em>`

Markdown: `~~Strikethrough~~`
HTML: `<del>Strikethrough</del>`

Markdown: &#96;code&#96;
HTML: `<code>code</code>`

Enclose inline code in single backticks. For example, you can display a variable name as `name`.

To display a backtick (&#96;) itself as a character, enter the following in the body:

| Purpose | Enter in the body | Display |
|---|---|---|
| Display one character in ordinary text | `&#96;` | &#96; |
| Display it in code | `<code>&#96;name&#96;</code>` | <code>&#96;name&#96;</code> |

Backslash escaping and enclosing code in pairs of backticks are not supported.

### Links

Write links as `[Link text](URL)`. They are output as `<a href="URL">Link text</a>`.

Markdown: `[Link text](URL)`
HTML: `<a href="URL">Link text</a>`

You can use relative URLs, links within the page (`#heading-ID`), and `https:`, `http:`, `mailto:`, and `tel:`. Other explicit schemes are output as `#`.

### Using media

Embed an image using `![Image description](filename)`. The text in `[ ]` describes the image, and the output is `<img src="image URL" alt="Image description">`. For images uploaded in Media, you only need the filename. You can also use the custom tag `{{IMAGE:filename}}`.

Markdown: `![Image description](filename)`
HTML: `<img src="image URL" alt="Image description">`

#### Automatically added attributes

When you insert an image from Media using Markdown image syntax or `{{IMAGE:filename}}`, attributes are automatically added to the output `<img>` tag if its dimensions can be obtained.

In the Cloud edition, they are added during the static build. {.cloud}

- `width` / `height` — The image's original dimensions in pixels. The browser reserves space before loading, reducing layout shifts.
- `fetchpriority="high"` — Prioritizes the first large image in processing order.
- `loading="lazy" decoding="async"` — Delays loading the remaining large images until they approach the viewport.

Small images under 200px in both dimensions, such as logos, icons, and dividers, receive only `width` / `height` and no loading instructions. Deferring images of a few KB has little benefit and can delay their display.

These attributes are not added to SVGs, images at external URLs, or images outside Media. They are not automatically added to `<img>` tags written directly in HTML either.

#### Audio and video {#markdown-audio-video}

In the PHP editions, embed uploaded files with `{{AUDIO:filename}}` or `{{VIDEO:filename}}`. {.flat .sqlite}

In the Cloud edition, embed externally hosted audio or video using HTML such as `<audio src="https://example.com/audio.mp3" controls></audio>` or `<video src="https://example.com/video.mp4" controls></video>`. {.cloud}

### Tables

Start each table row with `|` and separate cells with `|`. After the heading, write a separator row such as `|---|`, followed by one or more data rows. The first row becomes the heading row `<th>`, and the third and subsequent rows become data rows `<td>`. Use colons to specify alignment, as in `|:---|:---:|---:|`.

`| Default | Left | Center | Right |`
`|---|:---|:---:|---:|`
`| A | B | C | D |`

| Default | Left | Center | Right |
|---|:---|:---:|---:|
| A | B | C | D |

### Classes and IDs (custom extension)

Add `{.className}` or `{#idName}` at the end of a Markdown line to set a `class` or `id` on a heading, paragraph, and so on. To set both, combine them in one pair of braces, as in `{.className #idName}`. For a multiline paragraph, place them at the end of the paragraph.

Markdown: `## Heading{.highlight}`
HTML: `<h2 class="highlight">Heading</h2>`

Markdown: `Paragraph text{.note #intro}`
HTML: `<p id="intro" class="note">Paragraph text</p>`

To apply attributes to part of a line, write `[text]{.className}` to wrap the text in a `<span>`. You can combine multiple classes and one ID.

Markdown: Here is `[red text]{.red}`.
HTML: Here is `<span class="red">red text</span>`.

Markdown: `[**Bold** emphasis]{.highlight .bold}`
HTML: `<span class="highlight bold"><strong>Bold</strong> emphasis</span>`

Markdown: An `[anchor]{#my-id .note}`
HTML: An `<span id="my-id" class="note">anchor</span>`

#### Nested spans (such as buttons with icons) (custom extension)

`[text]{.className}` can be nested. Inner `[...]{...}` spans are processed first, and the outer span wraps them. Three or more levels are also possible.

Markdown: `[[save]{.material-symbols-outlined}Save]{.m-btn .m-btn-blue}`
HTML: `<span class="m-btn m-btn-blue"><span class="material-symbols-outlined">save</span>Save</span>`

This is useful for embedding icons from icon fonts such as Material Symbols in button-like spans or badges. The contents of `[...]{...}` cannot contain a bare `[` without a corresponding `{...}`, because nested brackets are interpreted as spans.

These are 🍊mikanBox-specific Markdown extensions and do not work in other Markdown renderers such as GitHub.

---
## Components (custom tags) {#components}

### Using tags

Custom tags can be used in page bodies and component HTML. The CSS field expands basic information tags such as `{{TITLE}}`, `{{SITE_URL}}`, and `{{PAGE_URL:ID}}`.

**Custom tags are not expanded in dedicated JavaScript fields** on pages or components. To use a value in JavaScript, write something like `data-site-url="{{SITE_URL}}"` in HTML and read the element's `dataset.siteUrl` from JavaScript.

For pages with the JavaScript output format, turn on “Expand tags” in the editor to expand tags in the body. See the guide at the bottom of the screen and the [available tags](#tags).

### Using components

Components created in Design can be embedded in pages or other components using `{{COMPONENT:ID}}`.

### AI question box on public pages {#cloud-ai-question}

This box lets visitors ask an external AI about public materials such as FAQs and guides. Place it by writing `{{COMPONENT:_ai_question}}` in the page body. By default, it references the public page on which it is placed.

#### Set the sources and prompt

1. Publish the materials, such as an FAQ. If you choose “HTML + Markdown” or “Markdown” as the public page's [output format](#page-edit-output-format), you can also use the published `.md` URL.
2. Open `_ai_question` in Design and specify public URLs in `data-sources=""` in the HTML. Separate multiple URLs with `|`.
3. To change the text sent to the AI, edit the `template` at the end of the HTML. `%QUESTION%` is replaced with the visitor's question and `%SOURCE_URLS%` with the list of source URLs. `%SOURCE_URL%` is the first URL and `%PAGE_URL%` is the URL of the public page containing the box.
4. Adjust the question box text and layout in HTML and colors and spacing in CSS. For a static page, build and check the public page.

If you used the old standard component in the Cloud edition without editing it, it is replaced with the new standard component during an update. Components edited on your site remain available. If the HTML is a single line, `{{AI_QUESTION}}`, you can use the following tag to specify sources easily. To edit the HTML, CSS, or prompt, use the component in `mikanBox/import/ja/components/_ai_question.json` in the distribution or the initial Cloud edition content.

```text
{{AI_QUESTION:sources=https://example.com/faq.md | https://example.com/guide/}}
```

Visitors enter a question, then copy the displayed prompt and paste it into ChatGPT or Claude. Whether the AI can read the materials depends on the external AI. To specify a section of the public mikanBox help, use `{{AI_QUESTION:sectionID}}`.

### Images, audio, and video

Embed images with `{{IMAGE:filename}}`.

In the PHP editions, embed uploaded audio or video with `{{AUDIO:filename}}` or `{{VIDEO:filename}}`. {.flat .sqlite}

Audio and video cannot be uploaded to Media in the Cloud edition. To embed externally hosted files, specify their URLs in HTML `<audio>` or `<video>` tags. {.cloud}

### Navigation {#navigation}

Use `{{NAV_LINKS:category}}` to output a list of links as `<li>` elements.

To output cards with images, use `{{NAV_CARDS:category:componentID}}`. Define the card design in a component. If you omit the component ID, the standard design (`_nav_card`) is used.

Set the order of lists or cards using the page's “Order”. Pages with a sort order below 0 and [data format](#data-formats) pages are excluded.

Use `all` as the category to include all categories, or leave the category argument empty for pages without a category. A page can have multiple categories, so appropriate categories allow many navigation arrangements.

#### List options

After positional arguments (category and card component ID), append `name=value` options separated by `:`.

```
{{NAV_LINKS:news:limit=5}}
{{NAV_CARDS:news:_nav_card:limit=5:sort=new}}
{{NAV_CARDS:news:sort=old}}
```

| Option | Meaning |
|---|---|
| `sort=new` | Newest update first (sort order breaks ties) |
| `sort=old` | Oldest update first (sort order breaks ties) |
| `limit=N` | At most the first N items (per category for `@same`) |
| `sep=string` | Separator between items, mainly for JSON arrays in data formats. The value is used as written up to the next `:` or `}}`, except that `\n` (newline) and `\t` (tab) are interpreted specially. |

`sort=new` and `sort=old` use the [update date](#page-editor-date) stored on each page. There is no offset or automatic pager. Create and link to a complete list, or use [catalog data and JavaScript](#catalog-pager).

#### Related pages `@same`

Automatically list pages sharing categories with the current page. Use `{{NAV_LINKS:@same}}` for links or `{{NAV_CARDS:@same}}` for cards. The current page itself is excluded.

For example, a page with `coffee` and `tokyo` produces a list for each category. Pages with both appear in both lists. A single category produces a list without a category label; no categories produce no output. Categories starting with `_`, such as `_nav`, are not used to find related pages.

By default, items use “Order”. For example, `{{NAV_LINKS:@same:sort=new:limit=3}}` displays up to three items per category, newest first.

To style categories separately, use `.nav-group` for the group and `.nav-group-label` for the category label. In the Markdown version, category lists are nested lists.

#### Displaying categories with `{{CATEGORY}}`

`{{CATEGORY}}` outputs the page's categories in their specified order, excluding those beginning with `_`. In HTML each is wrapped in `<span class="category">coffee</span>`; in Markdown and data formats they are comma-separated (`coffee, tokyo`). `{{CATEGORY:all}}` includes categories beginning with `_`. Inside a card component it outputs the categories of the card's page.

### Embedding another page

Embed another page's content with `{{POST_MD:ID}}`. Moving part of a body to another page lets you edit items such as announcements separately.

In the PHP editions, the embedded page may remain a draft and need not be published separately. {.flat .sqlite}

Public Cloud pages cannot load draft or pending-approval pages with `POST_MD`. Those pages also do not appear in `NAV_LINKS`, `NAV_CARDS`, `@same`, feeds, or the API. {.cloud}

### Embedding external Markdown {#external-markdown}

Embed Markdown hosted elsewhere, such as GitHub, with `{{EXT_MD:url}}`. Besides editing directly on GitHub, you can update the site by asking an AI agent to change the file there.

**PHP edition setting:** Toggle “Allow loading external Markdown” in site settings. It is OFF for new sites. Existing sites without the setting retain access if saved pages or components contain `EXT_MD`. When OFF, display, preview, and build make no external requests. When ON, the PHP cURL extension is required. Only public HTTP/HTTPS addresses are fetched; internal addresses and redirects are rejected. Use the document's direct URL. Documents may be up to about 1 MB and requests take at most 5 seconds. If a document cannot be loaded, that portion is not displayed. Turning the setting OFF does not change generated HTML until it is rebuilt. {.flat .sqlite}

In the Cloud edition, content is not displayed at `EXT_MD`. HTML retains only the comment `<!-- EXT_MD is not available in the Cloud edition -->`; Markdown and data outputs display nothing. {.cloud}

### Page components

In components that lay out a whole page, insert `{{CONTENT}}` to output the body. Use `{{HEAD_CSS}}` to specify where CSS is inserted. If omitted, CSS is inserted immediately before `</head>` (or at the start of HTML if there is no closing tag). If there are multiple markers, CSS is output only at the first and the rest are removed. See the initial `_layout` component for the basic structure.

---

## Available tags {#tags}

### Tags available in all editions

| Tag | Description |
|---|---|
|`{{ TITLE }}`|Page title|
|`{{ FULL_TITLE }}`|Page title - site name|
|`{{ UPDATE_DATE }}`|Update date (YYYY-MM-DD)|
|`{{ UPDATE_DATE:JP }}`|Update date (YYYY年MM月DD日)|
|`{{ UPDATE_DATE:SLASH }}`|Update date (YYYY/MM/DD)|
|`{{ IS_NEW:N }}`|“new” if updated within N days; otherwise empty|
|`{{ DESCRIPTION }}`|Page description (site-wide description if empty)|
|`{{ KEYWORDS }}`|Page keywords|
|`{{ OGP_IMAGE }}`|Page thumbnail / OGP image URL|
|`{{ PAGE_URL }}`|Page URL|
|`{{ PAGE_URL:ID }}`|URL of the specified page, with its output format extension and `?v=` for data pages. [Details](#page-url-id)|
|`{{ CATEGORY }}`|Page categories excluding those beginning with `_`|
|`{{ CATEGORY:all }}`|All page categories|
|`{{ SITE_URL }}`|Site URL. Also usable in Markdown links, e.g. `[Details]({{SITE_URL}}/about)`|
|`{{ SITE_NAME }}`|Site title|
|`{{ SITE_DESCRIPTION }}`|Site-wide description|
|`{{SITE_KEYWORDS}}`|Site-wide keywords|
|`{{BASE_URL}}`|Same site URL as `{{SITE_URL}}` (for compatibility)|
|`{{PAGE_TITLE}}`|Value of the page title field|
|`{{PAGE_DESCRIPTION}}`|Value of the page description field|
|`{{PAGE_KEYWORDS}}`|Value of the page keywords field|
|`{{PAGE_OGP_IMAGE}}`|URL of the image specified in the page OGP image field|
|`{{ SITE_OGP_IMAGE }}`|Site-wide OGP image URL|
|`{{ COMPONENT:ID }}`|Embed a component|
|`{{IS_ACTIVE}}`|In a card component, `active` for the current page's card; otherwise empty|
|`{{LINK}}`|In a card component, the target page URL (for compatibility with `{{PAGE_URL}}`)|
|`{{ IMAGE:filename }}`|Display an image|
|`{{ AUDIO:filename }}`|Embed uploaded audio in the PHP editions|
|`{{ VIDEO:filename }}`|Embed uploaded video in the PHP editions|
|`{{ AI_QUESTION }}`|Display an [AI question box](#cloud-ai-question) referencing the current public page|
|`{{ AI_QUESTION:sources=publicURL }}`|Display an AI question box referencing the specified public materials|
|`{{ AI_QUESTION:sectionID }}`|Display an AI question box referencing a section of the public mikanBox help|
|`{{ NAV_LINKS:category }}`|Display pages in a category as links|
|`{{ NAV_LINKS:all }}`|Display all pages as links|
|`{{NAV_LINKS}}`|Display pages without a category as links|
|`{{ NAV_CARDS:category:componentID }}`|Display pages in a category as cards; component ID is optional|
|`{{ NAV_CARDS:all:componentID }}`|Display all pages as cards; component ID is optional|
|`{{NAV_CARDS}}` or `{{NAV_CARDS::componentID}}`|Display pages without a category as cards|
|`{{ NAV_LINKS:category:sort=new:limit=N }}`|List with options: `sort=new`, `sort=old`, `limit=N`, `sep=string` (also for NAV_CARDS). [Details](#navigation)|
|`{{ NAV_LINKS:@same }}`|Pages sharing a category other than those beginning with `_` with the current page (also for NAV_CARDS)|
|`{{ POST_MD:ID }}`|Embed the page with the specified ID as Markdown|
|`{{DATA:key}}value{{/DATA}}`|Define and display data. A key starts with an ASCII letter, followed by ASCII letters, digits, or underscores. Keys are case-sensitive; values may contain Japanese.|
|`{{DATA:key:GHOST}}value{{/DATA}}`|Define hidden data. Key rules are the same as for displayed DATA.|
|`{{DATAROW:rowID}} {{DATA:key}}value{{/DATA}} {{/DATAROW}}`|Define tabular data. rowID may contain ASCII letters, digits, underscores, and hyphens. Key rules are the same as for DATA.|
|`{{ POST_MD::key }}`|Display data from the current page|
|`{{POST_MD:pageID:key}}`|Display data from the specified page|
|`{{ POST_MD::#rowID:key }}`|Display tabular data from the current page|
|`{{ POST_MD:pageID#rowID:key }}`|Display tabular data from the specified page|
|`{{CONTENT}}`|**Page Wrapper only:** Insert the body|
|`{{HEAD_CSS}}`|Insertion point for combined CSS. The first marker is used. If omitted, CSS is inserted before `</head>`, or at the start of HTML if there is no closing tag. Initially in `_global_head`.|

### Fetching external Markdown {.flat .sqlite}

| Tag | Description |
|---|---|
|`{{ EXT_MD:url }}`|Embed Markdown from the specified URL|
|`{{ EXT_MD:url:key }}`|Display data from an external page|
|`{{ EXT_MD:url#rowID:key }}`|Display tabular data from an external page|

Enable this in site-wide settings. See [embedding external Markdown](#external-markdown).

### Site search {.sqlite}

| Tag | Description |
|---|---|
|`{{SEARCH_RESULTS}}`|Display site search results. Specify a component for one result, e.g. `{{SEARCH_RESULTS:_search_result_item}}`.|
|`{{RESULT_TITLE}}`|In a search result component, the found page's title|
|`{{RESULT_SNIPPET}}`|In a search result component, an excerpt relevant to the query|
|`{{RESULT_URL}}`|In a search result component, the found page's URL|
|`{{RESULT_DATE}}`|In a search result component, the found page's update date|

### Search and JavaScript placement {.cloud}

| Tag | Description |
|---|---|
|`{{SEARCH_RESULTS}}`|Display a Google site search form. The form is the same even if a result component ID is supplied.|
|`{{HEAD_JS}}`|Insertion point for combined JavaScript in HTML. It is output at every marker; if omitted, it is inserted before `</body>`, or at the end if there is no closing tag.|

Search terms are sent to Google. Only pages indexed by Google are searched, so newly published pages may take time to appear.

### Displaying the year

JavaScript can display the current year, for example `<script>document.write(new Date().getFullYear())</script>`. This keeps the year current without server-side processing.

---

## Initial components {#init-comps}

You can inspect and edit the components included at installation in Design. The flat edition includes 11; the SQLite and Cloud editions each include 13.

Images referenced by Cloud sample pages are not included. Add them through Media if you want to use images in the samples. {.cloud}

### Initial components in all editions

| ID | Type | Purpose and content |
|---|---|---|
|`_layout`|[Page]{.type-badge .badge-page}|Standard shared layout combining `{{COMPONENT:_global_head}}`, `{{COMPONENT:_header}}`, `{{CONTENT}}`, and `{{COMPONENT:_footer}}`. `{{HEAD_CSS}}` is in `_global_head`. Select this for ordinary Markdown pages.|
|`_global_head`|[Part]{.type-badge .badge-part}|Shared tags inserted in HTML `<head>`, such as Google Analytics or web fonts. Initially contains `{{HEAD_CSS}}` to specify the CSS insertion point. CSS scoping is disabled (global).|
|`_header`|[Part]{.type-badge .badge-part}|Shared header with site name and navigation. Uses `{{NAV_LINKS:_nav}}` for pages in the structural `_nav` category.|
|`_footer`|[Part]{.type-badge .badge-part}|Shared footer containing copyright, footer navigation, and so on.|
|`_nav_card`|[Part]{.type-badge .badge-part}|Default card design when a card navigation component ID is omitted. Displays thumbnail, title, description, and date.|
|`_md_footer`|[Part]{.type-badge .badge-part}|Sample footer component for the [Markdown version](#markdown-version). Adds related links using `{{NAV_LINKS:@same:limit=5}}`. Used only when selected in the page editor, not by default.|
|`sample_card`|[Part]{.type-badge .badge-part}|Sample card display. Use with `{{NAV_CARDS:category:sample_card}}`.|
|`json_item`|[Part]{.type-badge .badge-part}|Sample item for a JSON catalog. Use with `{{NAV_CARDS:category:json_item:sep=,}}` ([catalog and page list](#catalog-pager)).|
|`_ai_question`|[Part]{.type-badge .badge-part}|[AI question box for public pages](#cloud-ai-question). Place with `{{COMPONENT:_ai_question}}`.|
|`DESIGN_sample.md`|[AI instructions]{.type-badge .badge-ai}|Sample instructions for AI design generation and editing. Duplicate or edit it to write your own rules as `DESIGN.md`, for example, or ask AI to write them. Similar files could include `BRAND.md` for brand information or `CONTENTS.md` for content policy.|
|`AGENTS_sample.md`|[AI instructions]{.type-badge .badge-ai}|Sample page production instructions for AI. Duplicate or edit it to write your own rules as `AGENTS.md`, for example. It covers field usage, Markdown bodies, shared components, media, publication settings, and builds.|

### Initial search components {.sqlite .cloud}

| ID | Type | Purpose and content |
|---|---|---|
|`_search_box`|[Part]{.type-badge .badge-part}|Sends a query from a form to the search page (`search`).|
|`_search_result_item`|[Part]{.type-badge .badge-part}|Design for one SQLite search result. Also included in Cloud, where public site search uses Google.|

---
## Embedding and loading data, and the API {#data-embedding}

### Data formats

You can use the following data formats:

- Markdown: Markdown data within the site or from an external source such as GitHub
- Data: Individual key-value data, like WordPress custom fields
- DataBase: Tabular data using `{{DATAROW}}`

### Loading Markdown

#### Load another page as Markdown

In addition to components, you can load another entire page as Markdown. Instead of directly editing a complex page, update a simple page used as its source.

#### Load a page from another site as Markdown

Load Markdown from another site, such as GitHub, to update your site without directly accessing it. HTML in external documents is displayed as text, and 🍊mikanBox template tags are not executed. JavaScript is also prevented from running for security.

### Output a GitHub page as Markdown

To load a GitHub Markdown file, change its URL to the raw data URL:

Before: [https://github.com/yoshihik0/testData/blob/main/md-test.md](https://github.com/yoshihik0/testData/blob/main/md-test.md)

After: [https://raw.githubusercontent.com/yoshihik0/testData/main/md-test.md](https://raw.githubusercontent.com/yoshihik0/testData/main/md-test.md)

#### Conversion rules

github.com → raw.githubusercontent.com
/blob/ → / (remove `blob`)

Load with:
`{{EXT_MD:https://raw.githubusercontent.com/yoshihik0/testData/main/md-test.md}}`

#### Other services

Dropbox: Change to `?dl=1`.
Google Docs: Publish the document and use `/export?format=txt`.

### Embedding and using data in a page

Write `{{DATA:key}}data{{/DATA}}` to embed information in a page, like WordPress custom fields, and use it in that page or another. Keys may use ASCII letters, digits, and `_`. Add `:GHOST`, as in `{{DATA:key:GHOST}}data{{/DATA}}`, to hide it on the page.

Use on the same page: `{{POST_MD::key}}`
Use from another page: `{{POST_MD:pageID:key}}`

### Embedding and using data from another site

The `{{DATA:key}}data{{/DATA}}` format can also be loaded from pages on other sites such as GitHub. Since updates do not require direct access to the site, using an AI-friendly service such as GitHub makes automation easier.

Use data from another site: `{{EXT_MD:url:key}}`

### DB Less DB

Write `{{DATAROW:rowID}}{{DATA:key}}data{{/DATA}}{{/DATAROW}}` to embed tabular data and use it on the same or another page. The page itself can act as a database without using a database.

Display tabular data on the same page: `{{POST_MD::#rowID:key}}`
Display tabular data from the specified page: `{{POST_MD:pageID#rowID:key}}`

### Load a database from another site

The `{{DATAROW:rowID}}{{DATA:key}}data{{/DATA}}{{/DATAROW}}` format can also be loaded from pages on other sites such as GitHub.

External page tabular data: `{{EXT_MD:url#rowID:key}}`

### Convert CSV data

[CSV Import on the Site screen](#csv-import) converts CSV data from Excel and similar applications into `{{DATAROW}}` format.

Select a CSV file exported from Excel, then click:

#### [content_copy]{.material-symbols-outlined} Convert & Copy{.m-btn .m-btn-gray}

Paste the copied data into a page body. The first row is read as field names; each following row is converted into a `{{DATAROW:sequence number}}` block and copied to the clipboard. Paste it into the page editor body and save.

### Use as a headless CMS

Publish a page containing `{{DATAROW}}` with the “[mikanBox DB](#mikanbox-db)” output format to make it accessible externally through the API.

`https://yoursite.com/api/pageID.json` (`/api/pageID` also works with dynamic PHP output and in the Cloud edition)

Example: [https://yoshihiko.com/mikanbox/demo/api/sample-DB](https://yoshihiko.com/mikanbox/demo/api/sample-DB)

### Create cards for various information

This information can also be used in card displays (lists of records), allowing you to create product listings and more.

| Tag | Content |
|:---|:---|
|`{{TITLE}}`|Page title|
|`{{UPDATE_DATE}}`|Update date (YYYY-MM-DD)|
|`{{UPDATE_DATE:JP}}`|Update date (YYYY年MM月DD日)|
|`{{UPDATE_DATE:SLASH}}`|Update date (YYYY/MM/DD)|
|`{{IS_NEW:30}}`|`new` within N days; otherwise empty|
|`{{DESCRIPTION}}`|Page description|
|`{{OGP_IMAGE}}`|Page OGP image URL|
|`{{PAGE_URL}}`|Full page URL|
|`{{POST_MD::name}}`|Display data|
|`{{POST_MD::date}}`|Display data|
|`{{POST_MD::#test:book}}`|Display row data|
|`{{IS_ACTIVE}}`|`active` for the current page; otherwise empty|

## Output formats (Markdown version and data) {#output-formats}

Select an “Output Format” in the page editor and write the body. Use “Preview” to check the output before saving. The publication URL appears below the output format. Changes to statically published pages take effect when you [build](#static-build-button).

| Purpose | Format | Output |
|---|---|---|
| Create a regular page | HTML (default) | HTML page |
| Add a Markdown version to an HTML page | HTML + Markdown | HTML page and `/ID.md` |
| Publish a Markdown-only document | Markdown | `/ID.md` |
| Publish a catalog or file | JSON, XML, CSV, Text, JavaScript, CSS | `/ID.extension` |
| Publish body data through the API | mikanBox DB | `/api/ID.json` |

Visual editing is available for HTML and HTML + Markdown pages.

### URL rules {#output-urls}

For page ID `news`, the Markdown version is `/news.md` and JSON is `/news.json`. The extension follows the output format. The home page's Markdown version is `/index.md`.

#### PHP edition URLs {.flat .sqlite}

Static HTML is written as `/ID/` or `/ID.html` depending on the [output structure](#site-ssg). Markdown versions and data formats use URLs with extensions regardless of the output structure.

#### Cloud edition URLs {#cloud-output-urls .cloud}

HTML is written to `/ID.html` and can also be opened at `/ID` and `/ID/`. For Markdown-only pages, these URLs open `/ID.md`. Use URLs with extensions for data formats.

HTML for page ID `news/index` is written to `/news/index.html` and can also be opened at `/news/index` and `/news/index/`. If no `news` page exists, `/news` and `/news/` also open it.

There is a site-wide limit of 2,000 URL aliases for HTML and Markdown pages. The build reports if this limit is exceeded.

### Publish a Markdown version {#markdown-version}

Select “HTML + Markdown” to publish HTML as well, or “Markdown” for Markdown only. The body is output as Markdown without the HTML layout. Internal links and image URLs become absolute URLs at the publication destination.

In the PHP editions, if you use a relative-link upload folder and leave the publication root URL blank, the Markdown version also uses relative links for internal pages and images and omits the leading `url`. Setting the publication root URL produces absolute URLs. {.flat .sqlite}

Use “Markdown version footer components” in the page editor to select a component appended to the document. For example, the included `_md_footer` adds links to related pages.

#### Details: What the Markdown version contains {.detail}

Page information is added at the start of the document. `date` is the date of “Updated”.

```yaml
---
title: "Page title"
date: 2026-11-08
description: "Page description"
url: "https://example.com/page/"
categories: "coffee, tokyo"
---
```

`url` is included only when an HTML version exists. Links to other pages on the site use their Markdown URLs if available. Text in code blocks, inline code, and HTML comments is preserved.

### Publish a data format file {#data-formats}

Select JSON, XML, CSV, Text, JavaScript, or CSS and write the contents in “Body”. For example, a CSS page with ID `site-style` is published at `/site-style.css`. Data format pages do not appear in navigation lists, sitemaps, RSS, or search.

“Expand tags” is on by default for JSON, XML, CSV, and Text, and off by default for JavaScript and CSS. Turn it on to use 🍊mikanBox custom tags in JavaScript or CSS.

### Publish body data through the API {#mikanbox-db}

Publish with “mikanBox DB” as the output format to expose `{{DATA}}` and `{{DATAROW}}` in the body at `/api/ID.json`. Headings and explanatory text in the body are not included in the API. Choose “JSON” to write the entire file in a free-form structure.

#### Details: mikanBox DB output example {.detail}

`fields` contains page-wide data, and `rows` contains each row written with `{{DATAROW}}`. Preview the JSON before publishing.

```json
{
  "id": "sample_data",
  "title": "Product list",
  "fields": { "SHOP": "Fruit shop" },
  "rows": [{ "id": 1, "GOODS": "Apple", "PRICE": "300" }]
}
```

### Embed a page URL {#page-url-id}

Specify a page ID to insert its URL according to the output format. For JavaScript or CSS written on data format pages, load it from HTML as follows:

```html
<link rel="stylesheet" href="{{PAGE_URL:site-style}}">
<script src="{{PAGE_URL:site}}"></script>
```

Data format URLs include `?v=` so browsers load the new file when content changes. `{{PAGE_URL}}` without an ID is the current page URL.

---

## Create a paginated list with catalog data {#catalog-pager}

Create paginated lists by loading a JSON catalog with JavaScript. Open the included `sample_list_page` to see a working example. To make your own list, create a catalog and load it from the page displaying the list.

### Create a JSON catalog

Create a page, set its output format to “JSON”, use `news-list` as the example ID, and turn on “Expand tags”. The following example outputs pages in the `news` category with the newest updates first.

```text
[{{NAV_CARDS:news:json_item:sort=new:sep=,}}]
```

The included `json_item` creates one list item. Replace `news` with your category name, or use `all` for all pages. Preview the JSON before publishing.

### Display the list

Paste the following into the body of the HTML page displaying the list. The included JavaScript page `sample_pager` loads the catalog and displays 20 items at a time.

```html
<div class="mikan-pager" data-src="{{PAGE_URL:news-list}}" data-per-page="20"></div>
<script src="{{PAGE_URL:sample_pager}}"></script>
```

`news-list` is the catalog ID and `20` is the number of items per page. Publish the catalog, JavaScript, and list pages. For static publication, [build](#static-build-button).

---

## Podcast publishing {#podcast}

Create a page with embedded audio and set its category to `podcast`. An RSS feed containing only pages in that category is produced at the following URL, allowing you to publish a podcast:

`https://yoursite.com/podcast.xml`

The following data is commonly embedded. AUDIO_FILE is required.

`{{DATA:AUDIO_FILE:GHOST}}episode01.mp3{{/DATA}}`
`{{DATA:DURATION:GHOST}}25:30{{/DATA}}`
`{{DATA:FILE_SIZE:GHOST}}24500000{{/DATA}}`

### Podcast fields

`{{DATA:AUDIO_FILE:GHOST}}episode01.mp3{{/DATA}}` ← Required
`{{DATA:DURATION:GHOST}}25:30{{/DATA}}`
`{{DATA:FILE_SIZE:GHOST}}24500000{{/DATA}}`
`{{DATA:EPISODE_NUM:GHOST}}1{{/DATA}}`
`{{DATA:SEASON:GHOST}}1{{/DATA}}`
`{{DATA:SUBTITLE:GHOST}}This episode's theme is...{{/DATA}}`
`{{DATA:EPISODE_IMAGE:GHOST}}ep01-cover.jpg{{/DATA}}`
`{{DATA:EPISODE_TYPE:GHOST}}full{{/DATA}}`
`{{DATA:EXPLICIT:GHOST}}false{{/DATA}}`

The title `{{DATA:TITLE}}` and description `{{DATA:DESCRIPTION}}` are obtained automatically from page settings.

Available data varies by service.

### Publish a podcast

1. Create a podcast episode page in 🍊mikanBox with the `podcast` category.
2. Open `https://yoursite.com/podcast.xml` to check it (in Cloud, after [building](#static-build-button) and publishing).
3. Register the URL with each platform.

Registration:

| Platform | Registration site |
|:---|:---|
|Apple Podcasts|podcastsconnect.apple.com|
|Spotify|podcasters.spotify.com|
|YouTube / YouTube Music|[Publish a podcast using an RSS feed](https://support.google.com/youtubemusic/answer/13525207?hl=ja)|
|Amazon Music|music.amazon.com/podcasts|

After registration, platforms automatically fetch `podcast.xml` and update whenever you add an episode page.

In the PHP editions, upload the audio file to Media and specify its filename in `AUDIO_FILE`. {.flat .sqlite}

In the Cloud edition, specify the URL of externally hosted audio in `AUDIO_FILE`, for example `https://example.com/episode01.mp3`. Build whenever you add or change an episode. {.cloud}

---

## Sitemap, RSS, and API {#site-mapfeed}

Normally PHP dynamically generates the latest content at these URLs on each request. However, if the [static site generation (SSG)](#site-ssg) output directory is the site root, these are also written as files on each static build and served with the content from that build until the next build. {.flat .sqlite}

The Cloud edition [builds](#static-build-button) the sitemap, RSS, Podcast RSS, and mikanBox DB JSON as files and serves them at the public address. The 404 page is excluded from sitemaps and feeds. {.cloud}

### Sitemap

The sitemap is available at:

`https://yoursite.com/sitemap.xml`

Example: [https://yoshihiko.com/mikanbox/demo/sitemap.xml](https://yoshihiko.com/mikanbox/demo/sitemap.xml)

[Data format](#data-formats) pages are excluded from the sitemap, RSS, and Podcast RSS. Markdown-only pages appear with `/ID.md` URLs.

### RSS

The RSS feed is available at:

`https://yoursite.com/rss.xml`

Example: [https://yoshihiko.com/mikanbox/demo/rss.xml](https://yoshihiko.com/mikanbox/demo/rss.xml)

### Podcast RSS

Embed audio and set the category to `podcast` to publish a podcast using the RSS feed containing only pages in that category:

`https://yoursite.com/podcast.xml`

Example: [https://yoshihiko.com/mikanbox/demo/podcast.xml](https://yoshihiko.com/mikanbox/demo/podcast.xml)

### API

Publish a page containing `{{DATAROW}}` with the “[mikanBox DB](#mikanbox-db)” output format to make it accessible externally through the API.

`https://yoursite.com/api/pageID.json` (`/api/pageID` also works with dynamic PHP output and in the Cloud edition)

Example: [https://yoshihiko.com/mikanbox/demo/api/sample-DB](https://yoshihiko.com/mikanbox/demo/api/sample-DB)

---

## Publish AI-generated designs {#ai-guide}

### Prompt for a complete page

`Output a single HTML file including CSS and JavaScript. Use media/filename for images.`

Add this instruction to your design prompt.

### Use the generated page

After generating a page, place it as follows:

1. In the page editor, choose “None (use the content HTML directly)” for the design component.
2. Paste the entire generated HTML into “Body” and save.

### Shared parts across pages

Shared parts such as headers, footers, and content inside &lt;head&gt; can be managed separately.

- Separate and register them as `_header`, `_footer`, `_global_head`, and so on.
- Select `_layout` as the design component to use them automatically.
- Include `{{COMPONENT:_global_head}}` in the component to reflect each page's SEO information, such as title, description, and OGP image.
- Site-wide and page-specific CSS is inserted automatically. To specify its insertion point, place `{{HEAD_CSS}}`.

---

## Announcement {#announcement}

The creator of this program can provide CMS design, UI design, construction, and operation based on this program. You are also welcome to get in touch about UI design, information architecture, digital transformation, AI use, internal communications, design education, or content production.

Books: 『デザインの教室』, 『デザインの授業』, 『フラットデザインの基本ルール』

[https://yoshihiko.com](https://yoshihiko.com)

---

## License {#license}

**MIT License**

Copyright (c) 2026 [yoshihiko.com](http://yoshihiko.com)

<div class="license-block">
Permission is granted, free of charge, to use this software and associated documentation under the following conditions, including without limitation commercial use, modification, distribution, and sublicensing.<br><br>
<strong>Condition:</strong> The above copyright notice and this permission notice must be included in all copies or substantial portions of the software.<br><br>
<strong>Disclaimer:</strong> The software is provided “as is”, without warranty of any kind, express or implied. The author or copyright holder is not liable for any claim, damages, or other liability arising from contract, tort, or otherwise.
</div>

<script>
(function () {
  var main = document.querySelector('main'), bar = document.querySelector('.manual-editions');
  if (!main || !bar) return;
  var editions = ['flat', 'sqlite', 'cloud'], nodes = Array.from(main.children);
  function level(el) { var m = /^H([1-6])$/.exec(el.tagName); return m ? +m[1] : 0; }
  function applicable(el, edition) { var own = editions.filter(function(e) { return el.classList.contains(e); }); return edition === 'all' || !own.length || own.indexOf(edition) !== -1; }
  nodes.forEach(function(el, i) {
    if (level(el) && el.classList.contains('detail')) {
      for (var j = i + 1; j < nodes.length && (!level(nodes[j]) || level(nodes[j]) > level(el)); j++) nodes[j].classList.add('detail');
    }
    var own = editions.filter(function(e) { return el.classList.contains(e); });
    if (own.length && level(el)) { var badge = document.createElement('span'); badge.className = 'manual-edition-badge'; badge.textContent = own.join(' / '); el.appendChild(badge); }
  });
  var groups = [], run = [];
  function flush() {
    if (!run.length) return;
    var group = run.slice(), button = document.createElement('button');
    button.type = 'button'; button.className = 'manual-more'; button.textContent = "Show details"; button.setAttribute('aria-expanded', 'false');
    group[0].before(button); groups.push({button:button, nodes:group});
    button.addEventListener('click', function() { var open = button.getAttribute('aria-expanded') !== 'true'; button.setAttribute('aria-expanded', String(open)); button.textContent = open ? "Hide details" : "Show details"; group.forEach(function(el) { el.classList.toggle('manual-open', open); }); });
    run = [];
  }
  nodes.forEach(function(el) { if (el.classList.contains('detail')) run.push(el); else flush(); }); flush();
  function apply(edition) {
    if (editions.concat('all').indexOf(edition) < 0) edition = 'all';
    document.body.dataset.edition = edition;
    main.querySelectorAll('.manual-hidden').forEach(function(el) { el.classList.remove('manual-hidden'); });
    nodes.forEach(function(el, i) {
      if (!applicable(el, edition)) {
        el.classList.add('manual-hidden'); var n = level(el);
        if (n) for (var j = i + 1; j < nodes.length && (!level(nodes[j]) || level(nodes[j]) > n); j++) nodes[j].classList.add('manual-hidden');
      }
    });
    main.querySelectorAll('li, span, td, tr').forEach(function(el) { if (!applicable(el, edition)) el.classList.add('manual-hidden'); });
    groups.forEach(function(g) { g.button.classList.toggle('manual-hidden', !g.nodes.some(function(el) { return !el.classList.contains('manual-hidden'); })); });
    bar.querySelectorAll('[data-edition-choice]').forEach(function(b) { b.setAttribute('aria-pressed', String(b.dataset.editionChoice === edition)); });
    document.querySelectorAll('nav.sidebar a[href^="#"]').forEach(function(a) { var target = document.getElementById(a.getAttribute('href').slice(1)); a.parentElement.classList.toggle('manual-hidden', !!target && target.classList.contains('manual-hidden')); });
    try { localStorage.setItem('mikanbox-manual-edition', edition); } catch (_) {}
  }
  bar.querySelectorAll('[data-edition-choice]').forEach(function(b) { b.addEventListener('click', function() { apply(b.dataset.editionChoice); }); });
  var details = document.getElementById('manual-details');
  details.addEventListener('change', function() { main.classList.toggle('manual-show-details', details.checked); });
  main.classList.add('manual-details-ready');
  var selected = new URLSearchParams(location.search).get('edition');
  try { selected = selected || localStorage.getItem('mikanbox-manual-edition'); } catch (_) {}
  apply(selected || 'all');
})();
</script>
</main>
