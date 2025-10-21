# Telegram Notify Enhanced

## 🇷🇺 Русская версия

Расширенный WordPress‑плагин для отправки уведомлений о заказах WooCommerce и заявках Contact Form 7 в Telegram. Поддерживает пользовательские шаблоны, асинхронную очередь и защиту от форматирования сообщений.

### Возможности

- Уведомления о смене статусов заказов и отправках CF7 в один или несколько чатов/каналов
- Шаблоны сообщений с HTML или MarkdownV2, placeholders и подсказками по экранированию
- Асинхронная отправка через очередь с автоматическими ретраями и логированием
- Кнопки тестовой отправки (заказ, CF7, произвольный текст) прямо из админки
- Защищённый модальный просмотр инструкции и примеров

### Требования

- WordPress 6.0+
- PHP 8.0+
- WooCommerce 6.0+ (для заказов)
- Contact Form 7 (для форм)
- Доступ из сервера к `https://api.telegram.org`

### Быстрый старт

1. Установите и активируйте плагин в WordPress
2. Получите токен у @BotFather и добавьте chat ID (поддерживаются несколько ID и @username каналов)
3. Настройте шаблоны и режим парсинга (HTML или MarkdownV2)
4. При необходимости включите асинхронную очередь и протестируйте отправку из раздела «Тестовые действия»

### Отладка

- Блок «Последний ответ Telegram API» фиксирует последний результат отправки
- Лог внизу страницы показывает цепочку действий очереди и ошибок
- Очередь можно очистить вручную, если сообщения зависли

## 🇬🇧 English Version

Enhanced WordPress plugin that delivers WooCommerce order updates and Contact Form 7 submissions to Telegram. Includes custom templating, async queueing, and safe formatting helpers.

### Features

- Status changes and CF7 submissions delivered to multiple chats/channels
- HTML or MarkdownV2 templates with placeholders and escaping hints
- Asynchronous queue with automatic retries and debug logging
- One-click test actions (order, CF7, custom message) from the settings page
- Protected modal window with documentation and examples

### Requirements

- WordPress 6.0+
- PHP 8.0+
- WooCommerce 6.0+ (if order notifications are needed)
- Contact Form 7 (for form submissions)
- Outbound HTTPS access to `https://api.telegram.org`

### Quick Start

1. Install and activate the plugin in WordPress
2. Obtain a bot token from @BotFather and add chat IDs (supports multiple IDs and @username channels)
3. Configure message templates and parse mode (HTML or MarkdownV2)
4. Enable the async queue if desired and send test messages from the “Test actions” section

### Debugging

- The “Last Telegram API response” block stores the latest API result
- The debug log shows queue activity alongside error messages
- You can manually clear the queue if jobs get stuck

## License

MIT License.
