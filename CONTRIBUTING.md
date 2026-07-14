# Contributing

Contributions, reproducible bug reports and focused improvement proposals are welcome.

The project is maintained as a practical WordPress plugin with minimal dependencies. Changes should preserve that scope and avoid unnecessary architectural complexity.

## Before opening an issue

Check the current documentation and existing issues first. Include enough information to reproduce the problem:

- plugin version;
- WordPress and PHP versions;
- WooCommerce and Contact Form 7 versions when relevant;
- enabled integration;
- HTML or MarkdownV2 parse mode;
- background queue enabled or disabled;
- recipient type: private chat, group, channel or `@channelusername`;
- minimal steps to reproduce;
- expected and actual behavior;
- relevant PHP or plugin diagnostics with tokens and private data removed.

Issues may be written in English or Russian.

## Pull requests

Keep pull requests focused on one problem or improvement. Large refactors should be discussed in an issue before implementation.

Please preserve the following constraints:

- PHP 8.0+ compatibility;
- WordPress 6.8+ compatibility;
- WooCommerce 6.0+ compatibility for the order integration;
- the existing `TN_Telegram_Notify_Enhanced`, `tn_enh_` and `telegram-notify` identifiers unless a migration is explicitly planned;
- no Composer or npm dependencies without prior discussion;
- no custom database tables unless there is a demonstrated need;
- WordPress HTTP API for Telegram requests;
- WooCommerce CRUD APIs for order metadata when available;
- capability checks, nonces, sanitization and output escaping for administrative actions;
- safe handling of Bot Tokens, Chat IDs, order data and Contact Form 7 submissions;
- English and Russian documentation updates when behavior changes.

Do not change the plugin version or prepare release archives in a pull request unless explicitly requested. Release versioning and packaging are handled by the maintainers.

## Testing

At minimum, check:

1. PHP syntax with `php -l`.
2. Plugin activation on a clean WordPress installation.
3. Saving settings without exposing or overwriting a Bot Token defined in `wp-config.php`.
4. Adding, removing and bulk-importing recipients.
5. A successful Telegram test send.
6. An invalid Chat ID or another controlled Telegram API error.
7. Contact Form 7 delivery when that integration is affected.
8. WooCommerce order creation and status changes when that integration is affected.
9. Background queue behavior when queue code is affected.
10. HTML and MarkdownV2 output when formatting code is affected.
11. No PHP warnings and no unrelated data in logs or diagnostics.

Never use real customer data, production Bot Tokens or private chat identifiers in public test fixtures, commits, issues or pull requests.

## License

By contributing, you agree that your contribution may be distributed under the repository's MIT License.