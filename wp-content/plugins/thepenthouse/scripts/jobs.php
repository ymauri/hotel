<?php
add_action('rest_api_init', function () {
    register_rest_route('tphapi', '/job/deleteBlocked/', ['methods' => 'GET', 'callback' => 'delete_old_blocked_run', 'permission_callback' => '__return_true']);
    register_rest_route('tphapi', '/job/syncReservations/', ['methods' => 'GET', 'callback' => 'sync_calendar_auto', 'permission_callback' => '__return_true']);

});
 
function delete_old_blocked_run() {
    $blockedRooms  = new BlockedRoom();
    $blockedRooms->deletePast();
    $seasons = new SeasonsRates();
    $seasons->deleteOldSeasons();
}

function sync_calendar_auto (){
    $calendar = new Calendar();
    global $wpdb;
    $listingIdParam = $_GET['listingId'] ?? null;

    if (empty($listingIdParam)) {
        $listings = $wpdb->get_results("SELECT guesty_id FROM {$wpdb->prefix}listings;", ARRAY_A);
    } else {
        $listings = [['guesty_id' => $listingIdParam]];
    }
    
    $from = date('Y-m-d');
    $to = date('Y-m-d', strtotime(date('Y-m-d') . " +1 year"));

    foreach ($listings as $listing) {
        $calendar->retrieveCalendarDays($listing['guesty_id'], $from, $to);
        sleep(30);
    }    
    exit();
}