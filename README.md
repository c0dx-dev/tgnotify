# c0dx: Telegram Notify Enhanced

WordPress plugin for sending WooCommerce order notifications and Contact Form 7 submissions to Telegram.

Current plugin version: **2.3.0**.

## Русская версия

Плагин отправляет уведомления о заказах WooCommerce и заявках Contact Form 7 в один или несколько Telegram-чатов, групп или каналов. Поддерживает пользовательские шаблоны, HTML и MarkdownV2, фоновую очередь, повторные попытки и тестовые отправки из админки WordPress.

### Возможности

- Уведомления о новых заказах и смене статусов WooCommerce.
- Уведомления об отправках Contact Form 7.
- Несколько получателей с отдельными Chat ID и понятными подписями.
- Массовый ввод Chat ID с преобразованием в отдельные строки.
- Поддержка формата массового ввода `ID | Имя`.
- Поддержка публичных каналов вида `@channelusername`.
- Шаблоны сообщений с плейсхолдерами.
- Режимы форматирования HTML и MarkdownV2.
- Асинхронная очередь с ограниченным числом повторных попыток.
- При частичной ошибке повторная отправка выполняется только в неудачные чаты.
- Тестовые отправки заказа, CF7 и произвольного сообщения.
- Краткий журнал очереди и ошибок без сохранения полного ответа Telegram API.
- Поддержка WooCommerce HPOS через CRUD API заказов.
- Возможность задать токен и Chat ID через константы в `wp-config.php`.

### Требования и целевая совместимость

- WordPress 6.8–7.0+.
- PHP 8.0+.
- WooCommerce 6.0+ — для уведомлений о заказах.
- Contact Form 7 — для уведомлений о формах.
- Исходящий HTTPS-доступ к `https://api.telegram.org` через WordPress HTTP API.

Совместимость с конкретной конфигурацией рекомендуется проверять на тестовой копии сайта перед обновлением рабочего сайта.

### Установка

1. Скопируйте папку `tgnotify2` в `/wp-content/plugins/` или установите подготовленный релизный ZIP-архив.
2. Активируйте **c0dx: Telegram Notify Enhanced** в разделе плагинов WordPress.
3. Откройте пункт **Telegram Notify** в главном меню админки или нажмите **Настройки** в списке плагинов.
4. Укажите Bot Token и добавьте одного или нескольких получателей.
5. Настройте шаблоны, режим форматирования и нужные типы уведомлений.
6. Выполните тестовые отправки перед использованием на рабочем сайте.

### Bot Token

