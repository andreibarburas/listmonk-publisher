# WP Listmonk Publisher

A WordPress plugin that automatically creates and sends a [listmonk](https://listmonk.app) campaign whenever a new post is published. Each campaign includes the featured image, post title, opening excerpt, and a read-more link back to your site.

## Features

- Automatically fires on post publish — no manual steps
- Sends via your self-hosted listmonk instance using the API
- Configurable send mode: immediate or draft (review before sending)
- Supports listmonk templates, custom from address, and multiple subscriber lists
- **Category filter**: restrict newsletter triggers to one or more specific categories
- Built-in test email flow: send a real campaign to a private test list before going live
- Activity log in the settings page for easy debugging
- Automatic updates via GitHub Releases

## Requirements

- WordPress 6.0 or later
- PHP 8.0 or later
- A running [listmonk](https://listmonk.app) instance with API access

## Installation

1. Go to the [Releases](https://github.com/andreibarburas/wp-listmonk-publisher/releases) page and download the latest `.zip` file.
2. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip and click **Install Now**, then **Activate**.
4. Go to **Settings → Listmonk Publisher** to configure the plugin.

After the first install, updates will appear automatically in your WordPress dashboard.

## Configuration

Go to **Settings → Listmonk Publisher** and fill in:

- **Server URL** — your listmonk instance URL, e.g. `https://listmonk.yoursite.com`
- **API username** and **API token** — create an API user under **Admin → Users** in listmonk
- **List ID(s)** — comma-separated IDs of the subscriber lists to send to
- **Template ID** — optional; the ID of a listmonk template to wrap the content
- **From email** — optional; leave blank to use listmonk's default sender
- **Send mode** — immediate (sends right away) or draft (creates the campaign for manual review)

Click **Test connection** to verify your credentials before enabling.

## Test emails

Set a **Test list ID** pointing to a private listmonk list with only yourself as a subscriber. Click **Send test email** to send a real campaign using your most recently published post — same template, same pipeline as a live send.

## Notes on listmonk display names

Listmonk rejects display names in the `From email` field that contain dots. Use a plain word before the `<`, e.g. `Newsletter <hello@yoursite.com>` rather than `my.newsletter <hello@yoursite.com>`.

## Changelog

### 1.1.0
- Added category filter in Campaign Settings — select one or more categories to restrict which posts trigger a newsletter. Leave all unchecked to send for every category (previous behaviour).

### 1.0.0
- Initial release

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) for details.
