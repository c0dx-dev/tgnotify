# c0dx: Telegram Notify Enhanced

[Русская версия](README.ru.md)

A WordPress plugin for sending WooCommerce order notifications and Contact Form 7 submissions to Telegram.

Current plugin version: **2.3.0**.

The plugin supports multiple named recipients, configurable message templates, HTML and MarkdownV2 formatting, background delivery, retries, test sends and compact diagnostics.

## Project status

Version 2.3.0 is the current public working baseline. The recipient interface, settings flow and Contact Form 7 test delivery have been verified in practical use.

WooCommerce order delivery and duplicate-protection logic are implemented, but complex repeated status transitions should still be tested against the workflow of each store before production use.

Further changes are made when they are justified by real use, testing or compatibility requirements.

## Features

- Notifications for new WooCommerce orders and order status changes.
- Notifications for Contact Form 7 submissions.
- Multiple Telegram chats, groups or channels with admin-only labels.
- Bulk recipient import with optional `ID | Name` labels.
- Numeric chat IDs and public channels such as `@channelusername`.
- Separate message templates for WooCommerce and Contact Form 7.
- HTML and MarkdownV2 parse modes.
- Background queue with a limited number of retries.
- Failed destinations are retried without resending to successful recipients.
- Test order, Contact Form 7 and custom-message actions.
- Compact queue and error diagnostics.
- WooCommerce HPOS compatibility through the order CRUD API.
- Bot token and recipient IDs may be defined in `wp-config.php`.
- No Composer or npm dependencies.

## Requirements

- WordPress 6.8+
- PHP 8.0+
- WooCommerce 6.0+ for order notifications
- Contact Form 7 for form notifications
- Outbound HTTPS access to `https://api.telegram.org` through the WordPress HTTP API

WooCommerce and Contact Form 7 integrations can be enabled or disabled independently. The plugin does not require both integrations to be installed.

## Tested environment

The current baseline has been manually tested with:

- WordPress 7.0
- PHP 8.4
- MySQL 5.7
- Contact Form 7 test delivery
- successful and failed Telegram recipient checks
- named recipients and bulk recipient import

Other supported WordPress, PHP, database and extension versions may work but have not all been tested in every delivery scenario.

## Installation

### Installable release ZIP

Use the `tgnotify.zip` asset attached to a GitHub Release when one is available. GitHub's automatically generated **Source code** archives are repository snapshots and are not intended to be uploaded directly through the WordPress plugin installer.

### Manual installation from the repository

1. Copy the `tgnotify` directory to `/wp-content/plugins/`.
2. Activate **c0dx: Telegram Notify Enhanced**.
3. Open **Telegram Notify** in the main WordPress admin menu, or use **Settings** on the Plugins screen.
4. Enter the Telegram bot token and add one or more recipients.
5. Configure templates, parse mode and notification types.
6. Run test sends before enabling production notifications.

## Telegram bot token

Create a Telegram bot and obtain its token through [@BotFather](https://t.me/BotFather).

The token can be stored outside the WordPress database:

```php
define( 'TN_TELEGRAM_BOT_TOKEN', '123456:ABC-DEF...' );
```

When `TN_TELEGRAM_BOT_TOKEN` is defined, the token is not displayed in the settings page and is not overwritten when settings are saved.

Never commit a real bot token to a repository or publish it in an issue, screenshot or log.

## Recipients and Chat IDs

Each recipient consists of:

- **Chat ID** — a personal chat, group or channel identifier;
- **Description** — an optional admin-only label.

Examples:

```text
200012345
-1002123456789
@channelusername
```

Numeric group and channel IDs are usually negative and often begin with `-100`.

Bulk import accepts line breaks, commas or semicolons. Labels can be added with the following format:

```text
200012345 | John
-1002123456789 | Work group
@channelusername | Public channel
```

Duplicate recipients are not added again.

Recipient IDs can also be defined in `wp-config.php`:

```php
define( 'TN_TELEGRAM_CHAT_IDS', '-1002123456789,@channelusername' );
```

When `TN_TELEGRAM_CHAT_IDS` is defined, it takes precedence over recipients stored in the database. Saved recipient labels remain available in the admin interface.

## Background queue

When background delivery is enabled, the message is stored temporarily and processed by a separate WP-Cron task.

- Up to three attempts are made for one queued job.
- Delay between attempts increases gradually.
- After a partial failure, only failed Chat IDs are queued again.
- An order notification is marked as processed only after confirmed delivery.
- Expired queue jobs are discarded automatically.
- The queue can be cleared manually from the settings page.

WP-Cron depends on site traffic. Delivery may be delayed on low-traffic websites unless WordPress cron is triggered by the server.

## Formatting

The plugin supports Telegram HTML and MarkdownV2 parse modes.

Dynamic order and form values are escaped automatically. Static MarkdownV2 text entered by the administrator must still follow Telegram's MarkdownV2 escaping rules.

## Diagnostics and privacy

- The settings page shows a short last result or error.
- Temporary diagnostics record queue and delivery events.
- Complete successful Telegram API responses are not stored.
- Message text in the background queue may temporarily contain customer or form data.
- Queue storage is limited to one day; an individual job expires after six hours.
- Data is sent only to the Telegram API required for the plugin's stated purpose.

Review the fields included in notification templates and apply the privacy rules relevant to the website and its jurisdiction.

## Known testing area

Protection against duplicate WooCommerce status notifications remains intentionally conservative. Before production use, test:

- creation of a new order;
- payment-driven `pending → processing` transitions;
- manual status changes;
- returning an order to a previously used status;
- background delivery enabled and disabled;
- recovery after a temporary Telegram API error.

## Documentation

- [Russian documentation](README.ru.md)
- [Changelog](CHANGELOG.md)
- the bundled `tgnotify/about.html` help document used inside the plugin settings

## Reporting issues and contributing

Bug reports and focused improvement proposals are welcome through GitHub Issues. Include:

- plugin, WordPress and PHP versions;
- WooCommerce and Contact Form 7 versions when relevant;
- enabled integration and parse mode;
- background queue state;
- minimal steps to reproduce;
- expected and actual behavior;
- relevant logs with tokens, customer data and private URLs removed.

See [CONTRIBUTING.md](CONTRIBUTING.md) before preparing a pull request.

## Security

Do not publish bot tokens, credentials, private URLs, customer data, database exports or complete production logs in issues. See [SECURITY.md](SECURITY.md) for the reporting policy.

## Repository layout

```text
README.md
README.ru.md
LICENSE
CHANGELOG.md
CONTRIBUTING.md
SECURITY.md
.gitattributes
.gitignore
.github/
  ISSUE_TEMPLATE/
  PULL_REQUEST_TEMPLATE.md
tgnotify/
  .htaccess
  about.html
  telegram_notify_enhanced.php
```

Installable ZIP archives are release artifacts and are not stored in the source tree.

## License

MIT. See [LICENSE](LICENSE).