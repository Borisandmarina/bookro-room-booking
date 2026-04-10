<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/db-install.php
 * Назначение:
 *  - установка таблиц базы данных плагина Booking Room
 *  - используется ТОЛЬКО при активации плагина
 * ------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ------------------------------------------------------------
 * Функция установки базы данных
 * ------------------------------------------------------------
 * ВАЖНО:
 *  - файл сам по себе НИЧЕГО не выполняет
 *  - весь SQL выполняется только при вызове этой функции
 */
function br_install_database() {

    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * ============================================================
 * ТАБЛИЦА: wp_1br_objects
 * ============================================================
 */
$table_objects = $wpdb->prefix . '1br_objects';

$sql_objects = "
    CREATE TABLE {$table_objects} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        timezone VARCHAR(100) NOT NULL,
        address TEXT NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50) NOT NULL,
        PRIMARY KEY (id)
    ) {$charset_collate};
";

dbDelta( $sql_objects );


/* ============================================================
   ДЕФОЛТНЫЕ ЗАПИСИ
   ============================================================ */

$existing_objects = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$table_objects}"
);

if ( $existing_objects === 0 ) {
    $wpdb->insert(
        $table_objects,
        [
            'name'     => 'Conference Hall Sunflower',
            'status'   => 'active',
            'timezone' => 'Europe/Berlin',
            'address'  => "Sunflower Business Center\nNauki 3\n03039 Berlin\Germany",
            'email'    => 'sunflower@sunflower.com',
            'phone'    => '+140000000000',
        ]
    );
}




    /**
     * ============================================================
     * ТАБЛИЦА: wp_1br_overrides
     * ============================================================
     */
    $table_overrides = $wpdb->prefix . '1br_overrides';

    $sql_overrides = "
        CREATE TABLE {$table_overrides} (
            id INT NOT NULL AUTO_INCREMENT,
            object_id INT NOT NULL,
            date_start DATE NOT NULL,
            date_end DATE NOT NULL,
            slot_start INT NOT NULL,
            slot_end INT NOT NULL,
            PRIMARY KEY (id),
            KEY idx_object_id (object_id),
            KEY date_range (date_start, date_end)
        ) {$charset_collate};
    ";

    dbDelta( $sql_overrides );

    /**
 * ============================================================
 * ТАБЛИЦА: wp_1br_global_settings
 * ============================================================
 */
$table_global_settings = $wpdb->prefix . '1br_global_settings';

$sql_global_settings = "
    CREATE TABLE {$table_global_settings} (
        id INT NOT NULL AUTO_INCREMENT,
        object_id INT NOT NULL,
        work_start_slot INT NOT NULL,
        work_end_slot INT NOT NULL,
        timezone VARCHAR(64) NOT NULL,
        weekends VARCHAR(50) NOT NULL,
        PRIMARY KEY (id),
        KEY idx_object_id (object_id)
    ) {$charset_collate};
";

dbDelta( $sql_global_settings );

/* === default records === */
$existing_settings = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$table_global_settings}"
);

if ( $existing_settings === 0 ) {

    $wpdb->insert(
        $table_global_settings,
        [
            'object_id'       => 1,
            'work_start_slot' => 9,
            'work_end_slot'   => 21,
            'timezone'        => 'Europe/Kyiv',
            'weekends'        => 'Sunday,Saturday',
        ]
    );

    $wpdb->insert(
        $table_global_settings,
        [
            'object_id'       => 2,
            'work_start_slot' => 9,
            'work_end_slot'   => 22,
            'timezone'        => 'Europe/Kyiv',
            'weekends'        => 'Saturday,Sunday',
        ]
    );
}

/* ============================================================
 * ТАБЛИЦА: wp_1br_bookings
 * ============================================================*/
$table_bookings = $wpdb->prefix . '1br_bookings';

