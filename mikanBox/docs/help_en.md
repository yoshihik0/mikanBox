<nav class="sidebar">

## Table of Contents

- <a href="#concept">Concept &amp; Features</a>
- <a href="#design-philosophy">Design Philosophy</a>
- <a href="#setup-dist">Setup &amp; Distribution</a>
- <a href="#overview">Overview</a>
- <a href="#page-mgmt">Pages</a>
- <a href="#page-edit">Page Edit</a>
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
- <a href="#site-mapfeed">Sitemap, RSS &amp; API</a>
- <a href="#podcast">Podcast</a>
- <a href="#ai-guide">Publishing an AI-generated Design</a>
- <a href="#announcement">Announcement</a>
- <a href="#license">License</a>

</nav>
<main>

<style>
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

# 🍊mikanBox User Manual

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

- Pages and components can each hold their own CSS
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

Requires a server running PHP 8.0 or later. [SQLite Edition]{.type-badge .badge-sqlite} additionally requires the SQLite3 extension to be enabled (this is enabled by default on most shared hosting).

### Installation

Upload the `mikanBox` folder into the same directory as `index.php`. This folder name can be changed — doing so is recommended for security, since it makes the location of the admin panel (`admin.php`) harder to guess. If you rename it, update `$core_dir` near the top of `index.php` accordingly. This document refers to it as the `mikanBox` directory throughout.

Site-specific data is created on first access in a `mikanData` folder beside `index.php`. If the legacy `mikanBox/data` directory exists, it is migrated automatically only when it can be moved safely. If the destination already exists or conflicts with a page URL, mikanBox keeps using the legacy location instead of switching to empty data.

To change the data directory name, create `local-config.php` inside the admin/core directory; this file is excluded from self-updates. Add `define('MIKANBOX_DATA_DIR_NAME', 'your-folder-name');`. The value must be one directory name containing only letters, numbers, hyphens, and underscores. If data already exists, move the physical directory to the same name before changing this setting.

Right after installation (before any page or component data exists), the first visit will automatically load sample content matching the visitor's browser language (Japanese or English).

### Initial Password Setup

Whoever accesses `mikanBox/admin.php` first after setup sets the admin password.

[SQLite Edition]{.type-badge .badge-sqlite} also asks for a username, display name, and a "security question and answer" used to verify your identity if you forget your password. These are stored in the `users` table inside `mikanData/mikanBox.sqlite`.

[flat Edition]{.type-badge .badge-flat} only asks for a password. Its hash (the encrypted value) is stored in `mikanData/settings.json`.

#### Distributing to Others

If you deliver a site to someone else — including your own custom design and content — the steps for letting them set up a new admin account on their own environment differ by edition.

- [SQLite Edition]{.type-badge .badge-sqlite}: Create an empty file named `mikanData/reset_password.txt` on the server. On the next visit, all registered users are deleted and the initial setup screen appears. This file is deleted automatically afterward, so there's no risk of accidentally leaving it in place.
- [flat Edition]{.type-badge .badge-flat}: Clear the value of `"password_hash"` inside the `mikanData/settings.json` file.

#### Security

Files other than `admin.php` (such as the JSON files inside the `data` folder) are blocked from direct browser access (via `.htaccess` or similar restrictions).

---

## Overview {#overview}

mikanBox builds and manages a site across these four parts.

### [description]{.material-symbols-outlined .mat-icon}Pages

Manages the pages that make up the actual substance of the site. You decide the URL (ID), set the title and SEO information, and write the body content (Markdown/HTML).

### [save]{.material-symbols-outlined .mat-icon}Site

Groups together the settings and management tools for running the site as a whole: the admin memo, Static Site Generation (SSG), language, the MCP API key, CSV import, data management/backup, common site settings, user management (editors), and changing your password.

### [widgets]{.material-symbols-outlined .mat-icon}Design

There are three types of components: [Page]{.type-badge .badge-page}, which sets the design for an entire page; [Part]{.type-badge .badge-part}, which builds shared building blocks; and [AI Instructions]{.type-badge .badge-ai}, for writing instructions aimed at an AI agent. Anything shared across the site, like a header, footer, or navigation, is worth building as a Part so it can be reused anywhere. This screen's main role is managing these design elements (components).

### [image]{.material-symbols-outlined .mat-icon}Media

Manages image, video, and audio files. You can quickly upload, browse the list, resize, and copy a filename ready to paste elsewhere.

---

