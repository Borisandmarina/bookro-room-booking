<?php
/**
 * Plugin Name: BookRo Room Booking
 * Description: Conference room booking plugin based on hourly slots.
 * Version: 4.2.0
 * Text Domain: bookro-room-booking
 * Author: Boris Devin
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ------------------------------------------------------------
 * ADMIN AJAX SECURITY GUARD
 * ------------------------------------------------------------
 * Защита всех админских AJAX:
 * - проверка прав (только администратор)
 * - проверка nonce
 * ------------------------------------------------------------
 */
function br_admin_ajax_guard() {

    // 1️⃣ Проверка прав — ОБЯЗАТЕЛЬНО
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Access denied', 403 );
    }

    // 2️⃣ Если nonce передан — проверяем
    if ( isset( $_POST['_wpnonce'] ) ) {

        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );

        if ( ! wp_verify_nonce( $nonce, 'br_admin_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce', 403 );
        }
    }

    // Если nonce не передан — не блокируем
}
/**
 * ------------------------------------------------------------
 * Рендер страницы настроек admin-settings.php
 * ------------------------------------------------------------
 */
function br_render_admin_page() {
    require plugin_dir_path( __FILE__ ) . 'admin-settings.php';
}
/**
 * ------------------------------------------------------------
 * Рендер старницы экспорта admin-tab-export.php
 * ------------------------------------------------------------
 */
function br_render_export_page() {
	require BR_PLUGIN_PATH . 'admin-tab-export.php';
    
}
/**
 * ------------------------------------------------------------
 * Рендер страницы букингов admin-tab-bookings.php)
 * ------------------------------------------------------------
 */
function br_render_bookings_page() {
    require BR_PLUGIN_PATH . 'admin-tab-bookings.php';
}
/**
 * ------------------------------------------------------------
 * ГЛОБАЛЬНЫЕ КОНСТАНТЫ ПЛАГИНА
 * ------------------------------------------------------------
 */
define( 'BR_SLOT_DURATION_HOURS', 1 );
define( 'BR_SLOTS_PER_DAY', 24 );
define( 'BR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'BR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * ------------------------------------------------------------
 * ПОДКЛЮЧЕНИЕ УСТАНОВЩИКА БД
 * ------------------------------------------------------------
 */

require_once BR_PLUGIN_PATH . 'db-install.php';
require_once BR_PLUGIN_PATH . 'rules.php';
require_once BR_PLUGIN_PATH . 'slot-calculation.php';
require_once BR_PLUGIN_PATH . 'admin-global-settings.php';
require_once BR_PLUGIN_PATH . 'form.php';
require_once BR_PLUGIN_PATH . 'ajax-availability.php';
require_once BR_PLUGIN_PATH . 'ajax-calc-price-form.php';
require_once BR_PLUGIN_PATH . 'booking-ajax-submit.php';
require_once BR_PLUGIN_PATH . 'admin-ajax-export-mail.php';
require_once BR_PLUGIN_PATH . 'booking-export-email.php';
require_once BR_PLUGIN_PATH . 'admin-ajax-bookings.php';
require_once BR_PLUGIN_PATH . 'bookings-export-html.php';
require_once BR_PLUGIN_PATH . 'bookings-export-excel.php';
/**
 * ------------------------------------------------------------
 * АКТИВАЦИЯ ПЛАГИНА
 * ------------------------------------------------------------
 * - установка БД выполняется ТОЛЬКО один раз
 * - повторная активация БД не трогает
 */
function br_activate_plugin() {

    // флаг установки БД
    $installed = get_option( 'br_db_installed' );

    if ( $installed ) {
        // БД уже установлена — ничего не делаем
        return;
    }

    // установка таблиц
    br_install_database();

    // фиксируем, что БД установлена
    update_option( 'br_db_installed', 1 );
}

register_activation_hook( __FILE__, 'br_activate_plugin' );


/**
 * ------------------------------------------------------------
 * МЕНЮ В АДМИНКЕ
 * ------------------------------------------------------------
 */
function br_register_admin_menu() {

    add_menu_page(
    'Booking Room',
    'Booking Room',
    'manage_options',
    'booking-room',
    'br_render_admin_page',
    'dashicons-calendar-alt',
    81
);
	
add_submenu_page(
    'booking-room',
    'Bookings+Schedule',
    'Bookings+Schedule',
    'manage_options',
    'booking-room-bookings',
    'br_render_bookings_page'
);

add_submenu_page(
    'booking-room',
    'Settings+Schedule',
    'Settings+Schedule',
    'manage_options',
    'booking-room-settings',
    'br_render_admin_page'
);

add_submenu_page(
    'booking-room',
    'Export',
    'Export',
    'manage_options',
    'booking-room-export',
    'br_render_export_page'
);



}
function br_remove_duplicate_booking_room_submenu() {

    remove_submenu_page(
        'booking-room',
        'booking-room'
    );
}
add_action( 'admin_menu', 'br_remove_duplicate_booking_room_submenu', 999 );

add_action( 'admin_menu', 'br_register_admin_menu' );

add_action('wp_ajax_br_save_override', 'br_save_override');
add_action('wp_ajax_br_save_break', 'br_save_break');
add_action('wp_ajax_br_delete_override', 'br_delete_override');
add_action('wp_ajax_br_update_booking_status', 'br_update_booking_status');
add_action('wp_ajax_br_confirm_booking', 'br_confirm_booking');
// ⚠ Public endpoint (email confirmation link)
// Protected via SHA256 token (booking_id + created_at + AUTH_KEY)
add_action('wp_ajax_nopriv_br_confirm_booking', 'br_confirm_booking');
add_action( 'wp_ajax_br_rename_object', 'br_ajax_rename_object' );
add_action('wp_ajax_br_update_user_contact', 'br_update_user_contact');
add_action('wp_ajax_br_delete_user_contact', 'br_delete_user_contact');

/* ============================================================
   UPDATE USER CONTACT (INLINE EDIT)
   ============================================================ */
function br_update_user_contact() {
	br_admin_ajax_guard();

    if (
        ! isset( $_POST['_wpnonce'] ) ||
        ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
            'br_admin_nonce'
        )
    ) {
        wp_send_json_error( 'Invalid nonce', 403 );
    }

    global $wpdb;

    $table = $wpdb->prefix . '1br_user_contacts';

    $email = isset( $_POST['email'] )
    ? sanitize_email( wp_unslash( $_POST['email'] ) )
    : '';

$field = isset( $_POST['field'] )
    ? sanitize_key( wp_unslash( $_POST['field'] ) )
    : '';

$value = isset( $_POST['value'] )
    ? sanitize_text_field( wp_unslash( $_POST['value'] ) )
    : '';

    if (!$email) {
        wp_die();
    }

    // email редактировать нельзя
    $allowed_fields = ['company', 'first_name', 'last_name', 'phone'];
    if (!in_array($field, $allowed_fields, true)) {
        wp_die();
    }

    $wpdb->update(
        $table,
        [$field => $value],
        ['email' => $email],
        ['%s'],
        ['%s']
    );

    wp_die();
}

/* ============================================================
   DELETE USER CONTACT
   ============================================================ */
