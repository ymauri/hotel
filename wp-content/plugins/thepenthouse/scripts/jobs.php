<?php
add_action('rest_api_init', function () {
    register_rest_route('tphapi', '/job/deleteBlocked/', ['methods' => 'GET', 'callback' => 'delete_old_blocked_run', 'permission_callback' => '__return_true']);
    register_rest_route('tphapi', '/job/syncReservations/', ['methods' => 'GET', 'callback' => 'sync_calendar_auto', 'permission_callback' => '__return_true']);

});
 
function delete_old_blocked_run() {
    $calendar  = new Calendar();
    $calendar->deletePast();
    $seasons = new SeasonsRates();
    $seasons->deleteOldSeasons();
}

function sync_calendar_auto (){
    $calendar = new Calendar();
    $calendar->populateCalendar();
    exit();
}