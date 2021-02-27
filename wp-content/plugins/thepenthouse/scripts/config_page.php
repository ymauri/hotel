<?php
add_action( 'admin_menu', 'thp_settings' );


function thp_settings()
{
    add_menu_page( 'The Penthouse Settings', 
    "TPH Settings", "manage_options", 
    sanitize_key('The Penthouse Settings'), 'thp_settings_content', 
    'dashicons-admin-multisite', 80 );
    
}


function thp_settings_content()
{
   ?>
    <h2> Welcome to The Penthouse admin page</h2>
    <p> Use this action for synchronize reservations with Guesty platform</p>
    <form method="POST" action="<?php echo admin_url( 'admin.php' ); ?>">
    <input type="hidden" name="action" value="thphsynccalendar" />
    <input type="submit" value="Sync now">
    </form>
    <br>
    <hr>
   <?php
    
    $configs = new Configs();
    $configs->datatable_content();
}

add_action( 'admin_action_thphsynccalendar', 'thphsynccalendar' );
function thphsynccalendar()
{
    $guesty = new Guesty();
    $reservationsCounter = $reservationsAvailable = 0;

    while ($reservationsCounter == 0 || $reservationsCounter < $reservationsAvailable) {
        $response = $guesty->reservations([
        'fields' => 'listingId guestsCount checkInDateLocalized checkOutDateLocalized status guest.firstName guest.lastName guest.email guest.phone customFields', 
        'filter' => '[{"field":"status", "operator":"$in", "value":["confirmed"]}', 
        'limit' => 50,
        'skip' => $reservationsCounter
        ]);
        if ($response['status'] == 200 && count($response['result']['results']) > 0) {
            $reservations = $response['result']['results'];
            $reservationsAvailable = $response['result']['count'];
            $reservationsCounter +=  $response['result']['limit'];
            
            foreach ($reservations as $reservation) {
                $calendar = new Calendar();
                $calendar->syncCalendar($reservation);
                echo $reservation['_id']." done </br>";
            }
        } else {
            break;
        }     
    }

    exit();
}

add_action( 'wp_ajax_delete_calendar', 'delete_calendar' );

function delete_calendar() {

	$room_id = $_POST['room_id'];
	$date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    
    $calendar = new Calendar();
    $calendar->deleteBlockedRoomById($room_id, $date_from, $date_to);

	wp_die(); // this is required to terminate immediately and return a proper response
}


add_action( 'wp_ajax_fill_datatable', 'fill_datatable' );

function fill_datatable() {
    $config = new Configs();
    $config->fill_datatable();
}
    

add_action( 'wp_ajax_fill_rooms_select', 'fill_rooms_select' );

function fill_rooms_select() {
    $config = new Configs();
    $config->fill_rooms_select();
}


add_action( 'wp_ajax_register_blocked_room', 'register_blocked_room' );

function register_blocked_room() {
    try {
        $calendar = new Calendar();
        if (!empty($_POST['room_id']) && !empty($_POST['date_from']) && !empty($_POST['date_to'])) {
            $keyBlocked = $calendar->isBlocked($_POST['room_id'], $_POST['date_from'], $_POST['date_to']);
            $calendar->addBlockedRoom($_POST['room_id'], $_POST['date_from'], $_POST['date_to'], "", $keyBlocked);
        }
        echo 'ok';
    } catch (Exception $e) {
        echo $e->getMessage();
    }
    wp_die();
}