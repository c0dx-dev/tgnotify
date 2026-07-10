from pathlib import Path
import re

path = Path('tgnotify2/telegram_notify_enhanced.php')
text = path.read_text(encoding='utf-8')


def replace_once(old: str, new: str, label: str) -> None:
    global text
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f'{label}: expected 1 occurrence, found {count}')
    text = text.replace(old, new, 1)


def regex_once(pattern: str, replacement: str, label: str) -> None:
    global text
    text, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
    if count != 1:
        raise RuntimeError(f'{label}: expected 1 match, found {count}')


replace_once(' * Version:     2.2.0', ' * Version:     2.3.0', 'version')

replace_once(
    "        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );\n        add_action( 'admin_init', [ $this, 'register_settings' ] );",
    "        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );\n        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_action_links' ] );\n        add_action( 'admin_init', [ $this, 'register_settings' ] );",
    'plugin action link hook',
)

replace_once(
    "    public function add_admin_menu() {\n        add_options_page(\n            __( 'Telegram Notify', 'telegram-notify' ),\n            __( 'Telegram Notify', 'telegram-notify' ),\n            'manage_options',\n            $this->settings_page_slug,\n            [ $this, 'settings_page' ]\n        );\n    }",
    "    public function add_admin_menu() {\n        add_menu_page(\n            __( 'Telegram Notify', 'telegram-notify' ),\n            __( 'Telegram Notify', 'telegram-notify' ),\n            'manage_options',\n            $this->settings_page_slug,\n            [ $this, 'settings_page' ],\n            'dashicons-format-chat',\n            81\n        );\n    }\n\n    private function get_settings_page_url(): string {\n        return admin_url( 'admin.php?page=' . $this->settings_page_slug );\n    }\n\n    public function plugin_action_links( array $links ): array {\n        $settings_link = sprintf(\n            '<a href=\"%1$s\">%2$s</a>',\n            esc_url( $this->get_settings_page_url() ),\n            esc_html__( 'Настройки', 'telegram-notify' )\n        );\n        array_unshift( $links, $settings_link );\n        return $links;\n    }",
    'admin menu method',
)

replace_once(
    "        register_setting( $this->settings_group, $this->option_prefix . 'chat_ids', [\n            'type'              => 'string',\n            'sanitize_callback' => [ $this, 'sanitize_chat_ids' ],\n            'default'           => '',\n        ] );",
    "        register_setting( $this->settings_group, $this->option_prefix . 'chat_ids', [\n            'type'              => 'string',\n            'sanitize_callback' => [ $this, 'sanitize_chat_ids' ],\n            'default'           => '',\n        ] );\n\n        register_setting( $this->settings_group, $this->option_prefix . 'chat_recipients', [\n            'type'              => 'array',\n            'sanitize_callback' => [ $this, 'sanitize_chat_recipients' ],\n            'default'           => [],\n        ] );",
    'recipient setting registration',
)