$sql_bookings = "
CREATE TABLE {$table_bookings} (
    id INT NOT NULL AUTO_INCREMENT,

    object_id INT NOT NULL,
    created_at DATETIME NOT NULL,

    client_name VARCHAR(100) NOT NULL,
    client_surname VARCHAR(100) NOT NULL,
    client_company VARCHAR(150) DEFAULT NULL,
    client_phone VARCHAR(30) NOT NULL,
    client_email VARCHAR(150) NOT NULL,

    event_date DATE NOT NULL,

    duration_slots INT NOT NULL,
    slot_start INT NOT NULL,
    slot_end INT NOT NULL,

    interval1_slot_start INT NOT NULL,
    interval1_slot_end INT NOT NULL,
    interval1_slots INT NOT NULL,

    interval2_slot_start INT NOT NULL,
    interval2_slot_end INT NOT NULL,
    interval2_slots INT NOT NULL,

    participants_option_id INT NOT NULL,
    event_type_id INT NOT NULL,
    event_type_custom VARCHAR(150) DEFAULT NULL,
    payment_method_id INT NOT NULL,

    equipment_ids TEXT NOT NULL,
    order_comment TEXT DEFAULT NULL,

    price_net DECIMAL(10,2) NOT NULL,
    price_gross DECIMAL(10,2) NOT NULL,

    status VARCHAR(20) NOT NULL DEFAULT 'pending',

    PRIMARY KEY (id),
    KEY idx_object_id (object_id),
    KEY idx_event_date (event_date)

   ) {$charset_collate};
 ";
    dbDelta( $sql_bookings );


    /**
     * ------------------------------------------------------------
     * Тестовые записи бронирований
     * ------------------------------------------------------------
     */
    $existing_bookings = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$table_bookings}"
    );

    if ( $existing_bookings === 0 ) {

        // Бронирование 1 — один интервал (09–12)
        $wpdb->insert(
            $table_bookings,
            array(
                'object_id' => 1,
                'created_at' => '2026-01-08 19:57:41',
                'client_name' => 'Orange',
                'client_surname' => 'Pending',
                'client_company' => 'Test Company',
                'client_phone' => '+49123456701',
                'client_email' => 'orange.pending@example.com',
                'event_date' => '2026-01-09',
                'duration_slots' => 3,
                'slot_start' => 9,
                'slot_end' => 12,
                'interval1_slot_start' => 9,
                'interval1_slot_end' => 12,
                'interval1_slots' => 3,
                'interval2_slot_start' => 9,
                'interval2_slot_end' => 12,
                'interval2_slots' => 3,
                'participants_option_id' => 1,
                'event_type_id' => 1,
                'event_type_custom' => null,
                'payment_method_id' => 1,
                'equipment_ids' => '',
                'order_comment' => 'Pending booking (single interval)',
                'price_net' => 0.00,
                'price_gross' => 0.00,
                'status' => 'confirmed'
            )
        );

        // Бронирование 2 — два тарифа (13–17 / 17–20)
        $wpdb->insert(
            $table_bookings,
            array(
                'object_id' => 1,
                'created_at' => '2026-01-08 19:58:02',
                'client_name' => 'Red',
                'client_surname' => 'Confirmed',
                'client_company' => 'Test Company',
                'client_phone' => '+49123456702',
                'client_email' => 'red.confirmed@example.com',
                'event_date' => '2026-01-09',
                'duration_slots' => 7,
                'slot_start' => 13,
                'slot_end' => 20,
                'interval1_slot_start' => 13,
                'interval1_slot_end' => 17,
                'interval1_slots' => 4,
                'interval2_slot_start' => 17,
                'interval2_slot_end' => 20,
                'interval2_slots' => 3,
                'participants_option_id' => 1,
                'event_type_id' => 1,
                'event_type_custom' => null,
                'payment_method_id' => 1,
                'equipment_ids' => '',
                'order_comment' => 'Confirmed booking with tariff split (13–17 / 17–20)',
                'price_net' => 0.00,
                'price_gross' => 0.00,
                'status' => 'confirmed'
            )
        );
    }

    /**
     * ============================================================
     * ТАБЛИЦА: wp_1br_breaks
     * ------------------------------------------------------------
     * Назначение:
     *  - хранение недоступных (заблокированных) слотов
     *  - используется для перекрытия глобальных рабочих часов
     *  - логика полностью аналогична wp_1br_overrides
     *
     * Принципы:
     *  - физические записи в БД
     *  - 1 слот = 1 час
     *  - используется шкалой и дополнительными инструментами
     * ============================================================
     */
    $table_breaks = $wpdb->prefix . '1br_breaks';

    $sql_breaks = "
        CREATE TABLE {$table_breaks} (
            id INT NOT NULL AUTO_INCREMENT,
            object_id INT NOT NULL,
            date_start DATE NOT NULL,
            date_end DATE NOT NULL,
            slot_start INT NOT NULL,
            slot_end INT NOT NULL,
            PRIMARY KEY (id),
            KEY idx_object_id (object_id),
            KEY date_range (date_start, date_end)
        ) {$charset_collate};
    ";

    dbDelta( $sql_breaks );

 /**
 * ============================================================
 * ТАБЛИЦА: wp_1br_holidays
 * ------------------------------------------------------------
 * Назначение:
 *  - хранение праздничных / нерабочих дат
 *  - привязка к объекту (object_id)
 *  - флаг разрешения бронирования в праздник
 * ============================================================
 */
