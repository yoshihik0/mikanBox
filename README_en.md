# 🍊mikanBox

**AI-era, parts-assembly, ultra-lightweight CMS**

🍊mikanBox is a file-based CMS designed to build and operate small-to-medium websites (a few to a few dozen pages) in the fastest and most secure way possible. No database required. It works simply by placing it on a PHP-enabled server.

---

## Features

- **File-based (JSON)** — No database required. Just place on a PHP-enabled server
- **Modeless UI** — No page transitions; all work completed on a single screen for a snappy experience
- **Markdown Support** — Easily edit and reuse content
- **Component Structure** — Reusable and reorderable building blocks (parts)
- **Per-component Scoped CSS** — Write CSS in small scopes without worrying about interference
- **AI-generated Code Works As-Is** — No manual design work needed
- **AI Agent Integration (MCP)** — AI understands the site structure and directly reads/writes files
- **Multimodal AI Support** — AI generates images and sends/places them directly in the media folder
- **Static (SSG), Dynamic, or Mixed** — Can be published as a high-speed static site
- **DB Less DB** — Embed data in pages and output via API. Can also be used as a headless CMS
- **Podcast** — Auto-generate RSS for podcast distribution

---

## Demo

[https://yoshihiko.com/mikanbox/demo/](https://yoshihiko.com/mikanbox/demo/)

---

## Requirements

- A web server with PHP support (no database required)

---

## Installation

1. Upload the `mikanBox` folder and `index.php` to your server
2. For security, we recommend renaming the `mikanBox` folder to a name of your choice (if renamed, also update the `$core_dir` variable at the top of `index.php` to match)
3. Access `mikanBox/admin.php` to set your admin password

That's all.

---

## Basic Usage

### Two Operating Styles

**Continuous Content** (blogs, service pages, etc.)

- Write in Markdown and reference images by filename only
- Well-suited for ongoing updates and content reuse

**Short-term Pages** (landing pages, event pages, etc.)

- Paste AI-generated HTML/CSS/JS directly to publish
- Elimination of manual design work

### Design (Components)

- **Page Components** — Wrappers that define the overall layout of the page
- **Parts Components** — Reusable parts embedded in pages or other components

Components contain HTML and scoped CSS, and can be nested.

### Static Site Generation (SSG)

Export all pages as static HTML with a single click. You can also mix static and dynamic pages.

### Page Publish Status

| Status | Behavior |
| :------- | :------- |
| Draft | Private (admin only) |
| Public (Dynamic) | Served dynamically via PHP |
| Public (Static) | Exported as static HTML |
| DB | Page itself is private; exposes data as an API |

---

## AI Integration

🍊mikanBox is designed with a strong focus on compatibility with AI tools.

- AI-generated HTML pages can be pasted directly into the content field for instant publishing
- The codebase is compact and simply structured, making it easy for AI to understand the specifications
- MCP support allows AI agents to understand the site structure and directly edit/update contents or components
- Multimodal input (like images) from AI can be directly received, automating uploads to the media folder and placement on pages
- Simple specifications mean AI can be tasked to create custom designs and plugin-like components without needing detailed explanations

### MCP Support

mikanBox supports the Model Context Protocol (MCP), providing a bridge for AI agents to safely interact with server files. This allows for page creation, design adjustments, and component building through AI conversation alone, without the user ever needing to touch the admin panel.

---

## Data Embedding & API

### Loading Other Pages or External Markdown

You can load other pages or Markdown files from external sites like GitHub. This is useful for embedding frequently updated sections (like news) into complex pages.

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

Set a page's status to **DB** to expose its data externally as a JSON API.

```
https://yoursite.com/api/pageID
```

### CSV Import

The site menu includes a built-in feature to convert CSV files (from Excel, etc.) into `{{DATAROW}}` format in bulk.

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
| `/podcast.xml` | Podcast RSS (Includes only "podcast" category) |

---

## Security

- Since no database is used, there is no attack surface for SQL injection
- Small codebase with no dependency on plugins
- By operating locally and uploading static files, you can keep PHP and JSON files out of the public directory, minimizing tampering risks
- Renaming the admin directory makes the URL harder to guess
- `.htaccess` restricts direct access to management files

---

## Intended Use & Scale

**Ideal for:** Personal sites, small business/corporate sites, event pages, and portfolios (Guideline: up to 50 pages)

---

## Documentation

- [日本語ヘルプ](https://yoshihiko.com/mikanbox/help_ja.html)
- [English Help](https://yoshihiko.com/mikanbox/help_en.html)

---

## License

MIT License — Copyright (c) 2026 [yoshihiko.com](http://yoshihiko.com)
