<?php

add_action('rest_api_init', function () {
    register_rest_route('tphapi', '/listing/', ['methods' => 'POST', 'callback' => 'sync_calendar', 'permission_callback' => '__return_true']);
    register_rest_route('tphapi', '/calendar/', ['methods' => 'POST', 'callback' => 'sync_calendar_object', 'permission_callback' => '__return_true']);
    register_rest_route('tphapi', '/prices/', ['methods' => 'POST', 'callback' => 'sync_prices', 'permission_callback' => '__return_true']);
    register_rest_route('tphapi', '/webhook/', ['methods' => 'POST', 'callback' => 'create_webhook', 'permission_callback' => '__return_true']);
});

function sync_calendar($request)
{
    try {
        $reservation = json_decode($request->get_body(), true);
        if (isset($reservation['reservation'])) {
            $calendar = new Calendar();
            $calendar->syncCalendar($reservation['reservation']);
            $response = new WP_REST_Response(["message" => "Reservation updated"]);
            $response->set_status(200);
        } else {
            $response = new WP_REST_Response(["message" => "Request with wrong format"]);
            $response->set_status(201);
        }
    } catch (Exception $e) {
        $response = new WP_REST_Response(["message" => $e->getMessage()]);
        $response->set_status(500);
    }

    return $response;
}

function create_webhook($datahook)
{
    try {
        if (!empty($datahook->get_json_params())) {
            $guesty = new Guesty();
            var_dump($guesty->createWebhook($datahook->get_json_params()));
        }
    } catch (Exception $e) {
        throw $e;
    }
}

function sync_calendar_object($request)
{
    try {
        $listingCalendar = json_decode($request->get_body(), true);
        if (isset($listingCalendar['calendar']) && count($listingCalendar['calendar']) > 0) {
            $calendar = new Calendar();           
            $calendar->update($listingCalendar['calendar']);            
            $response = new WP_REST_Response(["message" => "Calendar updated"]);
            $response->set_status(200);
        } else {
            $response = new WP_REST_Response(["message" => "Wrong format"]);
            $response->set_status(200);
        }
       
    } catch (Exception $e) {
        $response = new WP_REST_Response(["message" => "Request with wrong format"]);
        $response->set_status(201);
    }

    return $response;
}

function sync_prices($request)
{
    try {
        $listingCalendar = json_decode($request->get_body(), true);
        if (isset($listingCalendar['calendar']) && count($listingCalendar['calendar']) > 0) {
            $seasons = new SeasonsRates();           
            $seasons->updatePrice($listingCalendar['calendar']);            
            $response = new WP_REST_Response(["message" => "Season updated"]);
            $response->set_status(200);
        } else {
            $response = new WP_REST_Response(["message" => "Wrong format"]);
            $response->set_status(200);
        }
       
    } catch (Exception $e) {
        $response = new WP_REST_Response(["message" => "Request with wrong format"]);
        $response->set_status(201);
    }

    return $response;
}
