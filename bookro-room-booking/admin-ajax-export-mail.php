<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/admin-ajax-export-mail.php
 *
 * Назначение:
 *  - AJAX-обработчики НОВОГО блока "Export via Email"
 *  - Загрузка администраторов для селектов
 *  - Работа БЕЗ перезагрузки страницы
 *
 * Используемые таблицы:
 *  - wp_1br_admin_contacts
 *  - wp_1br_objects
 * ------------------------------------------------------------
 */

defined('ABSPATH') || exit;

/* ============================================================
  Чек бокс отключения отправки почты клиентам 
   ============================================================*/
   add_action('wp_ajax_br_toggle_client_emails', 'br_toggle_client_emails');

function br_toggle_client_emails() {
 error_log('BR_TOGGLE_CLIENT_EMAILS: HANDLER ENTERED');
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error('Access denied');
    }

    if ( ! isset($_POST['disabled']) ) {
        wp_send_json_error('Missing parameter');
    }

    $disabled = (int) $_POST['disabled'];
error_log('BR_TOGGLE_CLIENT_EMAILS: VALUE = ' . $disabled);
    // 0 = emails enabled, 1 = emails disabled
    update_option('br_disable_client_emails', $disabled);
 error_log('BR_TOGGLE_CLIENT_EMAILS: OPTION SAVED');
    wp_send_json_success([
     'disabled' => $disabled   
    ]);
}

   
/* ============================================================
   GET ADMINS FOR OBJECT (PARTIAL ACCESS)
   ------------------------------------------------------------
   Возвращает администраторов, которых МОЖНО привязать
   к выбранному объекту.

   Правило:
   - берём ТОЛЬКО админов из основной таблицы (object_id = 0)
   - исключаем админов, которые УЖЕ привязаны к этому объекту
   ============================================================ */
add_action('wp_ajax_br_get_admins_for_object', 'br_get_admins_for_object');

function br_get_admins_for_object() {

    global $wpdb;

    $object_id = (int) ($_POST['object_id'] ?? 0);
    if ($object_id <= 0) {
        wp_send_json_success([]);
    }

    $table_contacts = esc_sql( $wpdb->prefix . '1br_admin_contacts' );

$admins = $wpdb->get_results(
    $wpdb->prepare(
        "
        SELECT
            ac.email,
            ac.first_name,
            ac.last_name
        FROM {$table_contacts} ac
        WHERE ac.object_id = 0
          AND NOT EXISTS (
              SELECT 1
              FROM {$table_contacts} x
              WHERE x.email = ac.email
                AND x.object_id = %d
          )
        ORDER BY ac.last_name ASC
        ",
        $object_id
    ),
    ARRAY_A
);

wp_send_json_success($admins);
}




/* ============================================================
   ADD ADMIN TO OBJECT (PARTIAL ACCESS)
   ------------------------------------------------------------
   Создаёт связь admin ↔ object
   Таблица: wp_1br_admin_contact_objects
   ============================================================ */
add_action('wp_ajax_br_add_admin_to_object_partial', 'br_add_admin_to_object_partial');

function br_add_admin_to_object_partial() {

    global $wpdb;

    $table_contacts = esc_sql( $wpdb->prefix . '1br_admin_contacts' );
	$table_links    = esc_sql( $wpdb->prefix . '1br_admin_contact_objects' );


    $object_id = (int) ($_POST['object_id'] ?? 0);
    $email     = sanitize_email($_POST['email'] ?? '');

    if ($object_id <= 0 || $email === '') {
        wp_send_json_error('Invalid data');
    }

    /* Получаем admin_contact_id */
    $admin_id = (int) $wpdb->get_var(
        $wpdb->prepare(
            "
            SELECT id
            FROM {$table_contacts}
            WHERE email = %s AND object_id = 0
            LIMIT 1
            ",
            $email
        )
    );

    if (!$admin_id) {
        wp_send_json_error('Admin not found');
    }

    /* Проверяем: уже есть привязка */
    $table_links = esc_sql( $wpdb->prefix . '1br_admin_contact_objects' );

$exists = (int) $wpdb->get_var(
    $wpdb->prepare(
        "
        SELECT COUNT(*)
        FROM {$table_links}
        WHERE admin_contact_id = %d
          AND object_id = %d
        ",
        $admin_id,
        $object_id
    )
);


    if ($exists > 0) {
        wp_send_json_error('Already assigned');
    }

    /* Вставляем привязку */
    $wpdb->insert(
        $table_links,
        [
            'admin_contact_id' => $admin_id,
            'object_id'        => $object_id,
            'send_mail'        => 1,
            'is_active'        => 1
        ],
        ['%d','%d','%d','%d']
    );

    if ($wpdb->last_error) {
        wp_send_json_error($wpdb->last_error);
    }

    wp_send_json_success();
}

