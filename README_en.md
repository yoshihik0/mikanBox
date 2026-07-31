# 🍊mikanBox

[日本語 README →](README.md)

**AI-era, parts-assembly, ultra-lightweight CMS**

🍊mikanBox is an ultra-lightweight CMS with an easy-to-use UI, Markdown-based content management, and a flexible component-based design — all of which can be freely controlled by AI.

> 🍊 *Mikan* is the Japanese word for a small, easy-to-peel mandarin orange — the fruit that gave mikanBox its name.

🍊mikanBox comes in two versions:

- **🍊mikanBox flat** — A JSON file-based version with no database, for quickly launching small sites.
- **🍊mikanBox** (this repository) — Uses a SQLite database, so it stays snappy even on sites with more pages.

For campaign or event sites, shop sites — sites with few pages but that need regular updates — use **🍊mikanBox flat**. For sites you'll run long-term and expect to keep growing, use **this 🍊mikanBox**. Data can be migrated between the two.

[SQLite version (this repo) vs. JSON version (flat) →](#flat)

[🍊mikanBox flat →](https://github.com/yoshihik0/mikanBox-flat/)

---

## Features

- **SQLite (single-file DB)** — All data lives in one DB file. Just place it on a PHP-enabled server
- **Modeless UI** — No page transitions; nearly all work completed on a single screen for a snappy experience
- **Markdown Support** — Easy to edit content, and also well-suited as a content archive
- **Filter by Category** — Narrow down pages and images by category; use categories as workspaces
- **Images Shown by Filename Alone** — Place images without thinking about file paths
- **Component Structure** — Page templates and parts you can combine and reuse
- **Per-component Scoped CSS** — Write CSS in small scopes without worrying about interference
- **AI-generated Code Works As-Is** — Also supports running without any manual design work
- **AI Agent Integration (MCP)** — AI understands the site structure and design conventions, and reads/writes files directly
- **DESIGN.md Management** — Manage instructions for AI right on the site, and hand them to AI
- **Multimodal AI Support** — AI generates images and sends/places them directly in the media folder
- **Static (SSG), Dynamic, or Mixed** — Can be a fast static site, or mix static and dynamic pages
- **DB Less DB** — Embed data in pages and output via API. Can also be used as a headless CMS
- **Podcast** — Auto-generate RSS for podcast distribution

---

## Demo

[https://yoshihiko.com/mikanbox/demo/](https://yoshihiko.com/mikanbox/demo/)

---

<a name="flat"></a>

## SQLite Version (this repo) vs. JSON Version (flat)

| | 🗄️ SQLite version (this repo) | 📂 JSON version (flat) |
| :--- | :--- | :--- |
| Best for | Sites that will keep growing in page count | Sites with fewer pages (up to ~100 or so) |
| Storage | (single DB file) | JSON files |
| Requirements | PHP 8.0+ + SQLite3 extension (standard on most hosting providers) | PHP 8.0+ only |
| Approval workflow & preview share URL | ○ | − |
| Revision history & restore to a previous version | ○ | − |
| Self-service password reset | ○ (security question) | − (requires manually editing the settings file) |
| Keyword search box in the admin panel | ○ | − |

> [!TIP]
> For campaign or event sites, shop sites — sites with few pages but that need regular updates — use **🍊mikanBox flat**. For sites you'll run long-term and expect to keep growing, use **this 🍊mikanBox**. Data can be migrated between the two, so you can switch later.

[🍊mikanBox flat →](https://github.com/yoshihik0/mikanBox-flat/)

---

## Requirements

- A web server with PHP 8.0 or later (with the SQLite3 extension enabled — standard on most hosting providers)

---

## Installation

1. Upload the `mikanBox` folder and `index.php` to your server
2. For security, we recommend renaming the `mikanBox` folder to a name of your choice (if renamed, also update the `$core_dir` variable at the top of `index.php` to match)
3. Access `{your-admin-folder}/admin.php` to set your admin password

Site-specific data is created on first access in `mikanData` beside `index.php`. Existing `mikanBox/data` sites are migrated automatically only when the directory can be moved safely.

That's all.

---

## Basic Usage

### Two Operating Styles

**Continuous Content** (blogs, service pages, etc.)

- Write in Markdown and reference images by filename only
- Well-suited for ongoing updates and content reuse

**Short-term Pages** (landing pages, event pages, etc.)

- Paste AI-generated HTML/CSS/JS directly to publish
- No manual design work needed

### Design (Components)

- **Page Components** — Wrappers that define the overall layout of a page
- **Parts Components** — Reusable parts embedded in pages or other components
- **AI-Instruction Components** — Things like DESIGN.md, managing instructions read by AI as components too

Components contain HTML and scoped CSS, and can be nested.

### Static Site Generation (SSG)

Export all pages as static HTML with a single click. You can also mix static and dynamic pages. Choose between generating pages in the current server or creating an upload-ready folder containing portable relative links and a copied media folder.

### Page Publish Status

| Status | Behavior |
| :------- | :------- |
| Draft | Private (admin only) |
| Pending approval | Published only via a limited-access URL |
| Public (Dynamic) | Served dynamically via PHP |
| Public (Static) | Exported as static HTML |
| DB | Page itself is private; exposes data as an API |

---

## AI Integration

🍊mikanBox is designed with a strong focus on compatibility with AI tools.

- AI-generated HTML pages can be pasted directly into the content field for instant publishing
- The codebase is compact and simply structured, making it easy for AI to understand the specifications
- MCP support allows AI agents to understand the site structure and directly edit/update content or components
- Multimodal input (like images) from AI can be received directly, automating uploads to the media folder and placement on pages
- AI instructions are managed as components too — hand DESIGN.md or BRAND.md to AI, or have AI generate them for you
- Simple specifications mean it's easy to have AI add new functionality

### MCP Support

mikanBox supports the Model Context Protocol (MCP), providing a native connection for AI agents to safely operate on server files. This lets page creation, design changes, and component building all happen through conversation with AI alone, without the user ever touching the admin panel.

Starting with mikanBox 2.5, the server supports only the stable MCP 2026-07-28 specification. Connect from an up-to-date AI app using native Remote MCP (Streamable HTTP). The legacy `mcp-remote` bridge is not supported.

When several mikanBox sites are connected at once, the `get_site_info` tool returns the site's immutable ID, name, public URL, and environment (production, staging, development, or local). AI can verify the target before write operations, reducing the risk of editing the wrong site.

---

## Data Embedding & API

### Loading Other Pages or External Markdown

You can load other pages or Markdown files from external sites like GitHub. This is handy for embedding frequently updated sections (like news) inside otherwise complex pages.

### Embed Data in a Page

```
{{DATA:price:GHOST}}4800{{/DATA}}
```

Reference from the same page: `{{POST_MD::price}}`
Reference from another page: `{{POST_MD:pageID:price}}`

### Table-style Data (DB Less DB)

```
{{DATAROW:row1}}
  {{DATA:name}}Product A{{/DATA}}
  {{DATA:price}}4800{{/DATA}}
{{/DATAROW}}
```

Reference: `{{POST_MD:pageID#row1:name}}`

### Publish as an API

Set a page's status to **DB** to expose its data externally as a JSON API. It can also be used as a headless CMS.

```
https://yoursite.com/api/pageID
```

### CSV Import

The site menu includes a built-in feature to bulk-convert CSV files (from Excel, etc.) into `{{DATAROW}}` format.

---

## Podcast Distribution

Set a page's category to `podcast` and embed an audio file to auto-generate an RSS feed at:

```
https://yoursite.com/podcast.xml
```

Then simply submit that feed to Apple Podcasts, Spotify, Amazon Music, or other platforms.

---

## Sitemap · RSS

| URL | Content |
| :---| :---|
| `/sitemap.xml` | XML Sitemap |
| `/rss.xml`     | RSS Feed |
| `/podcast.xml` | Podcast RSS (podcast category only) |

---

## Security

- All DB operations use prepared statements, preventing SQL injection
- Small codebase with no dependency on plugins
- By operating locally and uploading static files, you can keep PHP and JSON files out of the public directory, minimizing tampering risk
- Renaming the admin directory makes the URL harder to guess
- `.htaccess` restricts direct access to management files

---

## Documentation

- [日本語ヘルプ →](https://yoshihiko.com/mikanbox/help_ja.html)
- [English Help →](https://yoshihiko.com/mikanbox/help_en.html)

---

## License

MIT License — Copyright (c) 2026 [yoshihiko.com](http://yoshihiko.com)