function br_delete_user_contact() {
	br_admin_ajax_guard();

    if (
        ! isset( $_POST['_wpnonce'] ) ||
        ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
            'br_admin_nonce'
        )
    ) {
        wp_send_json_error( 'Invalid nonce', 403 );
    }

    global $wpdb;

    $table = $wpdb->prefix . '1br_user_contacts';

    $email = isset( $_POST['email'] )
    ? sanitize_email( wp_unslash( $_POST['email'] ) )
    : '';
    if (!$email) {
        wp_die();
    }

    $wpdb->delete(
        $table,
        ['email' => $email],
        ['%s']
    );

    wp_die();
}

/**
 * ------------------------------------------------------------
 * AJAX: Create new object with default settings
 * Файл: (например) wp-content/plugins/booking-room/admin-ajax-objects.php
 *
 * Назначение:
 *  - создание нового объекта
 *  - инициализация всех обязательных таблиц,
 *    без которых форма бронирования не работает
 * ------------------------------------------------------------
 */
function br_ajax_create_object() {
    br_admin_ajax_guard();

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error('Access denied');
    }

    global $wpdb;

    /* ============================================================
       1. Определяем порядковый номер для имени объекта
       ============================================================ */
    $count = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}1br_objects"
    );

    $name = 'New Object ' . ($count + 1);

    /* ============================================================
       2. Создаём объект
       ============================================================ */
    $result = $wpdb->insert(
        $wpdb->prefix . '1br_objects',
        [
            'name'    => $name,
            'status'  => 'active',
            'address' => '',
            'email'   => '',
            'phone'   => ''
        ],
        ['%s','%s','%s','%s','%s']
    );

    if ( $result === false ) {
        wp_send_json_error($wpdb->last_error);
    }

    $object_id = (int) $wpdb->insert_id;

    

    /* ============================================================
       4. Day time coefficient
       ============================================================ */

	   $wpdb->insert(
        $wpdb->prefix . '1br_day_time_coeff',
        [
            'object_id'   => $object_id,
            'label'       => 'Coefficient (multiplier)',
            'coefficient' => 1.30
        ],
        ['%d','%s','%f']
    );

    /* ============================================================
       5. Rental rate
       ============================================================ */
    $wpdb->insert(
        $wpdb->prefix . '1br_rental_rate',
        [
            'object_id' => $object_id,
            'label'     => 'Working day hourly rate',
            'price'     => 15.00,
            'currency'  => 'EUR'
        ],
        ['%d','%s','%f','%s']
    );

    /* ============================================================
       6. Visitors count (обязательный селект формы)
       ============================================================ */
    $wpdb->insert(
        $wpdb->prefix . '1br_visitors_count',
        [
            'object_id'  => $object_id,
            'label'      => 'Up to 25 participants',
            'coef'       => 1.00,
            'is_visible' => 1,
            'sort_order' => 1
        ],
        ['%d','%s','%f','%d','%d']
    );

    $wpdb->insert(
        $wpdb->prefix . '1br_visitors_count',
        [
            'object_id'  => $object_id,
            'label'      => '26–50 participants',
            'coef'       => 1.80,
            'is_visible' => 1,
            'sort_order' => 2
        ],
        ['%d','%s','%f','%d','%d']
    );

    $wpdb->insert(
        $wpdb->prefix . '1br_visitors_count',
        [
            'object_id'  => $object_id,
            'label'      => 'More than 50 participants',
            'coef'       => 2.30,
            'is_visible' => 1,
            'sort_order' => 3
        ],
        ['%d','%s','%f','%d','%d']
    );

    /* ============================================================
       7. Services (обязательный селект формы)
       ============================================================ */
    $wpdb->insert(
        $wpdb->prefix . '1br_services',
        [
            'object_id'  => $object_id,
            'label'      => 'Video projector',
            'price'      => 50.00,
            'price_unit' => 'per_hour',
            'is_visible' => 1,
            'sort_order' => 0
        ],
        ['%d','%s','%f','%s','%d','%d']
    );

    $wpdb->insert(
        $wpdb->prefix . '1br_services',
        [
            'object_id'  => $object_id,
            'label'      => 'Wireless microphones',
            'price'      => 40.00,
            'price_unit' => 'per_hour',
            'is_visible' => 1,
            'sort_order' => 1
        ],
        ['%d','%s','%f','%s','%d','%d']
    );
	/* ============================================================
   8. Event types (обязательные типы событий)
   Таблица: wp_1br_event_types
   Назначение:
   - без записей форма бронирования не может выбрать тип события
   ============================================================ */

$wpdb->insert(
    $wpdb->prefix . '1br_event_types',
    [
        'object_id'  => $object_id,
        'label'      => 'Presentation',
        'is_visible' => 1,
        'sort_order' => 1
    ],
    ['%d','%s','%d','%d']
);

$wpdb->insert(
    $wpdb->prefix . '1br_event_types',
    [
        'object_id'  => $object_id,
        'label'      => 'Meeting',
        'is_visible' => 1,
        'sort_order' => 2
    ],
    ['%d','%s','%d','%d']
);


    /* ============================================================
       8. Ответ
       ============================================================ */
    wp_send_json_success([
        'object_id' => $object_id,
        'name'      => $name
    ]);
}



/**
 * ------------------------------------------------------------
 * AJAX: редактирование информации об объект в форме-попап на странице настроек admin-settings.php
 * Таблица: wp_1br_bookings
 * ------------------------------------------------------------
 */
add_action( 'wp_ajax_br_rename_object', 'br_ajax_rename_object' );

function br_ajax_rename_object() {
	br_admin_ajax_guard();


    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error();
    }

    $object_id = isset( $_POST['object_id'] )
    ? absint( wp_unslash( $_POST['object_id'] ) )
    : 0;

$name = isset( $_POST['name'] )
    ? sanitize_text_field( wp_unslash( $_POST['name'] ) )
    : '';

    if ( $object_id <= 0 || $name === '' ) {
        wp_send_json_error();
    }

    global $wpdb;

    $updated = $wpdb->update(
        $wpdb->prefix . '1br_objects',
        [ 'name' => $name ],
        [ 'id' => $object_id ],
        [ '%s' ],
        [ '%d' ]
    );

    if ( $updated === false ) {
        wp_send_json_error();
    }

    wp_send_json_success([ 'name' => $name ]);
}
add_action('wp_ajax_br_get_object', 'br_get_object');
function br_get_object() {
	br_admin_ajax_guard();


    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error();
    }

    if ( empty($_POST['object_id']) ) {
        wp_send_json_error();
    }

    global $wpdb;

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );

    $object = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT name, address, email, phone
             FROM {$wpdb->prefix}1br_objects
             WHERE id = %d",
            $object_id
        ),
        ARRAY_A
    );

    if ( ! $object ) {
        wp_send_json_error();
    }

    wp_send_json_success($object);
}
add_action('wp_ajax_br_update_object', 'br_update_object');
function br_update_object() {
	br_admin_ajax_guard();

    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error();
    }

    if ( empty($_POST['object_id']) ) {
        wp_send_json_error();
    }

    global $wpdb;

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );

    $data = [
    'name'    => isset( $_POST['name'] )
        ? sanitize_text_field( wp_unslash( $_POST['name'] ) )
        : '',
    'address' => isset( $_POST['address'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) )
        : '',
    'email'   => isset( $_POST['email'] )
        ? sanitize_email( wp_unslash( $_POST['email'] ) )
        : '',
    'phone'   => isset( $_POST['phone'] )
        ? sanitize_text_field( wp_unslash( $_POST['phone'] ) )
        : '',
];

    $updated = $wpdb->update(
        $wpdb->prefix . '1br_objects',
        $data,
        [ 'id' => $object_id ],
        [ '%s', '%s', '%s', '%s' ],
        [ '%d' ]
    );

    if ( $updated === false ) {
        wp_send_json_error();
    }

    wp_send_json_success();
}


