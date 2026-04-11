<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/bookings-export-template.php
 *
 * Назначение:
 *  - HTML-шаблон таблицы бронирований для экспорта
 *  - Используется для HTML и Excel (XLS через HTML)
 *  - НЕ содержит запросов, AJAX, прав доступа
 *
 * Ожидаемые переменные:
 *  - array $bookings
 *  - array $event_types        (id => object{label})
 *  - array $participants       (id => object{label})
 *  - array $services           (id => object{label})
 * ------------------------------------------------------------
 */

defined('ABSPATH') || exit;

if (empty($bookings)) {
    echo '<p>No bookings found.</p>';
    return;
}
?>

<table border="1" cellpadding="6" cellspacing="0" width="100%">
    <thead>
        <tr>
            <th>ID</th>
            <th>Status</th>
            <th>Date</th>
            <th>Time</th>
            <th>Duration</th>
            <th>Event type</th>
            <th>Participants</th>
            <th>Services</th>
            <th>Price</th>
            <th>Client</th>
            <th>Company</th>
            <th>Phone</th>
            <th>Email</th>
        </tr>
    </thead>
    <tbody>

<?php foreach ($bookings as $row): ?>

<?php
    // Event type
    $event_type_label = !empty($row['event_type_custom'])
        ? $row['event_type_custom']
        : ($event_types[$row['event_type_id']]->label ?? '');

    // Participants
    $participants_label = $participants[$row['participants_option_id']]->label ?? '';

    // Services
    $service_labels = [];
    if (!empty($row['equipment_ids'])) {
        foreach (array_map('intval', explode(',', $row['equipment_ids'])) as $sid) {
            if (isset($services[$sid])) {
                $service_labels[] = $services[$sid]->label;
            }
        }
    }
?>

        <tr>
            <td><?php echo (int) $row['id']; ?></td>
            <td><?php echo esc_html(ucfirst($row['status'])); ?></td>

            <td>
<?php
$export_timezone = ! empty( $object_timezone ) ? $object_timezone : 'UTC';

$dt = new DateTimeImmutable(
    $row['event_date'],
    new DateTimeZone( $export_timezone )
);

echo esc_html( $dt->format('d.m.Y') );
?>
</td>


            <td>
                <?php
                    printf(
                        '%02d:00 - %02d:00',
                        (int) $row['slot_start'],
                        (int) $row['slot_end']
                    );
                ?>
            </td>

            <td><?php echo (int) $row['duration_slots']; ?>h</td>

            <td><?php echo esc_html($event_type_label); ?></td>
            <td><?php echo esc_html($participants_label); ?></td>
            <td><?php echo esc_html(implode(', ', $service_labels)); ?></td>

            <td><?php echo esc_html( number_format( (float) $row['price_net'], 2 ) ); ?></td>

            <td>
                <?php echo esc_html(
                    $row['client_name'] . ' ' . $row['client_surname']
                ); ?>
            </td>

            <td><?php echo esc_html($row['client_company']); ?></td>
            <td><?php echo esc_html($row['client_phone']); ?></td>
            <td><?php echo esc_html($row['client_email']); ?></td>
        </tr>

<?php endforeach; ?>

    </tbody>
</table>