$table_holidays = $wpdb->prefix . '1br_holidays';

$sql_holidays = "
    CREATE TABLE {$table_holidays} (
        id INT NOT NULL AUTO_INCREMENT,
        object_id INT NOT NULL,
        date DATE NOT NULL,
        name TEXT NULL,
        allow_booking TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY object_date (object_id, date),
        KEY idx_object_id (object_id),
        KEY idx_allow_booking (allow_booking)
    ) {$charset_collate};
";

dbDelta( $sql_holidays );


/**
 * ============================================================
 * ТАБЛИЦА: wp_1br_event_types
 * ------------------------------------------------------------
 * Назначение:
 *  - справочник типов мероприятий
 * ============================================================
 */
$table_event_types = $wpdb->prefix . '1br_event_types';

$sql_event_types = "
    CREATE TABLE {$table_event_types} (
        id INT NOT NULL AUTO_INCREMENT,
        object_id INT NOT NULL,
        label VARCHAR(255) NOT NULL,
        is_visible TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_object_id (object_id)
    ) {$charset_collate};
";

dbDelta( $sql_event_types );

/* === default records === */
$records = [
    // object_id = 1
    [ 'object_id' => 1, 'label' => 'Meeting',      'is_visible' => 1, 'sort_order' => 0 ],
    [ 'object_id' => 1, 'label' => 'Workshop',     'is_visible' => 1, 'sort_order' => 1 ],
    [ 'object_id' => 1, 'label' => 'Training',     'is_visible' => 1, 'sort_order' => 2 ],
    [ 'object_id' => 1, 'label' => 'Seminar',      'is_visible' => 1, 'sort_order' => 3 ],
    [ 'object_id' => 1, 'label' => 'Presentation', 'is_visible' => 1, 'sort_order' => 4 ],
    [ 'object_id' => 1, 'label' => 'Lecture',      'is_visible' => 1, 'sort_order' => 5 ],
    [ 'object_id' => 1, 'label' => 'Briefing',     'is_visible' => 1, 'sort_order' => 6 ],
    [ 'object_id' => 1, 'label' => 'Conference',   'is_visible' => 1, 'sort_order' => 7 ],

    [ 'object_id' => 2, 'label' => 'Збори',        'is_visible' => 1, 'sort_order' => 1 ],
    [ 'object_id' => 2, 'label' => 'Навчання',     'is_visible' => 1, 'sort_order' => 2 ],
    [ 'object_id' => 2, 'label' => 'Тренінг',      'is_visible' => 1, 'sort_order' => 3 ],
    [ 'object_id' => 2, 'label' => 'Лекція',       'is_visible' => 1, 'sort_order' => 4 ],
    [ 'object_id' => 2, 'label' => 'Презентація',  'is_visible' => 1, 'sort_order' => 5 ],
];

foreach ( $records as $row ) {

    $exists = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_event_types} 
             WHERE object_id = %d AND label = %s",
            $row['object_id'],
            $row['label']
        )
    );

    if ( $exists === 0 ) {
        $wpdb->insert(
            $table_event_types,
            $row,
            [ '%d', '%s', '%d', '%d' ]
        );
    }
}



/**
 * ============================================================
 * ИНИЦИАЛИЗАЦИЯ ТИПОВ МЕРОПРИЯТИЙ (DEMO: object_id = 1 и 2)
 * ============================================================
 */

$defaults = [
    1 => [
        ['label' => 'Presentation', 'sort' => 1],
        ['label' => 'Meeting',      'sort' => 2],
        ['label' => 'Training',     'sort' => 3],
    ],
    2 => [
        ['label' => 'Збори',        'sort' => 1],
        ['label' => 'Навчання',     'sort' => 2],
        ['label' => 'Тренінг',      'sort' => 3],
        ['label' => 'Лекція',       'sort' => 4],
        ['label' => 'Презентація',  'sort' => 5],
    ],
];