/**
 * ------------------------------------------------------------
 * AJAX: Confirm booking (from Email / Telegram)
 * Таблица: wp_1br_bookings
 * ------------------------------------------------------------
 */

add_action('wp_ajax_nopriv_br_confirm_booking', 'br_confirm_booking');

function br_confirm_booking() {

    global $wpdb;

    if (
    empty( $_GET['booking_id'] ) ||
    empty( $_GET['token'] )
) {
    wp_die('Invalid confirmation link');
}

$booking_id_raw = wp_unslash( $_GET['booking_id'] );
$token_raw      = wp_unslash( $_GET['token'] );

// 🔒 Строгая проверка ID
if ( ! ctype_digit( $booking_id_raw ) ) {
    wp_die('Invalid booking ID');
}

$booking_id = (int) $booking_id_raw;
$token      = sanitize_text_field( $token_raw );

    // 🔒 Проверка длины токена SHA256
    if (strlen($token) !== 64) {
        wp_die('Invalid token');
    }

    // Получаем booking
    $booking = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, status, created_at
             FROM {$wpdb->prefix}1br_bookings
             WHERE id = %d
             LIMIT 1",
            $booking_id
        ),
        ARRAY_A
    );

    if (!$booking) {
        wp_die('Booking not found');
    }

    // 🔒 Разрешаем подтверждать только pending
    if ($booking['status'] !== 'pending') {
        wp_die('Booking already processed');
    }

    // Генерация ожидаемого токена
    $expected_token = hash(
        'sha256',
        $booking['id'] . '|' . $booking['created_at'] . '|' . AUTH_KEY
    );

    if (!hash_equals($expected_token, $token)) {
        wp_die('Invalid or expired token');
    }

    // Обновляем статус
    $updated = $wpdb->update(
        $wpdb->prefix . '1br_bookings',
        ['status' => 'confirmed'],
        ['id' => $booking_id],
        ['%s'],
        ['%d']
    );

    if ($updated === false) {
        wp_die('Database error');
    }

    require_once __DIR__ . '/booking-export-email.php';

br_export_booking_to_email_client_confirmed($booking_id);

    wp_die(
        '<h2>Booking confirmed</h2>
         <p>The booking has been successfully confirmed.</p>',
        'Booking confirmed',
        ['response' => 200]
    );
}


add_action('wp_ajax_br_delete_break', 'br_delete_break');
add_action('wp_ajax_br_reset_day', 'br_reset_day');
add_action('wp_ajax_br_check_holiday', 'br_check_holiday');

/*Инфо "Праздник" на шкале*/
function br_check_holiday() {
    br_admin_ajax_guard();
    global $wpdb;

    $date = isset( $_POST['date'] )
        ? sanitize_text_field( wp_unslash( $_POST['date'] ) )
        : '';

    $object_id = isset( $_POST['object_id'] )
        ? absint( wp_unslash( $_POST['object_id'] ) )
        : 0;

    if ( ! $date || ! $object_id ) {
        wp_send_json_success([
            'is_holiday'    => false,
            'name'          => null,
            'allow_booking' => 0,
        ]);
    }

    $table = $wpdb->prefix . '1br_holidays';

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT name, allow_booking
            FROM {$table}
            WHERE object_id = %d
              AND date = %s
            LIMIT 1
            ",
            $object_id,
            $date
        ),
        ARRAY_A
    );

    if ( $row ) {
        wp_send_json_success([
            'is_holiday'    => true,
            'name'          => $row['name'],
            'allow_booking' => (int) $row['allow_booking'],
        ]);
    }

    wp_send_json_success([
        'is_holiday'    => false,
        'name'          => null,
        'allow_booking' => 0,
    ]);
}


/* === AJAX: удаление диапазона (DB-driven, единая точка) === */
add_action('wp_ajax_br_remove_range', 'br_remove_range');

/* === AJAX: получение полного слепка шкалы из БД === */
add_action('wp_ajax_br_get_schedule_snapshot', 'br_get_schedule_snapshot');

function br_get_schedule_snapshot() {
	br_admin_ajax_guard();

    global $wpdb;

    if ( ! isset( $_POST['object_id'], $_POST['date'] ) ) {
    wp_send_json_error( 'Missing params' );
}

$object_id = absint( wp_unslash( $_POST['object_id'] ) );
$date      = sanitize_text_field( wp_unslash( $_POST['date'] ) );

    // SETTINGS
    $settings = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}1br_global_settings WHERE object_id = %d LIMIT 1",
            $object_id
        ),
        ARRAY_A
    );

    // OVERRIDES
    $overrides = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT *
            FROM {$wpdb->prefix}1br_overrides
            WHERE object_id = %d
              AND date_start <= %s
              AND date_end >= %s
            ",
            $object_id,
            $date,
            $date
        ),
        ARRAY_A
    );

    // BREAKS
    $breaks = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT *
            FROM {$wpdb->prefix}1br_breaks
            WHERE object_id = %d
              AND date_start <= %s
              AND date_end >= %s
            ",
            $object_id,
            $date,
            $date
        ),
        ARRAY_A
    );

    // BOOKINGS
    $bookings = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT *
            FROM {$wpdb->prefix}1br_bookings
            WHERE object_id = %d
              AND event_date = %s
              AND status IN ('pending','confirmed')
            ",
            $object_id,
            $date
        ),
        ARRAY_A
    );

    // ==================================================
    // ENRICH BOOKINGS (admin info blocks)
    // ==================================================

    if ( $bookings ) {

        // Event types
        $event_types = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, label FROM {$wpdb->prefix}1br_event_types WHERE object_id = %d",
                $object_id
            ),
            OBJECT_K
        );

        // Participants
        $participants = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, label FROM {$wpdb->prefix}1br_visitors_count WHERE object_id = %d",
                $object_id
            ),
            OBJECT_K
        );

        // Services
        $services = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, label FROM {$wpdb->prefix}1br_services WHERE object_id = %d",
                $object_id
            ),
            OBJECT_K
        );

        foreach ( $bookings as &$booking ) {

            // Event type
            if ( ! empty($booking['event_type_custom']) ) {
                $booking['event_type_label'] = $booking['event_type_custom'];
            } else {
                $et_id = (int) ($booking['event_type_id'] ?? 0);
                $booking['event_type_label'] =
                    isset($event_types[$et_id])
                        ? $event_types[$et_id]->label
                        : '';
            }

            // Participants
            $p_id = (int) ($booking['participants_option_id'] ?? 0);
            $booking['participants_label'] =
                isset($participants[$p_id])
                    ? $participants[$p_id]->label
                    : '';

            // Services
            $labels = [];

            if ( ! empty($booking['equipment_ids']) ) {
                $ids = array_map('intval', explode(',', $booking['equipment_ids']));
                foreach ( $ids as $sid ) {
                    if ( isset($services[$sid]) ) {
                        $labels[] = $services[$sid]->label;
                    }
                }
            }

            $booking['services_labels'] = implode(', ', $labels);
        }
        unset($booking);
    }

    wp_send_json_success([
        'settings'  => $settings,
        'overrides' => $overrides,
        'breaks'    => $breaks,
        'bookings'  => $bookings,
    ]);
}