## Common Elements

#### Preview

Opens the site's top page (in a new tab).

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

### Keyword Search [SQLite Edition only]{.type-badge .badge-sqlite}

#### Search box

Type a keyword and the page list is filtered in real time (via AJAX) against titles, body content, and more. Use the [[close]{.material-symbols-outlined}]{.m-btn .m-btn-gray} button to the right of the field to clear the search.

### Page List

#### Edit / Preview

- [[edit]{.material-symbols-outlined}Edit]{.m-btn .m-btn-blue} — Opens the edit screen for that page.
- [[open_in_new]{.material-symbols-outlined}]{.m-btn .m-btn-blue} — Opens the page in a new tab, at the URL matching its current status.
- For a page with "Pending approval" status, this instead opens a preview share URL (with a preview token). [SQLite Edition only]{.type-badge .badge-sqlite}

#### Status

A dropdown for switching each page's publication status. Changes are saved immediately.

- **Draft** — Private. Not accessible to anyone but the admin.
- **Pending approval** — [SQLite Edition only]{.type-badge .badge-sqlite} Private, but anyone who knows the unguessable preview URL (`?preview=token`) can view it. Useful for sending a page to a reviewer for approval. Pages pending approval are shown at the top of the list.
- **Public (Dynamic)** — Rendered dynamically by PHP.
- **Public (Static)** — Published as a static HTML file, following the directory/file settings configured under Static Site Generation (SSG) on the Site screen.
- **DB** — The page itself stays private, and its `{{DATAROW}}` data becomes readable as an API at `siteURL/api/pageID`.

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

Shows the categories assigned to the page. Used for filtering via things like `{{NAV_LINKS:category}}`.

### Static Site Build Button

#### [auto_awesome]{.material-symbols-outlined} Build Static Site{.m-btn .m-btn-blue}

Found at the bottom of the page list. Writes out every page whose status is "Public (Static)" as static HTML files. After editing a Public (Static) page, other pages can end up out of sync, so it's a good idea to rebuild the whole site here.

---

## Page Edit {#page-edit}

A screen for editing a page's content and settings in detail. Opens when you click "Create New," a page's title, or its Edit button.

#### History [SQLite Edition only]{.type-badge .badge-sqlite}

A dropdown marked with a <span class="material-symbols-outlined mat-icon">history</span> icon. Every time you save, up to 10 past versions are kept automatically, selectable along with the date/time and editor name (when multiple users are involved). Selecting a past revision loads its content into the form and shows a "Viewing Past Revision" warning banner. Pressing [[save]{.material-symbols-outlined}Save]{.m-btn .m-btn-blue} at that point restores that revision as the current version. Selecting the top entry ("Latest Version (Current)") again returns you to the latest content.

#### Updated

Set when the page is created. The date/time can be changed. When a page moves from a private status (draft, pending approval, etc.) to "Public (Dynamic)" or "Public (Static)," this is automatically updated to the actual moment of publication (simply editing an already-published page does not change it).

#### Title

The page's title. Required. Available inside components as `{{TITLE}}` or `{{FULL_TITLE}}` (formatted as "Page Title - Site Name").

#### Status

Choose the page's publication status via radio buttons.

- **Draft** — Private. Not accessible to anyone but the admin.
- **Pending approval** — [SQLite Edition only]{.type-badge .badge-sqlite} Private, but an unguessable preview share URL (`?preview=token`) is generated automatically and shown right on this screen. You can just send the URL to a reviewer so they can check the content without ever logging into the admin panel.
- **Public (Dynamic)** — Rendered dynamically by PHP.
- **Public (Static)** — Published as a static HTML file, following the directory/file settings configured under Static Site Generation (SSG) on the Site screen.
- **DB** — The page itself stays private, and its `{{DATAROW}}` data becomes readable as an API at `siteURL/api/pageID`.

If you switch a page that was set to Public (Static) to any other status, its static HTML file is deleted.

#### Preview Share URL [SQLite Edition only]{.type-badge .badge-sqlite}

Shown when status is set to "Pending approval." A text box holds the URL, with a [[content_copy]{.material-symbols-outlined}Copy]{.m-btn .m-btn-gray} button next to it to copy it to the clipboard.

#### Memo

A private note visible only to admins. Free-form space for anything you want to remember or hand off to someone else. It's never shown on the site, and is never passed to AI (MCP) either.

#### ID

