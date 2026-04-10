<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/admin-page.php
 *
 * Назначение:
 *  - Общая админ-страница плагина
 *  - Рендер вкладок
 *  - Подключение контента вкладок
 * ------------------------------------------------------------
 */

defined('ABSPATH') || exit;

// активная вкладка
$active_tab = $_GET['tab'] ?? 'settings';
?>

<div class="wrap">

    <h1>Booking Room</h1>

    <h2 class="nav-tab-wrapper">
        <a href="?page=booking-room&tab=settings"
           class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
            Настройки плагина
        </a>

        <a href="?page=booking-room&tab=export"
           class="nav-tab <?php echo $active_tab === 'export' ? 'nav-tab-active' : ''; ?>">
            Экспорт из формы бронирования
        </a>

        <a href="?page=booking-room&tab=bookings"
           class="nav-tab <?php echo $active_tab === 'bookings' ? 'nav-tab-active' : ''; ?>">
            Bookings
        </a>
    </h2>

    <div class="br-admin-tab-content">
        <?php
        switch ($active_tab) {

            case 'export':
                require BR_PLUGIN_PATH . 'admin-tab-export.php';
                break;

            case 'bookings':
                require BR_PLUGIN_PATH . 'admin-tab-bookings.php';
                break;

            case 'settings':
            default:
                require BR_PLUGIN_PATH . 'admin-tab-settings.php';
                break;
        }
        ?>
    </div>

</div>