/**
 * ------------------------------------------------------------
 * AJAX: получение доступных стартовых слотов для формы бронирования
 *
 * Назначение:
 *  - Используется ТОЛЬКО на фронтенде (форма)
 *  - Ничего не пишет в БД
 *  - Делает обёртку над br_calc_get_available_start_slots()
 *
 * Ожидаемые POST-параметры:
 *  - object_id        (int)    ID зала
 *  - date             (string) Y-m-d
 *  - duration_slots   (int)    длительность бронирования в слотах
 *
 * Возвращает:
 *  {
 *    success: true,
 *    data: {
 *      available_slots: int[]
 *    }
 *  }
 * ------------------------------------------------------------
 */
add_action('wp_ajax_br_get_available_start_slots', 'br_ajax_get_available_start_slots');
add_action('wp_ajax_nopriv_br_get_available_start_slots', 'br_ajax_get_available_start_slots');

function br_ajax_get_available_start_slots() {

    // Проверка обязательных параметров
    if (
        ! isset(
            $_POST['object_id'],
            $_POST['date'],
            $_POST['duration_slots']
        )
    ) {
        wp_send_json_error('Missing params');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );
$date      = sanitize_text_field( wp_unslash( $_POST['date'] ) );
$duration  = absint( wp_unslash( $_POST['duration_slots'] ) );

    // Защита от некорректных значений
    if ( $object_id <= 0 || $duration <= 0 ) {
        wp_send_json_error('Invalid params');
    }

    // ВАЖНО:
    // здесь используется уже существующий READ-ONLY расчёт
    // из файла slot-calculation.php
    $available_slots = br_calc_get_available_start_slots(
        $object_id,
        $date,
        $duration
    );
	
    wp_send_json_success([
        'available_slots' => $available_slots
    ]);
}

/* === AJAX: удаление диапазона (overrides + breaks, DB-only) === */
function br_remove_range() {
	br_admin_ajax_guard();

    global $wpdb;

    if (
        !isset($_POST['object_id'], $_POST['date'], $_POST['slot_start'], $_POST['slot_end'])
    ) {
        wp_send_json_error('Missing params');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );

$date = isset( $_POST['date'] )
    ? sanitize_text_field( wp_unslash( $_POST['date'] ) )
    : '';

$slot_start = isset( $_POST['slot_start'] )
    ? absint( wp_unslash( $_POST['slot_start'] ) )
    : 0;

$slot_end = isset( $_POST['slot_end'] )
    ? absint( wp_unslash( $_POST['slot_end'] ) )
    : 0;

    // удалить все пересекающиеся free
    $wpdb->query(
        $wpdb->prepare(
            "
            DELETE FROM {$wpdb->prefix}1br_overrides
            WHERE object_id = %d
              AND date_start = %s
              AND date_end   = %s
              AND NOT (slot_end <= %d OR slot_start >= %d)
            ",
            $object_id,
            $date,
            $date,
            $slot_start,
            $slot_end
        )
    );

    // удалить все пересекающиеся breaks
    $wpdb->query(
        $wpdb->prepare(
            "
            DELETE FROM {$wpdb->prefix}1br_breaks
            WHERE object_id = %d
              AND date_start = %s
              AND date_end   = %s
              AND NOT (slot_end <= %d OR slot_start >= %d)
            ",
            $object_id,
            $date,
            $date,
            $slot_start,
            $slot_end
        )
    );

    wp_send_json_success();
}

/* === AJAX: сохранение свободных слотов (wp_1br_overrides) === */
function br_save_override() {
    br_admin_ajax_guard();
    global $wpdb;

    if (!isset($_POST['object_id'], $_POST['date'], $_POST['slot_start'], $_POST['slot_end'])) {
        wp_send_json_error('Missing params');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );

$date = isset( $_POST['date'] )
    ? sanitize_text_field( wp_unslash( $_POST['date'] ) )
    : '';

$slot_start = isset( $_POST['slot_start'] )
    ? absint( wp_unslash( $_POST['slot_start'] ) )
    : 0;

$slot_end = isset( $_POST['slot_end'] )
    ? absint( wp_unslash( $_POST['slot_end'] ) )
    : 0;

    $result = $wpdb->insert(
        $wpdb->prefix . '1br_overrides',
        [
            'object_id'  => $object_id,
            'date_start' => $date,
            'date_end'   => $date,
            'slot_start' => $slot_start,
            'slot_end'   => $slot_end,
        ],
        ['%d','%s','%s','%d','%d']
    );

    if ($result === false) {
        wp_send_json_error($wpdb->last_error);
    }

    wp_send_json_success();
}

/* === AJAX: удаление свободных слотов === */
function br_delete_override() {
    br_admin_ajax_guard();
    global $wpdb;

    if (
        !isset(
            $_POST['object_id'],
            $_POST['date'],
            $_POST['slot_start'],
            $_POST['slot_end']
        )
    ) {
        wp_send_json_error('Missing params');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );

$date = isset( $_POST['date'] )
    ? sanitize_text_field( wp_unslash( $_POST['date'] ) )
    : '';

$slot_start = isset( $_POST['slot_start'] )
    ? absint( wp_unslash( $_POST['slot_start'] ) )
    : 0;

$slot_end = isset( $_POST['slot_end'] )
    ? absint( wp_unslash( $_POST['slot_end'] ) )
    : 0;

    $result = $wpdb->delete(
        $wpdb->prefix . '1br_overrides',
        [
            'object_id'  => $object_id,
            'date_start' => $date,
            'date_end'   => $date,
            'slot_start' => $slot_start,
            'slot_end'   => $slot_end,
        ],
        ['%d','%s','%s','%d','%d']
    );

    if ($result === false) {
        wp_send_json_error($wpdb->last_error);
    }

    wp_send_json_success();
}

/* === AJAX: сохранение недоступных слотов (wp_1br_breaks) === */
function br_save_break() {
	br_admin_ajax_guard();
    global $wpdb;

    if (
        !isset($_POST['object_id'], $_POST['date'], $_POST['slot_start'], $_POST['slot_end'])
    ) {
        wp_send_json_error('Missing params');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );

$date = isset( $_POST['date'] )
    ? sanitize_text_field( wp_unslash( $_POST['date'] ) )
    : '';

$slot_start = isset( $_POST['slot_start'] )
    ? absint( wp_unslash( $_POST['slot_start'] ) )
    : 0;

$slot_end = isset( $_POST['slot_end'] )
    ? absint( wp_unslash( $_POST['slot_end'] ) )
    : 0;

    $result = $wpdb->insert(
        $wpdb->prefix . '1br_breaks',
        [
            'object_id'  => $object_id,
            'date_start' => $date,
            'date_end'   => $date,
            'slot_start' => $slot_start,
            'slot_end'   => $slot_end,
        ],
        ['%d','%s','%s','%d','%d']
    );

    if ($result === false) {
        wp_send_json_error($wpdb->last_error);
    }

    wp_send_json_success();
}

