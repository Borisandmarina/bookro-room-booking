<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/booking-export-email.php
 *
 * Назначение:
 *  - Формирование и отправка email-уведомлений о бронировании
 *  - Поддержка:
 *      • письма администраторам (всегда, если Email: On / Off = ON)
 *      • письма клиенту (2 триггера)
 *  - Учет глобального флага:
 *      • "Не отправлять письма арендаторам"
 *
 * Триггеры:
 *  1) Новая запись в wp_1br_bookings (status = pending)
 *      → письмо админу
 *      → письмо клиенту: "We have received your booking request"
 *
 *  2) Изменение статуса pending → confirmed
 *      → письмо клиенту: "Your booking has been confirmed"
 *
 * ВАЖНО:
 *  - файл сам НИЧЕГО не триггерит
 *  - файл НЕ делает INSERT / UPDATE
 *  - файл только отправляет письма
 * ------------------------------------------------------------
 */

defined('ABSPATH') || exit;

/* ============================================================
   ГЛОБАЛЬНЫЕ ВСПОМОГАТЕЛЬНЫЕ ПРОВЕРКИ

   Назначение:
   - Общие флаги и проверки, влияющие на отправку писем
   - Используются всеми шаблонами писем
   ============================================================ */

function br_is_client_email_disabled(): bool {
    return (bool) get_option('br_disable_client_emails', false);
}

/* ============================================================
   ОБЩИЕ БИЛДЕРЫ ПИСЕМ (SUBJECT / BODY)

   Назначение:
   - Формирование темы письма
   - Формирование базового тела письма
   - Используются несколькими шаблонами
   - Сами по себе письма НЕ отправляют
   ============================================================ */

function br_build_email_subject(array $data): string {

    $parts = [];

    if (!empty($data['object_name'])) {
        $parts[] = $data['object_name'] . ' - rent';
    }

    if (!empty($data['event_date'])) {
        $parts[] = $data['event_date'];
    }

    if (!empty($data['time_start']) && !empty($data['time_end'])) {
        $parts[] = $data['time_start'] . ' - ' . $data['time_end'];
    }

    return implode(' | ', $parts);
}

/* ============================================================
   ОБЩИЙ БИЛДЕР ТЕЛА ПИСЬМА

   Назначение:
   - Формирует текстовое тело письма на основе данных бронирования
   - Используется всеми шаблонами писем
   - Может включать данные клиента ТОЛЬКО для письма администратору
   - Сам по себе письмо НЕ отправляет
   ============================================================ */


function br_build_email_body(array $data, string $intro, bool $include_client): string {

    $lines = [];

    $lines[] = $intro;
    $lines[] = '';

    /* Object */
    if (!empty($data['object_name'])) {
        $lines[] = 'Object: ' . $data['object_name'];
    }

    /* Date / Time */
    if (!empty($data['event_date'])) {
        $lines[] = 'Date: ' . $data['event_date'];
    }

    if (!empty($data['time_start']) && !empty($data['time_end'])) {
        $lines[] = 'Time: ' . $data['time_start'] . ' - ' . $data['time_end'];
    }

    if (!empty($data['duration'])) {
        $lines[] = 'Duration: ' . $data['duration'];
    }

    $lines[] = '';

    /* Event */
    if (!empty($data['event_type'])) {
        $lines[] = 'Event type: ' . $data['event_type'];
    } elseif (!empty($data['event_type_custom'])) {
        $lines[] = 'Event type: ' . $data['event_type_custom'];
    }

    if (!empty($data['participants'])) {
        $lines[] = 'Participants: ' . $data['participants'];
    }

    if (!empty($data['services'])) {
        $lines[] = 'Services: ' . $data['services'];
    }

    if (!empty($data['order_comment'])) {
        $lines[] = '';
        $lines[] = 'Comment: ' . $data['order_comment'];
    }

    /* Client — ТОЛЬКО админу */
    if ($include_client) {
        $lines[] = '';
        $lines[] = 'Client:';

        $lines[] = trim(
            ($data['client_name'] ?? '') . ' ' . ($data['client_surname'] ?? '')
        );

        if (!empty($data['client_company'])) {
            $lines[] = 'Company: ' . $data['client_company'];
        }

        if (!empty($data['client_phone'])) {
            $lines[] = 'Phone: ' . $data['client_phone'];
        }

        if (!empty($data['client_email'])) {
            $lines[] = 'Email: ' . $data['client_email'];
        }
    }

    /* Signature */
    $lines[] = '';
    $lines[] = 'Best regards,';
    if (!empty($data['object_name'])) {
        $lines[] = $data['object_name'];
    }

    return implode("\n", $lines);
}