foreach ( $defaults as $object_id => $rows ) {

    $exists = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}1br_event_types WHERE object_id = %d",
            $object_id
        )
    );

    if ( $exists === 0 ) {
        foreach ( $rows as $row ) {
            $wpdb->insert(
                $wpdb->prefix . '1br_event_types',
                [
                    'object_id'  => $object_id,
                    'label'      => $row['label'],
                    'is_visible' => 1,
                    'sort_order' => $row['sort'],
                ],
                [
                    '%d',
                    '%s',
                    '%d',
                    '%d',
                ]
            );
        }
    }
}

/**
 * ============================================================
 * ТАБЛИЦА: wp_1br_visitors_count
 * ------------------------------------------------------------
 * Назначение:
 *  - варианты количества посетителей
 *  - используется в форме бронирования
 * ============================================================
 */
$table_visitors = $wpdb->prefix . '1br_visitors_count';

$sql_visitors = "
    CREATE TABLE {$table_visitors} (
        id INT NOT NULL AUTO_INCREMENT,
        object_id INT NOT NULL,
        label VARCHAR(255) NOT NULL,
        coef DECIMAL(5,2) NOT NULL DEFAULT 1.00,
        is_visible TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_object_id (object_id)
    ) {$charset_collate};
";

dbDelta( $sql_visitors );

/* === default records === */
$existing_rows = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$table_visitors}"
);

if ( $existing_rows === 0 ) {

    // object_id = 1
    $wpdb->insert(
        $table_visitors,
        [
            'object_id'  => 1,
            'label'      => 'Up to 25 people',
            'coef'       => 1.00,
            'is_visible' => 1,
            'sort_order' => 0,
        ]
    );

    $wpdb->insert(
        $table_visitors,
        [
            'object_id'  => 1,
            'label'      => 'Up to 50 people',
            'coef'       => 1.80,
            'is_visible' => 1,
            'sort_order' => 1,
        ]
    );

    $wpdb->insert(
        $table_visitors,
        [
            'object_id'  => 1,
            'label'      => 'Over 50 people',
            'coef'       => 2.30,
            'is_visible' => 1,
            'sort_order' => 2,
        ]
    );

    // object_id = 2
    $wpdb->insert(
        $table_visitors,
        [
            'object_id'  => 2,
            'label'      => 'до 25 учасників',
            'coef'       => 1.00,
            'is_visible' => 1,
            'sort_order' => 0,
        ]
    );
}

// ============================================================
// Начальные значения количества посетителей (DEMO: object_id = 1 и 2)
// ============================================================

$defaults = [
    1 => [
        ['label' => 'Up to 25 participants',      'coef' => 1.00, 'sort' => 0],
        ['label' => '26–50 participants',         'coef' => 1.80, 'sort' => 1],
        ['label' => 'More than 50 participants',  'coef' => 2.30, 'sort' => 2],
    ],
    2 => [
        ['label' => 'кількість учасників до 25',  'coef' => 1.00, 'sort' => 0],
        ['label' => 'кількість учасників 26–50',  'coef' => 1.80, 'sort' => 1],
        ['label' => 'кількість учасників 51–80',  'coef' => 2.30, 'sort' => 2],
    ],
];

foreach ( $defaults as $object_id => $rows ) {

    $exists = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_visitors} WHERE object_id = %d",
            $object_id
        )
    );

    if ( $exists === 0 ) {
        foreach ( $rows as $row ) {
            $wpdb->insert(
                $table_visitors,
                [
                    'object_id'  => $object_id,
                    'label'      => $row['label'],
                    'coef'       => $row['coef'],
                    'sort_order' => $row['sort'],
                    'is_visible' => 1,
                ],
                ['%d','%s','%f','%d','%d']
            );
        }
    }
}

/**
 * ============================================================
 * ТАБЛИЦА: wp_1br_services
 * ------------------------------------------------------------
 * Назначение:
 *  - оборудование и дополнительные услуги
 *  - участвуют в расчёте стоимости аренды
 * ============================================================
 */
$table_services = $wpdb->prefix . '1br_services';

$sql_services = "
    CREATE TABLE {$table_services} (
        id INT NOT NULL AUTO_INCREMENT,
        object_id INT NOT NULL,
        label VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        price_unit VARCHAR(20) NOT NULL DEFAULT 'once',
        is_visible TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_object_id (object_id)
    ) {$charset_collate};
";

dbDelta( $sql_services );

/* ============================================================
   ДЕФОЛТНЫЕ ЗАПИСИ (из txt)
   ============================================================ */