The identifying slug that becomes part of the page's URL. Alphanumeric characters and hyphens are allowed. The page's URL takes the form `siteURL/ID`. Note that saving with an ID matching an existing page will overwrite it. The index page's ID cannot be changed. You can also use "/" within an ID. On a static site, any "/" in the ID is treated as a directory boundary; on a dynamic site, a pseudo directory structure is created the same way. For example, an ID of `news/2024` produces a URL like `siteURL/news/2024`.

#### Design Component

Choose which page component to use for this page's layout. Any component with "Use as a Page Component" checked, on the Design screen, appears as an option here.

#### Category (comma separated)

Freely enter, comma-separated, the categories this page belongs to. Categories currently in use are shown below the field. A category listing page is not generated automatically; if you need one, create a page and place `{{NAV_LINKS:category}}` or `{{NAV_CARDS:category:componentID}}` in it.

#### Order

Controls the sort order in things like navigation. Lower numbers come first. Anything below 0 is excluded from list output.

#### Thumbnail / OGP Image

Specify the filename (e.g. `ogp.jpg`) of the image shown when shared on social media, or in card lists. Output as the `meta` tag's `og:image`. Available inside components as `{{OGP_IMAGE}}`.

#### Keywords

Enter comma-separated keywords (meta keywords) used for the page's search visibility. Output inside components as `{{KEYWORDS}}`.

#### Description (meta description)

Set a summary description for the page. Output via `{{DESCRIPTION}}`, and used as the blurb shown under the title in search results. It's displayed with no gap right after the body field, so you can edit both as a single continuous block.

#### Content (Markdown or HTML)

Write the page's main content using Markdown syntax or HTML. Markdown and HTML can be mixed freely. Complex HTML generated by AI can be pasted in as-is and will still work correctly. See the guide at the bottom of the edit screen for the available Markdown syntax and custom tags — or for more detail, see [Markdown](#markdown) and [Components (Custom Tags)](#components).

#### Custom CSS

Write styles that should apply only to this page. This is automatically scoped, so it never affects other pages or anything outside the body content. Custom tags can also be used inside this CSS.

To intentionally style an element outside the page scope, wrap the entire selector in `:global(...)`. For example, `:global(.hero_box) { display: none; }` applies the rule to `.hero_box` outside the scoped page content, while ordinary selectors remain limited to this page's body content.

#### [save]{.material-symbols-outlined} Save{.m-btn .m-btn-blue}

Saves everything about the page.

#### [open_in_new]{.material-symbols-outlined} Preview{.m-btn .m-btn-blue}

(only shown while editing)

Opens the page in a new tab, at the URL matching its current status, so you can check how it looks.

#### [arrow_back]{.material-symbols-outlined} Back to List{.m-btn .m-btn-gray}

Returns to the page list. Note that unsaved changes will be lost.

#### [delete]{.material-symbols-outlined} Delete{.m-btn .m-btn-red}

Permanently deletes the page. This cannot be undone.

At the bottom of the edit screen, a quick reference for Markdown syntax and the list of available tags (tag guide) appears collapsed. Click to expand it.

---

## Site {#site}

A menu that groups together the settings and management tools related to running the site. Each item is collapsed into an accordion (except for the admin memo).

### Admin Memo {#site-memo}

A private note, visible only to admins, always shown at the top of the site. Free-form space for hand-off notes, work reminders, and the like.

#### [save]{.material-symbols-outlined} Save{.m-btn .m-btn-gray}

Saves the memo's content.

### System Update

Displays the installed version and the latest version available on GitHub. When a newer version is available, use the [Update] button to update mikanBox.

Only program files are replaced. Pages, settings, media, the database, and generated static files are left untouched. One generation of the previous program files is stored in a protected area on the server. A failed update is restored automatically, and after a successful update you can use the [Restore] button to return to the immediately preceding version.

After you confirm an update, the button changes to [Updating…] and remains disabled until the operation finishes. When the update completes, the admin screen reloads automatically and shows the new version number and a completion message. The restore operation similarly displays [Restoring…] while it is running.

### Static Site Generation (SSG) {#site-ssg}

mikanBox doesn't just render pages dynamically with PHP — it also has a feature (SSG: Static Site Generation) to export the entire site as "static HTML files," and the two can be mixed freely.

#### Benefits of SSG

- **Extremely fast rendering** — Since the server doesn't need to run PHP, pages load quickly.
- **Stronger security** — You can configure things so no PHP files or data (JSON) sit in the public area at all, minimizing the risk of tampering.

