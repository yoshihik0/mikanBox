<nav class="sidebar">

## Table of Contents

- <a href="#concept">Concept &amp; Features</a>
- <a href="#design-philosophy">Design Philosophy</a>
- <a href="#setup-dist">Setup &amp; Distribution</a>
- <a href="#overview">Overview</a>
- <a href="#page-mgmt">Pages</a>
- <a href="#page-edit">Page Edit</a>
- <a href="#visual-edit">Visual Editing</a>
- <a href="#site">Site</a>
- <a href="#data-migration">Data Migration &amp; Import Tool</a>
- <a href="#design-mgmt">Design</a>
- <a href="#design-edit">Design Edit</a>
- <a href="#media-mgmt">Media</a>
- <a href="#markdown">Markdown</a>
- <a href="#components">Components (Custom Tags)</a>
- <a href="#tags">Available Tags</a>
- <a href="#init-comps">Bundled Components</a>
- <a href="#data-embedding">Embedding, Loading &amp; API</a>
- <a href="#output-formats">Output Formats (Markdown &amp; Data)</a>
- <a href="#catalog-pager">Catalogs &amp; Page Lists</a>
- <a href="#site-mapfeed">Sitemap, RSS &amp; API</a>
- <a href="#podcast">Podcast</a>
- <a href="#ai-guide">Publishing an AI-generated Design</a>
- <a href="#announcement">Announcement</a>
- <a href="#license">License</a>

</nav>
<main>

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
.mat-icon {
  vertical-align: -0.2em;
  font-size: 1em;
}
</style>

# 🍊mikanBox User Manual <small class="manual-version">v2.19.0</small>

🍊mikanBox comes in two versions: **🍊mikanBox** [SQLite Edition]{.type-badge .badge-sqlite}, which uses a SQLite database, and **🍊mikanBox flat** [flat Edition]{.type-badge .badge-flat}, a JSON file-based edition with no database. The basic usage is shared between both, so this help document covers both. Where the content differs between versions, the badges above are used to mark the difference.

## Concept &amp; Features {#concept}

🍊mikanBox is a "lightweight, AI-era, parts-assembly CMS." There are two editions: 🍊mikanBox flat (JSON edition), designed to build and run sites of a few to a few dozen pages as fast and safely as possible, and 🍊mikanBox (SQLite edition), which can comfortably run larger sites.

- File-based (JSON) — No database required. Just place it on any PHP-capable server [flat Edition]{.type-badge .badge-flat}
- SQLite (single-file DB) — All data lives in a single DB file; just place it on any PHP-capable server [SQLite Edition]{.type-badge .badge-sqlite}
- Modeless UI — No page transitions; every task completes on a single screen for a snappy feel
- Markdown support — Easy to edit content, also works well as a content archive
- Filter by category — Filter pages and images by category; can be used as a workspace
- Show images by filename only — Paste images without worrying about file paths
- Component structure — Reusable, combinable page templates and parts
- Component-scoped CSS — Write CSS in a small scope without worrying about interference
- Drop in AI-generated code directly — Works even without any manual design work
- AI agent integration (MCP) — AI can understand your site's structure and design conventions and read/write files directly
- Manage DESIGN.md — Keep your instructions to AI on the site itself, so you can hand them to AI whenever needed
- Multimodal AI support — AI can generate images and send/place them directly into the media folder
- Static (SSG), dynamic, or a mix of both — Fast static sites, or a mix of static and dynamic, are both possible
- DB Less DB — Embed data in a page and output it via API; can even act as a headless CMS
- Podcast — Auto-generates an RSS feed so you can distribute a podcast too

---

## Design Philosophy {#design-philosophy}

### 🍊mikanBox flat: Deliberately Scoped [flat Edition]{.type-badge .badge-flat}

🍊mikanBox flat isn't meant for sites with thousands of pages. If you only have a handful of pages but still want to keep the content up to date, why introduce a heavy DB-backed system? By deliberately not using a database, it delivers:

- Easy setup
- A simple, easy-to-learn structure
- Easy backup and server migration

### 🍊mikanBox: Handles Larger Sites Too [SQLite Edition]{.type-badge .badge-sqlite}

🍊mikanBox (SQLite edition) is here for when you want to run a somewhat larger site. And since it's still a single SQLite file, it keeps nearly all of that same simplicity.

- Easy setup
- Easy backup and server migration
- Handles sites with a large number of pages
- Supports multi-user editing and an approval workflow
- Supports revision history management

### Flexible Design

Pages and components are tied directly to their CSS, so the structure is easy to follow and you don't need to worry about interference between parts. Depending on how you use components, you can design your site as freely and flexibly as you like.

- Pages and components can each hold their own CSS and JavaScript
- CSS is scoped, preventing interference
- Components can be nested
- Something like a WordPress theme is easy to build — and AI can build it too
- Paths to media files (images, etc.) are resolved automatically
- Data can be embedded into a page easily
- Data can be loaded and used from other pages and other sites
- Data can be stored in a variety of shapes, opening up many possible uses

### AI-Friendly

- AI-generated pages can be pasted in and used as-is. Even elaborate designs can go live with very little extra work
- The code is compact and simple, so AI understands it easily — making it easy to have AI build pages and designs around a custom data structure
- Through MCP, you can let AI remote-control everything from writing articles to designing pages
- You can manage your instructions to AI (like DESIGN.md) on the site itself, have AI read them, or even have AI generate a design system from an existing design
- By using the `{{EXT_MD:url}}` tag to source content from an external Markdown file (e.g. on GitHub), you can adopt a workflow where simply telling an AI agent to update the repository is enough to update the website — no need to even open the admin panel
- Because the codebase is small and easy for AI to understand, you add features by having AI rewrite the source directly, rather than relying on plugins

The official demo is a dedicated flat sample site frozen at v2.16.9. The product itself (v2.16.10 and later) has no demo feature. In the demo, MCP settings remain visible to demonstrate where configuration lives, but the displayed key is a non-functional dummy. Issuing, regenerating or enabling a key has no effect. Management MCP is unavailable over both HTTP and stdio. The system update and restore buttons are shown, but pressing them does nothing, even for a signed-in administrator. Never copy real data or production credentials into a demo.

### Keeping Security Simple

Static site support means the design doesn't have to expand its attack surface.

- No database (no SQL attack surface) [flat Edition]{.type-badge .badge-flat}
- All DB operations use prepared statements, preventing SQL injection [SQLite Edition]{.type-badge .badge-sqlite}
- Small codebase
- No complex dependencies like plugins
- Not the kind of attack target large-scale CMSes tend to be

## Setup &amp; Distribution {#setup-dist}

🍊mikanBox is ready to use as soon as you upload it to any PHP-capable server.

### Requirements

The minimum runtime remains PHP 8.0. For new installations and Internet-facing sites, use the latest patched release of supported PHP 8.4 or 8.5. [SQLite Edition]{.type-badge .badge-sqlite} additionally requires the SQLite3 extension to be enabled (this is enabled by default on most shared hosting).

The web server is assumed to be Apache with `.htaccess` enabled, because `.htaccess` is what keeps the data folder and similar files from being opened directly in a browser. Environments without `.htaccess` (such as Nginx) are not supported.

### Installation

Upload the `mikanBox` folder into the same directory as `index.php`. This folder name can be changed — doing so is recommended for security, since it makes the location of the admin panel (`admin.php`) harder to guess. If you rename it, update `$core_dir` near the top of `index.php` accordingly. From 2.6.4 on that setting survives system updates, because the updater writes back the folder it is running from. Up to 2.6.3 every update discarded it and the site stopped rendering; if you are in that state, running a system update from the admin panel repairs it. This document refers to it as the `mikanBox` directory throughout.

Site-specific data is created on first access in a `mikanData` folder beside `index.php`. If the legacy `mikanBox/data` directory exists, it is migrated automatically only when it can be moved safely. If the destination already exists or conflicts with a page URL, mikanBox keeps using the legacy location instead of switching to empty data.

To change the data directory name, create `local-config.php` inside the admin/core directory; this file is excluded from self-updates. Add `define('MIKANBOX_DATA_DIR_NAME', 'your-folder-name');`. The value must be one directory name containing only letters, numbers, hyphens, and underscores. If data already exists, move the physical directory to the same name before changing this setting.

Right after installation (before any page or component data exists), the first visit will automatically load sample content matching the visitor's browser language (Japanese or English).

#### Running it locally

You can run mikanBox on your own computer instead of uploading it to a server. This is the basis of the operating style where no admin panel is published at all — you upload only the generated static files — and it also makes the site folder a working directory for an AI coding tool.

PHP is the only requirement. No database, no build tooling, no Node.js.

