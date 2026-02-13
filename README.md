# Wireservice

A WordPress plugin that publishes your posts and pages to the [AT Protocol](https://atproto.com) using the [standard.site](https://standard.site) lexicons (`site.standard.publication` and `site.standard.document`).

## Requirements

- PHP 8.4+
- WordPress 6.7+
- Composer

## Installation

1. Clone or download this repository into your `wp-content/plugins/` directory.
2. Install dependencies:
   ```bash
   composer install
   ```
3. Activate the plugin in WordPress under **Plugins**.

## Setup

1. Go to **Settings > Wireservice**.
2. Connect your AT Protocol account via the OAuth flow.
3. Configure your **Publication** settings (name, description, icon, theme colors) and sync to AT Protocol.
4. Enable **Document** syncing and configure how post titles, descriptions, and cover images are sourced.

## How It Works

### Publication

Your WordPress site is represented as a `site.standard.publication` record on AT Protocol. The plugin syncs site-level metadata including:

- **Name** &mdash; from WordPress site title, Yoast SEO, or a custom value
- **Description** &mdash; from WordPress tagline, Yoast SEO, or a custom value
- **Icon** &mdash; from WordPress site icon or a custom upload
- **Theme colors** &mdash; background, foreground, accent, and accent foreground
- **Discoverability** &mdash; opt in or out of discovery feeds

The plugin also serves a `.well-known/site.standard.publication` endpoint that returns the AT-URI of your publication record.

### Documents

When document syncing is enabled, published posts and pages are automatically synced as `site.standard.document` records. Each document includes:

- Title and description (configurable source)
- Cover image (featured image, Yoast SEO image, or custom)
- Publication date and last-updated date
- Relative path (permalink)
- Tags and categories
- Optionally, full plain-text content

Documents are created on publish, updated on edit, and deleted when a post is trashed, deleted, or unpublished.

A `<link rel="site.standard.document">` tag is added to the `<head>` of each synced post for verification.

### Per-Post Overrides

A **Wireservice** meta box appears on the post editor, allowing per-post overrides for:

- Title source
- Description source
- Cover image source
- Whether to include full content

## Yoast SEO Integration

When Yoast SEO is active, additional source options become available for both publication and document settings:

- **Publication**: Yoast organization name, website name, homepage meta description
- **Documents**: Yoast SEO title, social title, X title, meta description, social description, X description, social image, X image

## Filters

```php
// Customize which post types are synced (default: post, page)
add_filter('wireservice_syncable_post_types', function ($types) {
    $types[] = 'custom_post_type';
    return $types;
});

// Control whether a specific post should sync
add_filter('wireservice_should_sync_post', function ($should_sync, $post) {
    return $should_sync;
}, 10, 2);
```

## License

[AGPL-3.0-or-later](LICENSE.md)