#### Static Site Build Method

- **Generate static pages on this server** — Normally writes HTML into the site root. Specify an output directory only when a different location is needed.
- **Create an upload-ready folder (for local operation)** — Writes HTML and a copy of `media/` into a dedicated output folder. For safety, the site root, the mikanBox core, and the existing media folder cannot be selected as that destination.

For an upload-ready folder, links can be written as portable relative paths (recommended), or fixed to a published URL. Relative links keep working when the folder is moved. Fixed URLs require a published root such as `https://example.com/site`.

#### Output Directory

Specify where the static HTML files get written, as a path relative to the site root (the location where the `mikanBox` folder lives). For example, entering `blog` writes into a `blog/` folder directly under the site root, while entering `../` writes one level above the site root. Leaving this blank writes directly into the site root.

An empty server-side output means the site root. When you select an upload-ready folder, `export` is supplied as the default output directory and can be changed to another dedicated folder.

#### Output Structure / URL Format

Choose the format of the generated HTML files.

- **Directory-based (folder/index.html)**: Writes as `pageID/index.html`. The URL becomes `/pageID/`.
- **File-name based (filename.html)**: Writes as `pageID.html`. The URL becomes `/pageID.html`.

Directory-based output can be published under the exact same URL as dynamic publishing, so switching between them never breaks links. File-name based output includes the extension in the URL and results in a simpler file layout.

The default is directory-based for "Generate static pages on this server" and file-name based for "Create an upload-ready folder." The last selected output structure is remembered separately for each build method.

#### [auto_awesome]{.material-symbols-outlined} Save &amp; Build Static Site{.m-btn .m-btn-blue}

Saves the settings, then writes out every page whose status is "Public (Static)" as static HTML files.

If a page ID contains a `/` (e.g. `news/2024`), it's treated as a directory structure as-is. During a static build, an actual folder is created inside the output directory (e.g. `news/2024/index.html` for directory-based output), and the file is generated inside it.

#### How the sitemap, RSS, and podcast RSS are handled