1. Check whether PHP is installed. Run `php -v` in a terminal; check the version and use the latest patched PHP 8.4 or 8.5 for a new installation.
2. If it is missing, install it. macOS no longer ships PHP, so install [Homebrew](https://brew.sh/) and run `brew install php`. On Windows, download it from [windows.php.net](https://windows.php.net/download/). On Linux, run `sudo apt install php` (Debian family) or `sudo dnf install php` (Fedora family).
3. Extract `index.php` and the `mikanBox` folder into any folder.
4. Change into that folder and run `php -S localhost:8000`. This is the server bundled with PHP; nothing else is needed.
5. Open `http://localhost:8000/mikanBox/admin.php` in a browser and complete the first-time setup. See [Initial Setup and Sign-in](#first-setup).

The `mikanData` and `media` folders are created on first access, exactly as they are on a server. To stop the server, press Control+C in the terminal that started it.

Open this folder in an AI coding tool and the agent can read and write your pages' Markdown and JSON directly. The admin MCP server works against the same site. Because the content is plain files, you can open them afterwards and check what the agent wrote.

To publish, upload the folder that static generation produces. In this style there is no PHP, no data and no admin panel on the public side, so there is nothing to tamper with.

### Initial Setup and Sign-in {#first-setup}

[flat Edition]{.type-badge .badge-flat}: whoever opens `mikanBox/admin.php` first sets the admin password (at least 7 characters). Its hash (a one-way converted value) is stored in `mikanData/settings.json`. After a password change, sign-ins in other browsers expire.

#### Set the first password {#flat-setup}

[flat Edition only]{.type-badge .badge-flat}

On a server, a setup token is required so that nobody else can set the password first (v2.16.8 and later).

1. Make a random string (at least 20 characters). It is used only once, so keep a copy until setup is done.
2. Open `setup-token.txt` in the `setup-token` folder and write that string on a single line below the instructions.
3. Upload the `mikanBox` folder, `index.php` and the `setup-token` folder.
4. Open `mikanBox/admin.php`, then enter the token and a password. The used token is removed from `setup-token.txt`.

If `setup-token.txt` holds no token, one shorter than 20 characters, or one made of a few repeated characters, the setup screen is not shown. The token is needed only when setting the first password; it is not needed on a site that is already set up.

When you run mikanBox on your own computer (`localhost`), no token is needed; a password is enough. This applies only when the connection comes from your own computer and the address is `localhost`. A connection through a tunnel or reverse proxy that exposes it to the Internet needs the token.

[SQLite Edition]{.type-badge .badge-sqlite} (v2.9.0 and later) has no passwords. Only registered devices (browsers) can open the admin screen. A device's key (a random token) lives in that browser's cookie, and the server stores only its hash. A leaked database cannot reveal the key, and there is no password to reuse or leak. Even if someone guesses the admin URL, they get no further than the sign-in screen without a key.

#### Registering the First Device {#device-setup}

[SQLite Edition only]{.type-badge .badge-sqlite}

1. Make a random string (at least 20 characters). Keep a copy. It is used only once, so you do not need to remember it for long.
2. Open `setup-token.txt` in the `setup-token` folder and write that string on a single line below the instructions.
3. Upload the `mikanBox` folder, `index.php` and the `setup-token` folder.
4. Open `mikanBox/admin.php`, enter the token you kept, a username, a display name and a name for this device (for example, "Work laptop"), then press "Register this device".
5. When registration succeeds, this browser stores the device's key and you are taken straight into the admin screen. A token works only once.

If `setup-token.txt` holds no token, one shorter than 20 characters, or one made of a few repeated characters, the registration screen is not shown. Only a notice reading "The installation method has changed. Please see this page." appears. Nobody becomes the admin just by visiting first. When you run mikanBox on your own computer (`localhost`), no token is needed to register the first device: enter a username and a display name (leaving the device name empty makes it "This computer"). A connection through a tunnel or reverse proxy that exposes it to the Internet needs the token. Recovery after every device is lost needs the token even locally.

After you clear cookies, or when you sign in from another browser or computer, that device is unregistered. Add it as follows.

#### Adding a Device

[SQLite Edition only]{.type-badge .badge-sqlite}

On a registered device, open Site → "Devices and users" → "Add a device" → "Issue a code". The code is shown at the top of the screen. Open the admin screen on the new device and enter the code and a device name. A code has 10 characters, is valid for 3 hours and works once. Keep it private. After 10 wrong entries from the same address (IP), entry from that address is blocked for 15 minutes.

To invite another editor, use "Invite someone" in the same place: issue an invite code and hand it over. The invited person chooses their own user ID and display name when they first sign in. A device added with an add code is registered as the same user as whoever issued the code.

#### When Every Device Is Lost (Recovery) {#device-recovery}

[SQLite Edition only]{.type-badge .badge-sqlite}

1. Make a new token, write it to `setup-token.txt` in the `setup-token` folder and upload it. A value that was used before does not work.
2. On the sign-in screen, open "Recovery (all devices lost)" and enter the token, an existing username and a device name.

Recovery stops every registered device and clears unused codes, edit locks and the MCP API key. Create a new key under "MCP API Key".

#### Updating from 2.8.x {#upgrade-to-devices}

[SQLite Edition only]{.type-badge .badge-sqlite}

Updating to v2.9.0 deletes passwords and security answers, so you can no longer sign in with a password. Usernames and display names are kept. The MCP API key does not change.

- If the browser you were signed in with before the update is still signed in, it becomes the first device automatically, whichever way you updated ("System Update" or overwriting files over FTP), provided it opens the admin screen within 2 hours of the first visit to the new version and no device exists yet. Opening a public page first does not matter. If the sign-in has lapsed (for example, the browser was closed), nothing is registered.
- If you use a different browser, the sign-in has lapsed, or more than 2 hours have passed, register with a token. Visiting the new site creates the `setup-token` folder automatically (if it does not, upload the `setup-token` folder from the package). Write a token as in steps 1 and 2 above, then enter the token, your existing username and a device name on `admin.php` (no display name is needed). If you update over FTP, preparing the `setup-token` folder and token beforehand is the safe way.
- The automatically registered browser shows a notice at the top of the screen, "Passwords are gone. …", until you press "Dismiss".
- A browser or user that was not registered sees the reason on the sign-in screen: "This site no longer uses passwords (device authentication). You cannot sign in with a password; …".
- Other users ask a registered device to issue an add code.
- Preview URLs of pending-approval pages stop working after the update because the signing secret changes.

### Signing In to Multiple Installations {#multi-site-login}

Starting with v2.8.6, login state, logout and CSRF tokens are separate for each installation, even on the same domain or when flat and SQLite editions are mixed. Logging into one site does not log you into another. Moving the installation directory on the server also requires a new login. Different URLs pointing to the same real directory are treated as the same installation.

[SQLite Edition]{.type-badge .badge-sqlite}: the name of the device-key cookie also differs per installation, and the key's hash exists only in that installation's database. A key from another site does not work.

#### Saved Passwords [flat Edition only]{.type-badge .badge-flat}

Browser-saved passwords and server login state are separate. Editing a saved password in your browser does not change the server password. Submitting a password change in the admin panel changes that site's password. The current password is verified and the new password needs at least four characters.

Setup, login and password-change forms show a read-only “Site ID for password saving”, using the admin URL path (for example, `/shiro/mikanBox/admin.php`). This is a hint to help password managers distinguish saved entries, not an additional credential. Check that this ID matches the intended site when saving or updating a password. Review saved entries if you move the site URL.

Forms identify username, current-password and new-password fields, but the browser or password manager decides how entries are stored or overwritten. The app cannot guarantee separate saved entries or automatically migrate existing ones.

This separation prevents accidental shared authentication. It does not provide complete isolation from other apps or scripts on the same origin.

#### Distributing to Others

If you deliver a site to someone else — including your own custom design and content — the steps for letting them set up a new admin account on their own environment differ by edition.

- [SQLite Edition]{.type-badge .badge-sqlite}: Create an empty file named `mikanData/reset-accounts.txt` on the server. On the next visit, all registered users, devices and unused codes are deleted and the initial setup screen appears. This file is deleted automatically afterward, so there's no risk of accidentally leaving it in place. The recipient then follows [Registering the First Device](#device-setup) with a token of their own.
- [flat Edition]{.type-badge .badge-flat}: Clear the value of `"password_hash"` inside the `mikanData/settings.json` file.

#### Security

Files other than `admin.php` (such as the JSON files inside the `data` folder) are blocked from direct browser access (via `.htaccess`). The program inside the admin folder (`lib`, `views`, `lang`) cannot be opened directly from a browser either.

---

## Overview {#overview}

mikanBox builds and manages a site across these four parts.

### [description]{.material-symbols-outlined .mat-icon}Pages

Manages the pages that make up the actual substance of the site. You decide the URL (ID), set the title and SEO information, and write the body content (Markdown/HTML).

### [save]{.material-symbols-outlined .mat-icon}Site

Groups together the settings and management tools for running the site as a whole: the admin memo, Static Site Generation (SSG), language, the MCP API key, CSV import, data management/backup, common site settings, devices and users (SQLite Edition), and changing your password (flat Edition).

### [widgets]{.material-symbols-outlined .mat-icon}Design

There are three types of components: [Page]{.type-badge .badge-page}, which sets the design for an entire page; [Part]{.type-badge .badge-part}, which builds shared building blocks; and [AI Instructions]{.type-badge .badge-ai}, for writing instructions aimed at an AI agent. Anything shared across the site, like a header, footer, or navigation, is worth building as a Part so it can be reused anywhere. This screen's main role is managing these design elements (components).

### [image]{.material-symbols-outlined .mat-icon}Media

In Cloud, use “Resize▼”, “Rename▼”, and “Convert to WebP” in that order. WebP conversion appears only for JPEG/PNG. WebP conversion automatically uses the `.webp` extension and updates references in page content, CSS, JavaScript, OGP images, components and the common site OGP image. Published HTML requires rebuilding and publication. Media follows the category selection at the top of the admin page, showing matching filename prefixes and shared `g_` images. Uploads receive the selected category prefix unless the name already starts with an alphanumeric prefix. Filename search operates within that selection.

Manages image, video, and audio files. You can quickly upload, browse the list, resize, and copy a filename ready to paste elsewhere.

---

## Common Elements

#### Preview

Opens the site's top page (in a new tab).

The browser tab shows "🍊site name" (the sign-in screen shows "🍊site name - Sign in").

#### How actions work (no full-page reloads)

Admin actions run without reloading the whole screen, and the result appears as a message at the bottom right. Confirmations (such as deleting) are shown inside the screen, never in the browser's own dialog. Opening and closing editors always looks the same: opening scrolls to the top of the editor, and closing returns to the row you were editing, keeping the filters, search and list page. The screen reloads only when what the screen is changes: system update and restore, switching the language, signing in and out, first setup, and stopping this device (which signs you out). Visual Editing's Undo and Redo reload because the rendered page itself changes.

#### Logout

Logs you out of the system.

#### Help (next to each section's heading)

Opens this help page (in a new tab).

#### Ask AI

Shown next to each Help button. It opens a question field and lets you launch GPT or Claude in a new tab. Only the question, current page and Help section, and the public information source needed for the answer are passed to the external AI. The administration MCP API key, admin memo, private pages, and site settings are never included.

The prompt is also copied to the clipboard in case the service does not prefill its input. GPT is directed to the official public manual for the current Help section. Japanese pages use the Japanese manual (`https://yoshihiko.com/mikanbox/help_ja.html`); all other page languages use the English manual.

Claude is not given an HTML manual URL. It is directed only to the anonymous, read-only public MCP at `https://yoshihiko.com/mikanbox/mcp`. Before using the Claude button, add that URL as the remote MCP server under Settings > Connectors > Add > Add custom connector.

The prompt begins by stating that it only requests guidance from official public information and does not request actions on external services or access to personal or private information.

WebMCP and the public MCP endpoint are optional capabilities for in-browser agents and structured retrieval. The public endpoint is `site URL/mcp`; it is anonymous and read-only. It is separate from the administration endpoint at `site URL/mikanBox/mcp` and never exposes API keys, admin memos, private pages, site settings, or write tools. GPT uses the official public manual URL; Claude uses the public MCP.

`/mcp` is the HTTP endpoint for Remote MCP clients. WebMCP tools on the page use the same public endpoint and expose only `search_help`, `get_help_section`, `get_product_info`, and `get_agent_instructions`. The old `mikanBox/public-mcp.php` URL remains available for compatibility.

In addition to stateless MCP 2026-07-28 requests, the public endpoint accepts initialize-based Streamable HTTP connections for MCP 2025-11-25, 2025-06-18, and 2025-03-26 for Remote MCP client compatibility. Every mode remains anonymous and read-only, exposes only the same four tools, and requires neither OAuth nor an API key.

The GPT bootstrap prompt includes the human-readable official manual URL. The Claude prompt contains no HTML manual URL and instead instructs the connected public MCP to use `search_help`, `get_help_section`, `get_product_info`, and `get_agent_instructions`. Neither prompt embeds the full manual.

The public MCP sources are the published CMS pages `help_ja` and `help_en`. Their content is split by heading only when the page status is `public_dynamic` or `public_static`; drafts and all other pages are excluded. If a designated page does not exist, mikanBox falls back to its packaged read-only manual.

To place the same question box on a public or product-introduction page, add `{{COMPONENT:_ai_question}}` to the page body. The `_ai_question` component calls the `{{AI_QUESTION}}` tag internally, so you can edit the component HTML or placement when needed. For the advanced case of explicitly selecting a Help section, place a tag such as `{{AI_QUESTION:page-edit}}` directly.

The question is sent to the selected external AI and may be stored in that service's history. Do not enter API keys, passwords, personal information, or unpublished content.

---
## Pages {#page-mgmt}

A screen for reviewing and managing all pages you've created, in a list.

#### [add]{.material-symbols-outlined} Create New{.m-btn .m-btn-blue}

Creates a new page. Clicking opens the page-edit area. If you create a new page while a category filter is active, the page-edit screen opens with that category already filled in.

### Filtering by Category

Above the page list, registered categories are shown as a tag cloud. Click one to narrow the list down to only pages in that category.

#### Category:

- [All]{.m-btn .m-btn-gray} — Clears the filter and shows every page.
- [Category name]{.m-btn .m-btn-gray} — Clicking narrows the list to pages that include that category. Clicking the "×" in the tag's corner removes that category from every page in one go (only shown in delete mode).
- [[add]{.material-symbols-outlined}New]{.m-btn .m-btn-blue} — Type a new category name directly and add it. Even a category not yet assigned to any page will show up in the cloud right away.
- [[delete]{.material-symbols-outlined}Delete]{.m-btn .m-btn-gray} — Switches to "delete mode," where an "×" appears on every category tag for removal.

Categories that start with "_" (`_nav`, `_sample` and so on) are structural markers for building the site. The cloud shows them dimmed, after the ordinary categories. See [Category](#page-edit) in Page Editing.

### Keyword Search [SQLite Edition only]{.type-badge .badge-sqlite}

#### Search box

Search titles, body content, and more with the field at the right of the heading row. The list updates as you type. The × in the field clears the search.

### Page List

#### Edit / Preview

The select to the right of "Ask AI" in the heading filters the list by output format (HTML, HTML + Markdown, Markdown, data, mikanBox DB), in the same place and style as the type filter on the Design screen. It combines with the category filter and the search, and stays when you open and close the page editor.

- [[edit]{.material-symbols-outlined}]{.m-btn .m-btn-blue} — The pencil button. Opens the edit screen for that page.
- [[edit_square]{.material-symbols-outlined}]{.m-btn .m-btn-blue} — Opens the page in [Visual Editing](#visual-edit).
- [[language]{.material-symbols-outlined}]{.m-btn .m-btn-blue} — The globe button. Opens a published page at its real site address in a new tab (Markdown-only and data pages at `/ID.extension`, mikanBox DB at `/api/ID.json`, Public (Static) at the built file). Drafts and pending pages show it greyed out.

The page editor preview and this globe work as follows.

|Status|Page editor preview (shown through the admin screen)|Globe in the page list (the real site address)|
|---|---|---|
|Draft|What you are editing|None (greyed out)|
|Pending (SQLite)|What you are editing|None (greyed out; the review link is in the editor)|
|Public (Dynamic)|What you are editing (check it before saving)|The real site (shows the saved content)|
|Public (Static), before a build|What you are editing|The real site (the previous content until you build, or nothing yet)|
|Public (Static), after a build|What you are editing|The real site (the same as the saved content)|

- For a page with "Pending approval" status, this instead opens a preview share URL (with a preview token). [SQLite Edition only]{.type-badge .badge-sqlite}

#### Status

A dropdown for switching each page's publication status. Changes are saved immediately.

- **Draft** — Private. Not accessible to anyone but the admin.
- **Pending approval** — [SQLite Edition only]{.type-badge .badge-sqlite} Private, but anyone who knows the unguessable preview URL (`?preview=token`, valid for 7 days) can view it. Useful for sending a page to a reviewer for approval. Pages pending approval are shown at the top of the list.
- **Public (Dynamic)** — Rendered dynamically by PHP.
- **Public (Static)** — Published as a static HTML file, following the directory/file settings configured under Static Site Generation (SSG) on the Site screen.

If you switch a page that was set to Public (Static) to any other status, its static HTML file is deleted.

#### ID

The identifier (slug) that becomes the page's URL. For example, setting this to `news` gives it a URL of `/news`.

#### Title

The page's title. Clicking it opens the edit screen.

#### Updated

Set when the page is created. The date/time can be changed.

#### Order

Controls display priority in lists like navigation. Within `NAV_LINKS`/`NAV_CARDS` output, and within this page list, lower numbers appear earlier (higher up). Anything below 0 is excluded from list output.

#### Category

Shows the categories assigned to the page. Used for filtering via things like `{{NAV_LINKS:category}}`. Categories that start with "_" are shown dimmed.

#### Output format labels

Pages whose output format is not HTML show a small label beside the title: `+MD` for HTML + Markdown, `MD` for Markdown, and `JSON`, `JS` and so on for data formats. The globe button opens published pages at their real site address. The Visual Editing button is greyed out and cannot be pressed on pages that do not publish HTML.

### Static Site Build Button

#### [auto_awesome]{.material-symbols-outlined} Build Static Site{.m-btn .m-btn-blue}

Found at the bottom of the page list. Writes out every page whose status is "Public (Static)" as static HTML files. After editing a Public (Static) page, other pages can end up out of sync, so it's a good idea to rebuild the whole site here.

---

## Page Edit {#page-edit}

A screen for editing a page's content and settings in detail. Opens when you click "Create New," a page's title, or its Edit button.

The [[edit_square]{.material-symbols-outlined} Visual edit]{.m-btn .m-btn-blue} button at the top right opens the page in [Visual Editing](#visual-edit).

#### History [SQLite Edition only]{.type-badge .badge-sqlite}

A dropdown marked with a <span class="material-symbols-outlined mat-icon">history</span> icon. Every time you save, up to 10 past versions are kept automatically, selectable along with the date/time and editor name (when multiple users are involved). Selecting a past revision loads its content into the form and shows a "Viewing Past Revision" warning banner. Pressing [[save]{.material-symbols-outlined}Save]{.m-btn .m-btn-blue} at that point restores that revision as the current version. Selecting the top entry ("Latest Version (Current)") again returns you to the latest content.

#### Updated

Set when the page is created. The date/time can be changed. When a page moves from a private status (draft, pending approval, etc.) to "Public (Dynamic)" or "Public (Static)," this is automatically updated to the actual moment of publication (simply editing an already-published page does not change it).

#### Title

The page's title. Required. Available inside components as `{{TITLE}}` or `{{FULL_TITLE}}` (formatted as "Page Title - Site Name").

#### Status

Choose the page's publication status via radio buttons.

- **Draft** — Private. Not accessible to anyone but the admin.
- **Pending approval** — [SQLite Edition only]{.type-badge .badge-sqlite} Private, but an unguessable preview share URL (`?preview=token`, valid for 7 days) is generated automatically and shown right on this screen. You can just send the URL to a reviewer so they can check the content without ever logging into the admin panel.
- **Public (Dynamic)** — Rendered dynamically by PHP.
- **Public (Static)** — Published as a static HTML file, following the directory/file settings configured under Static Site Generation (SSG) on the Site screen.

If you switch a page that was set to Public (Static) to any other status, its static HTML file is deleted.

#### Preview Share URL [SQLite Edition only]{.type-badge .badge-sqlite}

Shown when status is set to "Pending approval." A text box holds the URL, with a [[content_copy]{.material-symbols-outlined}Copy]{.m-btn .m-btn-gray} button next to it to copy it to the clipboard.

From mikanBox 2.9.5, this URL is valid for 7 days. An expired URL no longer opens, so reopen this screen to get a new URL and send it again. The same URL is shown throughout a given day. URLs issued before 2.9.5 stop working after the update. Pages opened through the preview URL are served without a referrer, are marked not to be indexed by search engines, and are not cached.

#### Memo

A private note visible only to admins. Free-form space for anything you want to remember or hand off to someone else. It is excluded from public pages and APIs. Authenticated management MCP clients, including AI, can read it as working context. Reading a note does not grant permission to copy it into published content.

#### ID

The identifying slug that becomes part of the page's URL. Alphanumeric characters and hyphens are allowed. A dot (`.`) is not allowed; the extension comes from the [output format](#output-formats). The page's URL takes the form `siteURL/ID`. Note that saving with an ID matching an existing page will overwrite it. The index page's ID cannot be changed. You can also use "/" within an ID. On a static site, any "/" in the ID is treated as a directory boundary; on a dynamic site, a pseudo directory structure is created the same way. For example, an ID of `news/2024` produces a URL like `siteURL/news/2024`.

#### Output format

Choose one output format: HTML, HTML + Markdown, Markdown, or a data format (JSON, XML, CSV, Text, JavaScript, CSS, [mikanBox DB](#mikanbox-db)). The default is HTML. The URL the page will be published at (for example `https://example.com/news-list.json`) is shown right below. Fields the chosen format does not use are hidden (for Markdown, the design component and the page CSS and JS; for data formats, also the OGP image, description and keywords). Without a published URL, formats with Markdown cannot be chosen. See [Output Formats](#output-formats).

#### Design Component

Choose which page component to use for this page's layout. Any component with "Use as a Page Component" checked, on the Design screen, appears as an option here.

#### Markdown version footer components

Shown for HTML + Markdown and Markdown. Enter, comma-separated and in order, the IDs of the components to add to the end of the [Markdown version](#markdown-version). Leave it empty for none. Page components (wrappers) and AI instructions cannot be used.

#### Expand tags

Shown for data formats. Switches whether `{{ }}` tags in the body are expanded. On by default for JSON, XML, CSV and Text; off for JavaScript and CSS. Values are escaped for the format ([Data Formats](#data-formats)).

#### Category (comma separated)

Freely enter, comma-separated, the categories this page belongs to. Categories currently in use are shown below the field. A category listing page is not generated automatically; if you need one, create a page and place `{{NAV_LINKS:category}}` or `{{NAV_CARDS:category:componentID}}` in it.

Categories that start with "_" (`_nav`, `_sample`, `_temp` and so on) are treated as structural markers for building the site. One character tells them apart from practical categories such as `coffee`, and a page can have any number of them. They work in explicit selections such as `{{NAV_LINKS:_nav}}`, but are not used by [`@same`](#navigation), `{{CATEGORY}}` or the `categories` of the [Markdown version](#markdown-version) (`{{CATEGORY:all}}` includes them). Category names that start with "@" are reserved, to keep them apart from selectors such as `@same`.

#### Order

Controls the sort order in things like navigation. Lower numbers come first. Anything below 0 is excluded from list output.

#### Thumbnail / OGP Image

Specify the filename (e.g. `ogp.jpg`) of the image shown when shared on social media, or in card lists. Output as the `meta` tag's `og:image`. Available inside components as `{{OGP_IMAGE}}`.

#### Keywords

Enter comma-separated keywords (meta keywords) used for the page's search visibility. Output inside components as `{{KEYWORDS}}`.

#### Description (meta description)

Set a summary description for the page. Output via `{{DESCRIPTION}}`, and used as the blurb shown under the title in search results. It's displayed with no gap right after the body field, so you can edit both as a single continuous block.

#### Content

Write the page's main content using Markdown syntax or HTML. Markdown and HTML can be mixed freely. Complex HTML generated by AI can be pasted in as-is and will still work correctly. See the guide at the bottom of the edit screen for the available Markdown syntax and custom tags — or for more detail, see [Markdown](#markdown) and [Components (Custom Tags)](#components).

The field heading follows the output format: "Content (Markdown or HTML)", "Content (Markdown)", "Content (JSON)" and so on. In data formats the body is published as it is ([Data Formats](#data-formats)).

#### Custom CSS

Write styles that should apply only to this page. This is automatically scoped, so it never affects other pages or anything outside the body content. Custom tags can also be used inside this CSS.

To intentionally style an element outside the page scope, wrap the entire selector in `:global(...)`. For example, `:global(.hero_box) { display: none; }` applies the rule to `.hero_box` outside the scoped page content, while ordinary selectors remain limited to this page's body content.

#### Page JavaScript

Available in mikanBox 2.6.10 and later. Write JavaScript without `<script>` tags in the collapsible field, whose heading uses the same bold weight as the CSS field. The field opens automatically when it contains code. The CMS inserts scripts at the end of the page body, normally just before `</body>`; HTML without closing tags receives them at the end. The page code is output once, following component JavaScript. This works in dynamic pages, previews, and static HTML.

CSS scoping does not apply to JavaScript. JS fields are used as code, without Markdown conversion, custom-tag expansion, or automatic URL completion. Existing `<script>` tags in the HTML body remain supported. Handle dependencies on external libraries and any required loading order in your code.

#### [save]{.material-symbols-outlined} Save{.m-btn .m-btn-blue}

Saves everything about the page.

#### [open_in_new]{.material-symbols-outlined} Preview{.m-btn .m-btn-blue}

Shows what you are editing in a new tab, without saving ("Shows what you are editing (nothing is saved)." appears next to the button). It works for every output format and status, and for new pages. A Public (Dynamic) page goes live the moment you save, so use this preview to check it first. Pages that publish HTML show as pages; Markdown-only, data and mikanBox DB pages show the text or JSON they publish, in UTF-8. The preview is shown cut off from the admin screen (its scripts run, but cannot touch the admin screen). To open the real site address, use the globe in the page list (see the table under [Pages](#page-mgmt)).

#### [arrow_back]{.material-symbols-outlined} Back to List{.m-btn .m-btn-gray}

Returns to the page list, scrolled to the row of the page you were editing, with the category and output-format filters, the search and the list page kept (opening the editor scrolls to its top). Note that unsaved changes will be lost.

#### [delete]{.material-symbols-outlined} Delete{.m-btn .m-btn-red}

Permanently deletes the page. This cannot be undone.

At the bottom of the edit screen, a quick reference for Markdown syntax and the list of available tags (tag guide) appears collapsed. Click to expand it.

## Visual Editing {#visual-edit}

Open a page **exactly as it looks** and edit its body and page CSS right there. Your layout, CSS and images are shown as they are, so you can rewrite the Markdown while watching the result. It uses the same login as the admin screen; if you are not logged in, you are sent to the admin login. It is not available on public demo (demo mode) sites.

Visual Editing works only on pages that publish HTML (output format HTML or HTML + Markdown). On Markdown-only and data pages the button is greyed out and cannot be pressed, and such pages are not listed in the Visual Editing page picker.

#### How to open it

- The [[edit_square]{.material-symbols-outlined}]{.m-btn .m-btn-blue} button in the page list (between the pencil and the preview button).
- The [[edit_square]{.material-symbols-outlined} Visual edit]{.m-btn .m-btn-blue} button at the top right of the page edit screen.
- Open `visual.php` in the admin folder (next to `admin.php`) directly. A page picker appears.

#### Editing the body (pick with the magnifier, edit in the "Body" panel below)

Body editing is **one panel that opens from the bottom: "Body"**. Only the part you picked is **cut out** into the input box, so you can focus on it. The page stays visible while you edit, changes show on it as you type, and the place you are changing gets a frame.

- The everyday screen is **browsing**. Clicking follows links as usual. Links within the site open in that page's **visual editor**, so you can look around. Links that lead outside the editor, such as other sites, open in a new tab and show a note when you touch them.
- To change something, click it with the [[search]{.material-symbols-outlined} Pick to edit]{.m-btn} (magnifier) at the bottom right. On a selector row, **click the path (such as `.pillar`)** or press [[edit_note]{.material-symbols-outlined}]{.m-btn}: the "Body" panel opens with **only that element** in the input box. The element also gets a frame on the page, and the page scrolls by itself so the picked place is visible. The cut-out part corresponds to that place in the whole body, and saving saves the whole body.
- Changes appear on the page right away (also inside the screen-width check, so you can edit **at phone width**). Save with ⌘/Ctrl+Enter or the Save button at the top right of the panel. The page is not reloaded after saving. "Unsaved" is shown until you save.
- Live updates are made in the browser (nothing is sent to the server). Only blocks with parts the server completes, such as `{{…}}` tags, links within the site and images, and changes that regroup the blocks around them (opening or closing a code block, for example), are rendered by the server. Either way, the page looks as it will when published.
- Live updates are separate from saving. If one cannot be shown (for example, the connection failed), "Could not update the page (saving still works)" appears at the top right of the panel; you can still save.
- While the page is open, this screen does not reload by itself when the page changes elsewhere (the management screen, AI through MCP, and so on). If it changed, saving stops and tells you (what you typed stays). Do not edit the same page in two places at once.
- There is no mode to switch after picking. The **target bar** in the panel shows which element is selected, such as `nav › div › a`, with its line numbers in the whole body. [[close]{.material-symbols-outlined}]{.m-btn} stops the cut-out and shows everything.
- If you pick a place that is not a body block (for example the layout), the body button is disabled and the reason is shown. If you pick the page title (the title shown by the layout), it says so and links to the page settings where you change it. If the page has no body block to pick, a note appears at the bottom of the screen. "Raw HTML" pages can be picked in the same way. For a page that is a whole document with `<html>` and `<body>`, you pick by following the tag hierarchy, such as `html › body › main › section › p` (text inside the page's `<script>` and `<style>` is not read as tags).
- When the target is a container such as `<div>` or `<ul>`, the [[subdirectory_arrow_right]{.material-symbols-outlined}]{.m-btn} button on the bar lets you **pick an inner element** (the badge number is how many inner elements there are). With one inner element it goes straight in; with several you pick by number, such as "1 `<li>` …". [[arrow_back]{.material-symbols-outlined}]{.m-btn} goes back out one level (the part in the input box changes accordingly). Element ranges are split by **character position**, not by line, so HTML written on a single line, or elements side by side on one line, can be picked.
- A Markdown paragraph written inside HTML such as a `<div>` (a run of lines separated by blank lines) is cut out on its own when you pick it on the page. Adding, duplicating or deleting next to it treats it as an ordinary paragraph (not wrapped in a tag).
- [[add_row_above]{.material-symbols-outlined}]{.m-btn} [[add_row_below]{.material-symbols-outlined}]{.m-btn} on the bar add a paragraph above or below the selected element (for an inner element, wrapped in the same tag), [[content_copy]{.material-symbols-outlined}]{.m-btn} duplicates it and [[delete]{.material-symbols-outlined}]{.m-btn} deletes it (press **twice** to confirm). These change things outside the cut-out part, so they are undone one by one with [[undo]{.material-symbols-outlined}]{.m-btn} on the bar, not with ⌘/Ctrl+Z in the input box. ⌘/Ctrl+Z still works in the input box for typing.
- The bar above the box inserts headings, bold, italic, links, bulleted and numbered lists, quotes, code and images (⌘/Ctrl+B, I and K also work).
- The [[image]{.material-symbols-outlined}]{.m-btn} button on that bar opens a **list of the images** in the `media` folder (newest first, filterable by name). Picking one inserts Markdown `![](filename)` at the cursor. "Add" in the list uploads a new image. You can also **paste** (or drag) an image into the box (png, jpg, gif, webp, up to 10 MB).
- An HTML block is picked as **one unit** even when it contains blank lines before its tags are closed.
- Picking a part built by a `{{…}}` tag, such as navigation cards, selects the single tag line. The text and images of the cards come from the linked pages' settings, so they cannot be edited here.

#### Whole body and page CSS

- "Body" at the bottom right opens the whole body without picking anything.
- "CSS" edits this page's own CSS. It applies as you type; save with ⌘/Ctrl+Enter. Closing with unsaved changes reverts them. The [[palette]{.material-symbols-outlined}]{.m-btn} button on a magnifier selector row shows a **list of the CSS rules** related to that row's element as numbered choices, such as `1 .a-mikan`, `2 .a-mikan p`. "applies" means the rule really applies to the element (including ones with `:hover`), and "mentions it" means the selector contains the element's class name or id (such as `.a-mikan p`, which applies to elements inside it). The rule you choose is cut out into the CSS panel. Each choice shows its condition such as `@media`, its line in the CSS and its first declaration (such as `padding: 12px 0`), so rules with the same selector in several places can be told apart. "applies" comes first, then "mentions it". The list is about **the first element of the row you pressed** (the same level as when picking the body: for the row `div.profile div.rows ul li` it is `div.profile`; to see the rules of the `li`, press the `li` row). [[arrow_upward]{.material-symbols-outlined}]{.m-btn} [[arrow_downward]{.material-symbols-outlined}]{.m-btn} on the bar move to the other choices and [[close]{.material-symbols-outlined}]{.m-btn} shows everything. Names inside CSS comments or strings are ignored. If nothing is listed, the rule may be in the site CSS or a part's CSS rather than the page CSS.

#### Undo and redo

- The ↶ ↷ buttons at the bottom right (⌘/Ctrl+Z, ⌘/Ctrl+Shift+Z) undo the last 3 saves of the body and CSS. Opening the page again (moving to another page or reloading) empties this history.
- The [[history]{.material-symbols-outlined}]{.m-btn .m-btn-blue} button opens the **edit history**. For each editing session, the state before your first save after opening the page is kept, up to 10 per page. Choose one to go back to it (you can undo that with ↶).
- Saving never changes the status. Only creating and duplicating make a draft.
- On the flat edition the history is stored in the data folder under `visual-history/` (it is not included in backups, and is removed when the page is deleted). On the SQLite edition it is kept in the page revisions of the admin page editor.

#### Switching, creating and duplicating pages

- ⌘/Ctrl+K opens a page list from the side. The page that is open comes first, then recently opened pages. Filter by title or ID.
- "New page" and "Duplicate this page" (and the duplicate button on each row) create pages. **They are always created as drafts**; change the status in the admin screen to publish. ID rules are the same as in the admin screen.
- Outside input boxes: `g` then `l` opens the admin page list, `g` then `e` this page's settings, `g` then `p` the page picker (the admin screen opens in another window).

#### Tools for checking

- Magnifier (Pick to edit): click an element on the page to see candidate selectors (class names, etc.). The upper rows are the clicked element, the lower rows its outer parents. Each row has [[edit_note]{.material-symbols-outlined}]{.m-btn} (same as clicking the path: select the element at that level in the "Body" panel; only for elements inside the body), [[palette]{.material-symbols-outlined}]{.m-btn} (choose among the CSS rules related to the element at that level) and [[content_copy]{.material-symbols-outlined}]{.m-btn} (copy).
- Screen width: shows the same page at phone (375), tablet (768) and PC (1200). CSS you type shows there at once. You can pick places with the magnifier here too, edit in the "Body" panel below without closing the check, and your changes show in this view at once. "Full width" ends the check and returns to the normal screen.

#### Safety rules and limits

- Writes are accepted only with the admin login, a CSRF token and a same-site request check.
- The page's own scripts (inline and external tags) do not run in this screen, so that they cannot act with the logged-in administrator's rights. This does not affect how the page looks or how you edit it. External styles, images and fonts are shown.
- Deleting only part of some HTML can break it. If that happens, undo with ⌘/Ctrl+Z.
- Texts on screen follow the language set in the admin screen.

#### After updating

When updating from 2.9.x or earlier, the visual editing files are not installed by that update, because the updater itself is replaced by it. After updating, run **[Reinstall]** once in "System Update" on the Site screen (uploading the files by FTP also works). Once the files are in place, the visual editing button appears in the page list.

---

## Site {#site}

A menu that groups together the settings and management tools related to running the site. Each item is collapsed into an accordion (except for the admin memo).

### Admin Memo {#site-memo}

A private note, visible only to admins, always shown at the top of the site. Free-form space for hand-off notes, work reminders, and the like.

#### [save]{.material-symbols-outlined} Save{.m-btn .m-btn-gray}

Saves the memo's content.

### System Update

Displays the installed version and the latest version available on GitHub. When a newer version is available, use the [Update] button to update mikanBox.

Updates use only published stable GitHub releases. Development branches and tags without a release are excluded. Publication is verified again when the update starts, and the download is pinned to a specific commit. If verification fails, the update stops. Only one update or restore can run per site; if another operation is running, try again after it finishes.

When you are already on the latest version, [Reinstall] is offered instead. It installs the program files for the same version again, and exists as a recovery path for when an update did not apply cleanly and left the program files incomplete. Your pages, settings and media are untouched.

Only program files are replaced. Pages, settings, media, the database, and generated static files are left untouched. One generation of the previous program files is stored in a protected area on the server. A failed update attempts automatic rollback. If rollback also fails, an error is displayed and the backup is retained under `update-backups/.pending-*` in protected storage; keep it for recovery by the server administrator. After a successful update you can use the [Restore] button to return to the immediately preceding version.

After you confirm an update, the button changes to [Updating…] and remains disabled until the operation finishes. When the update completes, the admin screen reloads automatically and shows the newly installed version number and a completion message without a manual reload. The restore operation similarly displays [Restoring…] while it is running.

### Static Site Generation (SSG) {#site-ssg}

mikanBox doesn't just render pages dynamically with PHP — it also has a feature (SSG: Static Site Generation) to export the entire site as "static HTML files," and the two can be mixed freely.

#### Benefits of SSG

- **Extremely fast rendering** — Since the server doesn't need to run PHP, pages load quickly.
- **Stronger security** — You can configure things so no PHP files or data (JSON) sit in the public area at all, minimizing the risk of tampering.

#### Static Site Build Method

- **Generate static pages on this server** — Normally writes HTML into the site root. Specify an output directory only when a different location is needed.
- **Create an upload-ready folder (for local operation)** — Writes HTML and a copy of `media/` into a dedicated output folder. For safety, the site root, the mikanBox core, and the existing media folder cannot be selected as that destination.

For an upload-ready folder, links can be written as portable relative paths (recommended), or fixed to a published URL. Relative links keep working when the folder is moved.

The published root URL can be entered with either link style. Give the whole root of the published site, such as `https://example.com/site`. When the published URL is already decided, as with Cloudflare Pages or Netlify, enter that URL as it is (for example `https://yoursite.pages.dev`).

The published root URL applies only while a static site is being written out. The admin panel and previews you run locally answer with the address you actually opened, so filling in a published root URL never points their images or links somewhere the site is not yet.

- **Fixed published URL** — Required. Used both for the links inside pages and for `sitemap.xml` and `rss.xml`.
- **Relative links** — Optional. Not used for the links inside pages. It is used only to generate `sitemap.xml` and `rss.xml`; left blank, neither file is generated.

#### Output Directory

Specify where the static HTML files get written, as a path relative to the site root (the location where the `mikanBox` folder lives). For example, entering `blog` writes into a `blog/` folder directly under the site root, while entering `../` writes one level above the site root. Leaving this blank writes directly into the site root.

An empty server-side output means the site root. When you select an upload-ready folder, `export` is supplied as the default output directory and can be changed to another dedicated folder.

#### Output Structure / URL Format

Choose the format of the generated HTML files.

- **Directory-based (folder/index.html)**: Writes as `pageID/index.html`. The URL becomes `/pageID/`.
- **File-name based (filename.html)**: Writes as `pageID.html`. The URL becomes `/pageID.html`.

Directory-based output can be published under the exact same URL as dynamic publishing, so switching between them never breaks links. File-name based output includes the extension in the URL and results in a simpler file layout.

The URL style affects HTML only. Markdown versions and data pages are always written as `ID.extension`, such as `ID.md` or `ID.json`. A Markdown-only page is readable only at `/ID.md`. No `.htaccess` is written ([URL Rules](#output-urls)).

The default is directory-based for "Generate static pages on this server" and file-name based for "Create an upload-ready folder." The last selected output structure is remembered separately for each build method.

#### [auto_awesome]{.material-symbols-outlined} Save &amp; Build Static Site{.m-btn .m-btn-blue}

Saves the settings, then writes out every page whose status is "Public (Static)" as static HTML files.

If a page ID contains a `/` (e.g. `news/2024`), it's treated as a directory structure as-is. During a static build, an actual folder is created inside the output directory (e.g. `news/2024/index.html` for directory-based output), and the file is generated inside it.

The build runs in place without leaving the screen, and the number of generated files is reported in a notification. Your settings and the panel you had open stay as they are.

#### How the sitemap, RSS, and podcast RSS are handled

`sitemap.xml`, `rss.xml`, and `podcast.xml` are normally generated fresh by PHP on every request (see [Sitemap, RSS &amp; API](#site-mapfeed) below). Because exported sitemap and RSS files require absolute URLs, SSG writes them when a published root URL is available. Server-side generation falls back to the current site URL when none is configured. Adding or changing pages after a build is not reflected until the static site is rebuilt.

The 404 page (page ID `404`) is left out of both the sitemap and the RSS feed. It is an error response rather than content, so there is no reason to point search engines or readers at it. This applies to static and dynamic generation alike.

[Data-format](#data-formats) pages are left out of the sitemap and RSS as well. Markdown-only pages are listed by their `/ID.md` URL. The sitemap always covers every "Public (Static)" page, also when only one page was built.

### Language {#site-lang}

Sets the display language for the admin panel.

Choose from one of the following three:

- **Match browser settings** — Automatically switches between Japanese and English based on your browser's language setting.
- **Japanese (日本語)**
- **English**

#### [save]{.material-symbols-outlined} Save{.m-btn .m-btn-blue}

Saves the language setting.

### MCP API Key {#site-mcp-key}

Issue and manage the API key that lets an AI agent (like Claude Code) connect to this site over MCP.

The mikanBox administration MCP server supports stateless **MCP 2026-07-28** and, during the compatibility transition, initialize-based MCP 2025-11-25, 2025-06-18, and 2025-03-26. Every protocol mode requires the administration API key.

Connect with the AI application's native Remote MCP (Streamable HTTP) feature.

- MCP endpoint: `site URL/mikanBox/mcp` (the old `mcp.php` URL also remains available)
- Protocols: `2026-07-28`, `2025-11-25`, `2025-06-18`, and `2025-03-26`
- Authentication: `Authorization: Bearer your-issued-API-key` or `X-API-Key: your-issued-API-key`

In clients that support fixed request headers, configure the issued API key using either header above. Never put the API key in the URL query string.

Every tool carries annotations (`readOnlyHint`, `destructiveHint`, `idempotentHint`) that state whether it only reads data or may overwrite or delete existing data. MCP clients that understand these annotations use them to ask for confirmation before high-impact operations such as updating or deleting a page. Tools that return page or component bodies also carry `untrustedContentHint`, so the retrieved text is treated as reference material and never as instructions to the AI.

Claude's custom connector screen supports authless or OAuth authentication. Its OAuth Client ID and OAuth Client Secret fields are not API-key fields. Until mikanBox implements OAuth, the administration MCP cannot be registered safely as a Claude custom connector. Do not paste the API key into the Client ID or Client Secret field. The authless public help MCP can be registered as a custom connector using only its URL.

Treat the API key like a password. Never store it in a public repository or shared file. If several mikanBox sites are connected, AI calls `get_site_info` on first connection and before writes to confirm the target site.

A page's [output format](#output-formats) is set through `output_format` (`html` / `html_md` / `md` / `json` / `xml` / `csv` / `txt` / `js` / `css` / `db`), `md_footer_comps` (an array of footer component IDs) and `expand_tags` (whether a data page expands tags; the format's default when omitted) in `create_page` and `update_page`. `get_page` and `list_pages` also return `output_format`. Updating a published page with `update_page` no longer changes its update date (`updated_at`); it becomes the current time only when a page goes from non-public to public, the same as on the admin screen (since 2.17.0). `status` is `draft`, `public_dynamic` or `public_static`; the former `db` is accepted and saved as `output_format` `db` with Public (Dynamic).

`preview_page` returns the result in the page's output format without saving and regardless of status (with only an `id` it shows the saved content; pass the body and other fields to render them unsaved; a new `id` works too), which is how to check tag expansion. `get_site_info` now returns `version` (the mikanBox version; when it differs from the last connection, read the tool list and `get_ai_context` again). The project rules in `get_ai_context` (`documents`) also include an entry that points at output formats (`mikanbox-output-formats`).

Starting with mikanBox 2.5.1, the MCP connection instructions tell AI to call `get_ai_context` on first connection. This tool returns every [AI Instructions]{.type-badge .badge-ai} component—such as `DESIGN.md`, `BRAND.md`, and `CONTENTS.md`—in a single response. AI treats the returned content as site-specific project instructions for content, design, and code changes. Updating an instruction component updates what AI receives the next time it loads the context.

#### Connection URL

The URL of this site's administration MCP. "Copy" puts it on the clipboard so you can paste it straight into the client's settings.

- [[content_copy]{.material-symbols-outlined}Copy]{.m-btn .m-btn-gray} — Copies the connection URL to the clipboard.

#### API Key

An issued API key is shown in a read-only field. Until one is issued there is no field, only "Generate API Key".

- [[content_copy]{.material-symbols-outlined}Copy]{.m-btn .m-btn-gray} — Copies the API key to the clipboard. Paste the generated key into the settings of whatever tool you're connecting (e.g. `.mcp.json` for Claude Desktop).
- [[auto_awesome]{.material-symbols-outlined}Generate API Key]{.m-btn .m-btn-blue} — (When none is issued) Issues a new API key.
- [[auto_awesome]{.material-symbols-outlined}Regenerate API Key]{.m-btn .m-btn-gray} — (When one is issued) Invalidates the current key and issues a new one. Any existing AI integrations will need to be reconfigured.

#### Enabling MCP and its last-use time (PHP editions)

Clear “Enable MCP” and save to stop management MCP while retaining its key. HTTP requests receive 403, and local stdio connections reject their next request. An already running request is not cancelled. Public help MCP remains available. Existing connections remain enabled after updating; enabling again permits the same key. Regenerate a leaked key as well as disabling access. This switch does not restrict tools that can edit server files directly.

The current key's last successful HTTP authentication is shown in UTC. Its timestamp is written at most once per hour. Invalid keys and public help requests do not update it. Regenerating the key resets the display to “Not recorded.” Local stdio requests do not use an API key and are excluded.

### Security activity (PHP editions) {#site-audit}

“Recent security activity” on the Site screen shows the latest 50 events. Both PHP editions record MCP key issuance/regeneration, enabling/disabling MCP, page publication-state changes, and failed sign-ins. SQLite also records device registration/stopping, enrollment/invitation code issuance, user deletion, recovery and account reset. Successful program rollbacks are recorded too.

Records contain the time, action, actor, necessary target IDs, and a masked IP address. Keys, tokens, codes, passwords, content and full IP addresses are excluded. The final IPv4 octet is masked; IPv6 addresses retain only their first 48 bits. Oldest records are removed above approximately 1 MB. Recording failures are shown in the admin screen without treating a successful operation as failed.

Logs and MCP state live in the data directory's `security/` folder. `events.jsonl.php` contains a PHP access-denial guard followed by JSON Lines records. Apache access rules and the front controller also protect the data directory. Servers serving static files without passing them through PHP must deny web access to the entire data directory. Backups including that directory also contain activity records and must be stored securely. These records do not detect tampering by someone able to modify server files.

### CSV Import {#csv-import}

A tool that converts CSV data into a database-like form (DATAROW blocks) usable within the site.

Find it on the Site screen in the PHP editions, or on Data migration and import in the Cloud edition.

#### Choose File

Select a CSV file. Encoding (UTF-8 / Shift-JIS) is detected automatically.

#### [content_copy]{.material-symbols-outlined} Convert &amp; Copy{.m-btn .m-btn-gray}

Reads the first row as field names and converts every other row into a `{{DATAROW:number}}` block, copied to the clipboard. After copying, paste it into the body content of a page and save.

#### Tips for Use

- Write field names (the first row) using alphanumeric characters.
- Thousands separators in prices (e.g. `1,000`) are read correctly, since Excel automatically quotes them.
- We recommend keeping data-only pages set to **Draft**. Visitors won't see them, but they can still be referenced via `{{POST_MD:pageID#rowID:FIELD}}` or `{{NAV_CARDS}}`.
- Publishing the page with the output format **mikanBox DB** shows nothing as a page, while its `{{DATA}}` and `{{DATAROW}}` data becomes readable as a JSON API at `siteURL/api/pageID.json` ([mikanBox DB](#mikanbox-db)).

### Data Management [SQLite Edition only]{.type-badge .badge-sqlite} {#site-data-mgmt}

Download and back up all of the site's data (SQLite file / JSON ZIP) as well as any uploaded media files. You can also import data from external sources.

#### [download]{.material-symbols-outlined} Download All Data (SQLite){.m-btn .m-btn-gray}

Downloads the site's database file (`mikanBox.sqlite`) as-is.

#### [download]{.material-symbols-outlined} Download All Data (JSON){.m-btn .m-btn-gray}

Exports all text data — pages, designs, settings, and more — as JSON files and downloads them.

#### [download]{.material-symbols-outlined} Download All Media{.m-btn .m-btn-gray}

Bundles every uploaded file (images, etc.) into a single ZIP and downloads it.

#### [settings_backup_restore]{.material-symbols-outlined} Data Import &amp; Migration Tool{.m-btn .m-btn-blue}

Opens the [Data Migration &amp; Import Tool](#data-migration) (`convert.php`) in a new tab.

### Backup [flat Edition only]{.type-badge .badge-flat} {#site-backup}

Downloads all of the site's data (JSON) and any uploaded media files (jpg, svg, etc.) as a ZIP. Regular backups are recommended.

#### [download]{.material-symbols-outlined} Download All Data (JSON){.m-btn .m-btn-gray}

Exports all text data — pages, designs, settings, and more — as JSON files and downloads them.

#### [download]{.material-symbols-outlined} Download All Media (files){.m-btn .m-btn-gray}

Bundles every uploaded file (images, etc.) into a single ZIP and downloads it.

### Site Settings {#site-settings}

The first row places the immutable site ID on the left and the site name on the right. Explanations appear below their input fields.

Common site settings show the site name on the left and the read-only site ID on the right in equal-width columns. The site URL follows on the next row, with its explanation below the field. Static generation method hints use normal font weight. In Cloud, open the dedicated Data Import & Migration Tool from site settings for complete JSON backup and restore.

#### Site ID (for AI)

An immutable ID generated for each site. AI uses it to avoid confusing one mikanBox site with another when several sites are connected. On existing sites, it is generated when Site Settings are saved or when AI first requests the site information.

#### Site Name

Sets the site's name. Output via `{{SITE_NAME}}`. `{{FULL_TITLE}}` is displayed as "Page Title - Site Name," and is also used for things like bookmark titles and search-result titles.

#### Site URL

Identifies the target site to AI. Normally leave it blank: HTTP connections infer the directory containing the site's `index.php`. This is separate from the SSG output directory or deployment destination. Override only to identify another public URL; it does not move or redirect the site, or change SSG output. CLI connections lack HTTP information, so configure the URL explicitly when needed.

#### Site Environment

Choose Production, Staging, Development, Local, or Unspecified. Through MCP, AI can call `get_site_info` to check this environment together with the site ID, name, and URL.

This is advisory metadata, not an automatic restriction on access or operations. For example, seeing Production before making test edits gives AI a reason to choose a development site or ask for clarification. Check it together with the immutable site ID. It was introduced with MCP site identity support in v2.3.

#### Site Description

Sets the site's overall description. When a page doesn't have its own description set, this is used as its description in search results. Output via `{{SITE_DESCRIPTION}}`.

#### Common Keywords

Sets the site's keywords. When a page doesn't have its own keywords set, these are used as its search keywords.

#### Global OGP Image

Sets the site-wide OGP image. When a page doesn't have its own OGP image set, this is used as the image shown when shared on social media. Output via `{{SITE_OGP_IMAGE}}`. Recommended size: 1200 × 630 px.

#### Pages per page (in list)

Sets how many items appear per page before the "Pages" list paginates (default: 30). If the total number of pages is below this count, pagination links aren't shown.

#### Media items per page (in list)

Sets how many items appear per page before the "Media" list paginates (default: 100).

#### [save]{.material-symbols-outlined} Save{.m-btn .m-btn-blue}

Saves the "Site Settings" content.

### Devices and Users [SQLite Edition only]{.type-badge .badge-sqlite} {#site-users}

Manages the devices that can open the admin screen and the editor accounts. Each editor can register devices, and edit history records the editor's name. There are no passwords (see [Initial Setup and Sign-in](#first-setup)). Keeping devices safe (screen lock, handling a lost device) is your responsibility. Stop a lost device here from another device.

#### Users and Devices

Each user is shown with their own devices. You see the username and display name, and each device's name, registration time, last use and expiry. Your own device is marked "(this device)".

- [Stop]{.m-btn .m-btn-red} — Stops that device and cancels unused codes it issued. It is signed out immediately and needs an add code to be used again. Stopping your own device is the same as logging out. There is no log-out button at the top right of the admin screen.
- A device added with a code cannot stop other devices or delete users for 3 days after it is registered. This prevents someone with a stolen code from locking the owner out. The first device has no such limit.
- When someone registers a new device, a notice appears the next time you open the admin screen. If you do not recognize it, stop it in this list.
- Your own user has a "Display name" field. Change the name shown in edit history and similar places, then press "Save". The username cannot be changed.
- [[delete]{.material-symbols-outlined}Delete]{.m-btn .m-btn-red} — Deletes another user, together with their devices and unused codes, including invitations issued by those devices. This cannot be undone. You cannot delete the user you are signed in as, or the only user. If a database operation fails while stopping a device or deleting a user, all related changes are rolled back and an error is shown.

#### Add a Device

Issues an add code. Open the admin screen on the new device and enter the code. A code is valid for 3 hours and works once. The code is shown only right after it is issued and cannot be viewed later.

##### Invite Someone

Issue an invite code and hand it to the person you want to invite. You do not choose a user ID or display name. When the invited person enters the code and a device name on the sign-in screen, a screen appears where they choose a user ID and display name (alphanumeric characters, underscores and hyphens only). Registering creates that user and registers the device. A user ID that someone else uses cannot be chosen; if it is refused, the code is not spent.

#### Unused Codes

Codes that have been issued but not used. "Cancel" invalidates one.

#### Unused Devices Expire After

Choose 30, 90 or 180 days. Each use of a device extends its period. A device that has expired must be added again with a code.

**If every device is lost:** register again from a new token, as described in [When Every Device Is Lost (Recovery)](#device-recovery).

### Change Password [flat Edition only]{.type-badge .badge-flat} {#site-password}

Changes the admin panel's login password.

#### Current Password

Enter the password you currently use to log in.

#### New Password

Enter a new password (at least 7 characters).

#### [save]{.material-symbols-outlined} Save{.m-btn .m-btn-blue}

Changes and saves the password.

**If you forget your password:** Using FTP or a file manager, edit the `mikanData/settings.json` file on the server and clear the value of `password_hash`. The initial password setup screen will appear on your next visit.

---

## Data Migration &amp; Import Tool {#data-migration}

A separate, standalone tool (`convert.php`) from the admin panel (`admin.php`). Access `mikanBox/convert.php` directly in your browser to use it. You can check the current state of the database and media, and bulk-import external data.

### Current State

#### SQLite Database State

Shows whether the database file exists, its size, and the number of registered pages, components, and users (editors).

#### Media File State

Shows the storage directory, the number of registered media files, and total media size.

### Content Package (ZIP) Import

Upload a ZIP package (a theme or a starter set of content) containing `posts/`, `components/`, and similar folders, and import it into the current SQLite database. Only the contents of `posts/` and `components/` are imported — the contents of `settings.json` (site setting values) are never read at all.

A package may have an outer folder containing `posts/` or `components/`. That outer folder is not part of the resulting IDs. Open “How to arrange files in the ZIP” on the import screen for an example. macOS metadata such as `__MACOSX` and `._` files is ignored.

Each page ID comes from its path under `posts/` without the `.json` extension: for example, `posts/coffee/menu.json` becomes `coffee/menu`. Component IDs come from their paths under `components/`. Titles inside the files do not determine IDs. Anything matching an existing ID is skipped; nothing is ever overwritten. If you want to bring in a page or component in a way that overwrites an existing one, either delete the existing item in the admin panel first, or rename the JSON file to your intended ID before including it in the ZIP.

ZIP extraction accepts JSON only (10 MB per file, 50 MB total, 10,000 entries). Once import finishes, the extracted folder is renamed to `{folder name}-{identifier}_imported` inside the protected `mikanData/private-import/` folder and left in place (it is not deleted).

#### Choose File

Select the ZIP file to upload.

#### [publish]{.material-symbols-outlined} Upload &amp; Import{.m-btn .m-btn-orange}

Imports the selected ZIP file.

### WordPress Migration (XML Import)

Upload an XML file (WXR format) exported from WordPress's "Tools > Export," and bulk-import posts and pages as mikanBox page data. Publication status (published/draft) is preserved, and the body HTML is automatically converted to simplified Markdown.

#### Choose File

Select the WordPress XML file to upload.

#### [publish]{.material-symbols-outlined} Parse &amp; Import XML{.m-btn .m-btn-orange}

Parses and imports the selected XML file.

### Execution Log

After running an import, the result (success, errors, etc.) is shown here as a log.

#### [arrow_back]{.material-symbols-outlined .mat-icon} Back to Admin Panel

Returns to the regular admin panel (`admin.php`).


### Cloud data import and export

Open `/admin?view=import` from Site settings. It follows the SQLite tool’s status cards, ZIP import, WordPress XML import and execution log. Cloud shows page/component/user counts and media count/total size, without PHP directory paths or a local SQLite file size.

ZIP import adds only JSON records under `posts/` and `components/`, skipping existing IDs. Settings, accounts and images are not imported. WordPress WXR maps published posts to dynamic pages, pending posts to pending pages, and other statuses to drafts; HTML is converted to basic Markdown. Upload images separately. ZIP/XML files are limited to 20 MB, ZIP expansion to 15 MB total, and imported content to 1 MB in UTF-8 with 300 total records. ZIP supports store and Deflate; encrypted or corrupt files are rejected. ZIP/XML parsing runs in the browser.

CSV conversion and Cloud’s complete JSON backup/restore controls follow the common import sections. Full JSON restore replaces current site data, unlike additive ZIP/XML import, and retains administrator-password and user-restoration requirements. Rebuild static pages after import. Site settings use shared CSS for two-column fields, account accordions and category add-button alignment.

---

Clear page and media keyword searches using the clear control; category filters remain selected. Cloud image renaming offers Cancel, Rename only and Update references when used by content. Image changes do not require the Cloud publication connection. Full backup for recovery (JSON) includes images, users and revision history beyond the separate data/media downloads. Current D1 image storage is capped at 400 MB; existing and restored images plus other temporary restore images must fit within 450 MB.

### Cloud: Migrate from D1 to R2 (development v0.5.0)

Start with D1 without enabling R2. Site → Media storage shows the current storage and usage and provides “Migrate from D1 to R2”. Storage for images and uploaded JS/CSS switches; content, settings, users and other records remain in D1.

R2 requires registering a payment method even when usage stays in the free allowance. Enable R2, create a bucket, and bind it to the Worker as `MEDIA`. Local Wrangler includes a test binding; production does not yet include R2. Add this configuration to `wrangler.production.json`, replace the bucket name, and deploy the code. Connecting a bucket alone does not switch storage.

```json
"r2_buckets": [{"binding": "MEDIA", "bucket_name": "your-bucket-name"}]
```

Enter your current administrator password to begin. Each image is copied and its checksum and R2 write are verified. D1 originals remain visible while image additions, modifications and deletions are paused. Resume or cancel from the same menu after closing the browser. After all images are verified, one database transaction removes D1 image bytes and switches pointers to R2. Incomplete, failed and cancelled migrations retain D1 originals. There is no reverse-switch menu after successful migration. Save a complete JSON backup first. Image URLs and content filenames stay unchanged.

D1 image storage is limited to 400 MB. D1 Free allows 500 MB per database; Paid allows 10 GB per database and requires a separate plan upgrade. R2 removes the 400 MB combined image limit, retaining 10 MB per image and 10,000 images. R2 Standard’s account-wide monthly free allowance is 10 GB-month, one million Class A operations and ten million Class B operations. These are billing allowances, not hard limits; excess usage is charged.

Site settings display D1 database size and tracked R2 storage, including temporary and retained images, with a notice near 80% of the free storage allowance. Other sites and untracked bucket objects are excluded. Check account totals, requests, billing and alerts in Cloudflare. Billing alerts do not stop usage. Removing D1 images does not necessarily shrink allocated database storage immediately.

R2 writes use new immutable objects followed by atomic D1 pointer changes. “Clean unused temporary images” removes up to 20 unreferenced images retained for at least 24 hours, excluding live migration and restore objects. Complete JSON backups include image bytes and can restore to either storage mode while preserving the destination’s storage choice. D1 restoration is limited to 400 MB images and 450 MB of existing plus staged images; both current and temporary R2 images count toward storage usage. D1 Time Travel and SQL export do not contain R2 image bytes; older pointers may reference already-cleaned objects. Use complete JSON backups with R2.

Images use the stable `/media/filename` Worker route in both modes. Image changes do not require the Cloud publication connection. Image requests consume Worker allowances; R2 serving also reads D1 metadata and R2 objects. Built page HTML is served directly from Static Assets. Updated page/component references after renaming or WebP conversion still require the usual rebuild and publication to update deployed HTML.

Run `node scripts/test-r2-local.mjs` from the Cloud directory to test the official local Worker, D1 and R2 bindings without registering a payment method. It creates disposable isolated state, runs 15 integration checks covering migration/cancellation, image operations and backup restoration, then removes the state. It does not use existing local data or remote services. Local integration testing does not verify live R2 permissions, network access or billing. Results are saved to `artifacts/r2-local-system-test.json`.

References: [D1 limits](https://developers.cloudflare.com/d1/platform/limits/), [R2 pricing](https://developers.cloudflare.com/r2/pricing/), [R2 setup](https://developers.cloudflare.com/r2/get-started/), [Usage-based billing](https://developers.cloudflare.com/billing/understand/usage-based-billing/).


## Design {#design-mgmt}

Manages three types of components: [Page]{.type-badge .badge-page}, which builds the design for a whole page; [Part]{.type-badge .badge-part}, which builds shared building blocks; and [AI Instructions]{.type-badge .badge-ai}, for writing instructions aimed at AI.

#### [add]{.material-symbols-outlined} Create New{.m-btn .m-btn-blue}

Creates a new component. Clicking opens the design-edit area.

### Component List

#### Edit

Clicking the [[edit]{.material-symbols-outlined}Edit]{.m-btn .m-btn-blue} button opens the edit screen for that component.


Use the selector to the right of “Ask AI” in the component list: All (default), Selected category, Page, Part, AI instructions, or Standard components. A standard component has an ID beginning with `_`; type filters use the component type setting. Omit `_` for ordinary new or duplicated components, but use it when standard classification is intended. These names are not rejected on save. “Selected category” uses the category at the top of the admin screen and matches component ID prefixes, just like media filenames: `apps_header` belongs to `apps`. With no category selected, it does not narrow the list.
#### Component ID

The component's identifier. Clicking it also opens the edit screen. IDs starting with "_" are system components provided out of the box — you're free to edit and reuse them.

`{{COMPONENT:_global_head}}` contains shared settings for inside the `<head>` tag, and already includes `{{HEAD_CSS}}`, which aggregates and outputs all CSS. `{{HEAD_CSS}}` is optional and specifies where CSS is inserted. If repeated, only the first occurrence outputs CSS and the remaining tags are removed. If omitted, CSS is inserted before `</head>`, or at the beginning of the HTML when there is no closing head tag. For content pasted straight from AI output, select "None (use the content HTML directly)" as the Design Component; for everything else, `_layout` is a good starting point. Of course, you're free to create as many of your own as you like — and those don't need to start with "_".

#### No wrapper and shared loading

Select “None (use the content HTML directly)” to display content without a layout wrapper. Enable Raw HTML for complete HTML documents. In MCP, use `wrapper_comp: "__none"`; omission still defaults to `_layout`.

Across editions, existing title and meta tags in the document are preserved. Only missing information is supplied inside head: UTF-8 charset, viewport `width=device-width, initial-scale=1.0`, and a title matching `{{FULL_TITLE}}`. Description and OGP image are supplied only when absent and page or site settings provide a value. Japanese and English titles are used as entered, without automatic translation. Robots and canonical tags are not added automatically. A missing head is created; HTML fragments receive a minimal html/head/body document. Existing duplicate metadata is not removed automatically.

In Site settings, under Shared loading, enter a Google tag ID (`G-…`) or a Tag Manager container ID (`GTM-…`) as the Tracking ID to insert the tracking tag into public pages automatically. The kind is detected from the prefix. `G-…` inserts gtag.js in head; `GTM-…` inserts the head script and also a noscript right after the opening body tag. Leave it empty to disable.

For server-side GTM, Consent Mode initialization or anything the standard tag does not cover, open “Enter code directly (advanced)” below it and paste the code into “Head code” and, if needed, “Code right after the opening body tag”. The code is inserted exactly as entered and `{{...}}` tags are not expanded. While code is entered it takes priority over the Tracking ID, and the ID field becomes read-only.

If you used the earlier “Tracking component” setting, opening the settings screen shows that part’s HTML in “Head code”. Saving moves it to the new setting and leaves the original part untouched. A part that contains component tags, JavaScript or CSS is not moved automatically and keeps working as before until you save.

“Do not load tracking tags on this page” appears below the page’s Design Component selector only when a Tracking ID, head code or body code is set. It suppresses the automatic head and body insertion, and, if the earlier tracking component is still in use, normal inclusion of that component. MCP supports `exclude_tracking: true`. Handwritten tracking code and tracking code in other components are not suppressed. Previews follow the same page setting. Rebuild static pages after changing settings.


#### Type

Shows the component's type. In the list, [AI Instructions]{.type-badge .badge-ai} components are sorted to the top as a group.

- [Page]{.type-badge .badge-page} — A component with "Use as a Page Component" checked. Selectable under "Design Component" in the Page Edit screen.
- [Part]{.type-badge .badge-part} — A building block embedded inside a page or another component.
- [AI Instructions]{.type-badge .badge-ai} — Instructions meant to be read by an AI agent, like your site's design rules (`DESIGN.md`, `BRAND.md`, etc.).

#### Tag Name

Shows the tag used to embed the component (e.g. `{{COMPONENT:header}}`). Clicking it copies it to the clipboard, ready to paste into a page's body content or another component's HTML.

---

## Design Edit {#design-edit}

A screen for defining a component's structure (HTML/CSS/JavaScript) and settings.

#### Component ID

The component's identifier. Required. Alphanumeric characters and underscores are allowed. Called from a template as `{{COMPONENT:ID}}`. When the type is set to "AI Instructions," dots (`.`) are also allowed, and a trailing `.md` is added automatically on save if not already present.

#### Memo

A private note visible only to admins. Excluded from public pages and APIs. Authenticated management MCP clients, including AI, can read it as working context. Reading a note does not grant permission to publish it.

#### Type

Choose one of three types via radio button.

- [Part]{.type-badge .badge-part} — A building block meant to be embedded inside a page or another component.
- [Page]{.type-badge .badge-page} — A component that lays out an entire page. Selecting this shows it as a [Page]{.type-badge .badge-page} in the Design list, and makes it selectable under "Design Component" on the Page Edit screen. The following extra tags become available:
  - `{{CONTENT}}` — Inserts the page's body content at this position.
  - `{{HEAD_CSS}}` — Aggregates and inserts page and component CSS. If omitted, CSS is inserted before `</head>` (at the beginning of the HTML if there is no closing head tag). If repeated, only the first occurrence is used and the remaining tags are removed.
- [AI Instructions]{.type-badge .badge-ai} — Hides fields not needed for AI instructions, like the CSS editor and the "Scope CSS" checkbox, and switches the body field into plain text input (usable as Markdown or HTML). Write anything you want an AI agent to read here — design rules and the like. You can even have AI generate this content itself.

#### HTML (when Type is "Part" or "Page")

Write the component's content as HTML. See the guide at the bottom of the edit screen for the custom tags available here. You can also nest other components inside it.

#### Content (Markdown / HTML) (when Type is "AI Instructions")

Freely write whatever you want to convey to an AI agent. Either Markdown or HTML is fine.

On an MCP connection, `get_ai_context` loads all AI-instruction components in one call. You can keep `DESIGN.md`, `BRAND.md`, `CONTENTS.md`, and other concerns as separate manageable components while still conveying them efficiently to AI.

#### CSS (Scoped styles for this component) (when Type is "Part" or "Page")

Write styles meant to apply only within this component. Custom tags can also be used inside this CSS.

#### Scope CSS (Prevent leaking to other parts) (when Type is "Part" or "Page")

A checkbox. Normally recommended to leave ON. Prevents the CSS you write here from affecting other parts of the site. Only uncheck this when building a global reset CSS meant to apply site-wide, or a part meant for the `<head>`.

To keep scoping enabled while making only a specific selector global, wrap the entire selector in `:global(...)`. Example: `:global(.hero_box) { display: none; }`. Components with scoping disabled are already global, so they do not need `:global(...)`.

#### JavaScript (when Type is "Part" or "Page")

Since 2.6.12, JS is output once per component ID per page. Versions 2.6.10–2.6.11 output it for each inclusion.

Write code without `<script>` tags in the collapsible field, whose heading uses the same bold weight as the CSS field. With a normal `{{COMPONENT:ID}}` inclusion, JS is output at the end of the page body once per component ID per page. Nested components are collected in parent-before-child order; repeated IDs keep their first collection position. Card-list and search-result templates share the same ID deduplication with normal inclusions. Any layout-wrapper JS not already collected follows, then page JS. Different component IDs and page JS are each output even if their code is identical. Code is not automatically scoped. AI Instructions hide the JS field and are neither served nor executed as JavaScript.

The JavaScript field uses the same “JavaScript ▼” heading as other collapsible sections. Click anywhere on its heading row to expand or collapse it.

Since 2.7.0, you can load only the JS by filename, and the editor shows an HTML snippet for this purpose.

The component JavaScript field shows the JavaScript-only embed example first, followed by the automatic path completion explanation and full JavaScript-only URL. Page and component JavaScript field explanations and display order are aligned across editions.

Saved design components show a JavaScript-only URL and a snippet to paste into HTML. `component-js/ID.js` returns only the JS field, regardless of whether the component contains HTML or CSS. Use the exact case of the saved ID. To load only the JS of `Test`, write this in page or component HTML, rather than in the JavaScript field:

```html
<script src="Test.js" defer></script>
```

Like image filenames, `ID.js` (or `./ID.js`) for a saved normal component is completed to its dedicated URL using the installation path and page depth. `component-js/Test.js` also works. Filenames without a matching component and external URLs are left unchanged. Static-site upload packages include JS files under `component-js/`; relative-URL mode adjusts references for the output page depth. Servers without clean-URL rewriting can also use `index.php?component_js=Test` (this query form requires PHP). Missing or invalid IDs and AI Instructions return 404; a normal component with an empty JS field returns empty JavaScript.

A normal `{{COMPONENT:Test}}` inclusion outputs its JS automatically, so it does not need the script tag above. Even when the same component appears multiple times on a page, its JavaScript is initially executed only once. Event handlers run each time the event occurs. Also, CSS scoping does not apply to JavaScript. JS-field code is used without custom-tag expansion. Combining a normal inclusion and an external script URL does not automatically adjust execution counts. For example, when a component has three buttons, the code is initially executed only once: use `document.querySelectorAll()` to find all three and attach a click handler to each. `document.querySelector()` finds only the first one. Design any required loading order in your code. The MCP tools `create_page`, `update_page`, `create_component`, and `update_component` also accept the `js` field.

#### [save]{.material-symbols-outlined} Save{.m-btn .m-btn-blue}

Saves the component.

#### [arrow_back]{.material-symbols-outlined} Back to List{.m-btn .m-btn-gray}

Returns to the Design screen. Note that unsaved changes will be lost.

#### [delete]{.material-symbols-outlined} Delete{.m-btn .m-btn-red}

Permanently deletes the component. This cannot be undone.

At the bottom of the edit screen, the list of available tags (tag guide) appears collapsed. Click to expand it.

---

## Media {#media-mgmt}

A screen for uploading and managing image, audio, and video files.


### Uploading JS and CSS libraries

You can upload browser-ready `.js` and `.css` files through Media. Code files must be nonempty UTF-8 text, at most 10 MB each; PHP server upload limits also apply. Use ASCII letters, numbers, `_`, `-`, and `.` in filenames. Other characters are replaced with `_`, and extensions are normalized to lowercase. Paths and dangerous double extensions are rejected.

JS/CSS files receive no category prefix and remain visible across categories. Image resizing and WebP conversion do not apply. The file-type filter offers images, audio, video, JS, and CSS. Code cards provide **Copy URL** and **Copy embed tag**, without executing the uploaded code in the admin screen. Renaming cannot change an extension. Existing files are never overwritten: PHP editions add a numeric suffix; Cloud asks for another filename. Prefer versioned filenames such as `library-1.2.3.min.js` for updates.

Paste embed tags into page content or a design component's HTML, rather than the JS/CSS code fields:

```html
<link rel="stylesheet" href="media/library-1.2.3.min.css">
<script src="media/library-1.2.3.min.js" defer></script>
```

These `media/` paths are completed for nested pages, and the files are included in static exports. Uploading does not automatically insert tags into pages. Follow the library's instructions for loading order and attributes such as `type="module"`. Uploading a complete folder hierarchy is not supported. If JS/CSS references other files by relative paths, arrange those files accordingly.

Uploads require a trusted administrator or MCP API key, just like code editing. Format validation does not guarantee safe code: check its source and contents. PHP editions save files under `media/` and configure JavaScript/CSS MIME types and `nosniff` on Apache. On other servers, configure `text/javascript` / `text/css` and `X-Content-Type-Options: nosniff`. Cloud uses the existing media storage setting (D1 or R2), with the same `/media/filename` URL in either mode. Backup, restore, and D1-to-R2 migration include these files. Page/component code fields remain in D1. MCP `upload_media` also accepts JS/CSS.

### Upload Area

#### Choose File

Click "Choose File" to select the file to upload. Dropping a file onto the Media section uploads it as soon as it's dropped. Other sections (except the import file fields) do not accept drops.

#### [upload]{.material-symbols-outlined} Upload{.m-btn .m-btn-blue}

Uploads the selected file.

#### Upload Options...

Click "Upload Options..." to expand it. The supported formats and the JS/CSS conditions (UTF-8, up to 10 MB; upload only files you trust) are shown here too. These choices apply only at upload time and are remembered by your browser, so the same settings are reused next time. They apply to drag-and-drop uploads as well.

- **Limit long edge to N px** — When an image's long edge exceeds the given value, it is scaled down on upload while keeping its aspect ratio. The default is 2000px. Photos straight from a phone or camera are often several thousand pixels wide, which badly hurts page speed; this setting prevents that.
- **Convert to WebP (jpg / png only)** — Re-encodes the image as WebP, typically 25-35% smaller. The extension becomes `.webp`, so the filename changes. jpg is converted lossily (quality 82) and png losslessly, so text and thin lines in screenshots and diagrams are not smeared.

Both options are destructive: **the original image data is not kept.** Do not use them for files you intend to distribute at full size.

gif and svg are never converted - gif because the animation would be lost, and svg because it is a vector format that never degrades when scaled.

If an image has too many pixels to fit in the server's memory limit, it is stored unchanged and a message says so; the upload itself still succeeds.

Media guidance shows supported formats, JS/CSS requirements, and how to use files on a page. The image conversion note and server maximum-size display are omitted. Markdown can omit the path; HTML uses `media/filename`.

#### Supported Formats &amp; Limits

Supports jpg, png, gif, webp, svg, mp3, m4a, and mp4. The maximum file size depends on your server's configuration.

Uploaded files are stored in the `media` directory. How to reference them in Markdown or HTML:

- Markdown: `![description](filename)`, or `{{IMAGE:filename}}` (also `{{AUDIO:filename}}`, `{{VIDEO:filename}}`)
- HTML: Use `media/filename` as the standard URL (for example, `<img src="media/filename">`). For images, the existing `images/filename` and bare filename forms remain supported.

If you navigate to the Media screen from the Pages screen while a category filter is active and upload a file there, that category name is automatically prepended to the filename (e.g. uploading `photo.jpg` while filtered to category "blog" produces `blog_photo.jpg`). However, if the filename already starts with some prefix (alphanumeric characters followed by `_`), it won't be prefixed twice.

A filename can include multiple category names separated by `_`. For example, naming a file `blog_news_001.jpg` makes it show up whether you filter by category "blog" or by category "news." Also, any filename starting with `g_` (e.g. `g_logo.png`) is always treated as a shared "global" image, shown regardless of which category filter is active.

### Filtering by Category

Navigating to the Media screen from the Pages screen while a category filter is active automatically narrows the list down to filenames carrying that category's prefix (e.g. `blog_photo.jpg`). Alongside a "Filtering by category '...'" message, the following buttons appear.

#### [filter_list]{.material-symbols-outlined} Apply category filter{.m-btn .m-btn-blue}

Enables filtering by the current category (this is the default state).

#### [visibility_off]{.material-symbols-outlined} Do not filter by category{.m-btn .m-btn-orange}

Temporarily lifts the filter and shows every media file.

### Media List

#### Preview

Images are shown as thumbnails. Video and audio files show an icon instead.

#### File name (click to copy)

Clicking the filename copies it to the clipboard, ready to paste into a page's body content.

#### Format &amp; size display

Shows the file's format (e.g. JPG) and, for images, its resolution (width × height).

#### [delete]{.material-symbols-outlined} Delete{.m-btn .m-btn-red}

Deletes the media file. This cannot be undone.

#### Resize...

Click "Resize..." to expand it. Enter either Width (W) or Height (H) and press [[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue} to resize it while keeping the aspect ratio (jpg, png, and webp only). A resize cannot be undone.

gif is not supported. The image library used here can only read the first frame of a gif, so resizing one would discard the animation. To change the size of a gif, rebuild it with a dedicated tool and upload the result.

If an image has too many pixels to fit in the server's memory limit, it is left unchanged and a message says so.

#### Rename▼

Click "Rename▼" to expand it. Edit the filename and press [[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue} to rename the media file. If that filename is already referenced somewhere in an existing page or component, a confirmation dialog appears with these choices:

- **Update Links** — Renames the file and also rewrites every reference to it inside pages/components to the new filename.
- **Rename File Only** — Renames only the file itself, leaving existing references untouched (note that those references will then point to a missing file).
- **Keep Original** — Cancels the rename.

In the PHP editions, a reference in the common site OGP also prompts for confirmation. “Update Links” updates matching filenames in page content, CSS, JavaScript and page OGP, component HTML, CSS and JavaScript, and the common site OGP. Similar filenames, notes and past revisions are left unchanged. In the SQLite edition, the operation stops if an affected page or component is being edited. Rebuild static pages to reflect the changes.

---


### Media operations

Filename search and pagination use the same layout across editions. Search applies within the current category filter.

Expand “Upload options▼” to configure uploads. After uploading, renaming, resizing, converting to WebP, or deleting a file, the media list retains its scroll position where possible. File actions appear in this order: “Resize▼”, “Rename▼”, “Convert to WebP”. Resize dimensions and filename inputs use the same height and save button size. Enter a width or height to preserve the aspect ratio.

“Convert to WebP” appears only for existing JPEG (jpg/jpeg) and PNG images, not WebP, GIF, SVG, audio or video. PHP requires server WebP encoding support. Conversion changes the extension to `.webp` and updates page content/CSS/JavaScript/OGP, component HTML/CSS/JavaScript, and the site OGP image. Memos and past revisions are unchanged. An existing WebP target is never overwritten; the source and references remain intact. Rebuild static pages afterward. Cloud converts the image in the browser and updates storage and delivery; keep the screen open until complete.

## Markdown {#markdown}

The page body supports standard Markdown syntax.

A guide to the available syntax is shown at the bottom of the Page Edit screen.

### Markdown and HTML

Markdown and HTML can be mixed. The body is treated as Markdown by default, but any line starting with one of the HTML tags below is treated as raw HTML code (not converted by Markdown).

`html` `head` `body` `div` `section` `article` `aside` `header` `footer` `main` `nav` `form` `p` `h1`–`h6` `ul` `ol` `li` `dl` `dt` `dd` `table` `thead` `tbody` `tr` `th` `td` `blockquote` `pre` `figure` `figcaption` `details` `summary` `dialog` `a` `span` `button` `select` `video` `audio` `script` `style` `link` `meta` `<!-- comment -->` and more

Both opening and closing tags (e.g. `</div>`, `</a>`) count. You don't need blank lines around them either way — it makes no difference to the output.

If a block is surrounded by blank lines with no HTML tags or Markdown symbols, it's treated as a "paragraph" and wrapped in `<p>…</p>`. A line break inside a paragraph becomes `<br>` (never right before/after an HTML tag). You can also mix HTML tags into a paragraph.

For layouts too complex for a Markdown+HTML mix to handle, turn on the "Raw HTML" checkbox on the Page Edit screen to fully disable Markdown processing and output the HTML exactly as written.

### Paragraphs

Surrounding a block with blank lines treats it as a paragraph — as long as it has no HTML tags or Markdown symbols — wrapping it in a tag like `<p>content</p>`. A line break within a paragraph inserts a `<br>` tag. You can also insert HTML tags within a paragraph.

### Headings

Using `## Heading` at the start of a line treats it as a heading, output as `<h2>Heading</h2>`. Since `#` (producing `<h1>`) is normally reserved for the page title, within the body content it's best to use `## Heading` / `### Subheading`. A space is required after the `#` symbol(s).

Markdown: `## Heading`
HTML: `<h2>Heading</h2>`

### Other Block Elements

Lists, numbered lists, quotes, horizontal rules, and inline code are each converted to their matching HTML when used at the start of a line.

Markdown: `- List item`
HTML: `<ul><li>List item</li></ul>`

Markdown: `* List item`
HTML: `<ul><li>List item</li></ul>`

Numbered list `1.`: `<ol><li>`
Markdown: `1. Numbered list item`
HTML: `<ol><li>Numbered list item</li></ol>`

List items can use either `-` or `*`. A space is required after the symbol for the above.

Markdown: `> Quote`
HTML: `<blockquote>Quote</blockquote>`

A space after the symbol is optional here.

Markdown: `---`
HTML: `<hr>`

### Code Blocks

Wrap code in lines that hold only three or more backticks or three or more tildes (`~`). The closing line uses the same character and at least as many of them as the opening line. Inside, nothing is converted: not Markdown, not HTML, not `{{tags}}`. It is shown exactly as written. In HTML it becomes `<pre><code>…</code></pre>`.

- A single word after the opening line (for example `php`, `json`, `html`) adds a class that starts with `language-`, as in `<code class="language-php">`. Use it in CSS or a highlighting library to style each language.
- To show a three-backtick code block inside code (for example on a page that explains Markdown), make the outer fence four or more backticks, or use tildes. A fence that is longer than the inner one is not closed by it.
- If you forget the closing line, everything from the opening line to the end is treated as code.

### Inline Elements Usable Mid-Sentence

Markdown: `**bold**`
HTML: `<strong>bold</strong>`

Markdown: `*italic*`
HTML: `<em>italic</em>`

### Links

Links can be written as `[link text](URL)`. This is output as `<a href="URL">link text</a>`.

Markdown: `[link text](URL)`
HTML: `<a href="URL">link text</a>`

### Using Media

To display an image, embed it with `![image description](filename)`. Whatever's inside `[ ]` becomes the image description, and it's output as `<img src="image URL" alt="image description">`. Any image you've uploaded via Media management can be used just by specifying its filename. The same is possible with the custom tag `{{IMAGE:filename}}`. Audio and video files managed in Media management can be used via `{{AUDIO:filename}}` and `{{VIDEO:filename}}`.

Markdown: `![image description](filename)`
HTML: `<img src="image URL" alt="image description">`

Custom tag: `{{IMAGE:filename}}`
HTML: `<img src="image URL">`

#### Automatically Added Attributes

When you reference an image that lives in the `media` directory, the generated `<img>` tag carries a few performance attributes automatically. Nothing changes in how you write the page.

- `width` / `height` - the image's intrinsic pixel size. The browser can reserve the right space before the image arrives, so the layout no longer shifts as images appear.
- `fetchpriority="high"` - added to the first large image on the page, so the image that defines the first impression is fetched with priority.
- `loading="lazy" decoding="async"` - added to large images after the first, deferring them until they are near the viewport.

Small images under 200px in both dimensions (logos, icons, dividers) get only `width` / `height` and no loading hint: deferring a few kilobytes saves nothing and only delays rendering.

Nothing is added for svg, for images at external URLs, or for filenames not present in the `media` directory. An `<img>` tag written directly into a wrapper or component is output exactly as written.

Custom tag: `{{AUDIO:filename}}`
HTML: `<audio src="audio URL" controls></audio>`

Custom tag: `{{VIDEO:filename}}`
HTML: `<video src="video URL" controls></video>`

### Displaying Tables

Tables are laid out with cells separated by `|`. A `|---|` row is required to mark it as a table. The first row becomes the header row `<th>`, and row 3 onward becomes data rows `<td>`. Adding colons, as in `|:---|:---:|---:|`, controls alignment.

`| Default | Left | Center | Right |`
`|---|:---|:---:|---:|`
`| A | B | C | D |`

| Default | Left | Center | Right |
|---|:---|:---:|---:|
| A | B | C | D |

### Specifying a Class / ID (mikanBox-specific Markdown Extension)

Adding `{.className}` or `{#idName}` at the end of a Markdown line lets you attach a CSS `class` or `id` to that line's block element.

Markdown: `## Heading{.highlight}`
HTML: `<h2 class="highlight">Heading</h2>`

Markdown: `paragraph text{.note #intro}`
HTML: `<p class="note" id="intro">paragraph text</p>`

For inline text, the `[text]{.className}` syntax outputs a `<span>` carrying the given class/ID. Multiple classes and IDs can also be combined.

Markdown: `[red text]{.red}`
HTML: `<span class="red">red text</span>`

Markdown: `[**bold** for emphasis]{.highlight .bold}`
HTML: `<span class="highlight bold"><strong>bold</strong> for emphasis</span>`

Markdown: `[anchor]{#my-id .note}`
HTML: `<span id="my-id" class="note">anchor</span>`

#### Nested Spans (e.g. Buttons with Icons) (mikanBox-specific Markdown Extension)

The `[text]{.className}` syntax can be nested. The inner `[...]{...}` is processed first, and the outer span wraps around it. Three or more levels of nesting are also possible.

Markdown: `[[save]{.material-symbols-outlined}Save]{.m-btn .m-btn-blue}`
HTML: `<span class="m-btn m-btn-blue"><span class="material-symbols-outlined">save</span>Save</span>`

This is handy for embedding icons from an icon font, such as Material Symbols, inside a button-style span or badge. Note that the content of a `[...]{...}` cannot contain a bare `[` without a matching `{...}`, since nested brackets are interpreted as spans.

These are mikanBox-specific Markdown extensions and won't work in other Markdown renderers (like GitHub's).

---

## Components (Custom Tags) {#components}

### Using Tags

Inside a page's body/CSS, or a component's body/CSS, you can use built-in tags such as `{{TITLE}}`. See the guide at the bottom of the Page Edit and Design Edit screens for the full list of available tags.

### Using Components

Any component you've created under Design management can be embedded into a page or another component as `{{COMPONENT:ID}}`.

### Images, Audio, and Video

Images, audio, and video can be embedded as `{{IMAGE:filename}}`, `{{AUDIO:filename}}`, and `{{VIDEO:filename}}`.

`{{IMAGE:filename}}` → `<img src="media/filename">`
`{{AUDIO:filename}}` → `<audio src="media/filename" controls></audio>`
`{{VIDEO:filename}}` → `<video src="media/filename" controls></video>`

### Navigation {#navigation}

A list of links: `{{NAV_LINKS:category}}` is output as a `<li>` list.

To output image-bearing cards instead, use `{{NAV_CARDS:category:componentID}}`. The card's design can be defined via a component. If you omit the component ID, the standard design (the `_nav_card` component) is used.

The order of the list or cards follows each page's "Order" setting. Pages with an order below 0 and [data-format](#data-formats) pages are excluded.

For both, setting the category to "all" outputs a list of every page, and leaving it empty outputs a list of pages with no category assigned. Since a page can have multiple categories, setting categories thoughtfully lets you build many different kinds of navigation.

#### List options

After the positional arguments (category, card component ID), add `name=value` options joined with `:`.

```
{{NAV_LINKS:news:limit=5}}
{{NAV_CARDS:news:_nav_card:limit=5:sort=new}}
{{NAV_CARDS:news:sort=old}}
```

|Option|Meaning|
|---|---|
|`sort=new`|Newest update date first (same date: display order)|
|`sort=old`|Oldest update date first (same date: display order)|
|`limit=N`|Up to N items from the top (per category with `@same`)|
|`sep=text`|Inserted between items (mainly to build JSON arrays in data formats). The value runs to the next `:` or `}}` as written; only `\n` (newline) and `\t` (tab) are interpreted|

The update date is set when a page is created, updated when the page goes from non-public to public, and kept when a published page is edited. It works as the publication date, which is what `sort=new` uses. There is no offset and no automatic pager: make a page listing everything and link to it, or build one from [catalog data and JavaScript](#catalog-pager).

#### Related pages: `@same`

`{{NAV_LINKS:@same}}` and `{{NAV_CARDS:@same:componentID}}` list the pages that share at least one category (other than those starting with "_") with the page being shown, excluding the page itself. If that page has no such category, nothing is output; with one, a flat list; with two or more, the list is grouped by category. A page in both categories appears in both groups, which shows why it is related. The order is the display order, or the `sort` option when given.

Grouped HTML is a nested `<ul>` for `NAV_LINKS` (`li.nav-group` holding `span.nav-group-label` and a `ul` of links) and one `div.nav-group` per category for `NAV_CARDS` (`div.nav-group-label` and the cards). In Markdown versions it is a nested list.

#### Showing categories: `{{CATEGORY}}`

`{{CATEGORY}}` outputs the page's categories in the order written, except those starting with "_". In HTML each is wrapped in `<span class="category">coffee</span>`; in Markdown versions and data formats they are comma-separated (`coffee, tokyo`). `{{CATEGORY:all}}` includes the "_" categories. Inside a card component it outputs the categories of that card's page.

### Embedding Another Page

The content of another page can be embedded via `{{POST_MD:ID}}`. This is handy when you want just one small part (like an announcements section) of a complex AI-built page to be easily editable — build that part as a simple Markdown page and embed it. The embedded page can be set to "Draft" so it stays hidden when viewed on its own.

### Embedding a Markdown Document from Another Site

A Markdown document hosted on another site, like GitHub, can be embedded via `{{EXT_MD:url}}`. Not only can you edit it directly on GitHub, but you can also adopt an "AI-friendly" workflow where having an AI agent rewrite the file on GitHub is all it takes to update the site.

**PHP setting:** Enable “Allow external Markdown” in Site settings. New sites default to OFF. Existing sites without the setting retain the feature if saved pages or components contain `EXT_MD`. When OFF, rendering, previews and builds make no external requests. When ON, the PHP cURL extension is required. Only public HTTP/HTTPS destinations are allowed; private addresses and redirects are rejected. Use the document's direct URL. Each response is limited to 1 MiB and the transfer to five seconds. Unavailable documents produce no visible content. External HTML is displayed as text, and mikanBox template tags are not executed. DATA/DATAROW values are also text; do not use untrusted values as JavaScript, CSS or URL-attribute code. Rebuild existing static output after disabling the setting.

**Not available in the Cloud edition.** `EXT_MD` fetches a document from an external server. The Cloud edition does not fetch it, so nothing is shown where the tag is.

### Page Components

A component that lays out an entire page should include `{{CONTENT}}` to output the body content. Optionally place `{{HEAD_CSS}}` to specify the CSS insertion point. If omitted, CSS is inserted before `</head>` (at the beginning of the HTML if there is no closing head tag). If repeated, only the first occurrence outputs CSS and the remaining tags are removed. See the `_layout` bundled component for the basic structure.

---

## Available Tags {#tags}

|Tag|Description|
|---|---|
|`{{ TITLE }}`|Page title|
|`{{ FULL_TITLE }}`|Page Title - Site Name|
|`{{ UPDATE_DATE }}`|Page's updated date (YYYY-MM-DD)|
|`{{ UPDATE_DATE:JP }}`|Page's updated date (Japanese format)|
|`{{ UPDATE_DATE:SLASH }}`|Page's updated date (YYYY/MM/DD)|
|`{{ IS_NEW:N }}`|"new" if updated within N days, else empty|
|`{{ DESCRIPTION }}`|Page description (falls back to the site's common description if empty)|
|`{{ KEYWORDS }}`|Page's keywords|
|`{{ OGP_IMAGE }}`|Page thumbnail / OGP image URL|
|`{{ PAGE_URL }}`|Page URL|
|`{{ PAGE_URL:ID }}`|URL of the given page, with its output format's extension (data pages add `?v=`). [Details](#page-url-id)|
|`{{ CATEGORY }}`|Page's categories (excluding those starting with "_")|
|`{{ CATEGORY:all }}`|All of the page's categories|
|`{{ SITE_URL }}`|Site URL. Also usable in Markdown link destinations (e.g. `[Details]({{SITE_URL}}/about)`)|
|`{{ SITE_NAME }}`|Site title|
|`{{ SITE_DESCRIPTION }}`|Site's common description|
|`{{ SITE_OGP_IMAGE }}`|Site's common OGP image URL|
|`{{ COMPONENT:ID }}`|Embeds a component|
|`{{ IMAGE:filename }}`|Displays a static image|
|`{{ AUDIO:filename }}`|Inserts an audio module|
|`{{ VIDEO:filename }}`|Displays a video|
|`{{ AI_QUESTION }}`|Displays the public mikanBox AI question box|
|`{{ AI_QUESTION:Help section ID }}`|Displays the AI question box with an initial Help section context|
|`{{ NAV_LINKS:category }}`|Shows pages in the given category as links|
|`{{ NAV_LINKS:all }}`|Shows every page as links|
|`{{ NAV_LINKS:(empty) }}`|Shows pages with no category as links|
|`{{ NAV_CARDS:category:componentID (optional) }}`|Shows pages in the given category as cards|
|`{{ NAV_CARDS:all:componentID (optional) }}`|Shows every page as cards|
|`{{ NAV_CARDS:(empty):componentID (optional) }}`|Shows pages with no category as cards|
|`{{ NAV_LINKS:category:sort=new:limit=N }}`|A list with options: `sort=new`, `sort=old`, `limit=N`, `sep=text` (same for NAV_CARDS). [Details](#navigation)|
|`{{ NAV_LINKS:@same }}`|Pages sharing a non-"_" category with the page being shown (same for NAV_CARDS)|
|`{{ POST_MD:ID }}`|Embeds the given page as Markdown|
|`{{ EXT_MD:url }}`|Embeds Markdown from the given URL (not available in Cloud)|
|`{{DATA:key}}value{{/DATA}}`|Defines data (visible). Key (case-insensitive) may only use alphanumeric characters and underscores. Value can be in any language.|
|`{{DATA:key:GHOST}}value{{/DATA}}`|Defines data (hidden). Key may only use alphanumeric characters and "_".|
|`{{DATAROW:rowID}} {{DATA:key}}value{{/DATA}} {{/DATAROW}}`|Defines table-format data. key and rowID may only use alphanumeric characters and "_".|
|`{{ POST_MD::key }}`|Displays data from within the current page|
|`{{ POST_MD::pageID:key }}`|Displays data from the given page ID|
|`{{ EXT_MD:url:key }}`|Displays data from an external page (not available in Cloud)|
|`{{ POST_MD::#rowID:key }}`|Displays table-format data from within the current page|
|`{{ POST_MD:pageID#rowID:key }}`|Displays table-format data from the given page ID|
|`{{ EXT_MD:url#rowID:key }}`|Displays table-format data from an external page (not available in Cloud)|
|`{{ CONTENT }}`|**Page wrapper only**: inserts the body content|
|`{{ HEAD_CSS }}`|Optional CSS insertion point: aggregates CSS and outputs it at the first occurrence, removing the remaining tags. If omitted, inserts before `</head>` (at the beginning of the HTML if there is no closing head tag). By default, this is already placed inside the `_global_head` component.|

※ If you want to show the current year (e.g. for a copyright notice), we recommend doing it with JavaScript instead (e.g. `<script>document.write(new Date().getFullYear())</script>`). This way the year is always current without going through any server-side processing.

---

## Bundled Components {#init-comps}

The components bundled with a fresh install. You can view and edit these in the Design management screen. The flat Edition, which has no keyword search, does not bundle `_search_box`, `_search_result_item`, or the search results page.

|ID|Type|Role / Description|
|---|---|---|
|`_layout`| [Page]{.type-badge .badge-page}|The standard shared layout. Combines `{{COMPONENT:_global_head}}`, `{{COMPONENT:_header}}`, `{{CONTENT}}`, `{{COMPONENT:_footer}}`, and `{{HEAD_CSS}}` into a basic structure. Regular Markdown pages should select this.|
|`_global_head`|[Part]{.type-badge .badge-part}|A collection of shared tags to insert inside the HTML `<head>` section — things like Google Analytics or web font loading that are common across every page. By default, `{{HEAD_CSS}}` is already placed here to specify the CSS insertion point. CSS scoping is disabled (global).|
|`_header`|[Part]{.type-badge .badge-part}|The site's shared header. A standard header component including the site name and navigation links. The navigation uses `{{NAV_LINKS:_nav}}` (pages in the structural category `_nav`).|
|`_footer`|[Part]{.type-badge .badge-part}|The site's shared footer. A standard footer component including copyright and footer navigation.|
|`_nav_card`|[Part]{.type-badge .badge-part}|The standard card design used for card-style navigation when a component ID is omitted. Outputs a card showing the thumbnail image, title, description, and date.|
|`_md_footer`|[Part]{.type-badge .badge-part}|A sample footer component for [Markdown versions](#markdown-version). `{{NAV_LINKS:@same:limit=5}}` adds a list of related pages. Used only when chosen in "Markdown version footer components" in the page editor (not a default).|
|`json_item`|[Part]{.type-badge .badge-part}|A sample entry for a JSON catalog, used as `{{NAV_CARDS:category:json_item:sep=,}}` ([Catalogs &amp; Page Lists](#catalog-pager)).|
|`_ai_question`|[Part]{.type-badge .badge-part}|The public AI question box. Place it with `{{COMPONENT:_ai_question}}` to pass the question, current page, and official public manual reference to an external AI.|
|`_search_box`|[Part]{.type-badge .badge-part}|The design for a search box. Its form includes `<input type="hidden" name="page" value="search">`, so submitting it takes you to the "search" page (which displays results using `_search_result_item`).|
|`_search_result_item`|[Part]{.type-badge .badge-part}|The design for a single search result entry. Placed on the search results page as `{{SEARCH_RESULTS:_search_result_item}}`.|
|`DESIGN_sample.md`|[AI Instructions]{.type-badge .badge-ai}|A sample instruction document for having AI generate or edit your design. Rename its ID to `DESIGN.md` to activate it. You can even have AI write the content itself. Similarly useful files include `BRAND.md` (brand information) and `CONTENTS.md` (content policy).|
|`AGENTS_sample.md`|[AI Instructions]{.type-badge .badge-ai}|A sample instruction document that hands your mikanBox page production rules to an AI. Rename its ID to `AGENTS.md` to activate it. It covers the separate content, CSS and JavaScript fields, writing the content in Markdown, turning shared parts into components, handling media, and publication status and builds.|

---

## Embedding, Loading &amp; API {#data-embedding}

### Data Formats

Three kinds of information can be handled:

- Markdown: Markdown data, either inside the site or from an external source (e.g. GitHub)
- Data: Individual key-value data, similar to WordPress custom fields
- Database: Table-format data, via `{{DATAROW}}`

### Loading Markdown

#### You can load another page as Markdown

Not just components — another page itself can also be loaded as Markdown. Rather than editing a complex page directly, you can update a simple page meant just for updates, and have it reflected automatically.

#### You can load a page from another site as Markdown

By loading a page hosted on another site, like GitHub, as Markdown, you can update your site's content without touching the site itself. External HTML is displayed as text, and mikanBox template tags are not executed.

### Outputting a GitHub Page as Markdown

A Markdown file's page on GitHub can be loaded by changing its URL to point at the raw data.

Before: [https://github.com/yoshihik0/testData/blob/main/md-test.md](https://github.com/yoshihik0/testData/blob/main/md-test.md)

After: [https://raw.githubusercontent.com/yoshihik0/testData/main/md-test.md](https://raw.githubusercontent.com/yoshihik0/testData/main/md-test.md)

#### Conversion rule
github.com → raw.githubusercontent.com
/blob/ → / (removed)

To load it:
`{{EXT_MD:https://raw.githubusercontent.com/yoshihik0/testData/main/md-test.md}}`

#### For sources other than GitHub:

Dropbox: change to `?dl=1`
Google Docs: make it public, then use `/export?format=txt`

### Embedding &amp; Using Data on a Page

Just write `{{DATA:key}}data{{/DATA}}` to embed information into a page, similar to a WordPress custom field, and use it within that page or from other pages. The key may use alphanumeric characters and "_". Adding `:GHOST`, as in `{{DATA:key:GHOST}}data{{/DATA}}`, keeps it hidden on the page itself.

Using it from within the page: `{{POST_MD::key}}`
Using it from another page: `{{POST_MD:pageID:key}}`

### Embedding &amp; Using Data on Another Site

The `{{DATA:key}}data{{/DATA}}` format can also be loaded from a page on another site, such as GitHub. Since you can update the content without touching the site directly, pairing this with AI-friendly GitHub makes automated operation easier too.

Using data from another site: `{{EXT_MD:url:key}}`

### DB Less DB: Embedding a Database Into a Page

Just write `{{DATAROW:rowID}}{{DATA:key}}data{{/DATA}}{{/DATAROW}}` to embed table-format data into a page, and use it within that page or from other pages. The page itself becomes a database, without ever using an actual database.

Displaying table-format data from within the page: `{{POST_MD::#rowID:key}}`
Displaying table-format data from a page ID: `{{POST_MD:pageID#rowID:key}}`

### You Can Also Load a Database From Another Site

The `{{DATAROW:rowID}}{{DATA:key}}data{{/DATA}}{{/DATAROW}}` format can also be loaded from a page on another site, such as GitHub.

External page, table-format: `{{EXT_MD:url#rowID:key}}`

### Converting CSV Data

Use “CSV Import” on the Site screen in the PHP editions or Data migration and import in the Cloud edition to convert CSV data into `{{DATAROW}}` format.

Select the CSV file exported from Excel, then click
#### [content_copy]{.material-symbols-outlined} Convert &amp; Copy{.m-btn .m-btn-gray}
to copy the converted data. Paste it into a page's body content to use it.

Reads the first row as field names and converts every other row into a `{{DATAROW:number}}` block, copied to the clipboard. After copying, paste it into the body content of a page and save.

### It Can Even Act as a Headless CMS

Publishing a page that embeds `{{DATAROW}}` with the output format "[mikanBox DB](#mikanbox-db)" makes it readable externally as an API.

`https://yoursite.com/api/pageID.json` (when served dynamically, `/api/pageID` works too)

Example: [https://yoshihiko.com/mikanbox/demo/api/sample-DB](https://yoshihiko.com/mikanbox/demo/api/sample-DB)

### Build Cards for All Kinds of Information

This data can also be used inside card-style displays (record listings), making it possible to build things like product listings.

|Tag|Content|
|:---|:---|
|`{{TITLE}}`|Page title|
|`{{UPDATE_DATE}}`|Updated date|
|`{{UPDATE_DATE:JP}}`|Updated date, Japanese format|
|`{{UPDATE_DATE:SLASH}}`|Updated date, slash-separated|
|`{{IS_NEW:30}}`|"new" within N days, else empty|
|`{{DESCRIPTION}}`|Page description|
|`{{OGP_IMAGE}}`|Page's OGP image URL|
|`{{PAGE_URL}}`|Page's full URL|
|`{{POST_MD::name}}`|Displays data|
|`{{POST_MD::date}}`|Displays data|
|`{{POST_MD::#test:book}}`|Displays row data|
|`{{IS_ACTIVE}}`|"active" if the current page, else empty|

## Output Formats (Markdown Versions and Data) {#output-formats}

Each page has one "Output format". The default is HTML, so existing pages do not change. mikanBox itself delivers plain data (Markdown versions, JSON and so on); how it is shown is left to JavaScript or an external renderer.

|Output format|Output|Typical use|
|---|---|---|
|HTML|HTML only (as before)|Ordinary pages|
|HTML + Markdown|HTML and a Markdown version|Pages meant for reading|
|Markdown|Markdown version only|Documents for Markdown readers and AI; Markdown-only sites|
|JSON, XML, CSV, Text, JavaScript, CSS|The body as it is|Catalogs, per-category RSS, data exports, `llms.txt`, the site's JS and CSS|
|mikanBox DB|The `{{DATA}}` and `{{DATAROW}}` data in the body, as JSON (`/api/ID.json`)|An API for data embedded in a page|

### URL Rules {#output-urls}

For a page at `/ID`, everything with an extension lives at `/ID.extension`.

|Kind|Directory style|File-name style|
|---|---|---|
|HTML|`/ID/`|`/ID.html`|
|Markdown version|`/ID.md`|`/ID.md`|
|Data formats|`/ID.json` etc.|`/ID.json` etc.|

- The URL style setting ([Output structure](#site-ssg)) affects HTML only. The top page's Markdown version is `/index.md`; for a nested ID such as `coffee/gallery` it is `/coffee/gallery.md`.
- Page IDs cannot contain a dot (`.`). The extension comes from the output format. An existing page whose ID contains a dot keeps working and stays editable as long as its ID is not changed.
- When served dynamically, a Markdown-only page also answers at `/ID` with its Markdown version, and `/ID.html` returns 404. A data page answers only at `/ID.extension`.
- A static build writes `ID.md` beside the HTML, and `ID.extension` for data pages. A Markdown-only page has no file at `/ID/`, so it is readable only at `/ID.md` (links inside the site always point at `/ID.md` explicitly). No `.htaccess` is written, so an `.htaccess` you placed on the server is never overwritten.
- `rss`, `sitemap` and `podcast` cannot use the XML format, because `/rss.xml` and the others are system files. The `404` page can only be HTML.
- **Garbled text and reading from other sites (static files)** — Markdown versions and data pages served dynamically by mikanBox carry `charset=utf-8` and `Access-Control-Allow-Origin: *` (so Markdown viewers and other sites can read them from the browser). Static files are sent by the web server as they are, so the server configuration handles them:
  - **Apache** — The site-root `.htaccess` written at first setup contains, between marker comments, `AddCharset utf-8 .md .txt .csv .js .css .json`, `AddType text/markdown .md`, and `Access-Control-Allow-Origin: *` for `.md`, `.txt`, `.csv`, `.json` and `.xml` (each part wrapped in `IfModule`, so a missing module causes no error). A system update appends this block when it is missing and brings an older mikanBox block up to date, changing nothing else (if the file cannot be written, the message after the update says so). Uploaded `.js` and `.css` in Media get a charset too.
  - **nginx** — `.htaccess` has no effect. Add to the server configuration, for example, `charset utf-8; charset_types text/markdown text/plain text/csv text/css application/javascript application/json;`, a `types` entry that maps `.md` to `text/markdown`, and `location ~ \.(md|txt|csv|json|xml)$ { add_header Access-Control-Allow-Origin *; }` (ask your hosting provider if you cannot change it).
  - Static `.md`, `.txt` and `.csv` files start with a UTF-8 BOM (an encoding marker), so browsers show them correctly even when the server sends no charset. Browser `fetch` removes the BOM. Some tools that do not understand a BOM will see an invisible character before the front matter. `.json`, `.xml`, `.js` and `.css` get no BOM (`.json` is UTF-8 by definition, and `.xml` declares its encoding inside the file).
  - When you put a static build on another server, configure that server the same way.
- Files built earlier for a page that is no longer Public (Static) (changed to dynamic or draft) are removed when that page is saved or its status changes (MCP included), or by a full static build, so a static file never hides a dynamic page or keeps a draft public.

### Markdown Versions {#markdown-version}

Pages whose output format is "HTML + Markdown" or "Markdown" publish a Markdown version at `/ID.md` (`Content-Type: text/markdown; charset=utf-8`). Raw Markdown runs no JavaScript, and every link target and image source is visible to the reader.

```
---
title: "Page title"
date: 2026-11-08
description: "Page summary"
url: "https://example.com/page/"
categories: "coffee, tokyo"
---

Body…

(footer components)
```

- The front matter holds `title`, `date` (the update date), `description` (only when the page has its own summary), `url` (the HTML version's URL; omitted for Markdown-only pages) and `categories` (excluding those that start with "_"; omitted when there are none).
- The wrapper (`_layout`, header, footer) is not used. The body is the saved Markdown as it is, not converted to HTML. HTML inside the body is output as text.
- **A published URL is required**, because Markdown versions use absolute URLs throughout. When the upload folder uses relative links and the published root URL is empty, formats with Markdown cannot be chosen.
- The HTML version of an HTML + Markdown page carries `<link rel="alternate" type="text/markdown" href="…/ID.md">`. Like RSS autodiscovery, tools and AI can find the Markdown version from the HTML page.

#### How Tags Are Handled in Markdown Versions

|Handling|Tags|
|---|---|
|Expanded|`NAV_LINKS`, `NAV_CARDS` (a `- [Title](URL)` list; cards become the same list; `@same` with two or more categories gives a nested list), `POST_MD` (all forms), `EXT_MD` (all forms), `{{DATA:key}}value{{/DATA}}` (the value only), `IMAGE` (`![](absolute URL)`), `AUDIO` and `VIDEO` (a `[filename](absolute URL)` link), `CATEGORY`, `PAGE_URL:ID`|
|Replaced with text|`TITLE`, `FULL_TITLE`, `UPDATE_DATE` (all forms), `IS_NEW`, `DESCRIPTION`, `KEYWORDS`, `OGP_IMAGE`, `PAGE_URL`, `SITE_URL`, `SITE_NAME`, `SITE_DESCRIPTION`, `SITE_OGP_IMAGE` (no HTML escaping)|
|Removed|`COMPONENT` (except the chosen footer components), `AI_QUESTION`, `SEARCH_RESULTS`, `CONTENT`, `HEAD_CSS`, `IS_ACTIVE`, `DATAROW` blocks, GHOST data|

Code blocks, inline code and HTML comments are output exactly as written.

#### Links and Images

Every URL becomes absolute. `![](ogp.jpg)` and `media/…` become absolute media URLs. An internal link such as `[Details](about)` points at `/ID.md` when the target page publishes a Markdown version, and at its HTML URL otherwise. External links, `#`, `mailto:` and `tel:` stay as they are. Matching a link to a page is shared with the HTML version. After you change a target's output format, rebuild the static site and the Markdown versions that link to it get the new URL too.

#### Footer Components

Each page can add zero or more components to the end of its Markdown version, in order (none by default). Write them in Markdown; their tags follow the table above. A sample, `_md_footer`, is included.

```
## Related pages
{{NAV_LINKS:@same:limit=5}}
```

### Data Formats {#data-formats}

Pages whose output format is JSON, XML, CSV, Text, JavaScript or CSS publish the body as it is at `/ID.extension`: no wrapper, no HTML completion, no Markdown conversion.

|Format|Extension|Content-Type|
|---|---|---|
|JSON|`.json`|`application/json; charset=utf-8`|
|XML|`.xml`|`application/xml; charset=utf-8`|
|CSV|`.csv`|`text/csv; charset=utf-8`|
|Text|`.txt`|`text/plain; charset=utf-8`|
|JavaScript|`.js`|`text/javascript; charset=utf-8`|
|CSS|`.css`|`text/css; charset=utf-8`|

Every format is sent with `X-Content-Type-Options: nosniff`.

- **Tag expansion** can be switched per page. By default JSON, XML, CSV and Text expand tags, and JavaScript and CSS do not (so code that uses `{{ }}` is not broken). When tags are expanded, values are escaped for the format: as a string in JSON; `&` `<` `>` `"` `'` as entities in XML; values containing a separator, quote or newline wrapped in `"…"` in CSV; as they are in Text. Values inside a `NAV_CARDS` item component follow the same rule. `COMPONENT` inserts just the component's content, without CSS or a scope.
- Write JavaScript and CSS in the body. The page CSS and JS fields, design component, OGP image, description and keywords are not used, so the editor hides them.
- Data pages are parts of a site rather than content, so they are never included in `NAV_LINKS`, `NAV_CARDS` (including `all`), `@same`, `sitemap.xml`, `rss.xml` or search results. Publication works as for any page: drafts are not served, and a static build writes only "Public (Static)" pages.
- The preview shows the raw text that is actually published. [Visual Editing](#visual-edit) works only on pages that publish HTML.
- To read the table data in a body (`{{DATAROW}}`) as an API, use the output format "[mikanBox DB](#mikanbox-db)".

### mikanBox DB {#mikanbox-db}

With the output format "mikanBox DB", the `{{DATA}}` and `{{DATAROW}}` data in the body is turned into JSON of a fixed shape and returned at `/api/ID.json`. Nothing is shown as a page (no HTML, no Markdown version). This is the former "DB" status (up to 2.17), moved to the output format.

The JSON looks like this. `fields` holds the `{{DATA:key}}` values outside `{{DATAROW}}` (GHOST included), and `rows` holds each `{{DATAROW}}`. **Headings, prose and anything else outside the data are not included.**

```json
{
    "id": "sample_data",
    "title": "Data sample",
    "fields": { "BOOK_TITLE": "Macbeth", "AUTHOR": "Shakespeare" },
    "rows": [
        { "id": 1, "GOODS": "Apple", "PRICE": "300" },
        { "id": 2, "GOODS": "Banana", "PRICE": "200" }
    ]
}
```

- The status works as for any page: Public (Dynamic) and Public (Static) serve the API, and Draft stops it.
- A static build writes the file `api/ID.json`, and removes it when the page becomes a draft. When served dynamically, the same JSON is also returned at the older `/api/ID` (a static build has only `/api/ID.json`).
- The URL shown under the output format is `/api/ID.json`. `{{PAGE_URL:ID}}` pointing at such a page gives `/api/ID.json` with `?v=`, like other data formats. The editor preview shows the JSON the API returns.
- These pages are not included in lists, sitemap, RSS or search. Other pages can still read their values with `{{POST_MD:ID:key}}` and `{{POST_MD:ID#rowID:key}}`.
- Pages saved with the "DB" status up to 2.17 are read as "output format mikanBox DB + Public (Dynamic)" and saved that way the next time they are saved. Their `/api/ID` URL keeps working. `status: db` over MCP is treated the same way.

|Kind|What is served|URL|
|---|---|---|
|mikanBox DB|The `{{DATA}}` and `{{DATAROW}}` data in the body, converted to a fixed JSON shape|`/api/ID.json`|
|Data formats (JSON etc.)|The body itself, in the shape you write (tags can be expanded)|`/ID.extension`|

Choose "mikanBox DB" to serve data embedded in a page as an API; choose a data format such as JSON to write the file's shape yourself.

### Embedding a Page's URL: `{{PAGE_URL:ID}}` {#page-url-id}

```html
<link rel="stylesheet" href="{{PAGE_URL:site-style}}">
<script src="{{PAGE_URL:site}}"></script>
→ <script src="https://example.com/site.js?v=3f9a2c1b"></script>
```

Outputs the URL of the given page, with its output format's extension. Data pages get `?v=` with a hash of their content, so browsers pick up the new file whenever the content changes. A static build hashes the built output (so a catalog that changes because other pages changed gets a new value too); dynamic pages hash the body. HTML and Markdown pages get no `?v=`. `{{PAGE_URL}}` without an argument is, as before, the URL of the page being shown.

Keep external libraries (files you do not edit yourself) in [Media](#media-mgmt), and write your own site code in JavaScript and CSS pages. The component JS field (`/component-js/ID.js`) works as before.

---

## Building Page Lists from Catalog Data and JavaScript {#catalog-pager}

mikanBox has no built-in pager or catalog file. Combine a [data-format](#data-formats) page with a JavaScript page instead. Static builds write them as files, so this works on servers without PHP too. Samples are included (`sample_list`, `json_item`, `sample_pager`, `sample_list_page`).

#### 1. The catalog page

Create a page whose output format is JSON (for example ID `news-list` → `/news-list.json`).

```
[{{NAV_CARDS:news:json_item:sort=new:sep=,}}]
```

The item component `json_item` describes one entry:

```
{"title":"{{TITLE}}","url":"{{PAGE_URL}}","date":"{{UPDATE_DATE}}","categories":"{{CATEGORY:all}}"}
```

Values are escaped for JSON, and `sep=,` puts a comma between items, so the result is a valid JSON array.

#### 2. The JavaScript page

A page whose output format is JavaScript (for example ID `pager` → `/pager.js`) reads the catalog and shows 20 entries at a time with page navigation. The included `sample_pager` works as it is.

#### 3. The page that shows the list

```html
<div class="mikan-pager" data-src="{{PAGE_URL:news-list}}" data-per-page="20"></div>
<script src="{{PAGE_URL:pager}}"></script>
```

- You can make a catalog of every page (`all`), or, for a large site, lighter catalogs per category or with `limit`.
- An HTML catalog (`<li data-date="…">` and so on) can also be read from JavaScript with `DOMParser`.
- `rss.xml` holds only the 20 newest pages of the whole site, with no category choice, so it is not suited as the source of a list. Per-category RSS can be made with an XML page.
- For the list options (`sort`, `limit`, `sep`), see [Navigation](#navigation).

---

## Podcast Distribution {#podcast}

Create a page with an audio file attached, set its category to "podcast," and an RSS feed containing only "podcast"-category pages is output at the URL below, letting you distribute a podcast.

`https://yoursite.com/podcast.xml`

Mainly embed the following information. `AUDIO_FILE` is required.

`{{DATA:AUDIO_FILE:GHOST}}episode01.mp3{{/DATA}}`
`{{DATA:DURATION:GHOST}}25:30{{/DATA}}`
`{{DATA:FILE_SIZE:GHOST}}24500000{{/DATA}}`

### Data Available on a Podcast Page

`{{DATA:AUDIO_FILE:GHOST}}episode01.mp3{{/DATA}}` ← required
`{{DATA:DURATION:GHOST}}25:30{{/DATA}}`
`{{DATA:FILE_SIZE:GHOST}}24500000{{/DATA}}`
`{{DATA:EPISODE_NUM:GHOST}}1{{/DATA}}`
`{{DATA:SEASON:GHOST}}1{{/DATA}}`
`{{DATA:SUBTITLE:GHOST}}This episode covers...{{/DATA}}`
`{{DATA:EPISODE_IMAGE:GHOST}}ep01-cover.jpg{{/DATA}}`
`{{DATA:EPISODE_TYPE:GHOST}}full{{/DATA}}`
`{{DATA:EXPLICIT:GHOST}}false{{/DATA}}`

The title `{{DATA:TITLE}}` and description `{{DATA:DESCRIPTION}}` are pulled automatically from the page's own settings.

Which data fields are usable varies by platform.

### Steps to Distribute a Podcast

1. Create a podcast episode page in 🍊mikanBox (category "podcast")
2. `https://yoursite.com/podcast.xml` is generated automatically
3. Register that URL with each platform

Registration links:
| Platform | Where to register |
|:---|:---|
|Apple Podcasts|podcastsconnect.apple.com|
|Spotify|podcasters.spotify.com|
|Google Podcasts|google.com/podcasts/publish|
|Amazon Music|music.amazon.com/podcasts|

Once registered, every time you add a new episode page, each platform automatically fetches `podcast.xml` again and updates on its own.

Just upload the audio file to 🍊mikanBox's Media, and you're all set.

---

## Sitemap, RSS &amp; API {#site-mapfeed}

These URLs are, by default, generated fresh by PHP on every request (dynamic). However, if [Static Site Generation (SSG)](#site-ssg)'s output directory is set to the same location as the site root, these also get written out as real files on every static build, and are then served as static files reflecting only the content at build time (not updated again until the next build).

### Sitemap

The sitemap is output at the following URL.

`https://yoursite.com/sitemap.xml`

Example: [https://yoshihiko.com/mikanbox/demo/sitemap.xml](https://yoshihiko.com/mikanbox/demo/sitemap.xml)

[Data-format](#data-formats) pages are left out of the sitemap, RSS and Podcast RSS. Markdown-only pages are listed by their `/ID.md` URL.

### RSS

The RSS feed is output at the following URL.

`https://yoursite.com/rss.xml`

Example: [https://yoshihiko.com/mikanbox/demo/rss.xml](https://yoshihiko.com/mikanbox/demo/rss.xml)

### Podcast RSS

Attach audio to a page, set its category to "podcast," and an RSS feed containing only "podcast"-category pages is output at the URL below, letting you distribute a podcast.

`https://yoursite.com/podcast.xml`

Example: [https://yoshihiko.com/mikanbox/demo/podcast.xml](https://yoshihiko.com/mikanbox/demo/podcast.xml)

### API

Publishing a page that embeds `{{DATAROW}}` with the output format "[mikanBox DB](#mikanbox-db)" makes it readable externally as an API.

`https://yoursite.com/api/pageID.json` (when served dynamically, `/api/pageID` works too)

Example: [https://yoshihiko.com/mikanbox/demo/api/sample-DB](https://yoshihiko.com/mikanbox/demo/api/sample-DB)

---

## Publishing an AI-generated Design As-Is {#ai-guide}

### Prompt for Having AI Build an Entire Page

`Output it as a single HTML file including CSS and JS. Use media/filename for images.`

Add the above to your design prompt.

### How to Use a Page Built Entirely by AI

Once you've generated the page, place it using these steps:

1. On the "Page Edit" screen, select "None (use the content HTML directly)" as the Design Component.
2. Paste the full content of the generated HTML into "Content" and save.

### If You Want to Build Shared Parts Across Multiple Pages

You can split out and manage shared parts (header, footer, contents inside `&lt;head&gt;`, etc.) separately.

- Register them separately as `_header`, `_footer`, `_global_head`, and so on.
- Selecting `_layout` as the "Design Component" makes these work automatically.
- To reflect each page's SEO information (title, description, OGP image, etc.), include `{{COMPONENT:_global_head}}` inside your component.
- CSS managed at the site level or per page is inserted automatically. Use `{{HEAD_CSS}}` to specify its insertion point.

---

## Announcement {#announcement}

The creator of this program is available for hire to design, build, and operate a CMS based on this program, including its UI design.
Feel free to reach out as well for anything in UI design, information architecture, DX promotion, applied AI, internal communications, design education, or content production.

Books: "The Design Classroom," "The Design Class," "The Basic Rules of Flat Design" (Japanese titles, published in Japan)

[https://yoshihiko.com](https://yoshihiko.com)

---

## License {#license}

**MIT License**

Copyright (c) 2026 [yoshihiko.com](http://yoshihiko.com)

<div class="license-block">
Permission is hereby granted, free of charge, to use this software and associated documentation files, subject to the conditions below, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software.<br><br>
<strong>Condition:</strong> The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.<br><br>
<strong>Disclaimer:</strong> The software is provided "as is," without warranty of any kind, express or implied. In no event shall the authors or copyright holders be liable for any claim, damages, or other liability, whether in an action of contract, tort, or otherwise, arising from, out of, or in connection with the software or the use or other dealings in the software.
</div>


### Authentication and private working data in 2.16.6

Flat password changes invalidate other browser sessions. Updating to 2.16.6 requires signing in again. Keep Internet-facing installations behind server access restrictions until initial setup is complete; the simple localhost setup is unchanged.

SQLite JSON migration and ZIP imports retain source data under the protected data directory's `private-import/` folder. On access after updating, old import packages under the management folder are moved to protected storage. If an old backup contained credentials, the MCP key is invalidated: issue a new key in the admin screen. Configure the web server to deny the entire data directory and the management folder's `import/` path.

Management notes, including site notes, are available to authenticated management MCP clients as working context. They are excluded from public pages, public APIs and public-help MCP. Reading a note is not permission to publish it.

SVG files are selected and uploaded by trusted administrators and may contain code. Upload processing does not guarantee complete sanitization. Check their source and contents.
