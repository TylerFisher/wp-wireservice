# Wireservice

A WordPress plugin that publishes your posts and pages to the [AT Protocol](https://atproto.com) using the [standard.site](https://standard.site) lexicons (`site.standard.publication` and `site.standard.document`).

## Requirements

- PHP 8.3+
- WordPress 6.4+
- Composer

## Dev Installation

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
- **Theme colors** &mdash; background, foreground, accent, and accent foreground. NOTE: these are used by other ATProto platforms to style your content, not on your WordPress site.
- **Discoverability** &mdash; opt in or out of discovery feeds. NOTE: these are used by other ATProto platforms to show your publication in algorithmic feeds, not on your WordPress site.

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

Note that Wireservice does not have a content lexicon yet. This is in development.

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

## Self-Hosting the OAuth Service

Wireservice authenticates with AT Protocol through an external OAuth service. By default it uses `https://aip.wireservice.net`, but you can run your own instance using [AIP](https://github.com/graze-social/aip), a high-performance OAuth 2.1 authorization server with native AT Protocol integration.

### Running AIP

AIP requires Rust 1.87+. To run locally:

1. Generate an OAuth signing key with `goat`: `goat key generate -t p256`. Save the public and private keys somewhere safe.

2. Clone AIP: `git clone https://github.com/graze-social/aip.git`

3. Setup environment variables:

```
EXTERNAL_BASE=https://your-domain.com
DPOP_NONCE_SEED=$(openssl rand -hex 32)
STORAGE_BACKEND=sqlite
ATPROTO_OAUTH_SIGNING_KEYS=`did:key:${YOUR_PRIVATE_KEY}`
OAUTH_SIGNING_KEYS=`did:key:${YOUR_PRIVATE_KEY}`
ENABLE_CLIENT_API=true
OAUTH_SUPPORTED_SCOPES="atproto:atproto atproto:repo:site.standard.publication atproto:repo:site.standard.document
atproto:blob:*/*"
```

4. Run AIP: `cargo run --bin aip`

Or with Docker:

```bash
docker build -t aip .
docker run -p 8080:8080 \
  // all of the above env vars here
  aip
```

For production, use the `postgres` storage backend instead of `sqlite`. Depending on your hosting environment, you may need to manually set the `DNS_NAMESERVERS` env var so that your AIP service can resolve handles properly. (Wireservice uses `8.8.8.8,1.1.1.1`).

### Configuring Wireservice

Once your AIP instance is running, update the OAuth Service URL in WordPress:

1. Go to **Settings > Wireservice**.
2. Set the **OAuth Service URL** to your AIP instance (e.g., `https://your-domain.com`).

This is stored as the `wireservice_oauth_url` option and can also be set programmatically:

```php
update_option('wireservice_oauth_url', 'https://your-domain.com');
```

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

[AGPL 3.0](LICENSE.md)
