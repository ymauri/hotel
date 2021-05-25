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
include(plugin_dir_path(__FILE__) . 'classes/blocked-rooms.php');
include(plugin_dir_path(__FILE__) . 'classes/reservation-notifier.php');
include(plugin_dir_path(__FILE__) . 'classes/reservation.php');
include(plugin_dir_path(__FILE__) . 'classes/calendar.php');
include(plugin_dir_path(__FILE__) . 'classes/configs.php');
include(plugin_dir_path(__FILE__) . 'classes/listings.php');
include(plugin_dir_path(__FILE__) . 'classes/seasons-rates.php');

// Create database structure
register_activation_hook(__FILE__, 'datatbase_structure');

function datatbase_structure()
{
  global $wpdb;

  $reservations = $wpdb->prefix . "reservations";
  $calendars = $wpdb->prefix . "calendars";
  $listings = $wpdb->prefix . "listings";
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
      ) $charset_collate;

      CREATE TABLE $listings (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        number mediumint(9) NOT NULL,
        guesty_id varchar(255) DEFAULT '' NOT NULL,
        UNIQUE KEY id (id)
      ) $charset_collate;";

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);
}

include(plugin_dir_path(__FILE__) . 'scripts/sync_reservations.php');
include(plugin_dir_path(__FILE__) . 'scripts/custom_fields.php');
include(plugin_dir_path(__FILE__) . 'scripts/sync_calendar.php');
include(plugin_dir_path(__FILE__) . 'scripts/scripts_hotelchamp.php');
include(plugin_dir_path(__FILE__) . 'scripts/scripts_hotelbooking.php');
include(plugin_dir_path(__FILE__) . 'scripts/config_page.php');
include(plugin_dir_path(__FILE__) . 'scripts/jobs.php');

add_action('admin_enqueue_scripts', "register_css_and_js");
function register_css_and_js()
{
  wp_enqueue_style('thepenthouse', plugins_url('/assets/css/styles.css', __FILE__));
  wp_enqueue_script('thepenthouse', plugins_url('/assets/js/scripts.js', __FILE__, false, true));
  wp_enqueue_style('thepenthouse-datatable', "//cdn.datatables.net/1.10.23/css/jquery.dataTables.min.css");
  wp_enqueue_script('thepenthouse-datatable', "//cdn.datatables.net/1.10.23/js/jquery.dataTables.min.js");
  wp_enqueue_style('thepenthouse-datepicker', "//cdnjs.cloudflare.com/ajax/libs/datepicker/1.0.10/datepicker.min.css");
  wp_enqueue_script('thepenthouse-datepicker', "//cdnjs.cloudflare.com/ajax/libs/datepicker/1.0.10/datepicker.min.js");

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

//Add metatag description
function thph_meta_description() {
  global $post;
  if ( is_singular() ) {
      $des_post = strip_tags( $post->post_content );
      $des_post = strip_shortcodes( $des_post );
      $des_post = str_replace( array("\n", "\r", "\t"), ' ', $des_post );
      $des_post = mb_substr( $des_post, 0, 300, 'utf8' );
      echo '<meta name="description" content="' . $des_post . '" />' . "\n";
  }
  if ( is_home() ) {
      echo '<meta name="description" content="' . get_bloginfo( "description" ) . '" />' . "\n";
  }
  if ( is_category() ) {
      $des_cat = strip_tags(category_description());
      echo '<meta name="description" content="' . $des_cat . '" />' . "\n";
  }
}
add_action( 'wp_head', 'thph_meta_description');

//Delete option view all reservations
// add_filter("views_edit-mphb_booking", 'mphb_booking_filter_text');
// function mphb_booking_filter_text($views)
// {
//   unset($views['all']);
//   return $views;
// }

add_filter( 'allowed_http_origins', 'add_allowed_origins' );
function add_allowed_origins( $origins ) {
    $origins[] = 'https://thepenthouse-apartments.nl';
    return $origins;
}