/* ============================================================
   GET PARTIAL ACCESS TABLE (HTML)
   ------------------------------------------------------------
   Возвращает <tbody> таблицы администраторов
   с ЧАСТИЧНОЙ привязкой (НЕ full)
   ============================================================ */
add_action('wp_ajax_br_get_partial_access_table', 'br_get_partial_access_table');

function br_get_partial_access_table() {

    global $wpdb;

    $table_contacts = esc_sql( $wpdb->prefix . '1br_admin_contacts' );
	$table_links    = esc_sql( $wpdb->prefix . '1br_admin_contact_objects' );
	$table_objects  = esc_sql( $wpdb->prefix . '1br_objects' );


    $sort = $_POST['sort'] ?? 'object';
$dir  = ($_POST['dir'] ?? 'asc');

$allowed_sort = [
    'object'    => 'o.name',
    'last_name' => 'ac.last_name',
];

$allowed_dir = [
    'asc'  => 'ASC',
    'desc' => 'DESC',
];

$sort_column = $allowed_sort[$sort] ?? 'o.name';
$sort_dir    = $allowed_dir[strtolower($dir)] ?? 'ASC';

$order_sql = esc_sql( $sort_column . ' ' . $sort_dir );



    $rows = $wpdb->get_results(
        "
        SELECT
            o.name AS object_name,
            o.id   AS object_id,
            ac.email,
            ac.first_name,
            ac.last_name,
            ac.position,
            ac.phone
        FROM {$table_links} l
        INNER JOIN {$table_contacts} ac
            ON ac.id = l.admin_contact_id
        INNER JOIN {$table_objects} o
            ON o.id = l.object_id
        ORDER BY {$order_sql}
        ",
        ARRAY_A
    );

    ob_start();

    if (empty($rows)) : ?>

        <tr>
            <td colspan="7" style="text-align:center; color:#777;">
                No administrators assigned to individual objects
            </td>
        </tr>

    <?php else : ?>

        <?php foreach ($rows as $row) : ?>

            <tr>
                <td><?php echo esc_html($row['object_name']); ?></td>
                <td><?php echo esc_html($row['last_name']); ?></td>
                <td><?php echo esc_html($row['first_name']); ?></td>
                <td><?php echo esc_html($row['position']); ?></td>
                <td><?php echo esc_html($row['phone']); ?></td>
                <td><?php echo esc_html($row['email']); ?></td>
                <td style="text-align:center;">
                    <button
                        type="button"
                        class="button button-link-delete br-delete-partial"
                        data-email="<?php echo esc_attr($row['email']); ?>"
                        data-object-id="<?php echo (int) $row['object_id']; ?>"
                    >
                        Delete
                    </button>
                </td>
            </tr>

        <?php endforeach; ?>

    <?php endif;

    wp_send_json_success([
        'html' => ob_get_clean()
    ]);
}

/* ============================================================
   GET FULL ACCESS TABLE (HTML)
   ------------------------------------------------------------
   Возвращает <tbody> таблицы администраторов
   с ПОЛНЫМ доступом (привязаны ко ВСЕМ объектам)
   ============================================================ */
add_action('wp_ajax_br_get_full_access_table', 'br_get_full_access_table');