$existing_services = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$table_services}"
);

if ( $existing_services === 0 ) {

    // object_id = 1
    $wpdb->insert(
        $table_services,
        [
            'object_id'  => 1,
            'label'      => 'Video projector',
            'price'      => 50.00,
            'price_unit' => 'per_hour',
            'is_visible' => 1,
            'sort_order' => 0,
        ]
    );

    $wpdb->insert(
        $table_services,
        [
            'object_id'  => 1,
            'label'      => 'Wireless microphones',
            'price'      => 50.00,
            'price_unit' => 'per_hour',
            'is_visible' => 1,
            'sort_order' => 1,
        ]
    );

    $wpdb->insert(
        $table_services,
        [
            'object_id'  => 1,
            'label'      => 'Flipchart',
            'price'      => 30.00,
            'price_unit' => 'per_hour',
            'is_visible' => 1,
            'sort_order' => 2,
        ]
    );

    $wpdb->insert(
        $table_services,
        [
            'object_id'  => 1,
            'label'      => 'Catering',
            'price'      => 1000.00,
            'price_unit' => 'once',
            'is_visible' => 1,
            'sort_order' => 3,
        ]
    );

    // object_id = 2
    $wpdb->insert(
        $table_services,
        [
            'object_id'  => 2,
            'label'      => 'TV',
            'price'      => 50.00,
            'price_unit' => 'per_hour',
            'is_visible' => 1,
            'sort_order' => 0,
        ]
    );

    $wpdb->insert(
        $table_services,
        [
            'object_id'  => 2,
            'label'      => 'Фліпчарт',
            'price'      => 20.00,
            'price_unit' => 'per_hour',
            'is_visible' => 1,
            'sort_order' => 1,
        ]
    );

    $wpdb->insert(
        $table_services,
        [
            'object_id'  => 2,
            'label'      => 'Промостійка',
            'price'      => 200.00,
            'price_unit' => 'once',
            'is_visible' => 1,
            'sort_order' => 2,
        ]
    );
}



/**
 * ============================================================
 * ТАБЛИЦА: wp_1br_rental_rate
 * ------------------------------------------------------------
 * Назначение:
 *  - базовая арендная ставка (1 запись на объект)
 * ============================================================
 */
$table_rental_rate = $wpdb->prefix . '1br_rental_rate';

$sql_rental_rate = "
    CREATE TABLE {$table_rental_rate} (
        id INT NOT NULL AUTO_INCREMENT,
        object_id INT NOT NULL,
        label VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_object_id (object_id)
    ) {$charset_collate};
";

dbDelta( $sql_rental_rate );

/* === default records === */
$existing_rates = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$table_rental_rate}"
);

if ( $existing_rates === 0 ) {

    $wpdb->insert(
        $table_rental_rate,
        [
            'object_id' => 1,
            'label'     => 'Base rental rate — working day',
            'price'     => 500.00,
            'currency'  => 'UAH',
        ]
    );

    $wpdb->insert(
        $table_rental_rate,
        [
            'object_id' => 2,
            'label'     => 'Рабочий день/1 час',
            'price'     => 15.00,
            'currency'  => 'EUR',
        ]
    );
}



/**
 * ============================================================
 * ТАБЛИЦА: wp_1br_admin_contacts
 * ------------------------------------------------------------
 * Назначение:
 *  - контакты администраторов для уведомлений
 * ============================================================
 */
$table_admin_contacts = $wpdb->prefix . '1br_admin_contacts';
$sql_admin_contacts = "
    CREATE TABLE {$table_admin_contacts} (
        id INT NOT NULL AUTO_INCREMENT,
        object_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        send_mail TINYINT(1) NOT NULL DEFAULT 1,
        first_name VARCHAR(255) DEFAULT NULL,
        last_name VARCHAR(255) DEFAULT NULL,
        position VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY idx_object_id (object_id),
        KEY idx_email (email)
    ) {$charset_collate};
";

dbDelta( $sql_admin_contacts );
/**
 * ============================================================
 * ТАБЛИЦА: wp_1br_user_contacts
 * ------------------------------------------------------------
 * Назначение:
 *  - контактные данные пользователей формы
 *  - автоподстановка данных по email
 * ============================================================
 */
$table_user_contacts = $wpdb->prefix . '1br_user_contacts';