regex_once(
    r"    public function sanitize_chat_ids\( \$value \): string \{.*?\n    \}\n\n    public function sanitize_parse_mode",
    """    private function sanitize_single_chat_id( $value ): string {
        $value = is_scalar( $value ) ? trim( (string) $value ) : '';
        $value = preg_replace( '/[^0-9A-Za-z_@\\-]/', '', $value );

        if ( ! is_string( $value ) || '' === $value ) {
            return '';
        }

        if ( preg_match( '/^-?\\d+$/', $value ) || preg_match( '/^@[A-Za-z0-9_]{5,}$/', $value ) ) {
            return $value;
        }

        return '';
    }

    private function parse_chat_id_list( $value ): array {
        if ( is_array( $value ) ) {
            $value = implode( "\\n", array_map( 'strval', $value ) );
        }

        if ( ! is_string( $value ) ) {
            return [];
        }

        $parts = preg_split( '/[\\r\\n,;]+/', $value ) ?: [];
        $ids = [];
        foreach ( $parts as $part ) {
            $id = $this->sanitize_single_chat_id( $part );
            if ( '' !== $id ) {
                $ids[] = $id;
            }
        }

        return array_values( array_unique( $ids ) );
    }

    public function sanitize_chat_ids( $value ): string {
        return implode( "\\n", $this->parse_chat_id_list( $value ) );
    }

    public function sanitize_chat_recipients( $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }

        $recipients = [];
        foreach ( $value as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $id = $this->sanitize_single_chat_id( $row['id'] ?? '' );
            if ( '' === $id ) {
                continue;
            }

            $label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
            if ( ! isset( $recipients[ $id ] ) ) {
                $recipients[ $id ] = [
                    'id'    => $id,
                    'label' => $label,
                ];
            }
        }

        return array_values( $recipients );
    }

    private function get_chat_recipients(): array {
        $stored = get_option( $this->option_prefix . 'chat_recipients', [] );
        $recipients = $this->sanitize_chat_recipients( $stored );
        if ( ! empty( $recipients ) ) {
            return $recipients;
        }

        $legacy_ids = $this->parse_chat_id_list( get_option( $this->option_prefix . 'chat_ids', '' ) );
        return array_map( static function( string $id ): array {
            return [
                'id'    => $id,
                'label' => '',
            ];
        }, $legacy_ids );
    }

    private function get_configured_chat_ids(): array {
        if ( defined( 'TN_TELEGRAM_CHAT_IDS' ) && TN_TELEGRAM_CHAT_IDS ) {
            return $this->parse_chat_id_list( TN_TELEGRAM_CHAT_IDS );
        }

        $recipients = $this->get_chat_recipients();
        $ids = array_map( static function( array $recipient ): string {
            return (string) ( $recipient['id'] ?? '' );
        }, $recipients );

        return array_values( array_unique( array_filter( $ids ) ) );
    }

    public function sanitize_parse_mode""",
    'chat sanitizers and recipients',
)

regex_once(
    r"    public function render_bot_token_field\(\) \{.*?\n    \}\n\n    public function render_chat_ids_field",
    """    public function render_bot_token_field() {
        $option_name = $this->option_prefix . 'bot_token';
        $value = (string) get_option( $option_name, '' );

        if ( defined( 'TN_TELEGRAM_BOT_TOKEN' ) && TN_TELEGRAM_BOT_TOKEN ) {
            printf(
                '<input type="password" id="tn_enh_bot_token" value="%1$s" class="regular-text" disabled autocomplete="new-password" />' .
                '<input type="hidden" name="%2$s" value="__TN_KEEP_EXISTING__" />' .
                '<p class="description">%3$s</p>',
                esc_attr( '••••••••••••' ),
                esc_attr( $option_name ),
                esc_html__( 'Токен задан через константу TN_TELEGRAM_BOT_TOKEN и не выводится в интерфейсе.', 'telegram-notify' )
            );
        } else {
            printf(
                '<input type="password" id="tn_enh_bot_token" name="%1$s" value="%2$s" class="regular-text" autocomplete="new-password" />' .
                '<p class="description">%3$s</p>',
                esc_attr( $option_name ),
                esc_attr( $value ),
                esc_html__( 'Token от BotFather, например 123456:ABC-DEF...', 'telegram-notify' )
            );
        }

        echo '<p class="description">' . esc_html__( 'Создать бота и получить токен:', 'telegram-notify' ) . ' <a href="https://t.me/BotFather" target="_blank" rel="noopener noreferrer">@BotFather</a>.</p>';
    }

    public function render_chat_ids_field""",
    'bot token field',
)

