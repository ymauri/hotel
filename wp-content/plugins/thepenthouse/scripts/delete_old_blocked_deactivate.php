<?php
function delete_old_blocked_deactivate() {
    wp_clear_scheduled_hook( 'delete_old_blocked_cron' );
}
 
add_action('init', function() {
    add_action( 'delete_old_blocked_cron', 'delete_old_blocked_run_cron' );
    register_deactivation_hook( __FILE__, 'delete_old_blocked_deactivate' );
 
    if (! wp_next_scheduled ( 'delete_old_blocked_cron' )) {
        wp_schedule_event( time(), 'weekly', 'delete_old_blocked_cron' );
    }
});
 
function delete_old_blocked_run_cron() {
    $calendar  = new Calendar();
    $calendar->deletePast();
}