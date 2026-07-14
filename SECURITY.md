# Security Policy

## Supported versions

Security fixes are applied to the latest published release in the current major version.

| Version | Supported |
| --- | --- |
| 2.3.x | Yes |
| 2.2.x and earlier | No |

## Reporting a vulnerability

Do not publish Bot Tokens, credentials, private URLs, customer data, Contact Form 7 submissions, order details, database exports or complete production logs in a public issue.

When GitHub private vulnerability reporting is available for this repository, use **Security → Report a vulnerability**.

If private reporting is not available, open a public issue containing only a minimal, non-sensitive summary and request a private communication channel. Do not include exploit details, secrets or production data in that issue.

A useful report should include:

- affected plugin version;
- WordPress and PHP versions;
- WooCommerce or Contact Form 7 versions when relevant;
- affected feature or request path;
- reproducible steps using non-sensitive sample data;
- expected security impact;
- any suggested mitigation.

## Sensitive data handled by the plugin

The plugin may process or temporarily store:

- Telegram Bot Tokens;
- Chat IDs and channel usernames;
- WooCommerce order and customer data;
- Contact Form 7 submission fields;
- queued notification text;
- short delivery diagnostics.

Reports and test cases must use synthetic data. Remove tokens, personal data, private URLs and unrelated log entries before sharing diagnostic information.

Reports will be reviewed as time permits. Confirmed vulnerabilities will be fixed in the current supported release line and documented without exposing unnecessary operational details.