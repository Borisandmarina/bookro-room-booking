<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/admin-block-global.php
 * Назначение:
 *  - блок "Global settings"
 *  - управление рабочими слотами, временной зоной и выходными
 * ------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="br-admin-block">

    <!-- ===============================
         Header
         =============================== -->
    <div class="br-admin-block-header">
        <h2>Global settings</h2>
    </div>

    <div class="br-admin-block-body">

        <table class="form-table">
            <tbody>

            <!-- Work start slot -->
            <tr>
                <th scope="row">Work start slot</th>
                <td>
                    <select id="br-work-start-slot"></select>
                </td>
            </tr>

            <!-- Work end slot -->
            <tr>
                <th scope="row">Work end slot</th>
                <td>
                    <select id="br-work-end-slot"></select>
                </td>
            </tr>

           

            <!-- Timezone -->
            <tr>
                <th scope="row">Timezone</th>
                <td>
                    <select id="br-timezone">
                        <?php
                        global $wpdb;

                        $current_timezone = $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT timezone
                                 FROM {$wpdb->prefix}1br_global_settings
                                 WHERE object_id = %d
                                 LIMIT 1",
                                $current_object_id
                            )
                        );

                        $now = new DateTime('now', new DateTimeZone('UTC'));
                        $timezones = DateTimeZone::listIdentifiers();

                        foreach ($timezones as $tz) {
                            try {
                                $tzObj = new DateTimeZone($tz);
                                $offsetSeconds = $tzObj->getOffset($now);
                            } catch (Exception $e) {
                                continue;
                            }

                            $hours   = (int) floor($offsetSeconds / 3600);
                            $minutes = abs((int) (($offsetSeconds % 3600) / 60));
                            $sign    = $hours >= 0 ? '+' : '-';

                            $offsetLabel = sprintf(
                                'UTC%s%02d:%02d',
                                $sign,
                                abs($hours),
                                $minutes
                            );

                            printf(
                                '<option value="%s"%s>%s — %s</option>',
                                esc_attr($tz),
                                selected($tz, $current_timezone, false),
                                esc_html($offsetLabel),
                                esc_html($tz)
                            );
                        }
                        ?>
                    </select>
                </td>
            </tr>

            </tbody>
        </table>

        <!-- ===============================
             Weekends
             =============================== -->
        <h3>Weekends</h3>

        <div style="display:flex; gap:10px; margin-bottom:10px;">
            <select id="br-weekend-day">
                <option value="Monday">Monday</option>
                <option value="Tuesday">Tuesday</option>
                <option value="Wednesday">Wednesday</option>
                <option value="Thursday">Thursday</option>
                <option value="Friday">Friday</option>
                <option value="Saturday">Saturday</option>
                <option value="Sunday">Sunday</option>
                <option value="0">No weekends</option>
            </select>

            <button class="button button-primary" id="br-add-weekend">
                Add
            </button>
        </div>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Day</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="br-weekends-table"></tbody>
        </table>

    </div>
</div>

<script>
/* ============================================================
   Helpers
   ============================================================ */

function brFillSelect(id, from, to) {
    const select = document.getElementById(id);
    select.innerHTML = '';
    for (let i = from; i <= to; i++) {
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = i;
        select.appendChild(opt);
    }
}

/* ============================================================
   Load global settings
   ============================================================ */
function brLoadGlobalSettings() {

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'br_get_global_settings',
            object_id: <?php echo (int) $current_object_id; ?>
        })
    })
    .then(r => r.json())
    .then(resp => {
        if (!resp || !resp.success) return;

        const s = resp.data;

        document.getElementById('br-work-start-slot').value = s.work_start_slot;
        document.getElementById('br-work-end-slot').value   = s.work_end_slot;
        document.getElementById('br-timezone').value        = s.timezone;

        const tbody = document.getElementById('br-weekends-table');
        tbody.innerHTML = '';

        const weekends = Array.isArray(s.weekends) ? s.weekends : [];

        weekends.forEach(d => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${d}</td>
                <td>
                    <button class="button button-small button-link-delete"
                        onclick="brDeleteWeekend('${d}')">
                        Delete
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    });
}

/* ============================================================
   Save setting
   ============================================================ */

function brSaveSetting(field, value) {
    return fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'br_save_global_setting',
            object_id: <?php echo (int) $current_object_id; ?>,
            field: field,
            value: value
        })
    }).then(r => r.json());
}

/* ============================================================
   Weekends handlers
   ============================================================ */

window.brDeleteWeekend = function(day) {
    brSaveSetting('weekends_remove', day)
        .then(resp => resp && resp.success && brLoadGlobalSettings());
};

document.addEventListener('DOMContentLoaded', () => {

    brFillSelect('br-work-start-slot', 1, 22);
    brFillSelect('br-work-end-slot',   2, 23);

    const map = {
        'br-work-start-slot': 'work_start_slot',
        'br-work-end-slot':   'work_end_slot',
        'br-timezone':        'timezone'
    };

    Object.keys(map).forEach(id => {
        document.getElementById(id).addEventListener('change', e => {
            brSaveSetting(map[id], e.target.value);
        });
    });

    document.getElementById('br-add-weekend').addEventListener('click', () => {
        const day = document.getElementById('br-weekend-day').value;
        brSaveSetting('weekends_add', day)
            .then(resp => resp && resp.success && brLoadGlobalSettings());
    });

    brLoadGlobalSettings();
});
</script>