/* === AJAX: удаление недоступных слотов (wp_1br_breaks) === */
function br_delete_break() {
	br_admin_ajax_guard();
    global $wpdb;

    if (
        !isset(
            $_POST['object_id'],
            $_POST['date'],
            $_POST['slot_start'],
            $_POST['slot_end']
        )
    ) {
        wp_send_json_error('Missing params');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );

$date = isset( $_POST['date'] )
    ? sanitize_text_field( wp_unslash( $_POST['date'] ) )
    : '';

$slot_start = isset( $_POST['slot_start'] )
    ? absint( wp_unslash( $_POST['slot_start'] ) )
    : 0;

$slot_end = isset( $_POST['slot_end'] )
    ? absint( wp_unslash( $_POST['slot_end'] ) )
    : 0;

    $result = $wpdb->delete(
        $wpdb->prefix . '1br_breaks',
        [
            'object_id'  => $object_id,
            'date_start' => $date,
            'date_end'   => $date,
            'slot_start' => $slot_start,
            'slot_end'   => $slot_end,
        ],
        ['%d','%s','%s','%d','%d']
    );

    if ($result === false) {
        wp_send_json_error($wpdb->last_error);
    }

    wp_send_json_success();
}

/* === AJAX: reset free + break slots for one day === */
function br_reset_day() {
	br_admin_ajax_guard();
    global $wpdb;

    if (!isset($_POST['object_id'], $_POST['date'])) {
        wp_send_json_error('Missing params');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );

$date = isset( $_POST['date'] )
    ? sanitize_text_field( wp_unslash( $_POST['date'] ) )
    : '';

    $wpdb->delete(
        $wpdb->prefix . '1br_overrides',
        [
            'object_id'  => $object_id,
            'date_start' => $date,
            'date_end'   => $date,
        ],
        ['%d','%s','%s']
    );

    $wpdb->delete(
        $wpdb->prefix . '1br_breaks',
        [
            'object_id'  => $object_id,
            'date_start' => $date,
            'date_end'   => $date,
        ],
        ['%d','%s','%s']
    );

    wp_send_json_success();
}
/* ============================================================
   APPLY RANGE (ENTRY POINT)
   ============================================================ */

add_action('wp_ajax_br_apply_range', 'br_apply_range');

function br_apply_range() {
    br_admin_ajax_guard();
    global $wpdb;

    if (
        !isset($_POST['object_id'], $_POST['date'], $_POST['type'], $_POST['slot_start'], $_POST['slot_end'])
    ) {
        wp_send_json_error('Missing params');
    }

    $object_id  = absint( wp_unslash( $_POST['object_id'] ) );
$date       = sanitize_text_field( wp_unslash( $_POST['date'] ) );

$type_raw = isset( $_POST['type'] )
    ? sanitize_key( wp_unslash( $_POST['type'] ) )
    : '';

$type = ( $type_raw === 'break' ) ? 'break' : 'free';

$slot_start = absint( wp_unslash( $_POST['slot_start'] ) );
$slot_end   = absint( wp_unslash( $_POST['slot_end'] ) );

    // таблицы
    $table       = ($type === 'free') ? $wpdb->prefix . '1br_overrides' : $wpdb->prefix . '1br_breaks';
    $other_table = ($type === 'free') ? $wpdb->prefix . '1br_breaks'    : $wpdb->prefix . '1br_overrides';

    /* === RULE 1 === */
    br_rule_new_inside_old(
    $wpdb,
    'break',
    $object_id,
    $date,
    $slot_start,
    $slot_end
);

    /* === RULE 2 === */
    br_rule_partial_overlap(
        $wpdb,
        $other_table,
        $object_id,
        $date,
        $slot_start,
        $slot_end
    );
	/* === RULE 4: SAME TYPE === */
$continue = br_rule_same_type(
    $wpdb,
    $table,
    $object_id,
    $date,
    $slot_start,
    $slot_end
);

if ($continue === false) {
    wp_send_json_success();
}


    /* === FINAL: новый диапазон всегда записывается === */
    $wpdb->insert(
        $table,
        [
            'object_id'  => $object_id,
            'date_start' => $date,
            'date_end'   => $date,
            'slot_start' => $slot_start,
            'slot_end'   => $slot_end,
        ],
        ['%d','%s','%s','%d','%d']
    );
/* === RULE 5: NORMALIZE SAME TYPE === */
br_rule_normalize_same_type(
    $wpdb,
    $table,
    $object_id,
    $date
);

    wp_send_json_success();
}
/*добавбление на страницу букинг в поля фильтров стандартного календаря для удоства выбора дат*/
add_action('admin_enqueue_scripts', function ($hook) {

    if ($hook !== 'booking-room_page_booking-room-bookings') {
        return;
    }

    wp_enqueue_script('jquery-ui-datepicker');

});

/**
 * ------------------------------------------------------------
 * Pikaday — подключение в админке
 * ------------------------------------------------------------
 */
add_action('admin_enqueue_scripts', function ($hook) {

    if (
        $hook !== 'toplevel_page_booking-room' &&
        $hook !== 'booking-room_page_booking-room-bookings' &&
        $hook !== 'booking-room_page_booking-room-settings' &&
        $hook !== 'booking-room_page_booking-room-export'
    ) {
        return;
    }

    wp_enqueue_style(
        'br-pikaday',
        plugins_url('pikaday.css', __FILE__),
        [],
        '1.8.2'
    );

    wp_enqueue_script(
        'br-pikaday',
        plugins_url('pikaday.js', __FILE__),
        [],
        '1.8.2',
        true
    );

});
/**
 * ------------------------------------------------------------
 * Frontend scripts — Booking Form
 * Регистрируем скрипты (НЕ подключаем)
 * ------------------------------------------------------------
 */
add_action('wp_enqueue_scripts', function () {

    wp_enqueue_style(
        'br-pikaday',
        plugins_url('pikaday.css', __FILE__),
        [],
        '1.8.2'
    );

    wp_enqueue_script(
        'br-pikaday',
        plugins_url('pikaday.js', __FILE__),
        [],
        '1.8.2',
        true
    );

    wp_enqueue_script(
        'br-form-step-1',
        plugins_url('form-step-1.js', __FILE__),
        ['br-pikaday'],
        '1.0.0',
        true
    );

    wp_localize_script(
    'br-form-step-1',
    'br_ajax',
    [
        'ajax_url'            => admin_url('admin-ajax.php'),
        'price_nonce'         => wp_create_nonce('br_calculate_price_nonce'),
        'user_contact_nonce'  => wp_create_nonce('br_user_contact_nonce')
    ]
);

});



/* * ------------------------------------------------------------
 * ДОБАВЛЕНИЕ ВЫХОДНОГО ВМЕСТО РАБОЧЕГО Convert working day to day off
 * ------------------------------------------------------------*/
 
add_action('wp_ajax_br_add_break_days', 'br_add_break_days');