function br_get_full_access_table() {

    global $wpdb;

    $table_admins  = esc_sql( $wpdb->prefix . '1br_admin_contacts' );
	$table_objects = esc_sql( $wpdb->prefix . '1br_objects' );


    // количество всех объектов
    $total_objects = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$table_objects}"
    );

    /*
     * Берём администраторов, у которых
     * количество привязок == количеству объектов
     */
    $rows = $wpdb->get_results(
    "
    SELECT
        ac.email,
        MAX(ac.first_name) AS first_name,
        MAX(ac.last_name)  AS last_name,
        MAX(ac.position)   AS position,
        MAX(ac.phone)      AS phone
    FROM {$table_admins} ac
    WHERE NOT EXISTS (
        SELECT 1
        FROM {$table_objects} o
        WHERE NOT EXISTS (
            SELECT 1
            FROM {$table_admins} x
            WHERE x.email = ac.email
              AND x.object_id = o.id
        )
    )
    GROUP BY ac.email
    ORDER BY last_name ASC
    ",
    ARRAY_A
);


    ob_start();

    if (empty($rows)) : ?>

        <tr>
            <td colspan="6" style="text-align:center; color:#777;">
                No administrators assigned to all objects
            </td>
        </tr>

    <?php else : ?>

        <?php foreach ($rows as $row) : ?>

            <tr>
                <td><?php echo esc_html($row['last_name']); ?></td>
                <td><?php echo esc_html($row['first_name']); ?></td>
                <td><?php echo esc_html($row['position']); ?></td>
                <td><?php echo esc_html($row['phone']); ?></td>
                <td><?php echo esc_html($row['email']); ?></td>
                <td style="text-align:center;">
                    <!-- кнопка Delete будет подключена отдельным шагом -->
                    <button
                        type="button"
                        class="button button-link-delete br-delete-full"
                        data-email="<?php echo esc_attr($row['email']); ?>"
                    >
                        Delete
                    </button>
                </td>
            </tr>

        <?php endforeach; ?>

    <?php endif;

    $html = ob_get_clean();

    wp_send_json_success([
        'html' => $html
    ]);
}
/* ============================================================
   GET ADMINS FOR FULL ACCESS SELECT
   ------------------------------------------------------------
   Возвращает администраторов, которых МОЖНО добавить
   ко всем объектам.
   Исключаются админы, которые УЖЕ имеют full access.
   ============================================================ */
add_action('wp_ajax_br_get_admins_for_full_access', 'br_get_admins_for_full_access');

function br_get_admins_for_full_access() {

    global $wpdb;

	$table_admins  = esc_sql( $wpdb->prefix . '1br_admin_contacts' );
	$table_objects = esc_sql( $wpdb->prefix . '1br_objects' );


    // количество всех объектов
    $total_objects = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$table_objects}"
    );

    /*
     * Берём администраторов:
     * - уникально по email
     * - которые НЕ привязаны ко всем объектам
     */
    $rows = $wpdb->get_results(
    "
    SELECT
        ac.email,
        MAX(ac.first_name) AS first_name,
        MAX(ac.last_name)  AS last_name
    FROM {$table_admins} ac
    WHERE EXISTS (
        SELECT 1
        FROM {$table_objects} o
        WHERE NOT EXISTS (
            SELECT 1
            FROM {$table_admins} x
            WHERE x.email = ac.email
              AND x.object_id = o.id
        )
    )
    GROUP BY ac.email
    ORDER BY last_name ASC
    ",
    ARRAY_A
);


    wp_send_json_success($rows);
}
/* ============================================================
   ADD ADMIN TO ALL OBJECTS (FULL ACCESS)
   ------------------------------------------------------------
   Делает администратора full access:
   - удаляет ВСЕ существующие привязки к объектам
   - добавляет привязки ко ВСЕМ объектам
   ============================================================ */
add_action('wp_ajax_br_add_admin_to_all_objects', 'br_add_admin_to_all_objects');

