<?php
/**
 * MTS Simple Booking Uninstall
 *
 * @Filename	uninstall.php
 * @Date		2012-12-24
 * @Author		S.Hayashi
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit();
}

global $wpdb;

$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mts_simple_booking%'");
$wpdb->query("DROP TABLE " . $wpdb->prefix . 'mtssb_booking');