function br_add_break_days() {
	br_admin_ajax_guard();

    global $wpdb;

    if ( ! isset($_POST['object_id'], $_POST['date_start'], $_POST['date_end']) ) {
        wp_send_json_error('Missing params');
    }

    $object_id  = absint( wp_unslash( $_POST['object_id'] ) );
$date_start = sanitize_text_field( wp_unslash( $_POST['date_start'] ) );
$date_end   = sanitize_text_field( wp_unslash( $_POST['date_end'] ) );

    $start = DateTime::createFromFormat('d.m.Y', $date_start);
    $end   = DateTime::createFromFormat('d.m.Y', $date_end);

    if ( ! $start || ! $end ) {
        wp_send_json_error('Invalid date format');
    }

    // перебор дней
    while ( $start <= $end ) {

        $date = $start->format('Y-m-d');

        // ❗ ВЕСЬ ДЕНЬ: 00:00–24:00
        $wpdb->insert(
            $wpdb->prefix . '1br_breaks',
            [
                'object_id'  => $object_id,
                'date_start' => $date,
                'date_end'   => $date,
                'slot_start' => '00:00:00',
                'slot_end'   => '24:00:00',
            ],
            ['%d','%s','%s','%s','%s']
        );

        $start->modify('+1 day');
    }

    wp_send_json_success();
}

/* ------------------------------------------------------------
 * УДАЛЕНИЕ ВЫХОДНОГО wp_1br_breaks
 * ------------------------------------------------------------*/
add_action('wp_ajax_br_delete_break_day', 'br_delete_break_day');

function br_delete_break_day() {
	br_admin_ajax_guard();

    global $wpdb;

    if ( ! isset($_POST['id']) ) {
        wp_send_json_error('Missing id');
    }

    $id = absint( wp_unslash( $_POST['id'] ) );

    if ($id <= 0) {
        wp_send_json_error('Invalid id');
    }

    // удаляем ровно одну запись
    $result = $wpdb->delete(
        $wpdb->prefix . '1br_breaks',
        [
            'id' => $id
        ],
        [
            '%d'
        ]
    );

    // если ничего не удалилось — это ошибка
    if ($result === false) {
        wp_send_json_error($wpdb->last_error);
    }

    if ($result === 0) {
        wp_send_json_error('Row not found');
    }

    wp_send_json_success([
        'deleted_id' => $id
    ]);
}
/* ------------------------------------------------------------
 * ДОБАВЛЕНИЕ РАБОЧЕГО ВМЕСТО ВЫХОДНОГО Convert day off to working day
 * ------------------------------------------------------------*/
 
add_action('wp_ajax_br_add_working_day', 'br_add_working_day');

function br_add_working_day() {
	br_admin_ajax_guard();

    global $wpdb;

    if ( ! isset($_POST['object_id'], $_POST['date']) ) {
        wp_send_json_error('Missing params');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );
$raw_date  = sanitize_text_field( wp_unslash( $_POST['date'] ) );

    // DD.MM.YYYY → Y-m-d (строго)
    $dt = DateTime::createFromFormat('d.m.Y', $raw_date);
    if ( ! $dt ) {
        wp_send_json_error('Invalid date format');
    }
    $date = $dt->format('Y-m-d');

    // === ПРОВЕРКА НА ДУБЛЬ ===
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT id
            FROM {$wpdb->prefix}1br_overrides
            WHERE object_id = %d
              AND date_start = %s
              AND date_end   = %s
            LIMIT 1
            ",
            $object_id,
            $date,
            $date
        )
    );

    if ( $exists ) {
        // запись уже есть — тихо выходим
        wp_send_json_success();
    }

    // получаем рабочие слоты из глобальных настроек
    $settings = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT work_start_slot, work_end_slot
            FROM {$wpdb->prefix}1br_global_settings
            WHERE object_id = %d
            LIMIT 1
            ",
            $object_id
        ),
        ARRAY_A
    );

    if ( ! $settings ) {
        wp_send_json_error('Settings not found');
    }

    // записываем рабочий день
    $result = $wpdb->insert(
        $wpdb->prefix . '1br_overrides',
        [
            'object_id'  => $object_id,
            'date_start' => $date,
            'date_end'   => $date,
            'slot_start' => (int) $settings['work_start_slot'],
            'slot_end'   => (int) $settings['work_end_slot'],
        ],
        ['%d','%s','%s','%d','%d']
    );

    if ( $result === false ) {
        wp_send_json_error($wpdb->last_error);
    }

    wp_send_json_success();
}



/** 

 * ------------------------------------------------------------
 * Get break days list
 * ------------------------------------------------------------
 */
add_action('wp_ajax_br_get_break_days', 'br_get_break_days');

function br_get_break_days() {
	br_admin_ajax_guard();

    global $wpdb;

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT id, date_start, date_end
            FROM {$wpdb->prefix}1br_breaks
            WHERE object_id = %d
            ORDER BY date_start ASC
            ",
            $object_id
        ),
        ARRAY_A
    );

    wp_send_json_success($rows);
}

/**
 * ------------------------------------------------------------
 * AJAX: получение рабочих дней (overrides)
 * Только одиночные дни: date_start = date_end
 * ------------------------------------------------------------
 */
add_action('wp_ajax_br_get_workdays', 'br_get_workdays');

function br_get_workdays() {
	br_admin_ajax_guard();

    global $wpdb;

    if ( ! isset($_POST['object_id']) ) {
        wp_send_json_error('Missing object_id');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT date_start, date_end
            FROM {$wpdb->prefix}1br_overrides
            WHERE object_id = %d
              AND date_start = date_end
            ORDER BY date_start ASC
            ",
            $object_id
        ),
        ARRAY_A
    );

    wp_send_json_success($rows);
}

/**
 * ============================================================
 * Редактирование типов мероприятий
 * Таблица: wp_1br_event_types
 * ============================================================
 */

add_action('wp_ajax_br_get_event_types', 'br_get_event_types');
add_action('wp_ajax_br_add_event_type', 'br_add_event_type');
add_action('wp_ajax_br_update_event_type', 'br_update_event_type');
add_action('wp_ajax_br_toggle_event_type', 'br_toggle_event_type');
add_action('wp_ajax_br_delete_event_type', 'br_delete_event_type');
/*изменение порядка списка с помощью мыши*/
add_action('wp_ajax_br_update_event_types_order', 'br_update_event_types_order');

function br_update_event_types_order() {
	br_admin_ajax_guard();

    global $wpdb;

    if ( ! isset($_POST['object_id'], $_POST['order']) ) {
        wp_send_json_error('Missing params');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );

$order_raw = isset( $_POST['order'] )
    ? sanitize_text_field( wp_unslash( $_POST['order'] ) )
    : '';

$order = json_decode( $order_raw, true );

    if ( ! is_array($order) ) {
        wp_send_json_error('Invalid order');
    }

    foreach ( $order as $item ) {

        if ( ! isset($item['id'], $item['sort_order']) ) {
            continue;
        }

        $wpdb->update(
            $wpdb->prefix . '1br_event_types',
            [ 'sort_order' => (int) $item['sort_order'] ],
            [
                'id'        => (int) $item['id'],
                'object_id' => $object_id
            ],
            [ '%d' ],
            [ '%d', '%d' ]
        );
    }

    wp_send_json_success();
}

/**
 * Получить список типов мероприятий
 */
