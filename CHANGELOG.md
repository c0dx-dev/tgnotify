# Changelog

All notable changes to this project are documented in this file.

## 2.3.0

- Moved the settings page to a top-level WordPress admin menu.
- Added a Settings link on the Plugins screen.
- Added a direct link to @BotFather.
- Replaced the plain Chat ID list with named recipient rows.
- Added recipient row creation and deletion with confirmation.
- Added bulk Chat ID import with optional `ID | Name` labels.
- Preserved compatibility with legacy Chat ID storage and `TN_TELEGRAM_CHAT_IDS`.
- Expanded Chat ID guidance and bundled documentation.

## 2.2.0

- Fixed Telegram delivery error handling and queue retries.
- Retried only failed destinations after partial delivery errors.
- Marked order notifications as processed only after confirmed delivery.
- Added WooCommerce HPOS compatibility through the order CRUD API.
- Removed the runtime dependency on PHP `mbstring`.
- Improved Bot Token handling when configured through `wp-config.php`.
- Stopped storing complete successful Telegram API responses.
- Updated CF7 field filtering, MarkdownV2 escaping and bundled documentation.

## 2.1.1

- Public baseline before the delivery, compatibility and admin-interface updates.
