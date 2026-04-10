<?php
/**
 * ------------------------------------------------------------
 * Файл: wp-content/plugins/booking-room/admin-block-event_types.php
 * Назначение:
 *  - управление типами мероприятий (wp_1br_event_types)
 * ------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ДОСТУПНЫ:
 *  - $current_object_id
 *  - $wpdb
 */
?>

<div class="br-admin-block">

    <div class="br-admin-block-header">
        <h2>Event types</h2>
    </div>

    <div class="br-admin-block-body">

        <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
            <input
                type="text"
                id="br-event-type-label"
                placeholder="Event type name"
            />
            <button
                type="button"
                class="button button-primary"
                id="br-add-event-type">
                Add
            </button>
        </div>

        <table class="widefat striped">
            <thead>
                <tr>
                    <!-- Drag handle -->
                    <th style="width:32px;"></th>

                    <!-- Title -->
                    <th>Title</th>

                    <!-- Actions -->
                    <th style="width:120px;"></th>
                </tr>
            </thead>
            <tbody id="br-event-types-table"></tbody>
        </table>

    </div>

</div>
<style>
/* ============================================================
   Event types — drag handle
   ============================================================ */

.br-drag-handle {
    cursor: grab;
    font-size: 18px;
    color: #666;
    user-select: none;
    text-align: center;
}

.br-drag-handle:active {
    cursor: grabbing;
}
</style>
<script>
/* ============================================================
   Load event types
   ============================================================ */

function brFetchEventTypes() {

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'br_get_event_types',
            object_id: <?php echo (int) $current_object_id; ?>
        })
    })
    .then(r => r.json())
    .then(resp => {
        if (!resp || !resp.success) return;

        const tbody = document.getElementById('br-event-types-table');
        tbody.innerHTML = '';

        resp.data.forEach(row => {

    const tr = document.createElement('tr');
    tr.setAttribute('draggable', 'true');
    tr.dataset.id = row.id;

    const eye = row.is_visible == 1 ? '👁' : '🚫';

    tr.innerHTML = `
        <!-- Drag handle -->
        <td class="br-drag-handle" title="Drag to reorder">≡</td>

        <!-- Title -->
        <td
            contenteditable="true"
            data-id="${row.id}"
            class="br-event-type-label">
            ${row.label}
        </td>

        <!-- Actions -->
        <td>
            <button
                type="button"
                class="button button-small"
                onclick="brToggleEventType(${row.id})">
                ${eye}
            </button>
            <button
                type="button"
                class="button button-small button-link-delete"
                onclick="brDeleteEventType(${row.id})">
                Delete
            </button>
        </td>
    `;

    tbody.appendChild(tr);
});


        brBindInlineEdit();
        brInitDragAndDrop();
    });
}


/* ============================================================
   Inline edit
   ============================================================ */

function brBindInlineEdit() {

    document.querySelectorAll('.br-event-type-label').forEach(td => {

        let original = td.innerText.trim();

        td.addEventListener('blur', function () {

            const value = td.innerText.trim();
            const id    = td.dataset.id;

            if (!value || value === original) {
                td.innerText = original;
                return;
            }

            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'br_update_event_type',
                    id: id,
                    object_id: <?php echo (int) $current_object_id; ?>,
                    label: value
                })
            })
            .then(r => r.json())
            .then(resp => {
                if (!resp || !resp.success) {
                    td.innerText = original;
                    return;
                }

                original = value;
            });

        });

        td.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                td.blur();
            }
        });
    });
}
/* ============================================================
   Drag & Drop (DOM only, no DB)
   ============================================================ */

function brInitDragAndDrop() {

    const tbody = document.getElementById('br-event-types-table');
    let draggedRow = null;

    tbody.querySelectorAll('tr').forEach(row => {

        const handle = row.querySelector('.br-drag-handle');
        if (!handle) return;

        handle.addEventListener('mousedown', () => {
            draggedRow = row;
        });

        row.addEventListener('dragstart', e => {
            if (draggedRow !== row) {
                e.preventDefault();
                return;
            }
            row.classList.add('dragging');
        });

        row.addEventListener('dragend', () => {
    row.classList.remove('dragging');
    draggedRow = null;

    // === SAVE ORDER TO DB ===
    const order = [];
    let index = 0;

    tbody.querySelectorAll('tr').forEach(tr => {
        order.push({
            id: tr.dataset.id,
            sort_order: index++
        });
    });

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'br_update_event_types_order',
            object_id: <?php echo (int) $current_object_id; ?>,
            order: JSON.stringify(order)
        })
    });
});


        row.addEventListener('dragover', e => {
            e.preventDefault();

            const dragging = tbody.querySelector('.dragging');
            if (!dragging || dragging === row) return;

            const rect = row.getBoundingClientRect();
            const offset = e.clientY - rect.top;

            if (offset > rect.height / 2) {
                row.after(dragging);
            } else {
                row.before(dragging);
            }
        });
    });
}

/* ============================================================
   Add event type
   ============================================================ */

document.getElementById('br-add-event-type').addEventListener('click', function () {

    const input = document.getElementById('br-event-type-label');
    const value = input.value.trim();
    if (!value) return;

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'br_add_event_type',
            object_id: <?php echo (int) $current_object_id; ?>,
            label: value
        })
    })
    .then(r => r.json())
    .then(resp => {
        if (!resp || !resp.success) return;

        input.value = '';
        brFetchEventTypes();
    });
});

/* ============================================================
   Toggle visibility
   ============================================================ */

function brToggleEventType(id) {

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'br_toggle_event_type',
            id: id,
            object_id: <?php echo (int) $current_object_id; ?>
        })
    })
    .then(() => brFetchEventTypes());
}

/* ============================================================
   Delete
   ============================================================ */

function brDeleteEventType(id) {

    if (!confirm('Delete this event type?')) return;

    fetch(ajaxurl, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'br_delete_event_type',
            id: id,
            object_id: <?php echo (int) $current_object_id; ?>
        })
    })
    .then(() => brFetchEventTypes());
}

/* ============================================================
   Initial load
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {
    brFetchEventTypes();
});
</script>
