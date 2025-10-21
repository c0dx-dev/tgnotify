<?php
/**
 * Plugin Name: Telegram Notify Enhanced
 * Plugin URI:  https://example.com
 * Description: Sends WooCommerce order and Contact Form 7 submission notifications to Telegram. Supports message templates with placeholders, multiple chat IDs, parse mode selection (HTML / MarkdownV2), asynchronous dispatching, i18n, and customization filters. Compatible with WP 6+ and PHP 8+.
 * Version:     2.1.1
 * Author:      svtagan@gmail.com
 * Text Domain: telegram-notify
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TN_Telegram_Notify_Enhanced {
    private string $option_prefix = 'tn_enh_';
    private string $settings_group = 'tn_enh_settings';
    private string $settings_page_slug = 'tn-enh-telegram-notify';
    private string $queue_transient_key = 'tn_enh_queue';
    private string $queue_lock_transient_key = 'tn_enh_queue_lock';
    private string $debug_transient_key = 'tn_enh_last_debug';
    private string $notice_transient_key = 'tn_enh_admin_notice';
    private string $about_file_path;
    private int $queue_limit = 50;
    private int $queue_job_ttl = 6 * HOUR_IN_SECONDS;
    private int $queue_storage_ttl = DAY_IN_SECONDS;
    private int $queue_lock_ttl = 60;
    private int $debug_storage_ttl = DAY_IN_SECONDS;

    public function __construct() {
        $this->about_file_path = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'about.html';
        $this->load_textdomain();

        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_post_tn_enh_save_test_message', [ $this, 'handle_test_message' ] );
        add_action( 'admin_post_tn_enh_clear_queue', [ $this, 'handle_clear_queue' ] );
        add_action( 'admin_post_tn_enh_send_test_order', [ $this, 'handle_send_test_order' ] );
        add_action( 'admin_post_tn_enh_send_test_cf7', [ $this, 'handle_send_test_cf7' ] );
        add_action( 'admin_notices', [ $this, 'render_stored_admin_notice' ] );

        add_action( 'woocommerce_order_status_changed', [ $this, 'on_order_status_changed' ], 10, 4 );
        add_action( 'wpcf7_mail_sent', [ $this, 'on_cf7_mail_sent' ], 10, 1 );

        // Register fallback hooks only if WooCommerce functions are available
        if ( function_exists( 'wc_get_order' ) ) {
            add_action( 'woocommerce_new_order', [ $this, 'on_order_created_fallback' ], 10, 1 );
        }

        add_action( 'admin_post_tn_resend_order', [ $this, 'admin_resend_order' ] );

        add_action( 'tn_enh_send_queued_messages', [ $this, 'process_message_queue' ] );
    }

    private function set_admin_notice( string $message, string $type = 'success' ): void {
        set_transient( $this->notice_transient_key, [
            'message' => $message,
            'type'    => $type,
        ], MINUTE_IN_SECONDS );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'telegram-notify', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function add_admin_menu() {
        add_options_page(
            __( 'Telegram Notify', 'telegram-notify' ),
            __( 'Telegram Notify', 'telegram-notify' ),
            'manage_options',
            $this->settings_page_slug,
            [ $this, 'settings_page' ]
        );
    }

    public function register_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        register_setting( $this->settings_group, $this->option_prefix . 'bot_token', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_bot_token' ],
            'default'           => '',
        ] );

        register_setting( $this->settings_group, $this->option_prefix . 'chat_ids', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_chat_ids' ],
            'default'           => '',
        ] );

        register_setting( $this->settings_group, $this->option_prefix . 'parse_mode', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_parse_mode' ],
            'default'           => 'HTML',
        ] );

        register_setting( $this->settings_group, $this->option_prefix . 'send_async', [
            'type'              => 'boolean',
            'sanitize_callback' => [ $this, 'sanitize_checkbox' ],
            'default'           => false,
        ] );

        register_setting( $this->settings_group, $this->option_prefix . 'enable_wc_notifications', [
            'type'              => 'boolean',
            'sanitize_callback' => [ $this, 'sanitize_checkbox' ],
            'default'           => true,
        ] );

        register_setting( $this->settings_group, $this->option_prefix . 'enable_cf7_notifications', [
            'type'              => 'boolean',
            'sanitize_callback' => [ $this, 'sanitize_checkbox' ],
            'default'           => true,
        ] );

        register_setting( $this->settings_group, $this->option_prefix . 'cf7_enabled_forms', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_cf7_form_ids' ],
            'default'           => '',
        ] );

        register_setting( $this->settings_group, $this->option_prefix . 'order_template', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_template' ],
            'default'           => $this->get_default_order_template(),
        ] );

        register_setting( $this->settings_group, $this->option_prefix . 'cf7_template', [
            'type'              => 'string',
            'sanitize_callback' => [ $this, 'sanitize_template' ],
            'default'           => $this->get_default_cf7_template(),
        ] );

        register_setting( $this->settings_group, $this->option_prefix . 'last_error', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => '',
        ] );

        add_settings_section(
            'tn_enh_general_section',
            __( 'Основные настройки', 'telegram-notify' ),
            '__return_null',
            $this->settings_page_slug
        );

        add_settings_field(
            'tn_enh_bot_token',
            __( 'Bot Token', 'telegram-notify' ),
            [ $this, 'render_bot_token_field' ],
            $this->settings_page_slug,
            'tn_enh_general_section'
        );

        add_settings_field(
            'tn_enh_chat_ids',
            __( 'Chat ID(s)', 'telegram-notify' ),
            [ $this, 'render_chat_ids_field' ],
            $this->settings_page_slug,
            'tn_enh_general_section'
        );

        add_settings_field(
            'tn_enh_parse_mode',
            __( 'Parse Mode', 'telegram-notify' ),
            [ $this, 'render_parse_mode_field' ],
            $this->settings_page_slug,
            'tn_enh_general_section'
        );

        add_settings_field(
            'tn_enh_send_async',
            __( 'Асинхронная отправка', 'telegram-notify' ),
            [ $this, 'render_send_async_field' ],
            $this->settings_page_slug,
            'tn_enh_general_section'
        );

        add_settings_field(
            'tn_enh_enable_wc_notifications',
            __( 'Уведомления WooCommerce', 'telegram-notify' ),
            [ $this, 'render_enable_wc_field' ],
            $this->settings_page_slug,
            'tn_enh_general_section'
        );

        add_settings_field(
            'tn_enh_enable_cf7_notifications',
            __( 'Уведомления Contact Form 7', 'telegram-notify' ),
            [ $this, 'render_enable_cf7_field' ],
            $this->settings_page_slug,
            'tn_enh_general_section'
        );

        add_settings_field(
            'tn_enh_cf7_forms_filter',
            __( 'Выберите формы CF7 для уведомлений', 'telegram-notify' ),
            [ $this, 'render_cf7_forms_field' ],
            $this->settings_page_slug,
            'tn_enh_general_section'
        );

        add_settings_section(
            'tn_enh_templates_section',
            __( 'Шаблоны сообщений', 'telegram-notify' ),
            [ $this, 'render_templates_section_intro' ],
            $this->settings_page_slug
        );

        add_settings_field(
            'tn_enh_order_template',
            __( 'Шаблон для заказов WooCommerce', 'telegram-notify' ),
            [ $this, 'render_order_template_field' ],
            $this->settings_page_slug,
            'tn_enh_templates_section'
        );

        add_settings_field(
            'tn_enh_cf7_template',
            __( 'Шаблон для сообщений CF7', 'telegram-notify' ),
            [ $this, 'render_cf7_template_field' ],
            $this->settings_page_slug,
            'tn_enh_templates_section'
        );
    }

    public function sanitize_bot_token( $value ): string {
        $value = is_string( $value ) ? trim( $value ) : '';
        return preg_replace( '/[^0-9A-Za-z:_-]/', '', $value );
    }

    public function sanitize_chat_ids( $value ): string {
        if ( ! is_string( $value ) ) {
            return '';
        }
        $ids = array_filter( array_map( 'trim', preg_split( '/[\r\n,;]+/', $value ) ?: [] ) );
        $ids = array_map( static function( $id ) {
            return preg_replace( '/[^0-9A-Za-z_@\-]/', '', $id );
        }, $ids );
        return implode( "\n", array_unique( array_filter( $ids ) ) );
    }

    public function sanitize_parse_mode( $value ): string {
        if ( ! is_string( $value ) ) {
            return 'HTML';
        }

        $normalized = strtoupper( trim( $value ) );

        if ( 'MARKDOWNV2' === $normalized ) {
            return 'MarkdownV2';
        }

        return 'HTML';
    }

    public function sanitize_checkbox( $value ): bool {
        return (bool) $value;
    }

    public function sanitize_template( $value ): string {
        if ( ! is_string( $value ) ) {
            return '';
        }
        return wp_kses_post( $value );
    }

    public function sanitize_cf7_form_ids( $value ): string {
        if ( is_array( $value ) ) {
            $value = implode( "\n", array_map( 'strval', $value ) );
        }

        if ( ! is_string( $value ) ) {
            return '';
        }

        $ids = preg_split( '/[\r\n,;]+/', $value );
        $ids = is_array( $ids ) ? array_map( 'trim', $ids ) : [];
        $ids = array_filter( $ids, static function( $item ) {
            return preg_match( '/^[0-9]+$/', $item );
        } );

        if ( empty( $ids ) ) {
            return '';
        }

        $ids = array_unique( $ids );
        return implode( "\n", $ids );
    }

    public function get_default_order_template(): string {
        return "<b>Новый заказ #{order_id}</b>\nСтатус: {from_status} → {to_status}\nКлиент: {customer_name}\nТелефон: {phone}\nE-mail: {email}\nСумма: {total}\nТовары:\n{items}\nСсылка: {order_link}";
    }

    public function get_default_cf7_template(): string {
        return "<b>Новая отправка CF7</b>\nФорма: {form_title}\n{all_fields}";
    }

    public function render_bot_token_field() {
        $value = get_option( $this->option_prefix . 'bot_token', '' );
        if ( defined( 'TN_TELEGRAM_BOT_TOKEN' ) && TN_TELEGRAM_BOT_TOKEN ) {
            $value = TN_TELEGRAM_BOT_TOKEN;
        }
        printf(
            '<input type="text" id="tn_enh_bot_token" name="%1$s" value="%2$s" class="regular-text" />' .
            '<p class="description">%3$s</p>',
            esc_attr( $this->option_prefix . 'bot_token' ),
            esc_attr( $value ),
            esc_html__( 'Token от BotFather, например 123456:ABC-DEF...', 'telegram-notify' )
        );
    }

    public function render_chat_ids_field() {
        $value = get_option( $this->option_prefix . 'chat_ids', '' );
        if ( defined( 'TN_TELEGRAM_CHAT_IDS' ) && TN_TELEGRAM_CHAT_IDS ) {
            $value = TN_TELEGRAM_CHAT_IDS;
        }
        printf(
            '<textarea id="tn_enh_chat_ids" name="%1$s" rows="3" class="large-text">%2$s</textarea>' .
            '<p class="description">%3$s</p>',
            esc_attr( $this->option_prefix . 'chat_ids' ),
            esc_textarea( $value ),
            esc_html__( 'Поддерживается несколько chat_id — разделяйте запятой или новой строкой.', 'telegram-notify' )
        );
        echo '<div id="tn_enh_chat_ids_feedback" class="notice notice-error" style="display:none;margin-top:8px;"><p></p></div>';
    }

    public function render_parse_mode_field() {
        $value = get_option( $this->option_prefix . 'parse_mode', 'HTML' );
        printf(
            '<select id="tn_enh_parse_mode" name="%1$s">' .
            '<option value="HTML" %2$s>HTML</option>' .
            '<option value="MarkdownV2" %3$s>MarkdownV2</option>' .
            '</select>',
            esc_attr( $this->option_prefix . 'parse_mode' ),
            selected( $value, 'HTML', false ),
            selected( $value, 'MarkdownV2', false )
        );
    }

    public function render_send_async_field() {
        $value = (bool) get_option( $this->option_prefix . 'send_async', false );
        printf(
            '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
            esc_attr( $this->option_prefix . 'send_async' ),
            checked( $value, true, false ),
            esc_html__( 'Не блокировать выполнение запроса (отправка через фон).', 'telegram-notify' )
        );
    }

    public function render_enable_wc_field() {
        $value = (bool) get_option( $this->option_prefix . 'enable_wc_notifications', true );
        printf(
            '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
            esc_attr( $this->option_prefix . 'enable_wc_notifications' ),
            checked( $value, true, false ),
            esc_html__( 'Включить отправку уведомлений о заказах WooCommerce.', 'telegram-notify' )
        );
    }

    public function render_enable_cf7_field() {
        $value = (bool) get_option( $this->option_prefix . 'enable_cf7_notifications', true );
        printf(
            '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
            esc_attr( $this->option_prefix . 'enable_cf7_notifications' ),
            checked( $value, true, false ),
            esc_html__( 'Включить отправку уведомлений из Contact Form 7.', 'telegram-notify' )
        );
    }

    public function render_cf7_forms_field() {
        $selected = $this->get_cf7_enabled_form_ids();

        if ( ! post_type_exists( 'wpcf7_contact_form' ) ) {
            echo '<p>' . esc_html__( 'Contact Form 7 не установлен или не активен.', 'telegram-notify' ) . '</p>';
            return;
        }

        $forms = get_posts( [
            'post_type'      => 'wpcf7_contact_form',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        echo '<input type="hidden" name="' . esc_attr( $this->option_prefix . 'cf7_enabled_forms' ) . '[]" value="" />';

        if ( empty( $forms ) ) {
            echo '<p>' . esc_html__( 'Нет доступных форм.', 'telegram-notify' ) . '</p>';
            return;
        }

        echo '<p>' . esc_html__( 'Если не выбрано ни одной формы, то уведомления отправляются по всем формам.', 'telegram-notify' ) . '</p>';

        foreach ( $forms as $form ) {
            $form_id = (int) $form->ID;
            $form_title = get_the_title( $form_id );
            $is_checked = in_array( $form_id, $selected, true );

            printf(
                '<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="%1$s[]" value="%2$d" %3$s /> %4$s (ID: %2$d)</label>',
                esc_attr( $this->option_prefix . 'cf7_enabled_forms' ),
                $form_id,
                checked( $is_checked, true, false ),
                esc_html( $form_title )
            );
        }
    }

    public function render_templates_section_intro() {
        echo '<p>' . esc_html__( 'Используйте плейсхолдеры, которые будут заменены реальными значениями.', 'telegram-notify' ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Доступные плейсхолдеры', 'telegram-notify' ) . '</strong></p>';
        echo '<ul>';
        echo '<li><code>{order_id}</code>, <code>{from_status}</code>, <code>{to_status}</code>, <code>{customer_name}</code>, <code>{phone}</code>, <code>{email}</code>, <code>{total}</code>, <code>{items}</code>, <code>{order_link}</code></li>';
        echo '<li><code>{form_title}</code>, <code>{all_fields}</code>, <code>{field_{field_name}}</code></li>';
        echo '</ul>';
    }

    public function render_order_template_field() {
        $value = get_option( $this->option_prefix . 'order_template', $this->get_default_order_template() );
        $parse_mode = get_option( $this->option_prefix . 'parse_mode', 'HTML' );
        printf(
            '<textarea id="tn_enh_order_template" name="%1$s" rows="8" class="large-text code">%2$s</textarea>',
            esc_attr( $this->option_prefix . 'order_template' ),
            esc_textarea( $value )
        );
        if ( 'MarkdownV2' === $parse_mode ) {
            echo '<p class="description">' . esc_html__( 'В режиме MarkdownV2 экранируйте символы _ * [ ] ( ) ~ ` > # + - = | { } . ! и используйте двойной обратный слеш для \.', 'telegram-notify' ) . '</p>';
        }
    }

    public function render_cf7_template_field() {
        $value = get_option( $this->option_prefix . 'cf7_template', $this->get_default_cf7_template() );
        $parse_mode = get_option( $this->option_prefix . 'parse_mode', 'HTML' );
        printf(
            '<textarea id="tn_enh_cf7_template" name="%1$s" rows="8" class="large-text code">%2$s</textarea>',
            esc_attr( $this->option_prefix . 'cf7_template' ),
            esc_textarea( $value )
        );
        if ( 'MarkdownV2' === $parse_mode ) {
            echo '<p class="description">' . esc_html__( 'В MarkdownV2 не забывайте экранировать спецсимволы _ * [ ] ( ) ~ ` > # + - = | { } . ! и вставлять \\ перед ними в статичном тексте.', 'telegram-notify' ) . '</p>';
        }
    }

    public function render_stored_admin_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $notice = get_transient( $this->notice_transient_key );
        if ( ! $notice || empty( $notice['message'] ) ) {
            return;
        }

        delete_transient( $this->notice_transient_key );

        $type = sanitize_html_class( $notice['type'] ?? 'info' );
        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr( $type ),
            esc_html( $notice['message'] )
        );
    }

    private function get_options(): array {
        $keys = [ 'bot_token', 'chat_ids', 'parse_mode', 'send_async', 'order_template', 'cf7_template', 'last_error' ];
        $opts = [];
        foreach ( $keys as $k ) {
            $opts[ $k ] = get_option( $this->option_prefix . $k );
        }
        return $opts;
    }

    public function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $last_error = get_option( $this->option_prefix . 'last_error', '' );
        $about_content = $this->get_about_content();
        $queue_overview = $this->get_queue_status_overview();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Telegram Notify — настройки', 'telegram-notify' ); ?></h1>

            <div class="notice notice-info tn-enh-queue-summary">
                <?php
                $queue_count = (int) ( $queue_overview['count'] ?? 0 );
                $earliest_attempt = $queue_overview['earliest_attempt'] ?? null;
                $scheduled_time = $queue_overview['scheduled'] ?? null;

                if ( $queue_count > 0 ) {
                    printf(
                        '<p><strong>%s</strong> %s</p>',
                        esc_html__( 'Очередь сообщений:', 'telegram-notify' ),
                        esc_html( sprintf( __( '%d задач(и) ожидают отправки.', 'telegram-notify' ), $queue_count ) )
                    );
                } else {
                    echo '<p>' . esc_html__( 'Очередь сообщений пуста.', 'telegram-notify' ) . '</p>';
                }

                if ( $earliest_attempt ) {
                    $formatted = wp_date( 'd.m.Y H:i:s', $earliest_attempt );
                    echo '<p>' . sprintf( esc_html__( 'Ближайшая попытка отправки: %s', 'telegram-notify' ), esc_html( $formatted ) ) . '</p>';
                } else {
                    echo '<p>' . esc_html__( 'Ближайшая попытка отправки не запланирована.', 'telegram-notify' ) . '</p>';
                }

                if ( $scheduled_time ) {
                    $formatted_schedule = wp_date( 'd.m.Y H:i:s', $scheduled_time );
                    echo '<p>' . sprintf( esc_html__( 'Следующее cron-событие: %s', 'telegram-notify' ), esc_html( $formatted_schedule ) ) . '</p>';
                } else {
                    echo '<p>' . esc_html__( 'Событие wp-cron для очереди не запланировано.', 'telegram-notify' ) . '</p>';
                }
                ?>
            </div>

            <?php if ( $about_content ) : ?>
                <p>
                    <button type="button" class="button" id="tn-enh-about-open" aria-haspopup="dialog" aria-expanded="false" aria-controls="tn-enh-about-modal">
                        <?php esc_html_e( 'Описание и примеры', 'telegram-notify' ); ?>
                    </button>
                </p>
                <div id="tn-enh-about-modal" class="tn-enh-modal" role="dialog" aria-modal="true" aria-labelledby="tn-enh-about-title" style="display:none;">
                    <div class="tn-enh-modal__backdrop" data-dismiss="modal"></div>
                    <div class="tn-enh-modal__dialog" role="document" tabindex="-1">
                        <div class="tn-enh-modal__header">
                            <h2 id="tn-enh-about-title"><?php esc_html_e( 'Описание и примеры', 'telegram-notify' ); ?></h2>
                            <button type="button" class="button-link tn-enh-modal__close" data-dismiss="modal" aria-label="<?php esc_attr_e( 'Закрыть', 'telegram-notify' ); ?>">&times;</button>
                        </div>
                        <div class="tn-enh-modal__body">
                            <?php echo $about_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </div>
                </div>
                <style>
                    .tn-enh-modal {
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        z-index: 100000;
                    }
                    .tn-enh-modal__backdrop {
                        position: absolute;
                        inset: 0;
                        background: rgba(0,0,0,0.5);
                    }
                    .tn-enh-modal__dialog {
                        position: relative;
                        max-width: 860px;
                        margin: 60px auto;
                        background: #fff;
                        border-radius: 6px;
                        box-shadow: 0 20px 45px rgba(0,0,0,0.3);
                        max-height: calc(100vh - 120px);
                        display: flex;
                        flex-direction: column;
                    }
                    .tn-enh-modal__header {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        padding: 16px 20px;
                        border-bottom: 1px solid #e2e4e7;
                    }
                    .tn-enh-modal__body {
                        padding: 0 20px 20px;
                        overflow: auto;
                    }
                    .tn-enh-modal__close {
                        font-size: 26px;
                        line-height: 1;
                        color: #646970;
                    }
                    .tn-enh-modal__close:hover,
                    .tn-enh-modal__close:focus {
                        color: #1d2327;
                    }
                    .tn-enh-modal .tn-enh-about-content h1,
                    .tn-enh-modal .tn-enh-about-content h2 {
                        margin-top: 1.2em;
                    }
                    .tn-enh-modal .tn-enh-about-content pre {
                        background: #f6f7f7;
                        padding: 12px;
                        overflow: auto;
                        border-radius: 4px;
                    }
                    .tn-enh-invalid {
                        border-color: #d63638 !important;
                        box-shadow: 0 0 0 1px rgba(214, 54, 56, 0.2);
                    }
                    #tn_enh_chat_ids_feedback {
                        max-width: 640px;
                    }
                </style>
                <script>
                ( function( $ ) {
                    $( function() {
                        var $modal = $( '#tn-enh-about-modal' );
                        var $openBtn = $( '#tn-enh-about-open' );

                        function closeModal() {
                            $modal.hide();
                            $openBtn.attr( 'aria-expanded', 'false' ).focus();
                            $( document.body ).removeClass( 'modal-open' );
                        }

                        $openBtn.on( 'click', function() {
                            $modal.show();
                            $openBtn.attr( 'aria-expanded', 'true' );
                            $( document.body ).addClass( 'modal-open' );
                            $modal.find( '.tn-enh-modal__dialog' ).focus();
                        } );

                        $modal.on( 'click', '[data-dismiss="modal"]', function() {
                            closeModal();
                        } );

                        $( document ).on( 'keyup', function( event ) {
                            if ( event.key === 'Escape' && $modal.is( ':visible' ) ) {
                                closeModal();
                            }
                        } );

                        var $chatField = $( '#tn_enh_chat_ids' );
                        var $feedback = $( '#tn_enh_chat_ids_feedback' );

                        var baseMessage = window.tnEnhChatIdError || '<?php echo esc_js( __( 'Проверьте chat ID: допускаются только цифры, буквы, подчёркивание, дефис и символ @. Неверные значения: ', 'telegram-notify' ) ); ?>';

                        function validateChatIds() {
                            if ( ! $chatField.length ) {
                                return;
                            }

                            var raw = $chatField.val() || '';
                            var parts = raw.split( /[\s,;]+/ );
                            var invalid = [];

                            for ( var i = 0; i < parts.length; i++ ) {
                                var part = parts[ i ].trim();
                                if ( part === '' ) {
                                    continue;
                                }
                                if ( ! /^[0-9A-Za-z_@\-]+$/.test( part ) ) {
                                    invalid.push( part );
                                }
                            }

                            if ( invalid.length ) {
                                $chatField.addClass( 'tn-enh-invalid' );
                                $feedback.show().find( 'p' ).text( baseMessage + invalid.join( ', ' ) );
                            } else {
                                $chatField.removeClass( 'tn-enh-invalid' );
                                $feedback.hide().find( 'p' ).text( '' );
                            }
                        }

                        if ( $chatField.length ) {
                            $chatField.on( 'input blur', validateChatIds );
                            validateChatIds();
                        }
                    } );
                } )( window.jQuery );
                </script>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                    settings_fields( $this->settings_group );
                    do_settings_sections( $this->settings_page_slug );
                    submit_button( __( 'Сохранить настройки', 'telegram-notify' ) );
                ?>
            </form>

            <hr />

            <h2><?php esc_html_e( 'Тестовые действия', 'telegram-notify' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:15px;">
                <?php wp_nonce_field( 'tn_enh_test_order', 'tn_enh_test_order_nonce' ); ?>
                <input type="hidden" name="action" value="tn_enh_send_test_order" />
                <?php submit_button( __( 'Отправить тестовый заказ', 'telegram-notify' ), 'secondary', 'submit', false ); ?>
                <p class="description"><?php esc_html_e( 'Подставит пример данных заказа и отправит сообщение по текущему шаблону.', 'telegram-notify' ); ?></p>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:15px;">
                <?php wp_nonce_field( 'tn_enh_test_cf7', 'tn_enh_test_cf7_nonce' ); ?>
                <input type="hidden" name="action" value="tn_enh_send_test_cf7" />
                <?php submit_button( __( 'Отправить тестовую форму CF7', 'telegram-notify' ), 'secondary', 'submit', false ); ?>
                <p class="description"><?php esc_html_e( 'Сгенерирует пример отправки формы и проверит текущий шаблон CF7.', 'telegram-notify' ); ?></p>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'tn_enh_test', 'tn_enh_test_nonce' ); ?>
                <input type="hidden" name="action" value="tn_enh_save_test_message" />
                <input type="text" name="tn_test_message" class="regular-text" placeholder="<?php esc_attr_e( 'Тестовое сообщение', 'telegram-notify' ); ?>" />
                <?php submit_button( __( 'Отправить тест', 'telegram-notify' ), 'secondary', 'submit', false ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:15px;">
                <?php wp_nonce_field( 'tn_enh_clear_queue', 'tn_enh_clear_queue_nonce' ); ?>
                <input type="hidden" name="action" value="tn_enh_clear_queue" />
                <?php submit_button( __( 'Очистить очередь сообщений', 'telegram-notify' ), 'secondary', 'submit', false ); ?>
            </form>

            <?php if ( $last_error ) : ?>
                <h3><?php esc_html_e( 'Последний ответ Telegram API', 'telegram-notify' ); ?></h3>
                <code><?php echo esc_html( $last_error ); ?></code>
            <?php endif; ?>

            <h3><?php esc_html_e( 'Отладочные логи (последние)', 'telegram-notify' ); ?></h3>
            <pre><?php echo esc_html( $this->get_debug_log() ); ?></pre>
        </div>
        <?php
    }

    public function handle_test_message() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Нет доступа' );
        }

        check_admin_referer( 'tn_enh_test', 'tn_enh_test_nonce' );

        $msg = isset( $_POST['tn_test_message'] ) ? sanitize_text_field( wp_unslash( $_POST['tn_test_message'] ) ) : 'Test message';
        $res = $this->send_message_to_configured_chats( $msg );

        if ( is_wp_error( $res ) ) {
            update_option( $this->option_prefix . 'last_error', $res->get_error_message() );
            $this->set_admin_notice( $res->get_error_message(), 'error' );
        } else {
            update_option( $this->option_prefix . 'last_error', wp_json_encode( $res ) );
            $this->set_admin_notice( __( 'Тест отправлен.', 'telegram-notify' ), 'success' );
        }

        $redirect = admin_url( 'options-general.php?page=' . $this->settings_page_slug );
        wp_safe_redirect( $redirect );
        exit;
    }

    public function handle_clear_queue() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Нет доступа' );
        }

        check_admin_referer( 'tn_enh_clear_queue', 'tn_enh_clear_queue_nonce' );

        $deleted_queue = delete_transient( $this->queue_transient_key );
        $deleted_lock  = delete_transient( $this->queue_lock_transient_key );

        if ( $deleted_queue || $deleted_lock ) {
            $this->set_admin_notice( __( 'Очередь сообщений очищена.', 'telegram-notify' ), 'success' );
        } else {
            $this->set_admin_notice( __( 'Очередь уже пуста.', 'telegram-notify' ), 'info' );
        }

        wp_safe_redirect( admin_url( 'options-general.php?page=' . $this->settings_page_slug ) );
        exit;
    }

    public function handle_send_test_order() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Нет доступа' );
        }

        check_admin_referer( 'tn_enh_test_order', 'tn_enh_test_order_nonce' );

        $parse_mode = get_option( $this->option_prefix . 'parse_mode', 'HTML' );
        if ( ! in_array( $parse_mode, [ 'HTML', 'MarkdownV2' ], true ) ) {
            $parse_mode = 'HTML';
        }

        $sample = [
            'order_id'      => 12345,
            'from_status'   => 'pending',
            'to_status'     => 'processing',
            'customer_name' => 'Иван Иванов',
            'phone'         => '+7 900 000-00-00',
            'email'         => 'test@example.com',
            'total'         => '10 500 ₽',
            'items'         => "1 × Тестовый товар\n2 × Аксессуар",
            'order_link'    => admin_url( 'edit.php?post_type=shop_order' ),
        ];

        $placeholders = [
            '{order_id}'      => $sample['order_id'],
            '{from_status}'   => $this->prepare_text_for_parse_mode( $sample['from_status'], $parse_mode ),
            '{to_status}'     => $this->prepare_text_for_parse_mode( $sample['to_status'], $parse_mode ),
            '{customer_name}' => $this->prepare_text_for_parse_mode( $sample['customer_name'], $parse_mode ),
            '{phone}'         => $this->prepare_text_for_parse_mode( $sample['phone'], $parse_mode ),
            '{email}'         => $this->prepare_text_for_parse_mode( $sample['email'], $parse_mode ),
            '{total}'         => $this->prepare_text_for_parse_mode( $sample['total'], $parse_mode ),
            '{items}'         => $this->prepare_text_for_parse_mode( $sample['items'], $parse_mode ),
            '{order_link}'    => $this->prepare_url_for_parse_mode( $sample['order_link'], $parse_mode ),
        ];

        $template = get_option( $this->option_prefix . 'order_template', $this->get_default_order_template() );
        $message  = strtr( $template, $placeholders );
        $message  = $this->apply_filters_safely( 'tn_order_message', $message, null );

        $result = $this->send_message_to_configured_chats( $message );

        if ( is_wp_error( $result ) ) {
            update_option( $this->option_prefix . 'last_error', $result->get_error_message() );
            $this->set_admin_notice( $result->get_error_message(), 'error' );
        } else {
            update_option( $this->option_prefix . 'last_error', wp_json_encode( $result ) );
            $this->set_admin_notice( __( 'Тестовый заказ отправлен.', 'telegram-notify' ), 'success' );
        }

        wp_safe_redirect( admin_url( 'options-general.php?page=' . $this->settings_page_slug ) );
        exit;
    }

    public function handle_send_test_cf7() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Нет доступа' );
        }

        check_admin_referer( 'tn_enh_test_cf7', 'tn_enh_test_cf7_nonce' );

        $parse_mode = get_option( $this->option_prefix . 'parse_mode', 'HTML' );
        if ( ! in_array( $parse_mode, [ 'HTML', 'MarkdownV2' ], true ) ) {
            $parse_mode = 'HTML';
        }

        $fields = [
            'name'    => 'Иван Иванов',
            'email'   => 'test@example.com',
            'phone'   => '+7 900 000-00-00',
            'message' => 'Здравствуйте! Хочу узнать про сроки доставки.',
        ];

        $all_lines = [];
        foreach ( $fields as $key => $value ) {
            $all_lines[] = $key . ': ' . $value;
        }
        $all_fields_text = implode( "\r\n", $all_lines );

        $template = get_option( $this->option_prefix . 'cf7_template', $this->get_default_cf7_template() );

        $message = strtr( $template, [
            '{form_title}' => $this->prepare_text_for_parse_mode( 'Тестовая форма', $parse_mode ),
            '{all_fields}' => $this->prepare_text_for_parse_mode( $all_fields_text, $parse_mode ),
        ] );

        foreach ( $fields as $key => $value ) {
            $prepared_value = $this->prepare_text_for_parse_mode( $value, $parse_mode );
            $message = str_replace( '{field_' . sanitize_key( $key ) . '}', $prepared_value, $message );
        }

        $message = $this->apply_filters_safely( 'tn_cf7_message', $message, null, $fields );

        $result = $this->send_message_to_configured_chats( $message );

        if ( is_wp_error( $result ) ) {
            update_option( $this->option_prefix . 'last_error', $result->get_error_message() );
            $this->set_admin_notice( $result->get_error_message(), 'error' );
        } else {
            update_option( $this->option_prefix . 'last_error', wp_json_encode( $result ) );
            $this->set_admin_notice( __( 'Тестовая отправка CF7 выполнена.', 'telegram-notify' ), 'success' );
        }

        wp_safe_redirect( admin_url( 'options-general.php?page=' . $this->settings_page_slug ) );
        exit;
    }

    private function get_about_content(): string {
        if ( empty( $this->about_file_path ) || ! file_exists( $this->about_file_path ) ) {
            return '';
        }

        $raw = file_get_contents( $this->about_file_path );
        if ( false === $raw ) {
            return '';
        }

        if ( preg_match( '/<main[^>]*>(.*)<\/main>/is', $raw, $matches ) ) {
            $raw = $matches[1];
        }

        return wp_kses_post( $raw );
    }

    public function on_order_status_changed( $order_id, $from_status, $to_status, $order ) {
        if ( ! $this->is_wc_notifications_enabled() ) {
            return;
        }

        $this->debug_log( "on_order_status_changed called: order_id={$order_id}, from={$from_status}, to={$to_status}" );

        $skip_once = get_post_meta( (int) $order_id, '_tn_enh_skip_once_status', true );
        if ( $skip_once && $skip_once === $to_status ) {
            delete_post_meta( (int) $order_id, '_tn_enh_skip_once_status' );
            $this->debug_log( "on_order_status_changed: пропускаем статус {$to_status} по флагу skip_once" );
            return;
        }
        
        if ( 'pending' === $from_status && 'processing' === $to_status ) {
            $skip_processing = get_post_meta( (int) $order_id, '_tn_enh_skip_processing_once', true );
            if ( $skip_processing ) {
                delete_post_meta( (int) $order_id, '_tn_enh_skip_processing_once' );
                $this->debug_log( "on_order_status_changed: пропускаем переход pending → processing для заказа {$order_id}" );
                return;
            }
        }

        if ( $this->has_sent_for_status( (int) $order_id, (string) $to_status, (string) $from_status ) ) {
            $this->debug_log( "on_order_status_changed: уже отправлено для заказа {$order_id} ({$from_status} → {$to_status})" );
            return;
        }

        if ( ! function_exists( 'wc_get_order' ) ) return;
        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) return;
        }

        $items = $order->get_items();
        $items_text_arr = [];
        foreach ( $items as $item ) {
            $qty = $item->get_quantity();
            $name = wp_strip_all_tags( $item->get_name() );
            $items_text_arr[] = $qty . ' × ' . $name;
        }
        $items_text = implode( "
", $items_text_arr );

        $customer_name = trim( (string) $order->get_formatted_billing_full_name() ?: ( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) );
        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();
        // Format total as plain text because wc_price may return HTML tags.
        $raw_total = (float) $order->get_total();
        $currency = '';
        if ( method_exists( $order, 'get_currency' ) ) {
            $currency = $order->get_currency();
        } elseif ( function_exists( 'get_woocommerce_currency' ) ) {
            $currency = get_woocommerce_currency();
        }
        $total_html = wc_price( $raw_total, [ 'currency' => $currency ] );
        $total_plain = wp_strip_all_tags( $total_html );
        $total_plain = str_replace( [ '&nbsp;', '&#160;', "\u{00A0}" ], ' ', $total_plain );
        $total = $this->normalize_plain_text( $total_plain );
        $order_link = admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' );

        $parse_mode = get_option( $this->option_prefix . 'parse_mode', 'HTML' );
        if ( ! in_array( $parse_mode, [ 'HTML', 'MarkdownV2' ], true ) ) {
            $parse_mode = 'HTML';
        }

        $customer_name = $this->prepare_text_for_parse_mode( $customer_name, $parse_mode );
        $email = $this->prepare_text_for_parse_mode( $email, $parse_mode );
        $phone = $this->prepare_text_for_parse_mode( $phone, $parse_mode );
        $total = $this->prepare_text_for_parse_mode( $total, $parse_mode );
        $items_text = $this->prepare_text_for_parse_mode( $items_text, $parse_mode );
        $order_link = $this->prepare_url_for_parse_mode( $order_link, $parse_mode );

        $template = get_option( $this->option_prefix . 'order_template' );
        if ( ! $template ) {
            $template = "<b>Новый заказ #{order_id}</b>
Статус: {from_status} → {to_status}
Клиент: {customer_name}
Телефон: {phone}
E-mail: {email}
Сумма: {total}
Товары:
{items}
Ссылка: {order_link}";
        }

        $placeholders = [
            '{order_id}' => $order->get_id(),
            '{from_status}' => $from_status,
            '{to_status}' => $to_status,
            '{customer_name}' => $customer_name,
            '{phone}' => $phone,
            '{email}' => $email,
            '{total}' => $total,
            '{items}' => $items_text,
            '{order_link}' => $order_link,
        ];

        $message = strtr( $template, $placeholders );
        $message = $this->apply_filters_safely( 'tn_order_message', $message, $order );

        $res = $this->send_message_to_configured_chats( $message );
        if ( ! is_wp_error( $res ) ) {
            // Record that this transition notification has been sent once.
            $this->mark_sent_for_status( (int) $order->get_id(), (string) $to_status, (string) $from_status );
            if ( 'pending' === $to_status ) {
                update_post_meta( (int) $order->get_id(), '_tn_enh_skip_processing_once', 1 );
                update_post_meta( (int) $order->get_id(), '_tn_enh_skip_once_status', 'pending' );
            }
        }
    }

    public function on_order_created_fallback( $order_id, $arg2 = null, $arg3 = null ) {
        if ( ! $this->is_wc_notifications_enabled() ) {
            return;
        }

        if ( ! function_exists( 'wc_get_order' ) ) {
            $this->debug_log( 'on_order_created_fallback: wc_get_order недоступна' );
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            $this->debug_log( "on_order_created_fallback: не удалось получить WC_Order для ID {$order_id}" );
            return;
        }

        $to_status = (string) $order->get_status();
        if ( $this->has_sent_for_status( (int) $order->get_id(), $to_status, null ) ) {
            $this->debug_log( "on_order_created_fallback: уже отправлено для заказа {$order->get_id()} (to_status={$to_status})" );
            return;
        }

        $this->debug_log( "on_order_created_fallback called: order_id={$order->get_id()}, status=" . $to_status );

        if ( get_post_meta( $order->get_id(), '_tn_enh_sent', true ) ) {
            $this->debug_log( "on_order_created_fallback: уже отправлено для заказа {$order->get_id()}" );
            return;
        }

        $items_arr = [];
        foreach ( $order->get_items() as $item ) {
            $qty = method_exists( $item, 'get_quantity' ) ? $item->get_quantity() : ( isset( $item['qty'] ) ? $item['qty'] : 1 );
            $name = method_exists( $item, 'get_name' ) ? $item->get_name() : ( is_array( $item ) && isset( $item['name'] ) ? $item['name'] : '' );
            $items_arr[] = "{$qty} × {$name}";
        }
        $items_text_raw = implode( "\n", $items_arr );

        $order_total_formatted = $order->get_formatted_order_total();
        $order_total_plain = wp_strip_all_tags( $order_total_formatted );
        $order_total_plain = str_replace( [ '&nbsp;', '&#160;', "\u{00A0}" ], ' ', $order_total_plain );
        $order_total = $this->normalize_plain_text( $order_total_plain );

        $parse_mode = get_option( $this->option_prefix . 'parse_mode', 'HTML' );
        if ( ! in_array( $parse_mode, [ 'HTML', 'MarkdownV2' ], true ) ) {
            $parse_mode = 'HTML';
        }

        $raw_customer = trim( (string) $order->get_formatted_billing_full_name() ?: ( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) );
        $raw_phone = $order->get_billing_phone();
        $raw_email = $order->get_billing_email();
        $order_link_raw = admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' );

        $customer_name = $this->prepare_text_for_parse_mode( $raw_customer, $parse_mode );
        $phone = $this->prepare_text_for_parse_mode( $raw_phone, $parse_mode );
        $email = $this->prepare_text_for_parse_mode( $raw_email, $parse_mode );
        $total = $this->prepare_text_for_parse_mode( $order_total, $parse_mode );
        $items_text = $this->prepare_text_for_parse_mode( $items_text_raw, $parse_mode );
        $order_link = $this->prepare_url_for_parse_mode( $order_link_raw, $parse_mode );

        $placeholders = [
            '{order_id}'      => $order->get_id(),
            '{from_status}'   => '',
            '{to_status}'     => $order->get_status(),
            '{customer_name}' => $customer_name,
            '{phone}'         => $phone,
            '{email}'         => $email,
            '{total}'         => $total,
            '{items}'         => $items_text,
            '{order_link}'    => $order_link,
        ];

        $template = get_option( $this->option_prefix . 'order_template' );
        if ( ! $template ) {
            $template = "<b>Новый заказ #{order_id}</b>
Статус: {to_status}
Клиент: {customer_name}
Телефон: {phone}
E-mail: {email}
Сумма: {total}
Товары:
{items}
Ссылка: {order_link}";
        }

        $message = strtr( $template, $placeholders );
        $message = $this->apply_filters_safely( 'tn_order_message', $message, $order );

        $res = $this->send_message_to_configured_chats( $message );

        if ( is_wp_error( $res ) ) {
            $this->debug_log( "on_order_created_fallback: ошибка отправки для заказа {$order->get_id()}: " . $res->get_error_message() );
        } else {
            $this->mark_sent_for_status( (int) $order->get_id(), $to_status, null );
            update_post_meta( $order->get_id(), '_tn_enh_sent', 1 );
            update_post_meta( $order->get_id(), '_tn_enh_skip_once_status', $to_status );
            if ( 'pending' === $to_status ) {
                update_post_meta( $order->get_id(), '_tn_enh_skip_processing_once', 1 );
            }
            $this->debug_log( "on_order_created_fallback: уведомление отправлено для заказа {$order->get_id()}" );
        }
    }

    public function on_order_payment_complete( $order_id ) {
        $this->on_order_created_fallback( $order_id );
    }

    public function on_order_status_specific( $order_id ) {
        $this->on_order_created_fallback( $order_id );
    }

    public function on_cf7_mail_sent( $contact_form ) {
        if ( ! $this->is_cf7_notifications_enabled() ) {
            return;
        }

        if ( ! class_exists( 'WPCF7_Submission' ) ) {
            return;
        }
        $submission = WPCF7_Submission::get_instance();
        if ( ! $submission ) {
            return;
        }

        $form_id = method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0;
        $allowed_forms = $this->get_cf7_enabled_form_ids();
        if ( ! empty( $allowed_forms ) && ( $form_id <= 0 || ! in_array( $form_id, $allowed_forms, true ) ) ) {
            return;
        }

        $raw_data = $submission->get_posted_data();
        if ( empty( $raw_data ) || ! is_array( $raw_data ) ) {
            return;
        }

        $title = sanitize_textarea_field( $contact_form->title() );

        $sanitized_data = [];
        $all_fields_lines = [];
        foreach ( $raw_data as $k => $v ) {
            if ( in_array( $k, [ '_wpcf7', '_wpcf7_version', '_wpnonce', '_wpcf7_unit_tag', '_wpcf7_is_ajax_call' ], true ) ) {
                continue;
            }

            if ( is_array( $v ) ) {
                $value = implode( ', ', array_map( 'sanitize_text_field', $v ) );
            } else {
                $value = sanitize_textarea_field( $v );
            }

            $sanitized_data[ $k ] = $value;
            $all_fields_lines[] = $k . ': ' . $value;
        }

        $all_fields_text = implode( "\r\n", $all_fields_lines );

        $parse_mode = get_option( $this->option_prefix . 'parse_mode', 'HTML' );
        if ( ! in_array( $parse_mode, [ 'HTML', 'MarkdownV2' ], true ) ) {
            $parse_mode = 'HTML';
        }

        $title_prepared = $this->prepare_text_for_parse_mode( $title, $parse_mode );
        $all_fields_prepared = $this->prepare_text_for_parse_mode( $all_fields_text, $parse_mode );

        $template = get_option( $this->option_prefix . 'cf7_template' );
        if ( ! $template ) {
            $template = "<b>Новая отправка CF7</b>
Форма: {form_title}
{all_fields}";
        }

        $message = strtr( $template, [ '{form_title}' => $title_prepared, '{all_fields}' => $all_fields_prepared ] );

        foreach ( $sanitized_data as $k => $v ) {
            $placeholder_key = sanitize_key( $k );
            if ( '' === $placeholder_key ) {
                continue;
            }

            $prepared_value = $this->prepare_text_for_parse_mode( $v, $parse_mode );
            $message = str_replace( '{field_' . $placeholder_key . '}', $prepared_value, $message );
        }

        $message = $this->apply_filters_safely( 'tn_cf7_message', $message, $contact_form, $sanitized_data );

        $this->send_message_to_configured_chats( $message );
    }

    private function enqueue_message( array $chats, string $text, string $parse_mode ) {
        $queue = $this->get_queue();
        $now = time();

        $queue = array_filter( $queue, function( $job ) use ( $now ) {
            return isset( $job['created'] ) && ( $now - (int) $job['created'] ) < $this->queue_job_ttl;
        } );

        if ( count( $queue ) >= $this->queue_limit ) {
            return new WP_Error( 'tn_queue_full', __( 'Очередь отправки переполнена.', 'telegram-notify' ) );
        }

        $queue[] = [
            'chats'        => $chats,
            'text'         => $text,
            'parse_mode'   => $parse_mode,
            'attempts'     => 0,
            'created'      => $now,
            'next_attempt' => $now,
        ];

        $this->set_queue( $queue );
        $this->schedule_queue_processing( $now + 5, 'enqueue' );
        return true;
    }

    public function process_message_queue() {
        if ( ! $this->acquire_queue_lock() ) {
            return;
        }

        $queue = $this->get_queue();
        if ( empty( $queue ) ) {
            $this->release_queue_lock();
            return;
        }

        $now = time();
        $new_queue = [];
        $next_run_timestamp = null;

        foreach ( $queue as $job ) {
            if ( ! isset( $job['chats'], $job['text'], $job['parse_mode'] ) ) {
                continue;
            }

            $job['attempts'] = isset( $job['attempts'] ) && is_int( $job['attempts'] ) ? $job['attempts'] : 0;
            $job['created'] = isset( $job['created'] ) ? (int) $job['created'] : $now;
            $job['next_attempt'] = isset( $job['next_attempt'] ) ? (int) $job['next_attempt'] : $now;

            if ( ( $now - $job['created'] ) > $this->queue_job_ttl ) {
                $this->debug_log( 'process_queue drop: ttl exceeded' );
                continue;
            }

            if ( $job['attempts'] >= 3 ) {
                $this->debug_log( 'process_queue drop: max attempts reached' );
                continue;
            }

            if ( $job['next_attempt'] > $now ) {
                $new_queue[] = $job;
                $next_run_timestamp = is_null( $next_run_timestamp ) ? $job['next_attempt'] : min( $next_run_timestamp, $job['next_attempt'] );
                continue;
            }

            $res = $this->do_send( $job['chats'], $job['text'], $job['parse_mode'], false );
            if ( is_wp_error( $res ) ) {
                $job['attempts']++;
                $job['next_attempt'] = $now + $this->get_queue_retry_delay( $job['attempts'] );
                $new_queue[] = $job;
                $next_run_timestamp = is_null( $next_run_timestamp ) ? $job['next_attempt'] : min( $next_run_timestamp, $job['next_attempt'] );
                update_option( $this->option_prefix . 'last_error', $res->get_error_message() );
                $this->debug_log( 'process_queue retry: ' . $res->get_error_message() );
            }
        }

        $this->set_queue( $new_queue );
        $this->release_queue_lock();

        if ( ! empty( $new_queue ) ) {
            if ( is_null( $next_run_timestamp ) ) {
                $next_run_timestamp = $now + $this->get_queue_retry_delay( 1 );
            }
            $this->schedule_queue_processing( $next_run_timestamp, 'process' );
        }
    }

    private function acquire_queue_lock(): bool {
        if ( get_transient( $this->queue_lock_transient_key ) ) {
            return false;
        }
        set_transient( $this->queue_lock_transient_key, 1, $this->queue_lock_ttl );
        return true;
    }

    private function release_queue_lock(): void {
        delete_transient( $this->queue_lock_transient_key );
    }

    private function schedule_queue_processing( int $timestamp, string $context = '' ) {
        $existing = wp_next_scheduled( 'tn_enh_send_queued_messages' );
        if ( ! $existing || $existing > $timestamp ) {
            wp_schedule_single_event( $timestamp, 'tn_enh_send_queued_messages' );
            $this->debug_log( 'queue scheduled (' . $context . ') at ' . gmdate( 'c', $timestamp ) );
        }
    }

    private function get_queue(): array {
        $queue = get_transient( $this->queue_transient_key );
        if ( ! is_array( $queue ) ) {
            $queue = [];
        }
        return $queue;
    }

    private function set_queue( array $queue ): void {
        if ( empty( $queue ) ) {
            delete_transient( $this->queue_transient_key );
            return;
        }
        set_transient( $this->queue_transient_key, $queue, $this->queue_storage_ttl );
    }

    private function get_queue_retry_delay( int $attempt ): int {
        $attempt = max( 1, $attempt );
        return min( 300, 30 * $attempt );
    }

    private function get_queue_status_overview(): array {
        $queue = $this->get_queue();
        $count = count( $queue );
        $now = time();
        $earliest_attempt = null;

        foreach ( $queue as $job ) {
            if ( ! isset( $job['next_attempt'] ) ) {
                $earliest_attempt = $now;
                break;
            }

            $attempt_time = (int) $job['next_attempt'];
            if ( $attempt_time <= 0 ) {
                $attempt_time = $now;
            }

            if ( is_null( $earliest_attempt ) || $attempt_time < $earliest_attempt ) {
                $earliest_attempt = $attempt_time;
            }
        }

        $scheduled = wp_next_scheduled( 'tn_enh_send_queued_messages' );

        return [
            'count'            => $count,
            'earliest_attempt' => $earliest_attempt,
            'scheduled'        => $scheduled ? (int) $scheduled : null,
        ];
    }

    private function do_send( array $chats, string $text, string $parse_mode, bool $blocking = true ) {
        $token = get_option( $this->option_prefix . 'bot_token' );
        if ( defined( 'TN_TELEGRAM_BOT_TOKEN' ) && TN_TELEGRAM_BOT_TOKEN ) $token = TN_TELEGRAM_BOT_TOKEN;
        if ( empty( $token ) ) return new WP_Error( 'tn_no_token', __( 'Bot token not configured', 'telegram-notify' ) );

        $results = [];
        $all_success = true;
        foreach ( $chats as $chat ) {
            $chat = trim( $chat );
            if ( $chat === '' ) continue;

            $url = 'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage';

            $body = [ 'chat_id' => $chat, 'text' => $text, 'parse_mode' => $parse_mode, 'disable_web_page_preview' => true ];

            $args = [ 'headers' => [ 'Content-Type' => 'application/json; charset=utf-8' ], 'body' => wp_json_encode( $body ), 'timeout' => 15, 'blocking' => $blocking ];

            $response = wp_remote_post( $url, $args );

            if ( is_wp_error( $response ) ) {
                $msg = 'HTTP error: ' . $response->get_error_message();
                update_option( $this->option_prefix . 'last_error', $msg );
                $this->debug_log( "do_send error: {$msg} (chat={$chat})" );
                $results[ $chat ] = $response;
                $all_success = false;
                continue;
            }

            if ( true === $response ) {
                $this->debug_log( "do_send non-blocking request queued: chat={$chat}" );
                $results[ $chat ] = [ 'http_code' => null, 'body' => null ];
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            $results[ $chat ] = [ 'http_code' => $code, 'body' => $body ];

            $decoded = json_decode( $body, true );
            if ( is_array( $decoded ) && isset( $decoded['ok'] ) && $decoded['ok'] === false ) {
                $desc = $decoded['description'] ?? 'unknown';
                $err = "Telegram API error for chat {$chat}: {$desc}";
                update_option( $this->option_prefix . 'last_error', $err );
                $this->debug_log( "do_send api_error: {$err}" );
                $all_success = false;
            } else {
                $this->debug_log( "do_send success: chat={$chat}, http={$code}" );
                if ( 200 !== $code ) {
                    $all_success = false;
                }
            }
        }

        if ( $blocking && $all_success ) {
            $this->clear_debug_log();
        }

        return $results;
    }

    private function is_wc_notifications_enabled(): bool {
        $value = get_option( $this->option_prefix . 'enable_wc_notifications', true );
        return (bool) $value;
    }

    private function is_cf7_notifications_enabled(): bool {
        $value = get_option( $this->option_prefix . 'enable_cf7_notifications', true );
        return (bool) $value;
    }

    private function get_cf7_enabled_form_ids(): array {
        $raw = get_option( $this->option_prefix . 'cf7_enabled_forms', '' );

        if ( is_array( $raw ) ) {
            $raw = implode( "\n", array_map( 'strval', $raw ) );
        }

        $normalized = $this->sanitize_cf7_form_ids( $raw );
        if ( '' === $normalized ) {
            return [];
        }

        $ids = preg_split( '/[\r\n]+/', $normalized );
        $ids = is_array( $ids ) ? array_filter( array_map( 'intval', $ids ) ) : [];
        return array_values( array_unique( $ids ) );
    }

    private function split_chat_ids( string $raw ): array {
        $raw = trim( $raw );
        if ( $raw === '' ) {
            return [];
        }
        // Split by commas, semicolons, or line breaks.
        $parts = preg_split( '/[\\r\\n,;]+/', $raw );
        $parts = array_map( 'trim', $parts );
        $parts = array_filter( $parts );
        return $parts;
    }

    private function normalize_plain_text( string $text ): string {
        $decoded = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $stripped = wp_strip_all_tags( $decoded );
        return preg_replace( '/\s+/u', ' ', trim( $stripped ) );
    }
    private function prepare_text_for_parse_mode( string $text, string $parse_mode ): string {
        if ( 'MarkdownV2' === $parse_mode ) {
            return $this->escape_markdown_v2( sanitize_textarea_field( $text ) );
        }

        return esc_html( $text );
    }

    private function prepare_url_for_parse_mode( string $url, string $parse_mode ): string {
        if ( 'MarkdownV2' === $parse_mode ) {
            $sanitized = esc_url_raw( $url );
            return $this->escape_markdown_v2( (string) $sanitized );
        }

        return esc_url( $url );
    }

    private function apply_filters_safely( string $tag, $value, ...$args ) {
        if ( ! has_filter( $tag ) ) {
            return $value;
        }

        try {
            return apply_filters( $tag, $value, ...$args );
        } catch ( TypeError $e ) {
            $this->debug_log( sprintf( '%s TypeError: %s', $tag, $e->getMessage() ) );
        } catch ( Throwable $e ) {
            $this->debug_log( sprintf( '%s exception: %s', $tag, $e->getMessage() ) );
        }

        return $value;
    }

    private function send_message_to_configured_chats( string $message ) {
        $raw_chats = get_option( $this->option_prefix . 'chat_ids' );
        if ( defined( 'TN_TELEGRAM_CHAT_IDS' ) && TN_TELEGRAM_CHAT_IDS ) {
            $raw_chats = TN_TELEGRAM_CHAT_IDS;
        }
        $chats = $this->split_chat_ids( (string) $raw_chats );
        if ( empty( $chats ) ) {
            return new WP_Error( 'tn_no_chat', __( 'Chat ID(s) not configured', 'telegram-notify' ) );
        }

        $parse_mode = get_option( $this->option_prefix . 'parse_mode' );
        if ( ! in_array( $parse_mode, [ 'HTML', 'MarkdownV2' ], true ) ) {
            $parse_mode = 'HTML';
        }

        // When HTML is selected, sanitize the message with wp_kses to keep only safe tags accepted by Telegram.
        if ( 'HTML' === $parse_mode ) {
            $allowed_tags = [
                'b' => [],
                'strong' => [],
                'i' => [],
                'em' => [],
                'u' => [],
                'code' => [],
                'pre' => [],
                'a' => [ 'href' => [] ],
                'br' => [],
            ];
            $message = wp_kses( $message, $allowed_tags );
        }

        $message = $this->apply_filters_safely( 'tn_final_message', $message );

        $send_async = get_option( $this->option_prefix . 'send_async' );
        $send_async = $send_async ? true : false;

        if ( $send_async ) {
            return $this->enqueue_message( $chats, $message, $parse_mode );
        }

        return $this->do_send( $chats, $message, $parse_mode, true );
    }


    private function escape_markdown_v2( string $text ): string {
        // Characters that must be escaped when using MarkdownV2.
        $chars = [ '_','*','[',']','(',')','~','`','>','#','+','-','=','|','{','}','.','!' ];
        $escaped = '';
        $len = mb_strlen( $text );
        for ( $i = 0; $i < $len; $i++ ) {
            $ch = mb_substr( $text, $i, 1 );
            if ( in_array( $ch, $chars, true ) ) {
                $escaped .= '\\' . $ch; // add a backslash before the symbol
            } else {
                $escaped .= $ch;
            }
        }
        return $escaped;
    }

    public function admin_resend_order() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Нет доступа' );
        $order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;
        $nonce = isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'tn_resend_order_' . $order_id ) ) wp_die( 'Неверный nonce' );
        if ( $order_id <= 0 ) wp_die( 'order_id не указан' );

        if ( ! function_exists( 'wc_get_order' ) ) wp_die( 'WooCommerce недоступен' );
        $order = wc_get_order( $order_id );
        if ( ! $order ) wp_die( 'Заказ не найден' );

        $items_arr = [];
        foreach ( $order->get_items() as $item ) {
            $qty = method_exists( $item, 'get_quantity' ) ? $item->get_quantity() : ( isset( $item['qty'] ) ? $item['qty'] : 1 );
            $name = method_exists( $item, 'get_name' ) ? $item->get_name() : ( is_array( $item ) && isset( $item['name'] ) ? $item['name'] : '' );
            $items_arr[] = "{$qty} × {$name}";
        }
        $items_text_raw = implode( "\n", $items_arr );

        $template = get_option( $this->option_prefix . 'order_template' );
        if ( ! $template ) {
            $template = "<b>Новый заказ #{order_id}</b>
Статус: {to_status}
Клиент: {customer_name}
Телефон: {phone}
E-mail: {email}
Сумма: {total}
Товары:
{items}
Ссылка: {order_link}";
        }

        $order_total_formatted = $order->get_formatted_order_total();
        $order_total_plain = wp_strip_all_tags( $order_total_formatted );
        $order_total_plain = str_replace( [ '&nbsp;', '&#160;', "\u{00A0}" ], ' ', $order_total_plain );
        $order_total = $this->normalize_plain_text( $order_total_plain );

        $parse_mode = get_option( $this->option_prefix . 'parse_mode', 'HTML' );
        if ( ! in_array( $parse_mode, [ 'HTML', 'MarkdownV2' ], true ) ) {
            $parse_mode = 'HTML';
        }

        $raw_customer = trim( (string) $order->get_formatted_billing_full_name() ?: ( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) );
        $raw_phone = $order->get_billing_phone();
        $raw_email = $order->get_billing_email();
        $order_link_raw = admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' );

        $customer_name = $this->prepare_text_for_parse_mode( $raw_customer, $parse_mode );
        $phone = $this->prepare_text_for_parse_mode( $raw_phone, $parse_mode );
        $email = $this->prepare_text_for_parse_mode( $raw_email, $parse_mode );
        $total = $this->prepare_text_for_parse_mode( $order_total, $parse_mode );
        $items_text = $this->prepare_text_for_parse_mode( $items_text_raw, $parse_mode );
        $order_link = $this->prepare_url_for_parse_mode( $order_link_raw, $parse_mode );

        $placeholders = [
            '{order_id}' => $order->get_id(),
            '{to_status}' => $order->get_status(),
            '{customer_name}' => $customer_name,
            '{phone}' => $phone,
            '{email}' => $email,
            '{total}' => $total,
            '{items}' => $items_text,
            '{order_link}' => $order_link,
        ];

        $message = strtr( $template, $placeholders );
        $this->debug_log( "admin_resend_order: manual send order {$order->get_id()}" );

        $this->send_message_to_configured_chats( $message );

        $redirect = admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' );
        wp_safe_redirect( $redirect );
        exit;
    }
    private function build_status_key( string $to_status ): string {
        return 'status:' . sanitize_key( $to_status );
    }

    /**
     * Checks if a notification has already been sent for the order and status.
     */
    private function has_sent_for_status( int $order_id, string $to_status, ?string $from_status = null ): bool {
        $sent = get_post_meta( $order_id, '_tn_enh_sent_statuses', true );
        if ( ! is_array( $sent ) ) {
            $sent = [];
        }

        $normalized_key = $this->build_status_key( $to_status );
        if ( in_array( $normalized_key, $sent, true ) ) {
            return true;
        }

        $needs_upgrade = false;
        foreach ( $sent as $idx => $stored_key ) {
            if ( $stored_key === $to_status ) {
                unset( $sent[ $idx ] );
                $needs_upgrade = true;
                continue;
            }

            if ( strpos( $stored_key, ':' ) !== false ) {
                [ $stored_to ] = explode( ':', $stored_key, 2 );
                if ( $stored_to === $to_status ) {
                    unset( $sent[ $idx ] );
                    $needs_upgrade = true;
                }
            }
        }

        if ( $needs_upgrade ) {
            $sent[] = $normalized_key;
            $sent = array_values( array_unique( $sent ) );
            update_post_meta( $order_id, '_tn_enh_sent_statuses', $sent );
            return true;
        }

        return false;
    }

    /**
     * Indicates that a notification has been sent for the status of the order.
     */
    private function mark_sent_for_status( int $order_id, string $to_status, ?string $from_status = null ): void {
        $sent = get_post_meta( $order_id, '_tn_enh_sent_statuses', true );
        if ( ! is_array( $sent ) ) {
            $sent = [];
        }

        $normalized_key = $this->build_status_key( $to_status );

        foreach ( $sent as $idx => $stored_key ) {
            if ( $stored_key === $to_status ) {
                unset( $sent[ $idx ] );
                continue;
            }
            if ( strpos( $stored_key, ':' ) !== false ) {
                [ $stored_to ] = explode( ':', $stored_key, 2 );
                if ( $stored_to === $to_status ) {
                    unset( $sent[ $idx ] );
                }
            }
        }

        if ( ! in_array( $normalized_key, $sent, true ) ) {
            $sent[] = $normalized_key;
            $sent = array_values( array_unique( $sent ) );
            update_post_meta( $order_id, '_tn_enh_sent_statuses', $sent );
        }
    }
    private function debug_log( string $text ) {
        try {
            $entries = get_transient( $this->debug_transient_key );
            if ( ! is_array( $entries ) ) {
                $entries = [];
            }
            $stamp = gmdate( 'd-m-Y H:i:s' );
            array_unshift( $entries, "{$stamp} - {$text}" );
            $entries = array_slice( $entries, 0, 50 );
            $payload = implode( "\n", $entries );
            if ( strlen( $payload ) > 5000 ) {
                $payload = substr( $payload, 0, 5000 );
                $entries = explode( "\n", $payload );
            }
            set_transient( $this->debug_transient_key, $entries, $this->debug_storage_ttl );
        } catch ( Throwable $e ) {
            // ignore
        }
    }

    private function clear_debug_log(): void {
        delete_transient( $this->debug_transient_key );
    }

    private function get_debug_log(): string {
        $entries = get_transient( $this->debug_transient_key );
        if ( is_array( $entries ) ) {
            return implode( "\n", $entries );
        }
        $legacy = get_option( $this->option_prefix . 'last_debug', '' );
        if ( $legacy ) {
            $normalized = explode( "\n", str_replace( "\r", '', $legacy ) );
            $normalized = array_slice( array_filter( $normalized ), 0, 50 );
            set_transient( $this->debug_transient_key, $normalized, $this->debug_storage_ttl );
            delete_option( $this->option_prefix . 'last_debug' );
            return implode( "\n", $normalized );
        }
        return '';
    }

}

add_action( 'plugins_loaded', function() {
    global $tn_telegram_notify_enh;
    $tn_telegram_notify_enh = new TN_Telegram_Notify_Enhanced();
} );