$sql_user_contacts = "
CREATE TABLE {$table_user_contacts} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    email VARCHAR(255) NOT NULL,
    company    VARCHAR(255) DEFAULT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name  VARCHAR(255) NOT NULL,
    phone      VARCHAR(50)  NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_email (email),
    KEY idx_company (company)
) {$charset_collate};
";

dbDelta( $sql_user_contacts );



/*============================================================
 * ТАБЛИЦА: wp_1br_admin_contact_objects
 * ============================================================*/
$table_admin_contact_objects = $wpdb->prefix . '1br_admin_contact_objects';

$sql_admin_contact_objects = "
CREATE TABLE {$table_admin_contact_objects} (
    id INT NOT NULL AUTO_INCREMENT,

    admin_contact_id INT NOT NULL,
    object_id INT NOT NULL,

    send_mail TINYINT(1) NOT NULL DEFAULT 1,
    is_active TINYINT(1) NOT NULL DEFAULT 1,

    PRIMARY KEY (id),
    KEY idx_admin_contact_id (admin_contact_id),
    KEY idx_object_id (object_id)
) {$charset_collate};
";

dbDelta( $sql_admin_contact_objects );



/**
 * ============================================================
 * ТАБЛИЦА: wp_1br_day_holiday_coeff
 * ============================================================
 */
$table_holiday_coeff = $wpdb->prefix . '1br_day_holiday_coeff';

$sql_holiday_coeff = "
    CREATE TABLE {$table_holiday_coeff} (
        id INT NOT NULL AUTO_INCREMENT,
        object_id INT NOT NULL,
        label VARCHAR(255) NOT NULL,
        coefficient DECIMAL(10,2) NOT NULL,
        PRIMARY KEY (id),
        KEY idx_object_id (object_id)
    ) {$charset_collate};
";

dbDelta( $sql_holiday_coeff );
/**
 * ============================================================
 * ТАБЛИЦА: wp_1br_day_time_coeff
 * ============================================================
 */
$table_time_coeff = $wpdb->prefix . '1br_day_time_coeff';

$sql_time_coeff = "
    CREATE TABLE {$table_time_coeff} (
        id INT NOT NULL AUTO_INCREMENT,
        object_id INT NOT NULL,
        label VARCHAR(255) NOT NULL,
        coefficient DECIMAL(10,2) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY idx_object_id (object_id)
    ) {$charset_collate};
";

dbDelta( $sql_time_coeff );

/**
 * ============================================================
 * Значения по умолчанию (из существующей БД)
 * ============================================================
 */
$existing_coeff = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$table_holiday_coeff}"
);

if ( $existing_coeff === 0 ) {

    $wpdb->insert(
        $table_holiday_coeff,
        [
            'object_id'   => 1,
            'label'       => 'Праздник/1час',
            'coefficient' => 1000.00,
        ]
    );

    $wpdb->insert(
        $table_holiday_coeff,
        [
            'object_id'   => 2,
            'label'       => 'Base rental rate — holiday',
            'coefficient' => 25.00,
        ]
    );
}



/**
 * ============================================================
 * ТАБЛИЦА: wp_1br_day_weekend_coeff
 * ------------------------------------------------------------
 * Назначение:
 *  - коэффициент / ставка для выходных дней
 * ============================================================
 */
$table_day_weekend_coeff = $wpdb->prefix . '1br_day_weekend_coeff';

$sql_day_weekend_coeff = "
    CREATE TABLE {$table_day_weekend_coeff} (
        id INT NOT NULL AUTO_INCREMENT,
        object_id INT NOT NULL,
        label VARCHAR(255) NOT NULL,
        coefficient DECIMAL(10,2) NOT NULL,
        PRIMARY KEY (id)
    ) {$charset_collate};
";

dbDelta( $sql_day_weekend_coeff );

/* === default records === */
$records = [
    [
        'object_id'   => 1,
        'label'       => 'Base rental rate — weekend',
        'coefficient' => 800.00,
    ],
    [
        'object_id'   => 2,
        'label'       => 'Выходной день/1 час',
        'coefficient' => 20.00,
    ],
];

foreach ( $records as $row ) {

    $exists = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_day_weekend_coeff} WHERE object_id = %d",
            $row['object_id']
        )
    );

    if ( $exists === 0 ) {
        $wpdb->insert(
            $table_day_weekend_coeff,
            $row,
            [ '%d', '%s', '%f' ]
        );
    }
}


/* над этой строкой место вставки следующего кода всегда перед самой последней скобкой */
}




  


