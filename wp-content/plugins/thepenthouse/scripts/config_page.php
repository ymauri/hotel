<?php
add_action('admin_menu', 'thp_settings');

function thp_settings()
{
    $thePentHouse = sanitize_key('The Penthouse Settings');
    add_menu_page('The Penthouse Settings', "TPH Settings", "manage_options", $thePentHouse, 'thp_settings_content', 'dashicons-admin-multisite', 80);
    add_submenu_page($thePentHouse, 'Booking Rules', 'Booking Rules', 'manage_options', sanitize_key("Booking Rules"), 'booking_rules');

    add_submenu_page($thePentHouse, 'Guesty Listings', 'Guesty Listings', 'manage_options', 'tph_listings_list', 'guesty_listing');
    add_submenu_page(null, 'Add Listing', 'Add Listing', 'manage_options', 'tph_listings_create', 'add_guesty_listing');
    add_submenu_page(null, 'Update Listing', 'Update Listing', 'manage_options', 'tph_listings_update', 'update_guesty_listing');

    add_submenu_page($thePentHouse, 'Seasons & Rates', 'Seasons & Rates', 'manage_options', 'tph_seasons_rates', 'list_seasons_rates');
    add_submenu_page(null, 'Seasons & Rates', 'Seasons & Rates', 'manage_options', 'tph_seasons_rates', 'retrieve_guesty_calendar');
}

// Config page. Basic view
function thp_settings_content()
{
?>
    <h2> Welcome to The Penthouse admin page</h2>
    <p> Use this action for synchronize reservations with Guesty platform</p>
    <form method="POST" action="<?php echo admin_url('admin.php'); ?>">
        <input type="hidden" name="action" value="thphsynccalendar" />
        <input type="submit" value="Sync now">
    </form>
    <br>
    <hr>
<?php
}

// Default datatable content
function booking_rules()
{
    $configs = new Configs();
    $configs->datatable_content();
}

// Sync calendar. Get calendars from guesty and fill the local calendar
add_action('admin_action_thphsynccalendar', 'thphsynccalendar');
function thphsynccalendar()
{
    $calendar = new Calendar();
    $calendar->populateCalendar();
    exit();
}

// Delete blocked room
add_action('wp_ajax_delete_calendar', 'delete_calendar');
function delete_calendar()
{
    $room_id = $_POST['room_id'];
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];

    $blockedRoom = new BlockedRoom();
    $blockedRoom->deleteByRoomId($room_id, $date_from, $date_to);

    wp_die(); // this is required to terminate immediately and return a proper response
}

// Fill blocked rooms datatable
add_action('wp_ajax_fill_datatable', 'fill_datatable');
function fill_datatable()
{
    $config = new Configs();
    $config->fill_datatable();
}

// Fill room select by accommodations type
add_action('wp_ajax_fill_rooms_select', 'fill_rooms_select');
function fill_rooms_select()
{
    $config = new Configs();
    $config->fill_rooms_select();
}

// Add new blocked romm
add_action('wp_ajax_register_blocked_room', 'register_blocked_room');
function register_blocked_room()
{
    try {
        $blockedRoom = new BlockedRoom();
        $resutl = false;
        if (!empty($_POST['room_id']) && !empty($_POST['date_from']) && !empty($_POST['date_to'])) {
            $resutl = $blockedRoom->add($_POST['room_id'], $_POST['date_from'], $_POST['date_to'], "");
        }
        echo $resutl ? 'ok' : "ko";
    } catch (Exception $e) {
        echo $e->getMessage();
    }
    wp_die();
}

// List Guesty listings
function guesty_listing()
{
    $listings = new Listings();
    $listings->list();
}

// Add Guesty listing
function add_guesty_listing() {
    $listings = new Listings();
    $listings->create();
}

// Update guesty listing
function update_guesty_listing() {
    $listings = new Listings();
    $listings->update();
}

// Manage seasons and rates
function list_seasons_rates() {
    $seasonsRates = new SeasonsRates();
    $seasonsRates->syncSeasonsView();
    $roomTypes = $_POST['roomTypes'] ?? null;
    $startDate = $_POST['startDate'] ?? null;
    $endDate = $_POST['endDate'] ?? null;
    $price = $_POST['price'] ?? null;
    $days = $_POST['days'] ?? [];
    // $seasonsRates->updateRatesPostName();
    // die;
    if (!empty($roomTypes) && !empty($startDate) && !empty($endDate) && !empty($price)) {
        $seasonsRates->syncSeasons($roomTypes, $price, $startDate, $endDate, $days);
    }
}

function retrieve_guesty_calendar() {
    $year = $_POST['year'] ?? null;
    $listingsId = $_POST['listingsId'] ?? null;
    if (!empty($year) && !empty($listingsId)) {
        foreach ($listingsId as $listingId) {
            $seasonsRates = new SeasonsRates();
            $seasonsRates->retrievePrices($listingId, $year);
            sleep(30);
        }
    }
}
