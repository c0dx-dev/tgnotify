# c0dx: Telegram Notify Enhanced

WordPress plugin for sending WooCommerce order notifications and Contact Form 7 submissions to Telegram.

Current plugin version: **2.2.0**.

## 🇷🇺 Русская версия

Плагин отправляет уведомления о заказах WooCommerce и заявках Contact Form 7 в один или несколько Telegram-чатов или каналов. Поддерживает пользовательские шаблоны, HTML и MarkdownV2, фоновую очередь, повторные попытки и тестовые отправки из админки WordPress.

### Возможности

- Уведомления о новых заказах и смене статусов WooCommerce.
- Уведомления об отправках Contact Form 7.
- Отправка в несколько chat ID или публичных каналов вида `@channelusername`.
- Шаблоны сообщений с плейсхолдерами.
- Режимы форматирования HTML и MarkdownV2.
- Асинхронная очередь с ограниченным числом повторных попыток.
- При частичной ошибке повторная отправка выполняется только в неудачные чаты.
- Тестовые отправки заказа, CF7 и произвольного сообщения из настроек.
- Краткий журнал очереди и ошибок без сохранения полного ответа Telegram API.
- Поддержка WooCommerce HPOS через CRUD API заказов.
- Возможность задать токен и chat ID через константы в `wp-config.php`.
- Встроенная инструкция `about.html`, доступная из настроек плагина.

### Требования и целевая совместимость

- WordPress 6.8–7.0+.
- PHP 8.0+.
- WooCommerce 6.0+ — для уведомлений о заказах.
- Contact Form 7 — для уведомлений о формах.
- Исходящий HTTPS-доступ к `https://api.telegram.org` через WordPress HTTP API.

Совместимость с конкретной конфигурацией рекомендуется проверять на тестовой копии сайта перед обновлением рабочего сайта.

### Установка

1. Скопируйте папку `tgnotify2` в `/wp-content/plugins/` или установите подготовленный ZIP-архив.
2. Активируйте **c0dx: Telegram Notify Enhanced** в разделе плагинов WordPress.
3. Откройте **Настройки → Telegram Notify**.
4. Укажите Bot Token и один или несколько chat ID.
5. Настройте шаблоны, режим форматирования и нужные типы уведомлений.
6. Выполните тестовые отправки перед включением на рабочем сайте.

### Токен и chat ID через `wp-config.php`

Для хранения настроек вне базы WordPress можно использовать:

```php
define( 'TN_TELEGRAM_BOT_TOKEN', '123456:ABC-DEF...' );
define( 'TN_TELEGRAM_CHAT_IDS', '-1001234567890,@channelusername' );
```

Если токен задан через `TN_TELEGRAM_BOT_TOKEN`, он не выводится в интерфейсе и не перезаписывается при сохранении настроек.

### Асинхронная очередь

При включённой фоновой отправке сообщение сначала сохраняется в очереди WordPress и отправляется отдельным заданием WP-Cron.

- Максимум три попытки для одной задачи.
- Интервал между попытками постепенно увеличивается.
- После частичной ошибки в очередь возвращаются только неудачные chat ID.
- Заказ помечается обработанным только после подтверждённой отправки.
- Старые задания автоматически удаляются по истечении срока хранения.

WP-Cron зависит от посещений сайта. На малопосещаемых сайтах отправка фоновых сообщений может происходить с задержкой.

### Форматирование

Плагин поддерживает HTML и MarkdownV2.

Динамические данные заказа и формы экранируются автоматически. Статический текст пользовательского MarkdownV2-шаблона должен быть экранирован автором шаблона в соответствии с правилами Telegram.

### Отладка

- В настройках отображается последняя краткая ошибка отправки.
- Временный журнал показывает работу очереди и диагностические сообщения.
- Полный успешный JSON-ответ Telegram в базе не хранится.
- Очередь можно очистить вручную.

### Конфиденциальность

Для работы фоновой очереди текст сообщения временно сохраняется в WordPress transient. Сообщение может содержать данные заказа или формы: имя, телефон, email и другие поля. По умолчанию очередь хранится не более суток, а отдельное задание — не более шести часов.

Плагин не отправляет данные сторонним сервисам, кроме Telegram API, указанного в его назначении.

### Известный вопрос для дальнейшего тестирования

Логика защиты от повторных уведомлений о статусах сохранена без архитектурного изменения. Перед публичным релизом требуется дополнительное тестирование повторных переходов заказа между одинаковыми статусами.

## 🇬🇧 English version

The plugin sends WooCommerce order notifications and Contact Form 7 submissions to one or more Telegram chats or channels. It supports custom templates, HTML and MarkdownV2 formatting, background queue processing, retries, and test actions in the WordPress admin area.

### Features

- New order and order status notifications for WooCommerce.
- Contact Form 7 submission notifications.
- Multiple chat IDs and public channels such as `@channelusername`.
- Message templates with placeholders.
- HTML and MarkdownV2 parse modes.
- Asynchronous queue with a limited number of retries.
- Failed destinations are retried without resending to successful chats.
- Test order, CF7 and custom message actions from the settings page.
- Compact error and queue diagnostics without storing complete Telegram API responses.
- WooCommerce HPOS support through the order CRUD API.
- Bot token and chat IDs may be defined in `wp-config.php`.
- Bundled `about.html` documentation displayed from the plugin settings.

### Requirements and compatibility target

- WordPress 6.8–7.0+.
- PHP 8.0+.
- WooCommerce 6.0+ for order notifications.
- Contact Form 7 for form notifications.
- Outbound HTTPS access to `https://api.telegram.org` through the WordPress HTTP API.

Test the plugin on a staging copy before updating a production site.

### Installation

1. Copy the `tgnotify2` directory to `/wp-content/plugins/` or install a prepared ZIP package.
2. Activate **c0dx: Telegram Notify Enhanced**.
3. Open **Settings → Telegram Notify**.
4. Enter the bot token and one or more chat IDs.
5. Configure templates, parse mode and notification types.
6. Run the test actions before enabling production notifications.

### Configuration constants

```php
define( 'TN_TELEGRAM_BOT_TOKEN', '123456:ABC-DEF...' );
define( 'TN_TELEGRAM_CHAT_IDS', '-1001234567890,@channelusername' );
```

When `TN_TELEGRAM_BOT_TOKEN` is defined, the token is not exposed in the settings page and is not overwritten when the form is saved.

### Async queue

- Up to three attempts per queued job.
- Increasing delay between attempts.
- Only failed chat IDs are retried after a partial failure.
- Order notifications are marked as processed only after confirmed delivery.
- Expired queue jobs are discarded automatically.

WP-Cron depends on site traffic, so background delivery may be delayed on low-traffic sites.

### Formatting

Dynamic order and form values are escaped automatically. Static MarkdownV2 template text must still follow Telegram escaping rules.

### Debugging and privacy

The plugin stores only a short last-error message and temporary diagnostic entries, not the complete successful Telegram response.

Queued message text is temporarily stored in a WordPress transient and may contain customer or form data. The queue lifetime is limited to one day, while individual jobs expire after six hours.

## Disclaimer

This plugin is provided as is, without any warranty. Test it on a staging site before production use. The authors and maintainers are not responsible for errors, data loss, downtime or other damage caused by using the plugin.

## License

MIT. See the `LICENSE` file when it is added to the repository.