/* ============================================================
   ПИСЬМО АДМИНИСТРАТОРУ

   Назначение:
   - Уведомление администраторов объекта о новой заявке
   - Отправляется при создании бронирования (status = pending)
   - Всегда отправляется, если для администратора включена отправка писем
   - Не зависит от глобального флага отключения писем клиентам
   - Содержит ссылку для подтверждения бронирования
   ============================================================ */

function br_export_booking_to_email_admin(int $booking_id): void {

    global $wpdb;

    require_once __DIR__ . '/booking-export-builder.php';

    $data = br_build_booking_export_data($booking_id);
	$data = array_map(
    function ($value) {
        return is_string($value)
            ? wp_strip_all_tags($value)
            : $value;
    },
    $data
);

    if (!$data || empty($data['object_id'])) {
        return;
    }

    // токен подтверждения
    $created_at = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT created_at
             FROM {$wpdb->prefix}1br_bookings
             WHERE id = %d",
            $booking_id
        )
    );

    $token = hash(
        'sha256',
        $booking_id . '|' . $created_at . '|' . AUTH_KEY
    );

    $confirm_url = add_query_arg(
        [
            'action'     => 'br_confirm_booking',
            'booking_id' => $booking_id,
            'token'      => $token,
        ],
        admin_url('admin-ajax.php')
    );

    /*
     * SUBJECT:
     * Object - rent | <id> | Date | Time
     */
    $subject_parts = [];

    if (!empty($data['object_name'])) {
        $subject_parts[] = $data['object_name'] . ' - rent';
    }

    $subject_parts[] = $booking_id;

    if (!empty($data['event_date'])) {
        $subject_parts[] = $data['event_date'];
    }

    if (!empty($data['time_start']) && !empty($data['time_end'])) {
        $subject_parts[] = $data['time_start'] . ' - ' . $data['time_end'];
    }

    $subject = implode(' | ', $subject_parts);

    /*
 * BODY
 */
$lines = [];

$lines[] = '📩 New booking request received.';
$lines[] = '';
$lines[] = '✅ Confirm booking:';
$lines[] = $confirm_url;
$lines[] = '';

if (!empty($data['object_name'])) {
    $lines[] = '🏢 Object: ' . $data['object_name'];
}

$lines[] = '🆔 Booking: ' . $booking_id;

if (!empty($data['event_date'])) {
    $lines[] = '📅 Date: ' . $data['event_date'];
}

if (!empty($data['time_start']) && !empty($data['time_end'])) {
    $lines[] = '⏰ Time: ' . $data['time_start'] . ' - ' . $data['time_end'];
}

if (!empty($data['duration'])) {
    $lines[] = '⏳ Duration: ' . $data['duration'];
}

$lines[] = '';

if (!empty($data['event_type'])) {
    $lines[] = '🎯 Event type: ' . $data['event_type'];
} elseif (!empty($data['event_type_custom'])) {
    $lines[] = '🎯 Event type: ' . $data['event_type_custom'];
}

if (!empty($data['participants'])) {
    $lines[] = '👥 Participants: ' . $data['participants'];
}

if (!empty($data['services'])) {
    $lines[] = '🧩 Services: ' . $data['services'];
}

if (!empty($data['price'])) {
    $lines[] = '';
    $lines[] = '💰 Price: ' . ($data['price'] ?? '') . ' ' . ($data['currency'] ?? '');
}