regex_once(
    r"    public function render_chat_ids_field\(\) \{.*?\n    \}\n\n    public function render_parse_mode_field",
    """    public function render_chat_ids_field() {
        $option_name = $this->option_prefix . 'chat_recipients';
        $recipients = $this->get_chat_recipients();
        if ( empty( $recipients ) ) {
            $recipients[] = [ 'id' => '', 'label' => '' ];
        }

        echo '<div id="tn-enh-chat-recipient-list" data-next-index="' . esc_attr( (string) count( $recipients ) ) . '">';
        foreach ( $recipients as $index => $recipient ) {
            $id = (string) ( $recipient['id'] ?? '' );
            $label = (string) ( $recipient['label'] ?? '' );
            echo '<div class="tn-enh-chat-recipient-row">';
            printf(
                '<input type="text" class="regular-text tn-enh-chat-id" name="%1$s[%2$d][id]" value="%3$s" placeholder="%4$s" autocomplete="off" />',
                esc_attr( $option_name ),
                (int) $index,
                esc_attr( $id ),
                esc_attr__( 'Chat ID', 'telegram-notify' )
            );
            printf(
                '<input type="text" class="regular-text tn-enh-chat-label" name="%1$s[%2$d][label]" value="%3$s" placeholder="%4$s" />',
                esc_attr( $option_name ),
                (int) $index,
                esc_attr( $label ),
                esc_attr__( 'Имя, сотрудник или канал', 'telegram-notify' )
            );
            echo '<button type="button" class="button-link-delete tn-enh-remove-recipient">' . esc_html__( 'Удалить', 'telegram-notify' ) . '</button>';
            echo '</div>';
        }
        echo '</div>';

        echo '<p><button type="button" class="button" id="tn-enh-add-recipient">' . esc_html__( 'Добавить получателя', 'telegram-notify' ) . '</button></p>';
        echo '<details class="tn-enh-bulk-import"><summary>' . esc_html__( 'Массовый ввод Chat ID', 'telegram-notify' ) . '</summary>';
        echo '<p class="description">' . esc_html__( 'Вставьте ID через новую строку, запятую или точку с запятой. Можно использовать формат ID | Имя.', 'telegram-notify' ) . '</p>';
        echo '<textarea id="tn-enh-chat-bulk" rows="4" class="large-text code" placeholder="200013814 | Иван&#10;-1002987209369 | Рабочая группа"></textarea>';
        echo '<p><button type="button" class="button" id="tn-enh-import-recipients">' . esc_html__( 'Преобразовать в строки', 'telegram-notify' ) . '</button></p>';
        echo '</details>';

        echo '<p class="description">' . esc_html__( 'Личный Chat ID обычно выглядит как 200013814. ID группы или канала обычно отрицательный и часто начинается с -100, например -1002987209369. Для публичного канала также можно указать @channelusername.', 'telegram-notify' ) . '</p>';
        echo '<p class="description">' . esc_html__( 'Произвольное имя используется только в админке и не передаётся в Telegram.', 'telegram-notify' ) . '</p>';

        if ( defined( 'TN_TELEGRAM_CHAT_IDS' ) && TN_TELEGRAM_CHAT_IDS ) {
            echo '<p class="description"><strong>' . esc_html__( 'Внимание:', 'telegram-notify' ) . '</strong> ' . esc_html__( 'При отправке используется список из константы TN_TELEGRAM_CHAT_IDS. Строки ниже сохраняются как подписи и резервная конфигурация.', 'telegram-notify' ) . '</p>';
        }

        echo '<div id="tn_enh_chat_ids_feedback" class="notice notice-error" style="display:none;margin-top:8px;"><p></p></div>';
    }

    public function render_parse_mode_field""",
    'chat recipient field',
)

replace_once(
    "        $keys = [ 'bot_token', 'chat_ids', 'parse_mode', 'send_async', 'order_template', 'cf7_template', 'last_error' ];",
    "        $keys = [ 'bot_token', 'chat_ids', 'chat_recipients', 'parse_mode', 'send_async', 'order_template', 'cf7_template', 'last_error' ];",
    'get_options keys',
)