Создать Telegram-бота и получить токен можно через [@BotFather](https://t.me/BotFather).

Для хранения токена вне базы WordPress:

```php
define( 'TN_TELEGRAM_BOT_TOKEN', '123456:ABC-DEF...' );
```

Если токен задан через `TN_TELEGRAM_BOT_TOKEN`, он не выводится в интерфейсе и не перезаписывается при сохранении настроек.

### Получатели и Chat ID

Каждый получатель отображается отдельной строкой:

- **Chat ID** — идентификатор личного чата, группы или канала;
- **Описание** — произвольное имя сотрудника, отдела, группы или канала.

Описание используется только в админке и не передаётся в Telegram.

Примеры:

- личный чат: `200012345`;
- группа или канал: `-1002123456789`;
- публичный канал: `@channelusername`.

Числовые идентификаторы групп и каналов имеют отрицательное значение и часто начинаются с `-100`.

Старый список Chat ID автоматически отображается в новом интерфейсе. После сохранения настроек он сохраняется как набор отдельных получателей.

#### Как узнать Chat ID

Для своего аккаунта можно использовать [@getmyid_bot](https://t.me/getmyid_bot): отправьте боту сообщение и найдите значение `Your user ID`.

Для группы или канала перешлите сообщение боту [@ShowJsonBot](https://t.me/ShowJsonBot) или добавьте его в нужный чат. В полученном JSON найдите объект `chat` и поле `id`.

#### Массовый ввод

В блок массового ввода можно вставить ID, разделённые переносами строк, запятыми или точками с запятой. Дополнительно поддерживается формат:

```text
200012345 | Иван
-1002123456789 | Рабочая группа
```

После нажатия **Преобразовать в строки** значения добавляются в список получателей. Дубликаты не добавляются повторно.

### Chat ID через `wp-config.php`

```php
define( 'TN_TELEGRAM_CHAT_IDS', '-1002123456789,@channelusername' );
```

Если определена константа `TN_TELEGRAM_CHAT_IDS`, при отправке используется список из неё. Получатели, сохранённые в админке, остаются резервной конфигурацией и могут использоваться как подписи.

### Асинхронная очередь

При включённой фоновой отправке сообщение сначала сохраняется в очереди WordPress и отправляется отдельным заданием WP-Cron.

- Максимум три попытки для одной задачи.
- Интервал между попытками постепенно увеличивается.
- После частичной ошибки в очередь возвращаются только неудачные Chat ID.
- Заказ помечается обработанным только после подтверждённой отправки.
- Старые задания автоматически удаляются по истечении срока хранения.

WP-Cron зависит от посещений сайта. На малопосещаемых сайтах отправка фоновых сообщений может происходить с задержкой.

### Форматирование

Плагин поддерживает HTML и MarkdownV2.

Динамические данные заказа и формы экранируются автоматически. Статический текст пользовательского MarkdownV2-шаблона должен быть экранирован автором шаблона в соответствии с правилами Telegram.

### Отладка

- В настройках отображается последняя краткая ошибка или результат отправки.
- Временный журнал показывает работу очереди и диагностические сообщения.
- Полный успешный JSON-ответ Telegram в базе не хранится.
- Очередь можно очистить вручную.

Проверена тестовая отправка CF7. Ошибочный Chat ID корректно возвращает сообщение Telegram вида `Bad Request: chat not found`.

### Конфиденциальность

Для работы фоновой очереди текст сообщения временно сохраняется в WordPress transient. Сообщение может содержать данные заказа или формы: имя, телефон, email и другие поля. По умолчанию очередь хранится не более суток, а отдельное задание — не более шести часов.

Плагин не отправляет данные сторонним сервисам, кроме Telegram API, указанного в его назначении.

### Известный вопрос для дальнейшего тестирования

Логика защиты от повторных уведомлений о статусах сохранена без архитектурного изменения. Требуется дополнительное тестирование повторных переходов заказа между одинаковыми статусами.

## English version

The plugin sends WooCommerce order notifications and Contact Form 7 submissions to one or more Telegram chats, groups or channels. It supports custom templates, HTML and MarkdownV2 formatting, background queue processing, retries, and test actions in the WordPress admin area.

### Features

- New order and order status notifications for WooCommerce.
- Contact Form 7 submission notifications.
- Multiple named recipients with separate Chat IDs.
- Bulk Chat ID import with optional `ID | Name` labels.
- Public channels such as `@channelusername`.
- Message templates with placeholders.
- HTML and MarkdownV2 parse modes.
- Asynchronous queue with a limited number of retries.
- Failed destinations are retried without resending to successful chats.
- Test order, CF7 and custom-message actions.
- Compact error and queue diagnostics without storing complete Telegram API responses.
- WooCommerce HPOS support through the order CRUD API.
- Bot token and Chat IDs may be defined in `wp-config.php`.
- Bundled `about.html` documentation displayed from the plugin settings.

### Requirements and compatibility target

- WordPress 6.8–7.0+.
- PHP 8.0+.
- WooCommerce 6.0+ for order notifications.
- Contact Form 7 for form notifications.
- Outbound HTTPS access to `https://api.telegram.org` through the WordPress HTTP API.

Test the plugin on a staging copy before updating a production site.

### Installation

1. Copy the `tgnotify2` directory to `/wp-content/plugins/` or install a prepared release ZIP package.
2. Activate **c0dx: Telegram Notify Enhanced**.
3. Open **Telegram Notify** in the main admin menu or use the **Settings** link on the Plugins screen.
4. Enter the bot token and add one or more recipients.
5. Configure templates, parse mode and notification types.
6. Run the test actions before enabling production notifications.

### Configuration constants

```php
define( 'TN_TELEGRAM_BOT_TOKEN', '123456:ABC-DEF...' );
define( 'TN_TELEGRAM_CHAT_IDS', '-1002123456789,@channelusername' );
```

When `TN_TELEGRAM_BOT_TOKEN` is defined, the token is not exposed in the settings page and is not overwritten when the form is saved. When `TN_TELEGRAM_CHAT_IDS` is defined, it takes precedence over recipients stored in the database.

### Named recipients and bulk import

Each recipient has a Chat ID and an optional admin-only label. Existing legacy Chat IDs are shown in the new interface and are migrated when settings are saved.

Bulk import accepts line breaks, commas or semicolons, and optionally supports:

```text
200012345 | John
-1002123456789 | Work group
```

### Async queue

- Up to three attempts per queued job.
- Increasing delay between attempts.
- Only failed Chat IDs are retried after a partial failure.
- Order notifications are marked as processed only after confirmed delivery.
- Expired queue jobs are discarded automatically.

WP-Cron depends on site traffic, so background delivery may be delayed on low-traffic sites.

### Formatting

Dynamic order and form values are escaped automatically. Static MarkdownV2 template text must still follow Telegram escaping rules.

### Debugging and privacy

The plugin stores only a short last-error message and temporary diagnostic entries, not the complete successful Telegram response.

Queued message text is temporarily stored in a WordPress transient and may contain customer or form data. The queue lifetime is limited to one day, while individual jobs expire after six hours.

## Repository layout

```text
README.md
LICENSE
CHANGELOG.md
.gitignore
tgnotify2/
  .htaccess
  about.html
  telegram_notify_enhanced.php
```

Installable ZIP archives are release artifacts and are not stored in the source tree.

## Disclaimer

This plugin is provided as is, without any warranty. Test it on a staging site before production use. The authors and maintainers are not responsible for errors, data loss, downtime or other damage caused by using the plugin.

## License

MIT. See [LICENSE](LICENSE).