`sitemap.xml`, `rss.xml`, and `podcast.xml` are normally generated fresh by PHP on every request (see [Sitemap, RSS &amp; API](#site-mapfeed) below). Because exported sitemap and RSS files require absolute URLs, SSG writes them when a published root URL is available. Server-side generation falls back to the current site URL when none is configured. Adding or changing pages after a build is not reflected until the static site is rebuilt.

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

Starting with mikanBox 2.5.1, the MCP connection instructions tell AI to call `get_ai_context` on first connection. This tool returns every [AI Instructions]{.type-badge .badge-ai} component—such as `DESIGN.md`, `BRAND.md`, and `CONTENTS.md`—in a single response. AI treats the returned content as site-specific project instructions for content, design, and code changes. Updating an instruction component updates what AI receives the next time it loads the context.

#### If no API key has been issued yet

- [[auto_awesome]{.material-symbols-outlined}Generate API Key]{.m-btn .m-btn-blue} — Issues a new API key.

#### API Key (once issued)

The issued API key is shown in a read-only field.

- [[auto_awesome]{.material-symbols-outlined}Regenerate API Key]{.m-btn .m-btn-gray} — Invalidates the current key and issues a new one. Any existing AI integrations will need to be reconfigured.
- [[content_copy]{.material-symbols-outlined}Copy]{.m-btn .m-btn-gray} — Copies the API key to the clipboard. Paste the generated key into the settings of whatever tool you're connecting (e.g. `.mcp.json` for Claude Desktop).

### CSV Import {#csv-import}

A tool that converts CSV data into a database-like form (DATAROW blocks) usable within the site.

#### Choose File

Select a CSV file. Encoding (UTF-8 / Shift-JIS) is detected automatically.

#### [content_copy]{.material-symbols-outlined} Convert &amp; Copy{.m-btn .m-btn-gray}

Reads the first row as field names and converts every other row into a `{{DATAROW:number}}` block, copied to the clipboard. After copying, paste it into the body content of a page and save.

#### Tips for Use

- Write field names (the first row) using alphanumeric characters.
- Thousands separators in prices (e.g. `1,000`) are read correctly, since Excel automatically quotes them.
- We recommend keeping data-only pages set to **Draft**. Visitors won't see them, but they can still be referenced via `{{POST_MD:pageID#rowID:FIELD}}` or `{{NAV_CARDS}}`.
- Setting the status to **DB** keeps the page itself private, while its `{{DATAROW}}` data becomes readable as an API at `siteURL/api/pageID`.

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

#### Site ID (for AI)

An immutable ID generated for each site. AI uses it to avoid confusing one mikanBox site with another when several sites are connected. On existing sites, it is generated when Site Settings are saved or when AI first requests the site information.

#### Site Name

Sets the site's name. Output via `{{SITE_NAME}}`. `{{FULL_TITLE}}` is displayed as "Page Title - Site Name," and is also used for things like bookmark titles and search-result titles.

#### Site URL

Sets the public URL AI uses to confirm the target site. When blank, the root URL configured for Static Site Generation is used.

#### Site Environment

Choose Production, Staging, Development, Local, or Unspecified. Through MCP, AI can call `get_site_info` to check this environment together with the site ID, name, and URL.

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

### User Management (Editors) [SQLite Edition only]{.type-badge .badge-sqlite} {#site-users}

A feature for managing editor accounts when multiple people work on the same site. Each editor logs in with their own username and password, and edit history records which editor made each change.

#### Registered Editors

A list of usernames and display names. You cannot delete your own currently logged-in account, or the only remaining user.

- [[delete]{.material-symbols-outlined}Delete]{.m-btn .m-btn-red} — Deletes that user's account. This cannot be undone.

#### Add New User

Click to expand and reveal the following fields.

- **User ID** — Alphanumeric characters, underscores, and hyphens only. This becomes the login ID.
- **Display Name** — The name shown in things like edit history. If left blank, the User ID is used instead.
- **Password** — At least 4 characters.

#### [add]{.material-symbols-outlined} Add User{.m-btn .m-btn-blue}

Creates a new editor account with the entered details.

#### Account Settings (User ID, Password, Security Question)

Click to expand and edit the details of your own currently logged-in account, all in one place.

- **User ID** — Your login ID. Alphanumeric characters, underscores, and hyphens only.
- **Display Name** — The name shown in things like edit history.
- **Current Password** — Enter the password you currently use to log in.
- **New Password** — Enter a new password (at least 4 characters). Leave blank to keep your password unchanged.
- **Security Question** — Set the question used to verify your identity if you forget your password. A warning is shown if this isn't set.
- **Security Answer (leave blank to keep it unchanged)** — Set the answer to the question. This is checked against what you enter when resetting your password from the login screen.

#### [save]{.material-symbols-outlined} Update{.m-btn .m-btn-blue}

Saves the account settings.

**If you forget your password:** From the login screen's "Forgot your password?" link, you can reset it yourself — enter your User ID, answer your security question, then set a new password (only available if a security question has been configured).

If you have direct server access, creating an empty file named `mikanData/reset_password.txt` deletes all registered users on the next visit and shows the initial setup screen. This file is deleted automatically afterward, so there's no risk of accidentally leaving it in place.

### Change Password [flat Edition only]{.type-badge .badge-flat} {#site-password}

Changes the admin panel's login password.

#### Current Password

Enter the password you currently use to log in.

#### New Password

Enter a new password (at least 4 characters).

#### [save]{.material-symbols-outlined} Save{.m-btn .m-btn-blue}

Changes and saves the password.

**If you forget your password:** Using FTP or a file manager, edit the `mikanData/settings.json` file on the server and clear the value of `password_hash`. The initial password setup screen will appear on your next visit.

---

## Data Migration &amp; Import Tool [SQLite Edition only]{.type-badge .badge-sqlite} {#data-migration}

A separate, standalone tool (`convert.php`) from the admin panel (`admin.php`). Access `mikanBox/convert.php` directly in your browser to use it. You can check the current state of the database and media, and bulk-import external data.

### Current State

#### SQLite Database State

Shows whether the database file exists, its size, and the number of registered pages, components, and users (editors).

#### Media File State

Shows the storage directory, the number of registered media files, and total media size.

### Content Package (ZIP) Import

Upload a ZIP package (a theme or a starter set of content) containing `posts/`, `components/`, and similar folders, and import it into the current SQLite database. Only the contents of `posts/` and `components/` are imported — the contents of `settings.json` (site setting values) are never read at all.

When you unzip a package, `posts/` and `components/` sometimes don't appear directly — there may be one extra folder wrapping them (e.g. `themeName/posts/`, `themeName/components/`). In this case, the tool keeps descending as long as a folder contains exactly one subfolder, and treats the level where `posts/`/`components/` appear side by side (i.e. the level has two or more subfolders) as the root. If a `settings.json` file is present, its mere existence (not its contents) is used to immediately identify that level as the root.

Each item's ID is taken directly from its JSON **filename** (minus the extension) — not from any title inside the file. Anything matching an existing ID is skipped; nothing is ever overwritten. If you want to bring in a page or component in a way that overwrites an existing one, either delete the existing item in the admin panel first, or rename the JSON file to your intended ID before including it in the ZIP.

Once import finishes, the extracted folder is renamed to `{folder name}_imported` inside the `import/` folder and left in place (it is not deleted).

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

---

## Design {#design-mgmt}

Manages three types of components: [Page]{.type-badge .badge-page}, which builds the design for a whole page; [Part]{.type-badge .badge-part}, which builds shared building blocks; and [AI Instructions]{.type-badge .badge-ai}, for writing instructions aimed at AI.

#### [add]{.material-symbols-outlined} Create New{.m-btn .m-btn-blue}

Creates a new component. Clicking opens the design-edit area.

### Component List

#### Edit

Clicking the [[edit]{.material-symbols-outlined}Edit]{.m-btn .m-btn-blue} button opens the edit screen for that component.

#### Component ID

The component's identifier. Clicking it also opens the edit screen. IDs starting with "_" are system components provided out of the box — you're free to edit and reuse them.

`{{COMPONENT:_global_head}}` contains shared settings for inside the `<head>` tag, and already includes `{{HEAD_CSS}}`, which aggregates and outputs all CSS. Since `{{HEAD_CSS}}` is required to manage CSS, make sure it appears exactly once per page. For content pasted straight from AI output, base it on `_ai`; for everything else, `_layout` is a good starting point. Of course, you're free to create as many of your own as you like — and those don't need to start with "_".

#### Type

Shows the component's type. In the list, [AI Instructions]{.type-badge .badge-ai} components are sorted to the top as a group.

- [Page]{.type-badge .badge-page} — A component with "Use as a Page Component" checked. Selectable under "Design Component" in the Page Edit screen.
- [Part]{.type-badge .badge-part} — A building block embedded inside a page or another component.
- [AI Instructions]{.type-badge .badge-ai} — Instructions meant to be read by an AI agent, like your site's design rules (`DESIGN.md`, `BRAND.md`, etc.).

#### Tag Name

Shows the tag used to embed the component (e.g. `{{COMPONENT:header}}`). Clicking it copies it to the clipboard, ready to paste into a page's body content or another component's HTML.

---

## Design Edit {#design-edit}

A screen for defining a component's structure (HTML/CSS) and settings.

#### Component ID

The component's identifier. Required. Alphanumeric characters and underscores are allowed. Called from a template as `{{COMPONENT:ID}}`. When the type is set to "AI Instructions," dots (`.`) are also allowed, and a trailing `.md` is added automatically on save if not already present.

#### Memo

A private note visible only to admins. Never shown on the site, and never passed to AI (MCP) either.

#### Type

Choose one of three types via radio button.

- [Part]{.type-badge .badge-part} — A building block meant to be embedded inside a page or another component.
- [Page]{.type-badge .badge-page} — A component that lays out an entire page. Selecting this shows it as a [Page]{.type-badge .badge-page} in the Design list, and makes it selectable under "Design Component" on the Page Edit screen. The following extra tags become available:
  - `{{CONTENT}}` — Inserts the page's body content at this position.
  - `{{HEAD_CSS}}` — Aggregates and inserts page and component CSS. Without this tag, styles won't be applied.
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

### Upload Area

#### Choose File

Click "Choose File" to select the file to upload. Dragging and dropping a file onto this window uploads it as soon as it's dropped.

#### [upload]{.material-symbols-outlined} Upload{.m-btn .m-btn-blue}

Uploads the selected file.

#### Supported Formats &amp; Limits

Supports jpg, png, gif, webp, svg, mp3, m4a, and mp4. The maximum file size depends on your server's configuration.

Uploaded files are stored in the `media` directory. How to reference them in Markdown or HTML:

- Markdown: `![description](filename)`, or `{{IMAGE:filename}}` (also `{{AUDIO:filename}}`, `{{VIDEO:filename}}`)
- HTML: any of `<img src="filename">`, `<img src="images/filename">`, or `<img src="media/filename">` will display it.

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

Click "Resize..." to expand it. Enter either Width (W) or Height (H) and press [[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue} to resize it while keeping the aspect ratio (jpg, png, gif, and webp only).

#### Rename...

Click "Rename..." to expand it. Edit the filename and press [[save]{.material-symbols-outlined}]{.m-btn .m-btn-blue} to rename the media file. If that filename is already referenced somewhere in an existing page or component, a confirmation dialog appears with these choices:

- **Update Links** — Renames the file and also rewrites every reference to it inside pages/components to the new filename.
- **Rename File Only** — Renames only the file itself, leaving existing references untouched (note that those references will then point to a missing file).
- **Keep Original** — Cancels the rename.

---

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

### Navigation

A list of links: `{{NAV_LINKS:category}}` is output as a `<li>` list.

To output image-bearing cards instead, use `{{NAV_CARDS:category:componentID}}`. The card's design can be defined via a component. If you omit the component ID, the standard design (the `_nav_card` component) is used.

The order of the list or cards follows each page's "Order" setting. Pages with an order below 0 are excluded.

For both, setting the category to "all" outputs a list of every page, and leaving it empty outputs a list of pages with no category assigned. Since a page can have multiple categories, setting categories thoughtfully lets you build many different kinds of navigation.

### Embedding Another Page

The content of another page can be embedded via `{{POST_MD:ID}}`. This is handy when you want just one small part (like an announcements section) of a complex AI-built page to be easily editable — build that part as a simple Markdown page and embed it. The embedded page can be set to "Draft" so it stays hidden when viewed on its own.

### Embedding a Markdown Document from Another Site

A Markdown document hosted on another site, like GitHub, can be embedded via `{{EXT_MD:url}}`. Not only can you edit it directly on GitHub, but you can also adopt an "AI-friendly" workflow where having an AI agent rewrite the file on GitHub is all it takes to update the site.

### Page Components

A component that lays out an entire page should include `{{CONTENT}}` (to output the body content) and `{{HEAD_CSS}}` (to output CSS). Without `{{HEAD_CSS}}`, styles won't be applied. See the `_layout` bundled component for the basic structure.

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
|`{{ SITE_URL }}`|Site URL|
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
|`{{ POST_MD:ID }}`|Embeds the given page as Markdown|
|`{{ EXT_MD:url }}`|Embeds Markdown from the given URL|
|`{{DATA:key}}value{{/DATA}}`|Defines data (visible). Key (case-insensitive) may only use alphanumeric characters and underscores. Value can be in any language.|
|`{{DATA:key:GHOST}}value{{/DATA}}`|Defines data (hidden). Key may only use alphanumeric characters and "_".|
|`{{DATAROW:rowID}} {{DATA:key}}value{{/DATA}} {{/DATAROW}}`|Defines table-format data. key and rowID may only use alphanumeric characters and "_".|
|`{{ POST_MD::key }}`|Displays data from within the current page|
|`{{ POST_MD::pageID:key }}`|Displays data from the given page ID|
|`{{ EXT_MD:url:key }}`|Displays data from an external page|
|`{{ POST_MD::#rowID:key }}`|Displays table-format data from within the current page|
|`{{ POST_MD:pageID#rowID:key }}`|Displays table-format data from the given page ID|
|`{{ EXT_MD:url#rowID:key }}`|Displays table-format data from an external page|
|`{{ CONTENT }}`|**Page wrapper only**: inserts the body content|
|`{{ HEAD_CSS }}`|**Page wrapper only**: aggregates and inserts CSS. By default, this is already placed inside the `_global_head` component.|

※ If you want to show the current year (e.g. for a copyright notice), we recommend doing it with JavaScript instead (e.g. `<script>document.write(new Date().getFullYear())</script>`). This way the year is always current without going through any server-side processing.

---

## Bundled Components {#init-comps}

The components bundled with a fresh install. You can view and edit these in the Design management screen.

|ID|Type|Role / Description|
|---|---|---|
|`_layout`| [Page]{.type-badge .badge-page}|The standard shared layout. Combines `{{COMPONENT:_global_head}}`, `{{COMPONENT:_header}}`, `{{CONTENT}}`, `{{COMPONENT:_footer}}`, and `{{HEAD_CSS}}` into a basic structure. Regular Markdown pages should select this.|
|`_ai`|[Page]{.type-badge .badge-page}|A simple page component for AI-generated HTML. Made up of only `{{CONTENT}}`, designed so you can paste in a complete HTML document generated by AI and use it as-is for the page body.|
|`_global_head`|[Part]{.type-badge .badge-part}|A collection of shared tags to insert inside the HTML `<head>` section — things like Google Analytics or web font loading that are common across every page. By default, `{{HEAD_CSS}}` is already placed here, so it's best practice to always include this in a page component. CSS scoping is disabled (global).|
|`_header`|[Part]{.type-badge .badge-part}|The site's shared header. A standard header component including the site name and navigation links.|
|`_footer`|[Part]{.type-badge .badge-part}|The site's shared footer. A standard footer component including copyright and footer navigation.|
|`_nav_card`|[Part]{.type-badge .badge-part}|The standard card design used for card-style navigation when a component ID is omitted. Outputs a card showing the thumbnail image, title, description, and date.|
|`_ai_question`|[Part]{.type-badge .badge-part}|The public AI question box. Place it with `{{COMPONENT:_ai_question}}` to pass the question, current page, and official public manual reference to an external AI.|
|`_search_box`|[Part]{.type-badge .badge-part}|The design for a search box. Its form includes `<input type="hidden" name="page" value="search">`, so submitting it takes you to the "search" page (which displays results using `_search_result_item`).|
|`_search_result_item`|[Part]{.type-badge .badge-part}|The design for a single search result entry. Placed on the search results page as `{{SEARCH_RESULTS:_search_result_item}}`.|
|`DESIGN_sample.md`|[AI Instructions]{.type-badge .badge-ai}|A sample instruction document for having AI generate or edit your design. Rename its ID to `DESIGN.md` to activate it. You can even have AI write the content itself. Similarly useful files include `BRAND.md` (brand information) and `CONTENTS.md` (content policy).|

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

By loading a page hosted on another site, like GitHub, as Markdown, you can update your site's content without touching the site itself. And for security, JavaScript is never executed.

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

Using "CSV Import" on the Site screen, you can convert CSV data from something like Excel into `{{DATAROW}}` format. No extra effort required, even for fairly large data sets.

Select the CSV file exported from Excel, then click
#### [content_copy]{.material-symbols-outlined} Convert &amp; Copy{.m-btn .m-btn-gray}
to copy the converted data. Paste it into a page's body content to use it.

Reads the first row as field names and converts every other row into a `{{DATAROW:number}}` block, copied to the clipboard. After copying, paste it into the body content of a page and save.

### It Can Even Act as a Headless CMS

By publishing a page that embeds `{{DATAROW}}` with status set to "DB," it becomes readable externally as an API.

`https://yoursite.com/api/pageID`

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

### RSS

The RSS feed is output at the following URL.

`https://yoursite.com/rss.xml`

Example: [https://yoshihiko.com/mikanbox/demo/rss.xml](https://yoshihiko.com/mikanbox/demo/rss.xml)

### Podcast RSS

Attach audio to a page, set its category to "podcast," and an RSS feed containing only "podcast"-category pages is output at the URL below, letting you distribute a podcast.

`https://yoursite.com/podcast.xml`

Example: [https://yoshihiko.com/mikanbox/demo/podcast.xml](https://yoshihiko.com/mikanbox/demo/podcast.xml)

### API

By publishing a page that embeds `{{DATAROW}}` with status set to "DB," it becomes readable externally as an API.

`https://yoursite.com/api/pageID`

Example: [https://yoshihiko.com/mikanbox/demo/api/sample-DB](https://yoshihiko.com/mikanbox/demo/api/sample-DB)

---

## Publishing an AI-generated Design As-Is {#ai-guide}

### Prompt for Having AI Build an Entire Page

`Output it as a single HTML file including CSS and JS. Use images/filename for images.`

Add the above to your design prompt.

### How to Use a Page Built Entirely by AI

Once you've generated the page, place it using these steps:

1. On the "Page Edit" screen, select `_ai` as the Design Component.
2. Paste the full content of the generated HTML into "Content" and save.

### If You Want to Build Shared Parts Across Multiple Pages

You can split out and manage shared parts (header, footer, contents inside `&lt;head&gt;`, etc.) separately.

- Register them separately as `_header`, `_footer`, `_global_head`, and so on.
- Selecting `_layout` as the "Design Component" makes these work automatically.
- To reflect each page's SEO information (title, description, OGP image, etc.), include `{{COMPONENT:_global_head}}` inside your component.
- To reflect CSS managed at the site level or per page, include `{{HEAD_CSS}}`.

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