replace_once(
    "                    #tn_enh_chat_ids_feedback {\n                        max-width: 640px;\n                    }",
    "                    #tn_enh_chat_ids_feedback {\n                        max-width: 760px;\n                    }\n                    .tn-enh-chat-recipient-row {\n                        display: grid;\n                        grid-template-columns: minmax(220px, 1fr) minmax(220px, 1fr) auto;\n                        gap: 8px;\n                        align-items: center;\n                        max-width: 900px;\n                        margin-bottom: 8px;\n                    }\n                    .tn-enh-chat-recipient-row .regular-text {\n                        width: 100%;\n                    }\n                    .tn-enh-bulk-import {\n                        max-width: 760px;\n                        margin: 12px 0;\n                    }\n                    .tn-enh-bulk-import summary {\n                        cursor: pointer;\n                        font-weight: 600;\n                    }\n                    @media (max-width: 782px) {\n                        .tn-enh-chat-recipient-row {\n                            grid-template-columns: 1fr;\n                        }\n                        .tn-enh-chat-recipient-row .tn-enh-remove-recipient {\n                            justify-self: start;\n                        }\n                    }",
    'recipient styles',
)

regex_once(
    r"                        var \$chatField = \$\( '#tn_enh_chat_ids' \);.*?                            validateChatIds\(\);\n                        \}",
    """                        var $recipientList = $( '#tn-enh-chat-recipient-list' );
                        var $feedback = $( '#tn_enh_chat_ids_feedback' );
                        var nextRecipientIndex = parseInt( $recipientList.attr( 'data-next-index' ), 10 ) || 0;
                        var invalidMessage = '<?php echo esc_js( __( 'Проверьте Chat ID. Допускается числовой ID, отрицательный ID группы/канала или @username. Неверные значения: ', 'telegram-notify' ) ); ?>';
                        var deleteMessage = '<?php echo esc_js( __( 'Удалить этого получателя?', 'telegram-notify' ) ); ?>';

                        function createRecipientRow( id, label ) {
                            var index = nextRecipientIndex++;
                            var $row = $( '<div>', { class: 'tn-enh-chat-recipient-row' } );
                            $( '<input>', {
                                type: 'text',
                                class: 'regular-text tn-enh-chat-id',
                                name: '<?php echo esc_js( $this->option_prefix . 'chat_recipients' ); ?>[' + index + '][id]',
                                value: id || '',
                                placeholder: '<?php echo esc_js( __( 'Chat ID', 'telegram-notify' ) ); ?>',
                                autocomplete: 'off'
                            } ).appendTo( $row );
                            $( '<input>', {
                                type: 'text',
                                class: 'regular-text tn-enh-chat-label',
                                name: '<?php echo esc_js( $this->option_prefix . 'chat_recipients' ); ?>[' + index + '][label]',
                                value: label || '',
                                placeholder: '<?php echo esc_js( __( 'Имя, сотрудник или канал', 'telegram-notify' ) ); ?>'
                            } ).appendTo( $row );
                            $( '<button>', {
                                type: 'button',
                                class: 'button-link-delete tn-enh-remove-recipient',
                                text: '<?php echo esc_js( __( 'Удалить', 'telegram-notify' ) ); ?>'
                            } ).appendTo( $row );
                            return $row;
                        }

                        function validateRecipientRows() {
                            var invalid = [];
                            $recipientList.find( '.tn-enh-chat-id' ).each( function() {
                                var $field = $( this );
                                var value = ( $field.val() || '' ).trim();
                                var valid = value === '' || /^(?:-?\\d+|@[A-Za-z0-9_]{5,})$/.test( value );
                                $field.toggleClass( 'tn-enh-invalid', ! valid );
                                if ( ! valid ) {
                                    invalid.push( value );
                                }
                            } );

                            if ( invalid.length ) {
                                $feedback.show().find( 'p' ).text( invalidMessage + invalid.join( ', ' ) );
                                return false;
                            }

                            $feedback.hide().find( 'p' ).text( '' );
                            return true;
                        }

                        $( '#tn-enh-add-recipient' ).on( 'click', function() {
                            $recipientList.append( createRecipientRow( '', '' ) );
                            $recipientList.find( '.tn-enh-chat-id' ).last().focus();
                        } );

                        $recipientList.on( 'click', '.tn-enh-remove-recipient', function() {
                            if ( ! window.confirm( deleteMessage ) ) {
                                return;
                            }

                            var $rows = $recipientList.find( '.tn-enh-chat-recipient-row' );
                            var $row = $( this ).closest( '.tn-enh-chat-recipient-row' );
                            if ( $rows.length > 1 ) {
                                $row.remove();
                            } else {
                                $row.find( 'input' ).val( '' );
                            }
                            validateRecipientRows();
                        } );

                        $( '#tn-enh-import-recipients' ).on( 'click', function() {
                            var raw = $( '#tn-enh-chat-bulk' ).val() || '';
                            var lines = raw.split( /[\\r\\n,;]+/ );
                            var existing = {};
                            $recipientList.find( '.tn-enh-chat-id' ).each( function() {
                                var id = ( $( this ).val() || '' ).trim();
                                if ( id ) {
                                    existing[ id ] = true;
                                }
                            } );

                            lines.forEach( function( line ) {
                                line = line.trim();
                                if ( ! line ) {
                                    return;
                                }

                                var parts = line.split( '|' );
                                var id = ( parts.shift() || '' ).trim();
                                var label = parts.join( '|' ).trim();
                                if ( ! id || existing[ id ] ) {
                                    return;
                                }

                                var $blankRow = $recipientList.find( '.tn-enh-chat-recipient-row' ).filter( function() {
                                    return ( $( this ).find( '.tn-enh-chat-id' ).val() || '' ).trim() === '';
                                } ).first();

                                if ( $blankRow.length ) {
                                    $blankRow.find( '.tn-enh-chat-id' ).val( id );
                                    $blankRow.find( '.tn-enh-chat-label' ).val( label );
                                } else {
                                    $recipientList.append( createRecipientRow( id, label ) );
                                }
                                existing[ id ] = true;
                            } );

                            $( '#tn-enh-chat-bulk' ).val( '' );
                            validateRecipientRows();
                        } );

                        $recipientList.on( 'input blur', '.tn-enh-chat-id', validateRecipientRows );
                        $( 'form[action="options.php"]' ).on( 'submit', function( event ) {
                            if ( ! validateRecipientRows() ) {
                                event.preventDefault();
                                $recipientList.find( '.tn-enh-invalid' ).first().focus();
                            }
                        } );
                        validateRecipientRows();""",
    'recipient javascript',
)

replace_once(
    "    private function split_chat_ids( string $raw ): array {\n        $raw = trim( $raw );\n        if ( $raw === '' ) {\n            return [];\n        }\n        // Split by commas, semicolons, or line breaks.\n        $parts = preg_split( '/[\\r\\n,;]+/', $raw );\n        $parts = array_map( 'trim', $parts );\n        $parts = array_filter( $parts );\n        return $parts;\n    }",
    "    private function split_chat_ids( string $raw ): array {\n        return $this->parse_chat_id_list( $raw );\n    }",
    'split chat ids',
)

replace_once(
    "        $raw_chats = get_option( $this->option_prefix . 'chat_ids' );\n        if ( defined( 'TN_TELEGRAM_CHAT_IDS' ) && TN_TELEGRAM_CHAT_IDS ) {\n            $raw_chats = TN_TELEGRAM_CHAT_IDS;\n        }\n        $chats = $this->split_chat_ids( (string) $raw_chats );",
    "        $chats = $this->get_configured_chat_ids();",
    'configured chat retrieval',
)

text = text.replace(
    "admin_url( 'options-general.php?page=' . $this->settings_page_slug )",
    "$this->get_settings_page_url()",
)

path.write_text(text, encoding='utf-8')
