# RequestDesk Connector

A WordPress plugin that adds AI-powered SEO optimization, headless CMS capabilities, and content publishing tools to your WordPress site.

## Features

### AEO/SEO Optimization
- **AI-First Schema Markup** - Automatic schema generation optimized for AI search engines
- **8 Schema Types** - Article, FAQ, HowTo, Product, LocalBusiness, Video, Course, Breadcrumb
- **Smart Content Detection** - Auto-detects content type with confidence scoring
- **Content Freshness Tracking** - Monitors content age and flags stale pages
- **Citation Tracking** - Tracks external references and source attribution
- **Bulk Optimizer** - Analyze and optimize schema across all posts at once
- **Yoast Import** - Migrate existing Yoast SEO data into the RequestDesk schema system

### Content Publishing
- **RequestDesk.ai Integration** - Receive and publish content from RequestDesk.ai via REST API
- **Draft Workflow** - Content arrives as drafts for your review before publishing
- **Category and Tag Support** - Auto-assign categories and tags from the API
- **Sync History** - Track all content syncs with status and timestamps

### Headless CMS
- **REST API for SSR Frontends** - Fetch posts, pages, categories, and site metadata
- **One-Click API Key** - Generate a secure key from WordPress admin. No external accounts needed.
- **Works with Any Framework** - Astro, Next.js, Nuxt, SvelteKit, or any platform that can make HTTP requests

### Page Building Tools
- **Homepage Hero** - Configurable hero section with admin settings
- **Stats Bar** - Animated statistics display with customizable metrics
- **Comparison Table** - Shortcode-driven feature comparison grid
- **Child Page Grid** - Auto-generates grid layouts from child pages
- **Frontend Q&A** - Display question and answer sections on posts
- **Partner Directory** - Custom post type for partner/integration showcases with tier levels and import tools

### AI Integration
- **Claude API Support** - Optional Anthropic Claude integration for content analysis and schema suggestions
- **WooCommerce Integration** - Automatic Product schema for WooCommerce products
- **LMS Integration** - Course schema for LearnDash, LifterLMS, Tutor LMS

## Installation

1. Upload the `requestdesk-connector` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Go to **RequestDesk** in your WordPress admin to configure settings

## Headless API Setup (for SSR Frontends)

If you're using WordPress as a headless CMS with a framework like Astro, follow these steps.

### Prerequisites

Your frontend project needs:

- **Node.js 18+** installed on your development machine and hosting environment
- **SSR mode enabled** (server-side rendering). SSR is required so pages fetch fresh content on each request instead of only at build time.

For Astro, set the output mode in `astro.config.mjs`:

```js
// astro.config.mjs
import { defineConfig } from 'astro/config';
import node from '@astrojs/node';

export default defineConfig({
  output: 'server',
  adapter: node({ mode: 'standalone' }),
});
```

Then install the Node adapter:

```bash
npm install @astrojs/node
```

Your hosting must support Node.js server processes (Vercel, Netlify, AWS, any VPS). Static-only hosts like GitHub Pages will not work with SSR.

### 1. Generate an API Key

Go to **RequestDesk > Headless API** in your WordPress admin and click **Generate New Key**. Copy the key.

No RequestDesk account is required. The headless API works standalone.

### 2. Add the Key to Your Frontend

Create or update your `.env` file:

```
WORDPRESS_URL=https://your-wordpress-site.com
WORDPRESS_API_KEY=your-generated-key
```

### 3. Fetch Content

**Astro example (single post):**

```astro
---
// src/pages/blog/[slug].astro
const { slug } = Astro.params;

const response = await fetch(
  `${import.meta.env.WORDPRESS_URL}/wp-json/requestdesk/v1/headless/posts/${slug}`,
  { headers: { 'X-WP-Headless-Key': import.meta.env.WORDPRESS_API_KEY } }
);
const { post } = await response.json();
---

<h1>{post.title}</h1>
<div set:html={post.content} />
```

**Astro example (post list):**

```astro
---
// src/pages/blog/index.astro
const response = await fetch(
  `${import.meta.env.WORDPRESS_URL}/wp-json/requestdesk/v1/headless/posts?per_page=10`,
  { headers: { 'X-WP-Headless-Key': import.meta.env.WORDPRESS_API_KEY } }
);
const { posts, total, pages } = await response.json();
---

{posts.map(post => (
  <article>
    <a href={`/blog/${post.slug}`}>{post.title}</a>
    <p>{post.excerpt}</p>
  </article>
))}
```

## Headless API Reference

All endpoints require authentication via one of these methods:

- Header: `X-WP-Headless-Key: your-api-key`
- Header: `Authorization: Bearer your-api-key`
- Query param: `?api_key=your-api-key`

### Posts

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/requestdesk/v1/headless/posts` | List posts |
| GET | `/wp-json/requestdesk/v1/headless/posts/{slug}` | Get single post by slug |

**Query parameters for listing posts:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | integer | 1 | Page number |
| `per_page` | integer | 10 | Posts per page (max 50) |
| `category` | string | | Filter by category slug |
| `tag` | string | | Filter by tag slug |
| `search` | string | | Search posts |
| `orderby` | string | date | Sort by: `date`, `modified`, `title` |
| `order` | string | DESC | Sort direction: `ASC`, `DESC` |

### Pages

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/requestdesk/v1/headless/pages` | List pages |
| GET | `/wp-json/requestdesk/v1/headless/pages/{slug}` | Get single page by slug |

**Query parameters for listing pages:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `page` | integer | 1 | Page number |
| `per_page` | integer | 50 | Pages per page (max 100) |
| `parent` | integer | | Filter by parent page ID (0 for top-level) |

### Site Metadata

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/requestdesk/v1/headless/site` | Site name, description, categories, menus |

## RequestDesk.ai Integration (Optional)

If you have a RequestDesk.ai account, the plugin can also:

- Receive and publish content from RequestDesk.ai via API
- Track headless API request counts and sync them back to RequestDesk

To enable this, go to **RequestDesk > Settings** and enter your RequestDesk Agent API key. This is completely optional. All other features work without it.

## Requirements

**WordPress side:**
- WordPress 5.0+
- PHP 7.4+

**Frontend side (headless API only):**
- Node.js 18+
- SSR-capable framework (Astro, Next.js, Nuxt, SvelteKit, etc.)
- Hosting that runs Node.js

## License

GPL v2 or later
