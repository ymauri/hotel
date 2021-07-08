<?php

class Restaurant
{
    public function __construct()
    {
    }

    /**
     * Returns the restaurant reservations in xml format
     *
     * @return array
     */
    public function getNext()
    {
        $bookings = get_posts([
            'post_type'  => 'mphb_booking',
                'posts_per_page' => -1,
                'post_status' => 'confirmed',
                'meta_query' => [
                    [
                        'key'   => 'mphb_check_in_date',
                        'value' => date("Y-m-d"),
                        'compare'   => '>=',
                        'type' => 'DATE'
                    ],
                ]
        ]);
        $arrayToXML = [];
        foreach ($bookings as $booking) {
            if (empty(get_post_meta( $booking->ID, 'mphb_is_from_guesty', true ))) {
                
                $reservedRooms = get_posts([
                    "post_type" => "mphb_reserved_room",
                    "post_parent" => $booking->ID,
                    'posts_per_page' => -1
                ]);
                $reservedRoomText = "";

                foreach ($reservedRooms as $reservedRoom) {
                    $roomId = get_post_meta($reservedRoom->ID, '_mphb_room_id', true);
                    $reservedRoomText = get_the_title($roomId);
                    $date = get_post_meta( $booking->ID, 'mphb_check_in_date', true )." 15:00:00";
                    $shortDate = date("(m-d)", strtotime($date));
                    $orderInfo = strtolower("order-".date("M-d-Y-hi-a", strtotime(get_the_date('Y-m-d H:i:s', $booking->ID))));
                    $roomTypeId = get_post_meta($roomId, 'mphb_room_type_id', true);
                    $isPackage = is_package($roomTypeId);
                   
                    $services = get_post_meta($reservedRoom->ID, '_mphb_services');

                    if (count($services)) {
                        foreach ( $services[0] as $service) {
                            if (!empty(get_post_meta($service['id'], "mphb_show_in_xml", true))) {
                                $serviceText = get_the_title($service['id']);
                                $arrayToXML[] =  [
                                    'ticketid' => "web_".$booking->ID,
                                    'ticketname' => "15:00 ".htmlspecialchars($serviceText). " in " .htmlspecialchars($reservedRoomText) . " ". $shortDate . " price per guest",
                                    'productid' => "web_" . $booking->ID,
                                    'startdate' => $date,
                                    'orderid' => "web_" . $booking->ID,
                                    'eventid' => "web_" . $booking->ID,
                                    'code' => "web_" . $booking->ID,
                                    'orderinfo' => $orderInfo,
                                    'remarks' => htmlspecialchars(get_post_meta( $booking->ID, 'mphb_note', true )),
                                    'name' => htmlspecialchars(get_post_meta( $booking->ID, 'mphb_first_name', true )  . " " . get_post_meta( $booking->ID, 'mphb_last_name', true )),
                                    'email' => htmlspecialchars(get_post_meta( $booking->ID, 'mphb_email', true )),
                                    'phone' => htmlspecialchars(get_post_meta( $booking->ID, 'mphb_phone', true )),
                                    'ordertotal' => htmlspecialchars(get_post_meta( $booking->ID, 'mphb_total_price', true )),
                                ];
                            }
                        }
                    } else if ($isPackage){
                        $arrayToXML[] =  [
                            'ticketid' => "web_".$booking->ID,
                            'ticketname' => "15:00 in " .htmlspecialchars($reservedRoomText) . " ". $shortDate . " price per guest",
                            'productid' => "web_" . $booking->ID,
                            'startdate' => $date,
                            'orderid' => "web_" . $booking->ID,
                            'eventid' => "web_" . $booking->ID,
                            'code' => "web_" . $booking->ID,
                            'orderinfo' => $orderInfo,
                            'remarks' => htmlspecialchars(get_post_meta( $booking->ID, 'mphb_note', true )),
                            'name' => htmlspecialchars(get_post_meta( $booking->ID, 'mphb_first_name', true )  . " " . get_post_meta( $booking->ID, 'mphb_last_name', true )),
                            'email' => htmlspecialchars(get_post_meta( $booking->ID, 'mphb_email', true )),
                            'phone' => htmlspecialchars(get_post_meta( $booking->ID, 'mphb_phone', true )),
                            'ordertotal' => htmlspecialchars(get_post_meta( $booking->ID, 'mphb_total_price', true )),
                        ];
                    }                    
                }
            }
        }

        $xmlConf = new SimpleXMLElement('<reservations></reservations>');
        $this->array_to_xml($arrayToXML,$xmlConf);
        return $xmlConf->asXML();
    }
    
    function array_to_xml( $data, &$xml_data ) {
        foreach( $data as $key => $value ) {
            if( is_array($value) ) {
                $key = 'reservation'; 
                $subnode = $xml_data->addChild($key);
                $this->array_to_xml($value, $subnode);
            } else {
                $xml_data->addChild("$key",htmlspecialchars("$value"));
            }
         }
    }
}