if (!empty($data['order_comment'])) {
    $lines[] = '';
    $lines[] = '💬 Comment: ' . $data['order_comment'];
}


    /* ============================================================
       ДАННЫЕ КЛИЕНТА (ТОЛЬКО ДЛЯ ПИСЬМА АДМИНИСТРАТОРУ)
       ============================================================ */

    $lines[] = '';
    $lines[] = 'Client:';

    $lines[] = trim(
        ($data['client_name'] ?? '') . ' ' . ($data['client_surname'] ?? '')
    );

    if (!empty($data['client_company'])) {
        $lines[] = 'Company: ' . $data['client_company'];
    }

    if (!empty($data['client_phone'])) {
        $lines[] = 'Phone: ' . $data['client_phone'];
    }

    if (!empty($data['client_email'])) {
        $lines[] = 'Email: ' . $data['client_email'];
    }

    $body = implode("\n", $lines);

    $admins = $wpdb->get_results(
    $wpdb->prepare(
        "
        SELECT ac.email
        FROM {$wpdb->prefix}1br_admin_contact_objects aco
        INNER JOIN {$wpdb->prefix}1br_admin_contacts ac
            ON ac.id = aco.admin_contact_id
        WHERE aco.object_id = %d
          AND aco.send_mail = 1
          AND aco.is_active = 1
          AND ac.is_active = 1
        ",
        (int) $data['object_id']
    ),
    ARRAY_A
);




    if (!$admins) {
        return;
    }

    foreach ($admins as $admin) {
        wp_mail($admin['email'], $subject, $body);
    }
}



/* ============================================================
   ПИСЬМО КЛИЕНТУ №1 — ЗАЯВКА ПОЛУЧЕНА

   Назначение:
   - Отправляется клиенту сразу после создания бронирования
   - Статус бронирования: pending
   - Информационное письмо
   - Подтверждение бронирования отправляется отдельным письмом
   - НЕ отправляется, если включён глобальный флаг
     "Не отправлять письма арендаторам" (br_disable_client_emails)
   ============================================================ */

function br_export_booking_to_email_client_received(int $booking_id): void {

    if (br_is_client_email_disabled()) {
        return;
    }

    require_once __DIR__ . '/booking-export-builder.php';

    $data = br_build_booking_export_data($booking_id);
	$data = array_map(
    function ($value) {
        return is_string($value)
            ? wp_strip_all_tags($value)
            : $value;
    },
    $data
);

    if (!$data || empty($data['client_email'])) {
        return;
    }

    /*
     * SUBJECT:
     * Object - rent | <id> | Date | Time
     */
    $subject_parts = [];

    if (!empty($data['object_name'])) {
        $subject_parts[] = $data['object_name'] . ' - rent';
    }

    $subject_parts[] = $booking_id;

    if (!empty($data['event_date'])) {
        $subject_parts[] = $data['event_date'];
    }

    if (!empty($data['time_start']) && !empty($data['time_end'])) {
        $subject_parts[] = $data['time_start'] . ' - ' . $data['time_end'];
    }

    $subject = implode(' | ', $subject_parts);

    /*
 * BODY
 */
$lines = [];

$lines[] = '👋 Hello ' . trim($data['client_name'] ?? '') . '!';
$lines[] = '📨 We have received the information you submitted. Booking confirmation will be sent in a separate email.';
$lines[] = '';

if (!empty($data['object_name'])) {
    $lines[] = '🏢 Object: ' . $data['object_name'];
}

$lines[] = '🆔 Booking: ' . $booking_id;

if (!empty($data['event_date'])) {
    $lines[] = '📅 Date: ' . $data['event_date'];
}

if (!empty($data['time_start']) && !empty($data['time_end'])) {
    $lines[] = '⏰ Time: ' . $data['time_start'] . ' - ' . $data['time_end'];
}

if (!empty($data['duration'])) {
    $lines[] = '⏳ Duration: ' . $data['duration'];
}

$lines[] = '';

if (!empty($data['event_type'])) {
    $lines[] = '🎯 Event type: ' . $data['event_type'];
} elseif (!empty($data['event_type_custom'])) {
    $lines[] = '🎯 Event type: ' . $data['event_type_custom'];
}

if (!empty($data['participants'])) {
    $lines[] = '👥 Participants: ' . $data['participants'];
}

if (!empty($data['price'])) {
    $lines[] = '';
    $lines[] = '💰 Price: ' . ($data['price'] ?? '') . ' ' . ($data['currency'] ?? '');
}

$lines[] = '';
$lines[] = 'Kind regards,';
$lines[] = '';


    /* ============================================================
       КОНТАКТНЫЕ ДАННЫЕ ОБЪЕКТА (ПОДПИСЬ ДЛЯ КЛИЕНТА)

       Назначение:
       - Добавляется ТОЛЬКО в клиентские письма
       - Содержит контактные данные объекта (адрес, телефон, email)
       - Название объекта НЕ дублируется, т.к. уже указано выше в письме
       ============================================================ */

    if (!empty($data['object_address'])) {
        $lines[] = $data['object_address'];
    }

    if (!empty($data['object_phone'])) {
        $lines[] = $data['object_phone'];
    }

    if (!empty($data['object_email'])) {
        $lines[] = $data['object_email'];
    }

    $body = implode("\n", $lines);

    wp_mail($data['client_email'], $subject, $body);
}