function br_get_event_types() {
	br_admin_ajax_guard();

    global $wpdb;

    if ( ! isset($_POST['object_id']) ) {
        wp_send_json_error('Missing object_id');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT id, label, is_visible
            FROM {$wpdb->prefix}1br_event_types
            WHERE object_id = %d
            ORDER BY sort_order ASC, id ASC
            ",
            $object_id
        ),
        ARRAY_A
    );

    wp_send_json_success($rows);
}

/**
 * Добавить тип мероприятия
 */
function br_add_event_type() {
	br_admin_ajax_guard();

    global $wpdb;

    if ( ! isset($_POST['object_id'], $_POST['label']) ) {
        wp_send_json_error('Missing params');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );
$label     = isset( $_POST['label'] )
    ? trim( sanitize_text_field( wp_unslash( $_POST['label'] ) ) )
    : '';

    if ( $label === '' ) {
        wp_send_json_error('Empty label');
    }

    // 🔹 определяем следующий порядок
    $max_order = (int) $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT MAX(sort_order)
            FROM {$wpdb->prefix}1br_event_types
            WHERE object_id = %d
            ",
            $object_id
        )
    );

    $next_order = $max_order + 1;

    $wpdb->insert(
        $wpdb->prefix . '1br_event_types',
        [
            'object_id'  => $object_id,
            'label'      => $label,
            'is_visible' => 1,
            'sort_order' => $next_order,
        ],
        ['%d','%s','%d','%d']
    );

    if ( $wpdb->last_error ) {
        wp_send_json_error($wpdb->last_error);
    }

    wp_send_json_success();
}


/**
 * Обновить название (inline edit)
 */
function br_update_event_type() {
	br_admin_ajax_guard();

    global $wpdb;

    if ( ! isset($_POST['id'], $_POST['object_id'], $_POST['label']) ) {
        wp_send_json_error('Missing params');
    }

    $id        = absint( wp_unslash( $_POST['id'] ) );
$object_id = absint( wp_unslash( $_POST['object_id'] ) );
$label     = isset( $_POST['label'] )
    ? trim( sanitize_text_field( wp_unslash( $_POST['label'] ) ) )
    : '';

    if ( $label === '' ) {
        wp_send_json_error('Empty label');
    }

    $wpdb->update(
        $wpdb->prefix . '1br_event_types',
        [ 'label' => $label ],
        [ 'id' => $id, 'object_id' => $object_id ],
        [ '%s' ],
        [ '%d', '%d' ]
    );

    if ( $wpdb->last_error ) {
        wp_send_json_error($wpdb->last_error);
    }

    wp_send_json_success();
}

/**
 * Показать / скрыть
 */
function br_toggle_event_type() {
	br_admin_ajax_guard();

    global $wpdb;

    if ( ! isset($_POST['id'], $_POST['object_id']) ) {
        wp_send_json_error('Missing params');
    }

    $id        = absint( wp_unslash( $_POST['id'] ) );
$object_id = absint( wp_unslash( $_POST['object_id'] ) );

    $current = $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT is_visible
            FROM {$wpdb->prefix}1br_event_types
            WHERE id = %d AND object_id = %d
            ",
            $id,
            $object_id
        )
    );

    if ( $current === null ) {
        wp_send_json_error('Not found');
    }

    $new = $current ? 0 : 1;

    $wpdb->update(
        $wpdb->prefix . '1br_event_types',
        [ 'is_visible' => $new ],
        [ 'id' => $id, 'object_id' => $object_id ],
        [ '%d' ],
        [ '%d', '%d' ]
    );

    wp_send_json_success();
}

/**
 * Удалить тип мероприятия
 */
function br_delete_event_type() {
	br_admin_ajax_guard();

    global $wpdb;

    if ( ! isset($_POST['id'], $_POST['object_id']) ) {
        wp_send_json_error('Missing params');
    }

    $wpdb->delete(
    $wpdb->prefix . '1br_event_types',
    [
        'id'        => absint( wp_unslash( $_POST['id'] ) ),
        'object_id' => absint( wp_unslash( $_POST['object_id'] ) ),
    ],
    [ '%d', '%d' ]
);

    if ( $wpdb->last_error ) {
        wp_send_json_error($wpdb->last_error);
    }

    wp_send_json_success();
}

/**
 * ============================================================
 * AJAX: Save rental rate (one per object)
 * Таблица: wp_1br_rental_rate
 * ============================================================
 */
add_action('wp_ajax_br_save_rental_rate', 'br_save_rental_rate');

function br_save_rental_rate() {
	    br_admin_ajax_guard();


    global $wpdb;

    if (
        ! isset(
            $_POST['object_id'],
            $_POST['label'],
            $_POST['price'],
            $_POST['currency']
        )
    ) {
        wp_send_json_error('Missing params');
    }

    $object_id = absint( wp_unslash( $_POST['object_id'] ) );
$label     = sanitize_text_field( wp_unslash( $_POST['label'] ) );
$price     = (float) wp_unslash( $_POST['price'] );
$currency  = sanitize_text_field( wp_unslash( $_POST['currency'] ) );

    if ( $object_id <= 0 || $price <= 0 || $currency === '' ) {
        wp_send_json_error('Invalid params');
    }

    $table = $wpdb->prefix . '1br_rental_rate';

    // проверяем, есть ли уже запись
    $exists = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE object_id = %d",
            $object_id
        )
    );

    if ( $exists ) {

        // UPDATE
        $result = $wpdb->update(
            $table,
            [
                'label'    => $label,
                'price'    => $price,
                'currency' => $currency
            ],
            [
                'object_id' => $object_id
            ],
            [ '%s', '%f', '%s' ],
            [ '%d' ]
        );

    } else {

        // INSERT
        $result = $wpdb->insert(
            $table,
            [
                'object_id' => $object_id,
                'label'     => $label,
                'price'     => $price,
                'currency'  => $currency
            ],
            [ '%d', '%s', '%f', '%s' ]
        );
    }

    if ( $result === false ) {
        wp_send_json_error($wpdb->last_error);
    }

    wp_send_json_success();
}
/**
 * ============================================================
 * AJAX: Administrator contacts (MASTER RECORDS)
 * Таблица: wp_1br_admin_contacts
 *
 * Правило:
 *  - Администратор = object_id = 0
 *  - Этот файл работает ТОЛЬКО с object_id = 0
 * ============================================================
 */

defined('ABSPATH') || exit;

/* ============================================================
   GET LIST (object_id = 0)
   ============================================================ */
add_action('wp_ajax_br_get_admin_contacts', 'br_get_admin_contacts');
function br_get_admin_contacts() {
	br_admin_ajax_guard();

    global $wpdb;

    $rows = $wpdb->get_results(
        "
        SELECT
            id,
            email,
            first_name,
            last_name,
            position,
            phone,
            is_active
        FROM {$wpdb->prefix}1br_admin_contacts
        WHERE object_id = 0
        ORDER BY id ASC
        ",
        ARRAY_A
    );

    wp_send_json_success($rows);
}

/* ============================================================
   ADD ADMIN (object_id = 0 ONLY)
   ============================================================ */
