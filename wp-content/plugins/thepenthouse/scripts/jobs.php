<?php
function delete_old_blocked_deactivate() {
    wp_clear_scheduled_hook( 'delete_old_blocked' );
}
 
add_action('init', function() {
    add_action( 'delete_old_blocked', 'delete_old_blocked_run' );
    register_deactivation_hook( __FILE__, 'delete_old_blocked_deactivate' );
 
    if (! wp_next_scheduled ( 'delete_old_blocked' )) {
        wp_schedule_event( strtotime('11:02:00'), 'daily', 'delete_old_blocked' );
    }
});
 
function delete_old_blocked_run() {
    $calendar  = new Calendar();
    $calendar->deletePast();
    $seasons = new SeasonsRates();
    $seasons->deleteOldSeasons();
}