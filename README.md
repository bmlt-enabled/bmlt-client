# Crumb for WordPress

WordPress plugin that embeds the [Crumb Widget](https://crumb.bmlt.app/) meeting finder widget via shortcode.

## Usage

```
[crumb]
```

Override settings per page:

```
[crumb root_server="https://your-server/main_server" service_body="42"]
```

## Installation

1. Upload to `/wp-content/plugins/crumb/`
2. Activate in WordPress admin
3. Go to **Settings → Crumb** and enter your root server URL
4. Add `[crumb]` to any page or post

## Settings

Configured under **Settings → Crumb**. All settings can be overridden per-shortcode via attributes.

| Setting          | Shortcode Attribute | Description                                 |
|------------------|---------------------|---------------------------------------------|
| Root Server URL  | `root_server`       | Required. Full URL to your BMLT root server |
| Service Body IDs | `service_body`      | Optional. Single ID or comma-separated list |

Full documentation at **[crumb.bmlt.app](https://crumb.bmlt.app/)**.