add_action('wp_ajax_br_add_admin_contact', 'br_add_admin_contact');
function br_add_admin_contact() {
	br_admin_ajax_guard();

    global $wpdb;

    $email = isset( $_POST['email'] )
    ? sanitize_email( wp_unslash( $_POST['email'] ) )
    : '';

if ( ! $email || ! is_email( $email ) ) {
    wp_send_json_error( 'Invalid email' );
}

    // защита от дублей карточек админа
    $exists = (int) $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT COUNT(*)
            FROM {$wpdb->prefix}1br_admin_contacts
            WHERE object_id = 0 AND email = %s
            ",
            $email
        )
    );

    if ($exists > 0) {
        wp_send_json_error('Administrator already exists');
    }

    $wpdb->insert(
        $wpdb->prefix . '1br_admin_contacts',
        [
            'object_id'  => 0,
            'email'      => $email,
            'first_name' => isset( $_POST['first_name'] )
    ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) )
    : '',
'last_name'  => isset( $_POST['last_name'] )
    ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) )
    : '',
'position'   => isset( $_POST['position'] )
    ? sanitize_text_field( wp_unslash( $_POST['position'] ) )
    : '',
'phone'      => isset( $_POST['phone'] )
    ? sanitize_text_field( wp_unslash( $_POST['phone'] ) )
    : '',
            'is_active'  => 1
        ],
        ['%d','%s','%s','%s','%s','%s','%d']
    );

    wp_send_json_success();
}

/* ============================================================
   UPDATE FIELD (object_id = 0 ONLY)
   ============================================================ */
add_action('wp_ajax_br_update_admin_contact', 'br_update_admin_contact');
function br_update_admin_contact() {
	br_admin_ajax_guard();

    global $wpdb;

    if (
        empty($_POST['id']) ||
        empty($_POST['field'])
    ) {
        wp_send_json_error('Missing params');
    }

    $id    = absint( wp_unslash( $_POST['id'] ) );
$field = sanitize_key( wp_unslash( $_POST['field'] ) );
$value = isset( $_POST['value'] )
    ? wp_unslash( $_POST['value'] )
    : '';

    $allowed_fields = [
        'email',
        'first_name',
        'last_name',
        'position',
        'phone'
    ];

    if (!in_array($field, $allowed_fields, true)) {
        wp_send_json_error('Invalid field');
    }

    if ($field === 'email') {
        if (!is_email($value)) {
            wp_send_json_error('Invalid email');
        }
        $value = sanitize_email($value);
    } else {
        $value = sanitize_text_field($value);
    }

    $wpdb->update(
        $wpdb->prefix . '1br_admin_contacts',
        [ $field => $value ],
        [
            'id'        => $id,
            'object_id' => 0
        ],
        [ '%s' ],
        [ '%d','%d' ]
    );

    wp_send_json_success();
}

/* ============================================================
   TOGGLE ACTIVE (object_id = 0 ONLY)
   ============================================================ */
add_action('wp_ajax_br_toggle_admin_contact', 'br_toggle_admin_contact');
function br_toggle_admin_contact() {
	br_admin_ajax_guard();

    global $wpdb;

    if (empty($_POST['id'])) {
        wp_send_json_error('Missing id');
    }

    $wpdb->query(
    $wpdb->prepare(
        "
        UPDATE {$wpdb->prefix}1br_admin_contacts
        SET is_active = IF(is_active = 1, 0, 1)
        WHERE id = %d AND object_id = 0
        ",
        absint( wp_unslash( $_POST['id'] ) )
    )
);

    wp_send_json_success();
}

/* ============================================================
   DELETE ADMIN (CARD ONLY, object_id = 0)
   ============================================================ */
add_action('wp_ajax_br_delete_admin_contact', 'br_delete_admin_contact');
function br_delete_admin_contact() {
	br_admin_ajax_guard();

    global $wpdb;

    if (empty($_POST['id'])) {
        wp_send_json_error('Missing id');
    }

    $wpdb->delete(
    $wpdb->prefix . '1br_admin_contacts',
    [
        'id'        => absint( wp_unslash( $_POST['id'] ) ),
        'object_id' => 0
    ],
    ['%d','%d']
);

    wp_send_json_success();
}

/* ============================================================
   AJAX: Get user contact by email
   Таблица: wp_1br_user_contacts
   ============================================================ */

add_action('wp_ajax_br_get_user_contact', 'br_get_user_contact');
add_action('wp_ajax_nopriv_br_get_user_contact', 'br_get_user_contact');

function br_get_user_contact() {

    global $wpdb;

    // 🔐 Nonce protection (frontend)
    if (
    ! isset( $_POST['_wpnonce'] ) ||
    ! wp_verify_nonce(
        sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
        'br_user_contact_nonce'
    )
) {
    wp_send_json_error();
}

    if ( empty($_POST['email']) ) {
        wp_send_json_error();
    }

    $email = isset( $_POST['email'] )
    ? strtolower( trim( sanitize_email( wp_unslash( $_POST['email'] ) ) ) )
    : '';

    if ( ! is_email($email) ) {
        wp_send_json_error();
    }

    $table = $wpdb->prefix . '1br_user_contacts';

    $contact = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT company, first_name, last_name, phone
             FROM {$table}
             WHERE email = %s
             LIMIT 1",
            $email
        ),
        ARRAY_A
    );

    if ( ! $contact ) {
        wp_send_json_error();
    }

    wp_send_json_success($contact);
}


/**
 * ============================================================
 * AJAX: Save weekend surcharge coefficient
 * Таблица: wp_1br_day_weekend_coeff
 * ============================================================
 */
add_action('wp_ajax_br_save_weekend_coeff', 'br_save_weekend_coeff');

function br_save_weekend_coeff() {
	    br_admin_ajax_guard();

    if (
        ! isset( $_POST['_wpnonce'] ) ||
        ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ),
            'br_admin_nonce'
        )
    ) {
        wp_send_json_error( 'Invalid nonce', 403 );
    }

    global $wpdb;

    if (
        ! isset($_POST['object_id'], $_POST['label'], $_POST['coefficient'])
    ) {
        wp_send_json_error('Missing params');
    }

    $object_id   = absint( wp_unslash( $_POST['object_id'] ) );
$label       = sanitize_text_field( wp_unslash( $_POST['label'] ) );
$coefficient = (float) wp_unslash( $_POST['coefficient'] );

    $table = $wpdb->prefix . '1br_day_weekend_coeff';

    $exists = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$table} WHERE object_id = %d LIMIT 1",
            $object_id
        )
    );

    if ( $exists ) {
        $wpdb->update(
            $table,
            [
                'label'       => $label,
                'coefficient' => $coefficient
            ],
            [ 'object_id' => $object_id ],
            [ '%s', '%f' ],
            [ '%d' ]
        );
    } else {
        $wpdb->insert(
            $table,
            [
                'object_id'   => $object_id,
                'label'       => $label,
                'coefficient' => $coefficient
            ],
            [ '%d', '%s', '%f' ]
        );
    }

    wp_send_json_success();
}


/**
 * ------------------------------------------------------------
 * Admin styles — Booking Room
 * ------------------------------------------------------------
 */
add_action('admin_enqueue_scripts', function ($hook) {

    // грузим ТОЛЬКО на странице плагина
    if ($hook !== 'toplevel_page_booking-room') {
        return;
    }

    wp_enqueue_style(
        'br-admin',
        plugins_url('admin.css', __FILE__),
        [],
        '1.0.0'
    );

});
