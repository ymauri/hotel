<?php

add_action('rest_api_init', function () {
    register_rest_route('tphapi', '/restaurant/', ['methods' => 'GET', 'callback' => 'sync_restaurant', 'permission_callback' => '__return_true']);
});

function sync_restaurant($request)
{
    try {
        $restaurant = new Restaurant();
        header("Content-type: text/xml");
        echo $restaurant->getNext();
        
    } catch (Exception $e) {
        $response = new WP_REST_Response(["message" => $e->getMessage()]);
        $response->set_status(500);
    }
    die;
}
