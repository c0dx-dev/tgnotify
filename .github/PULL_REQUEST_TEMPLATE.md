## Summary

Describe the problem and the focused change made to solve it.

## Affected areas

- [ ] WooCommerce notifications
- [ ] Contact Form 7 notifications
- [ ] Recipients or Chat IDs
- [ ] Message templates or Telegram formatting
- [ ] Background queue or retries
- [ ] Admin interface or diagnostics
- [ ] Compatibility
- [ ] Documentation only

## Testing

- [ ] PHP syntax checked with `php -l`.
- [ ] Plugin activates without PHP warnings.
- [ ] Existing settings remain readable.
- [ ] Bot Tokens and private data are not exposed in the diff, screenshots or logs.
- [ ] A successful Telegram test send was checked when delivery code changed.
- [ ] A controlled error case was checked when delivery or retry code changed.
- [ ] WooCommerce behavior was checked when order code changed.
- [ ] Contact Form 7 behavior was checked when form code changed.
- [ ] Background queue behavior was checked when queue code changed.
- [ ] HTML and MarkdownV2 behavior was checked when formatting code changed.
- [ ] English and Russian documentation were updated when behavior changed.

## Compatibility

Confirm that the change preserves:

- [ ] PHP 8.0+
- [ ] WordPress 6.8+
- [ ] WooCommerce 6.0+ where relevant
- [ ] existing options, constants and metadata, or includes an explicit migration
- [ ] no new Composer, npm or external-service dependency without prior discussion

## Additional notes

Include any known limitations, migration considerations or follow-up testing.