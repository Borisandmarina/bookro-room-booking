<?php
/**
 * Plugin: Booking Room
 * File: uninstall.php
 * Purpose: Remove personal data tables on plugin deletion.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$tables = [
    $wpdb->prefix . '1br_admin_contacts',
    $wpdb->prefix . '1br_admin_contact_objects',
    $wpdb->prefix . '1br_user_contacts',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}
