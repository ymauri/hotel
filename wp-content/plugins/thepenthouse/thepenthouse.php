<?php

/**
 * Plugin Name: ThePenthouse
 * Plugin URI: https://www.thepenthouse.nl/
 * Description: Send every reservation to Guesty Platform
 * Author: Yolanda Mauri Pérez
 * Version: 1.0
 * License: GPL2+
 * Requires at least: 5.5
 * Requires PHP: 5.6
 *
 */

include(plugin_dir_path(__FILE__) . 'classes/guesty.php');
include(plugin_dir_path(__FILE__) . 'classes/reservation-notifier.php');
include(plugin_dir_path(__FILE__) . 'classes/reservation.php');
include(plugin_dir_path(__FILE__) . 'classes/calendar.php');
include(plugin_dir_path(__FILE__) . 'classes/configs.php');

// Create database structure
register_activation_hook(__FILE__, 'datatbase_structure');

function datatbase_structure()
{
  global $wpdb;

  $reservations = $wpdb->prefix . "reservations";
  $calendars = $wpdb->prefix . "calendars";
  $charset_collate = $wpdb->get_charset_collate();

  $sql = "CREATE TABLE $reservations (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_item_id mediumint(9) NOT NULL,
        guesty_id varchar(255) DEFAULT '' NOT NULL,
        created timestamp NOT NULL default CURRENT_TIMESTAMP,
        updated timestamp NOT NULL default CURRENT_TIMESTAMP,
        UNIQUE KEY id (id)
      ) $charset_collate;
      
      CREATE TABLE $calendars (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        booking_id mediumint(9) NOT NULL,
        guesty_id varchar(255) DEFAULT '' NOT NULL,
        created timestamp NOT NULL default CURRENT_TIMESTAMP,
        updated timestamp NOT NULL default CURRENT_TIMESTAMP,
        UNIQUE KEY id (id)
      ) $charset_collate;";

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);
}

include(plugin_dir_path(__FILE__) . 'scripts/sync_reservations.php');
include(plugin_dir_path(__FILE__) . 'scripts/accommodation_custom_field_guesty_id.php');
include(plugin_dir_path(__FILE__) . 'scripts/sync_calendar.php');
include(plugin_dir_path(__FILE__) . 'scripts/scripts_hotelchamp.php');
include(plugin_dir_path(__FILE__) . 'scripts/scripts_hotelbooking.php');
include(plugin_dir_path(__FILE__) . 'scripts/config_page.php');
include(plugin_dir_path(__FILE__) . 'scripts/delete_old_blocked_deactivate.php');

add_action('admin_enqueue_scripts', "register_css_and_js");
function register_css_and_js()
{
  wp_enqueue_style('thepenthouse', plugins_url('/assets/css/styles.css', __FILE__));
  wp_enqueue_style('thepenthouse', "https://cdn.datatables.net/1.10.23/css/jquery.dataTables.min.css");

  wp_enqueue_script('thepenthouse', "https://cdn.datatables.net/1.10.23/js/jquery.dataTables.min.js");
  // wp_enqueue_script('thepenthouse', plugins_url('/thepenthouse/assets/js/scripts.js', __FILE__, false, true));
}

add_filter('wp_mail_content_type', 'set_email_format');
function set_email_format()
{
  return "text/html";
}

// Remove duplicate option for booking list
add_filter('post_row_actions', 'remove_row_actions_post', 15, 2);
function remove_row_actions_post($actions, $post)
{
  if ($post->post_type === 'mphb_booking') {
    unset($actions['duplicate']);
  }
  return $actions;
}

//By default only show confirmed bookings
add_filter('parse_query', 'mphb_booking_table_filter');
function mphb_booking_table_filter($query)
{
  if (!empty($query->query['post_type']) && $query->query['post_type'] == 'mphb_booking') {
    $qv = &$query->query_vars;

    if (empty($_GET['post_status']) && (!isset($_GET['page']) || $_GET['page'] != 'mphb_calendar')) {
      $qv['post_status'] = "confirmed";
    }
  } 
}

//Delete option view all reservations
add_filter("views_edit-mphb_booking", 'mphb_booking_filter_text');
function mphb_booking_filter_text($views)
{
  unset($views['all']);
  return $views;
}


