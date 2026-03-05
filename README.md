# RequestDesk Connector

A WordPress plugin that turns your WordPress site into a headless CMS with a secure REST API. Built for Astro, Next.js, and any SSR frontend framework.

## Features

- **Headless API** - Fetch posts, pages, categories, and site metadata via REST endpoints
- **One-Click API Key** - Generate a secure key from the WordPress admin. No external accounts needed.
- **AI-First Schema Markup** - Automatic schema generation optimized for AI search engines (Article, FAQ, HowTo, Product, LocalBusiness, Video, Course, Breadcrumb)
- **Smart Content Detection** - Auto-detects content type with confidence scoring
- **WooCommerce + LMS Integration** - Product schema for WooCommerce, Course schema for LearnDash/LifterLMS/Tutor LMS
- **RequestDesk Integration** (Optional) - Publish content from RequestDesk.ai and track API request counts

## Quick Start: Headless WordPress with Astro

### 1. Install the Plugin

Upload the `requestdesk-connector` folder to `/wp-content/plugins/` and activate it in WordPress.

### 2. Generate an API Key

Go to **RequestDesk > Headless API** in your WordPress admin and click **Generate New Key**. Copy the key.

No RequestDesk account is required. The headless API works standalone.

### 3. Add the Key to Your Astro Project

Create or update your `.env` file:

```
WORDPRESS_URL=https://your-wordpress-site.com
WORDPRESS_API_KEY=your-generated-key
```

### 4. Fetch Content in Astro

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

### 5. Fetch a Post List

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

## API Endpoints

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

## RequestDesk Integration (Optional)

If you have a RequestDesk.ai account, the plugin can also:

- Receive and publish content from RequestDesk.ai via API
- Track headless API request counts and sync them back to RequestDesk

To enable this, go to **RequestDesk > Settings** and enter your RequestDesk Agent API key. This is completely optional. The headless CMS functionality works without it.

## Requirements

- WordPress 5.0+
- PHP 7.4+

## License

GPL v2 or later
