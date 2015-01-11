<?php
// si no llamamos a uninstall desde el administrador,salimos.
if (!defined('WP_UNINSTALL_PLUGIN')) exit();
if (!defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

delete_option('qsmz_category_hide');
delete_option('qsmz_category_shop');
delete_option('qsmz_getCNX');
delete_option('qsmz_myplugin_options');
delete_option('qsmz_permitir_imagenes');
delete_option('qsmz_permitir_png');
delete_option('qsmz_store_id');
delete_option('qsmz_store_key');
delete_option('qsmz_user_id');
delete_option('qsmz_user_key');
delete_option('qsmz_user_login');
delete_option('qsmz_user_password');
delete_option('qsmz_wp_key');
delete_option('qstomizer_license_key');
delete_option('qstomizer_license_status');
delete_option('qstomizer_version');
delete_option('qsmz_ignore_promo');

global $current_user ;
$user_id = $current_user->ID;
delete_user_meta($user_id, 'qsmz_ignore_promo');

// delete custom tables
global $wpdb;
$prefijodb = $wpdb->prefix;
$table_name = $prefijodb."qstomizer_plugin_nonces";
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

?>