function br_add_admin_to_all_objects() {

    global $wpdb;

	$table_admins  = esc_sql( $wpdb->prefix . '1br_admin_contacts' );
	$table_objects = esc_sql( $wpdb->prefix . '1br_objects' );


    $email = sanitize_email($_POST['email'] ?? '');
    if ($email === '') {
        wp_send_json_error('Invalid email');
    }

    // получаем администратора (шаблон данных)
    $admin = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT email, first_name, last_name, position, phone
             FROM {$table_admins}
             WHERE email = %s
             LIMIT 1",
            $email
        ),
        ARRAY_A
    );

    if (!$admin) {
        wp_send_json_error('Admin not found');
    }

    // получаем все объекты
    $objects = $wpdb->get_results(
        "SELECT id FROM {$table_objects}",
        ARRAY_A
    );

    if (empty($objects)) {
        wp_send_json_error('No objects found');
    }

    // 1) удаляем ВСЕ существующие привязки администратора к объектам
    $wpdb->delete(
        $table_admins,
        [
            'email'     => $email,
            'object_id' => ['>', 0],
        ],
        ['%s', '%d']
    );

    // 2) добавляем привязки ко ВСЕМ объектам
    foreach ($objects as $object) {

        $wpdb->insert(
            $table_admins,
            [
                'object_id'  => (int) $object['id'],
                'email'      => $admin['email'],
                'first_name' => $admin['first_name'],
                'last_name'  => $admin['last_name'],
                'position'   => $admin['position'],
                'phone'      => $admin['phone'],
                'send_mail'  => 1,
                'is_active'  => 1,
            ],
            ['%d','%s','%s','%s','%s','%s','%d','%d']
        );
    }

    wp_send_json_success();
}

/* ============================================================
   DELETE ADMIN ↔ OBJECT (PARTIAL ACCESS)
   ------------------------------------------------------------
   Удаляет одну привязку admin ↔ object
   ============================================================ */
add_action('wp_ajax_br_delete_admin_partial', 'br_delete_admin_partial');

function br_delete_admin_partial() {

    global $wpdb;

    $email     = sanitize_email($_POST['email'] ?? '');
    $object_id = (int) ($_POST['object_id'] ?? 0);

    if ($email === '' || $object_id <= 0) {
        wp_send_json_error('Invalid data');
    }

    // получаем id администратора
    $table_contacts = esc_sql( $wpdb->prefix . '1br_admin_contacts' );


$admin_id = (int) $wpdb->get_var(
    $wpdb->prepare(
        "
        SELECT id
        FROM {$table_contacts}
        WHERE email = %s
          AND object_id = 0
        LIMIT 1
        ",
        $email
    )
);




    if (!$admin_id) {
        wp_send_json_error('Admin not found');
    }

    // удаляем связь из таблицы связей
    $wpdb->delete(
        $wpdb->prefix . '1br_admin_contact_objects',
        [
            'admin_contact_id' => $admin_id,
            'object_id'        => $object_id
        ],
        ['%d', '%d']
    );

    wp_send_json_success();
}

/* ============================================================
   DELETE ADMIN (FULL ACCESS)
   ------------------------------------------------------------
   Удаляет ВСЕ привязки администратора ко всем объектам
   ============================================================ */
add_action('wp_ajax_br_delete_admin_full', 'br_delete_admin_full');

function br_delete_admin_full() {

    global $wpdb;

    $table = esc_sql( $wpdb->prefix . '1br_admin_contacts' );


    $email = sanitize_email($_POST['email'] ?? '');

    if ($email === '') {
        wp_send_json_error('Invalid email');
    }

    // удаляем все записи администратора
    $wpdb->query(
    $wpdb->prepare(
        "
        DELETE FROM {$table}
        WHERE email = %s
          AND object_id > 0
        ",
        $email
    )
);


    // помечаем администратора как неактивного
    $wpdb->update(
        $table,
        ['is_active' => 0],
        ['email' => $email],
        ['%d'],
        ['%s']
    );

    wp_send_json_success();
}
