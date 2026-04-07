# 🍊mikanBox

**AI-era, parts-assembly, ultra-lightweight CMS**

🍊mikanBox is a file-based CMS designed to build and operate small-to-medium websites (a few to a few dozen pages) in the fastest and most secure way possible. No database required — just upload to any PHP-enabled server and you're ready to go.

---

## Features

- **File-based (JSON)** — No database required. Upload to any PHP-enabled server
- **Modeless UI** — No page transitions; all work done on a single screen for a snappy experience
- **Markdown support** — Easily edit and reuse content
- **Component structure** — Reusable and reorderable building blocks
- **Per-component scoped CSS** — Write CSS in small scopes without worrying about interference
- **AI-generated code works as-is** — No manual design work needed
- **Static (SSG), dynamic, or mixed** — Can be published as a fast static site
- **DB Less DB** — Embed data in pages and output via API for headless CMS use
- **Podcast** — Auto-generate RSS for podcast distribution
- **MCP support** — Connect with AI tools like Claude via MCP for automated operations

---

## Demo

[https://yoshihiko.com/mikanbox/demo/](https://yoshihiko.com/mikanbox/demo/)

---

## Requirements

- A web server with PHP support (no database required)

---

## Installation

1. Upload the `mikanBox` folder and `index.php` to your server
2. For security, it is recommended to rename the `mikanBox` folder to something unique (if renamed, also update `$core_dir` at the top of `index.php` to match)
3. Access `mikanBox/admin.php` to set your admin password

That's all.

---

## Basic Usage

### Two Operating Styles

**Ongoing content** (blogs, service pages, etc.)

- Write in Markdown and reference images by filename only
- Well-suited for continuous updates and content reuse

**Short-term pages** (landing pages, event pages, etc.)

- Paste AI-generated HTML/CSS/JS directly into the content field and publish
- No manual design work needed

### Design (Components)

- **Page components** — Wrappers that define the overall page layout
- **Parts components** — Reusable parts embedded in pages or other components

Components have HTML and scoped CSS, and can be nested.

### Static Site Generation (SSG)

Export all pages as static HTML with a single click. Static and dynamic pages can coexist.

### Page Publish Status

| Status | Behavior |
| :--- | :--- |
| Draft | Private (admin only) |
| Public (Dynamic) | Served dynamically via PHP |
| Public (Static) | Exported as static HTML |
| DB | Page itself is private; `{{DATAROW}}` data is published as an API |

---

## AI Integration

🍊mikanBox is designed with AI tools in mind.

- AI-generated HTML pages can be pasted directly into the content field and published
- Using `{{EXT_MD:url}}` to load external Markdown files (e.g., from GitHub), you can keep your site updated simply by "instructing an AI agent to update the repository" — without ever opening the admin panel
- The codebase is compact and simply structured, making it easy for AI to understand the specifications
- Because AI can easily understand the specs, you can have it create custom designs with no explanation needed
- New features can be added easily by having AI rewrite the source — no plugins required
- **MCP (Model Context Protocol) support** — AI tools like Claude can directly operate mikanBox to create and update pages, manage components, and build the static site

---

## Data Embedding & API

### Embed Data in a Page

```
{{DATA:price:GHOST}}4800{{/DATA}}
```

Reference from same page: `{{POST_MD::price}}`
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

Set a page's status to **DB** to expose its `{{DATAROW}}` data as a public JSON API.

```
https://yoursite.com/api/pageID
```

### Load External Markdown

Load Markdown files from GitHub or other external sources.

```
{{EXT_MD:https://raw.githubusercontent.com/user/repo/main/file.md}}
```

### CSV Import

A built-in tool in the Site menu converts CSV files (from Excel, etc.) into `{{DATAROW}}` format in bulk.

---

## Podcast Distribution

Set a page's category to `podcast` and embed an audio file to auto-generate an RSS feed at:

```
https://yoursite.com/podcast.xml
```

Required field:

```
{{DATA:AUDIO_FILE:GHOST}}episode01.mp3{{/DATA}}
```

Then simply submit that feed to Apple Podcasts, Spotify, Amazon Music, and others.

---

## Sitemap · RSS

| URL | Content |
| :--- | :--- |
| `/sitemap.xml` | XML sitemap |
| `/rss.xml` | RSS feed |
| `/podcast.xml` | Podcast RSS (podcast category only) |

---

## Security

- No database means no SQL injection attack surface
- Small codebase with no plugin dependencies
- SSG keeps PHP files and JSON data out of the public directory, minimizing the risk of tampering
- Renaming the admin directory makes the URL harder to guess
- `.htaccess` restricts direct access to admin files

---

## Intended Use & Scale

**Best suited for:** Personal sites, small business or company sites, event pages, portfolios (guideline: up to 50 pages)

---

## Documentation

- [日本語ヘルプ](https://yoshihiko.com/mikanbox/help_ja.html)
- [English Help](https://yoshihiko.com/mikanbox/help_en.html)

---

## License

MIT License — Copyright (c) 2026 [yoshihiko.com](http://yoshihiko.com)