/* ============================================================
   ПИСЬМО КЛИЕНТУ №2 — БРОНИРОВАНИЕ ПОДТВЕРЖДЕНО

   Назначение:
   - Отправляется клиенту при подтверждении бронирования
   - Триггер: смена статуса pending → confirmed
   - Финальное подтверждение бронирования
   - НЕ отправляется, если включён глобальный флаг
     "Не отправлять письма арендаторам" (br_disable_client_emails)
   ============================================================ */

function br_export_booking_to_email_client_confirmed(int $booking_id): void {

    if (br_is_client_email_disabled()) {
        return;
    }

    require_once __DIR__ . '/booking-export-builder.php';

    $data = br_build_booking_export_data($booking_id);
	$data = array_map(
    function ($value) {
        return is_string($value)
            ? wp_strip_all_tags($value)
            : $value;
    },
    $data
);

    if (!$data || empty($data['client_email'])) {
        return;
    }

    /*
     * SUBJECT:
     * Object - rent | <id> | Date | Time
     */
    $subject_parts = [];

    if (!empty($data['object_name'])) {
        $subject_parts[] = $data['object_name'] . ' - rent';
    }

    $subject_parts[] = $booking_id;

    if (!empty($data['event_date'])) {
        $subject_parts[] = $data['event_date'];
    }

    if (!empty($data['time_start']) && !empty($data['time_end'])) {
        $subject_parts[] = $data['time_start'] . ' - ' . $data['time_end'];
    }

    $subject = implode(' | ', $subject_parts);

    /*
 * BODY
 */
$lines = [];

$lines[] = '👋 Hello ' . trim($data['client_name'] ?? '') . '!';
$lines[] = '✅ Your booking has been confirmed.';
$lines[] = '';

if (!empty($data['object_name'])) {
    $lines[] = '🏢 Object: ' . $data['object_name'];
}

$lines[] = '🆔 Booking: ' . $booking_id;

if (!empty($data['event_date'])) {
    $lines[] = '📅 Date: ' . $data['event_date'];
}

if (!empty($data['time_start']) && !empty($data['time_end'])) {
    $lines[] = '⏰ Time: ' . $data['time_start'] . ' - ' . $data['time_end'];
}

if (!empty($data['duration'])) {
    $lines[] = '⏳ Duration: ' . $data['duration'];
}

$lines[] = '';

if (!empty($data['event_type'])) {
    $lines[] = '🎯 Event type: ' . $data['event_type'];
} elseif (!empty($data['event_type_custom'])) {
    $lines[] = '🎯 Event type: ' . $data['event_type_custom'];
}

if (!empty($data['participants'])) {
    $lines[] = '👥 Participants: ' . $data['participants'];
}

if (!empty($data['price'])) {
    $lines[] = '';
    $lines[] = '💰 Price: ' . ($data['price'] ?? '') . ' ' . ($data['currency'] ?? '');
}

$lines[] = '';
$lines[] = 'Kind regards,';
$lines[] = '';


    /* ============================================================
       КОНТАКТНЫЕ ДАННЫЕ ОБЪЕКТА (ПОДПИСЬ ДЛЯ КЛИЕНТА)

       Назначение:
       - Добавляется ТОЛЬКО в клиентские письма
       - Содержит контактные данные объекта (адрес, телефон, email)
       - Название объекта НЕ дублируется, т.к. уже указано выше в письме
       ============================================================ */

    if (!empty($data['object_address'])) {
        $lines[] = $data['object_address'];
    }

    if (!empty($data['object_phone'])) {
        $lines[] = $data['object_phone'];
    }

    if (!empty($data['object_email'])) {
        $lines[] = $data['object_email'];
    }

    $body = implode("\n", $lines);

    wp_mail($data['client_email'], $subject, $body);
}


