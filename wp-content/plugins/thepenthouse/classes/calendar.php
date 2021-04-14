<?php

class Calendar
{
    public function __construct()
    {
    }

    /**
     * Sync calendar with reservation object
     * https://docs.guesty.com/#reservations
     * 
     * @param array $reservation
     * @param array $oldReservation 
     * 
     * @return int $bookingId
     */
    public function syncCalendar(array $reservation, array $oldReservation = []) //, bool $isFromGuesty = true, bool $isFromPackage = false, int $roomId = null, int $parentBooking = 0, bool $syncOtherRooms = true)
    {
        global $wpdb;
        $bookingId = $roomId = "";
        $status = $this->parseStatus($reservation['status']);

        $room = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}postmeta where meta_key like 'guesty_id' and meta_value like '{$reservation['listingId']}' order by post_id asc");
        if (!empty($room)) {
            $roomId = $room->post_id;
        }

        $booking = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}postmeta where meta_key like 'mphb_reservation_id' and meta_value like '{$reservation['_id']}' order by post_id desc");
        if (!empty($booking)) {
            $bookingStatus = get_post_status($booking->post_id);
            $bookingId = !in_array($bookingStatus, ['cancelled', 'canceled']) ? $booking->post_id : "";
        }

        if (!empty($roomId)) {
            if (!empty($bookingId)) {
                // Update reservation metadata
                $this->updateBookingMetdata($reservation, $bookingId, $status, $roomId, $oldReservation);
            } else {
                // Create new booking 
                $bookingId = $this->createBookingPost($reservation);
                // Create new reserved room
                $this->createReservedRoomPost($bookingId, $roomId, $reservation);
                $this->populateChanges($reservation, $roomId, $status);
            }
        }

        //When we want to update a reservation and the final listing_id is not in BD 
        //we must delete the booking
        else {
            if (!empty($bookingId)) {
                wp_delete_post($bookingId, true);
            }
            if (
                isset($oldReservation['listingId']) &&
                isset($oldReservation['checkInDateLocalized']) &&
                isset($oldReservation['checkOutDateLocalized'])
            ) {
                $oldCheckout =  date("Y-m-d", strtotime($oldReservation['checkOutDateLocalized'] . " -1 day"));
                $this->deleteBlockedRoom($oldReservation['listingId'], $oldReservation['checkInDateLocalized'], $oldCheckout);
            }
        }

        $this->log($bookingId, $reservation["_id"]);

        return $bookingId;
    }

    /**
     * Populate changes after create reservation
     * 
     * @param array $reservation
     * @param int $roomId
     * @param string $reservation
     * 
     * @return void
     */
    private function populateChanges(array $reservation, int $roomId, string $status)
    {
        $checkout =  date("Y-m-d", strtotime($reservation['checkOutDateLocalized'] . " -1 day"));

        if ($status == 'cancelled') {
            $this->deleteBlockedRoom($reservation['listingId'], $reservation['checkInDateLocalized'], $checkout);
        } else {
            $this->syncOtherRooms($reservation['listingId'], $roomId, $reservation);
        }
    }

    /**
     * Create reserved room post
     * 
     * @param int $bookingId
     * @param int $roomId
     * @param array $reservation
     * 
     * @return void
     */
    private function createReservedRoomPost(int $bookingId, int $roomId, array $reservation)
    {
        $bookedRoomId = wp_insert_post([
            "post_type" => "mphb_reserved_room",
            "post_status" => "publish",
            "post_parent" => $bookingId,
            "comment_status" => "closed",
            "ping_status" => "closed",
            "post_name" => 'Room'
        ], false, false);

        wp_update_post(["ID" => $bookedRoomId, "post_name" => $bookedRoomId]);

        add_post_meta($bookedRoomId, '_mphb_room_id', $roomId);
        add_post_meta($bookedRoomId, '_mphb_adults', $reservation["guestsCount"]);
        add_post_meta($bookedRoomId, '_mphb_children', 0);
        add_post_meta($bookedRoomId, '_mphb_guest_name', $reservation['guest']["firstName"]);
        add_post_meta($bookedRoomId, '_mphb_uid', $reservation['guestId'] ?? "");
    }

    /**
     * update booking metadata if it exists
     * 
     * @param array $reservation
     * @param int $bookingId
     * @param string $status
     * @param int $roomId
     * @param array $oldReservation 
     * 
     * @return void
     */
    private function updateBookingMetdata(array $reservation, int $bookingId, string $status, int $roomId, array $oldReservation)
    {
        wp_update_post([
            'ID' => $bookingId,
            "post_status" => $status
        ]);
        update_post_meta($bookingId, 'mphb_check_in_date', $reservation['checkInDateLocalized']);
        update_post_meta($bookingId, 'mphb_check_out_date', $reservation['checkOutDateLocalized']);

        // Get rooms asociated to the reservation for update them by listingID
        $reservedRooms = get_posts([
            "post_type" => "mphb_reserved_room",
            "post_parent" => $bookingId,
            'posts_per_page' => -1
        ]);
        foreach ($reservedRooms as $reservedRoom) {
            update_post_meta($reservedRoom->ID, '_mphb_room_id', $roomId);
        }

        //Delete old blocked rooms if there is any change on dates or listing
        if (
            count($oldReservation) > 0 &&
            isset($oldReservation['listingId']) &&
            isset($oldReservation['checkInDateLocalized']) &&
            isset($oldReservation['checkOutDateLocalized'])
        ) {
            $oldCheckout =  date("Y-m-d", strtotime($oldReservation['checkOutDateLocalized'] . " -1 day"));
            $this->deleteBlockedRoom($oldReservation['listingId'], $oldReservation['checkInDateLocalized'], $oldCheckout);
        }

        //Delete any blocked room associated to the current reservation
        //This is because Guesty generates many bloks before change on reservation from a listing to another.
        $checkout = $reservation['checkOutDateLocalized'];
        if (strtotime($reservation['checkOutDateLocalized']) > strtotime($reservation['checkInDateLocalized'])) {
            $checkout =  date("Y-m-d", strtotime($reservation['checkOutDateLocalized'] . " -1 day"));
        }
        $currentBlockedKey = $this->isBlocked($roomId, $reservation['checkInDateLocalized'], $checkout);
        if ($currentBlockedKey !== -1) {
            $blockedRooms = $this->getBlockedRooms();
            unset($blockedRooms[$currentBlockedKey]);
            update_option('mphb_booking_rules_custom', $blockedRooms);
        }
    }

    /**
     * create booking and save its 
     * 
     * @param array $reservation
     * 
     * @return int
     */
    private function createBookingPost(array $reservation)
    {
        $bookingId = wp_insert_post([
            "post_type" => "mphb_booking",
            "post_status" =>  $this->parseStatus($reservation['status']),
            "comment_status" => "closed",
            "ping_status" => "closed",
            "post_name" => 'Booking'
        ], false, false);
        wp_update_post(["ID" => $bookingId, "post_name" => $bookingId]);

        add_post_meta($bookingId, 'mphb_key', "booking_{$bookingId}_{$reservation["_id"]}");
        add_post_meta($bookingId, 'mphb_check_in_date', $reservation['checkInDateLocalized']);
        add_post_meta($bookingId, 'mphb_check_out_date', $reservation['checkOutDateLocalized']);
        add_post_meta($bookingId, 'mphb_note', $reservation['details'] ?? "");
        add_post_meta($bookingId, 'mphb_email', $reservation['guest']['email'] ?? 'without_email@thph.nl');
        add_post_meta($bookingId, 'mphb_first_name', $reservation['guest']["firstName"]);
        add_post_meta($bookingId, 'mphb_last_name', $reservation['guest']["lastName"]);
        add_post_meta($bookingId, 'mphb_phone', $reservation['guest']["phone"] ?? '+31666666666');
        add_post_meta($bookingId, 'mphb_country', "NL");
        add_post_meta($bookingId, 'mphb_total_price', 0);
        add_post_meta($bookingId, 'mphb_language', "en");
        add_post_meta($bookingId, 'mphb_is_from_guesty', true);
        add_post_meta($bookingId, 'mphb_reservation_id', $reservation["_id"]);

        return $bookingId;
    }

    /**
     * Log reservation data
     * 
     * @param int $bookingId
     * @param int $reservationId
     * 
     * @return void
     */
    public function log($bookingId, $reservationId)
    {
        global $wpdb;
        $row = [
            'booking_id' => $bookingId,
            'guesty_id' => $reservationId
        ];
        $wpdb->insert($wpdb->prefix . 'calendars', $row, ['%d', '%s']);
    }

    /**
     * Sync other rooms with the same listing ID
     * 
     * @param string $listingId
     * @param int $reservedRoom
     * @param array $reservation
     * 
     * @return void
     */
    public function syncOtherRooms($listingId, $reservedRoom, $reservation)
    {
        $rooms = get_posts([
            'post_type'     => 'mphb_room',
            'meta_key'      => 'guesty_id',
            'posts_per_page' => -1,
            'meta_value'    => $listingId
        ]);

        foreach ($rooms as $room) {
            if (empty($reservedRoom) || $room->ID != $reservedRoom) {
                $this->addBlockedRoom($room->ID, $reservation['checkInDateLocalized'], $reservation['checkOutDateLocalized'], "");
            }
        }
    }

    /**
     * Get bloked rooms from configuration variable
     * 
     * @return array
     */
    public function getBlockedRooms()
    {
        return  get_option('mphb_booking_rules_custom', array());
    }

    /**
     * Get true or false if a room is blocked or not
     * 
     * @param int $roomId
     * @param string $checkin
     * @param string $checkout
     * @param string $comment
     * 
     * @return int
     */
    public function isBlocked(int $roomId, string $checkin, string $checkout, string $comment = null)
    {
        $blockedRooms = $this->getBlockedRooms();
        foreach ($blockedRooms as $key => $blokedRoom) {
            if (
                $blokedRoom['room_id'] == $roomId &&
                $blokedRoom['date_from'] == $checkin &&
                $blokedRoom['date_to'] == $checkout
            ) {
                return $key;
            }
        }
        return -1;
    }

    /**
     * create blocked room or update it if it exists
     * 
     * @param int $roomId
     * @param string $checkin
     * @param string $checkout
     * @param string $comment
     * @param int $key
     * 
     * @return int
     */
    public function addBlockedRoom(int $roomId, string $checkin, string $checkout, string $comment = "", $key = false)
    {
        if (strtotime($checkout) >= strtotime($checkin)) {
            // Do not add new blocked room if there is any reservation which start_date is on the range
            //Search booking by dates
            $bookings = get_posts([
                'post_type'  => 'mphb_booking',
                'posts_per_page' => -1,
                'post_status' => 'confirmed',
                'meta_query' => [
                    [
                        'key'   => 'mphb_check_in_date',
                        'value' => $checkin,
                        'compare'   => '>=',
                        'type'      => 'DATE',
                    ],
                    [
                        'key'   => 'mphb_check_in_date',
                        'value' => $checkout,
                        'compare'   => '<',
                        'type'      => 'DATE',
                    ]
                ]
            ]);

            //Search rooms include into this bookings
            foreach ($bookings as $booking) {
                $rooms = get_posts([
                    'post_type'  => 'mphb_reserved_room',
                    'posts_per_page' => -1,
                    'post_parent' => $booking->ID,
                    'meta_query' => [
                        [
                            'key'   => '_mphb_room_id',
                            'value' => $roomId
                        ]
                    ]
                ]);
                if (count($rooms)) {
                    //If i found any room i cant add the blocked room 
                    //There is at least one reservation starting on the range date you gave            
                    return false;
                }
            } 

            $blockedRooms = $this->getBlockedRooms();
            if (strtotime($checkout) > strtotime($checkin)) {
                $checkout = date("Y-m-d", strtotime($checkout . " -1 day"));
            }

            $roomTypeId = get_post_meta($roomId, 'mphb_room_type_id', true);
            if (!empty($roomTypeId)) {
                $dataToUpdate = [
                    'room_type_id' => $roomTypeId,
                    'room_id' => (string) $roomId,
                    'date_from' => $checkin,
                    'date_to' => $checkout,
                    'restrictions' => ['stay-in'],
                    'comment' => $comment
                ];
                if ($key === false) {
                    $key = $this->isBlocked($roomId, $checkin, $checkout);
                }
                if ($key < 0) {
                    $blockedRooms[] = $dataToUpdate;
                } else {
                    $blockedRooms[$key] = $dataToUpdate;
                }
                update_option('mphb_booking_rules_custom', $blockedRooms);
                return true;
            }
        }
        return false;
    }

    /**
     * Delete past blocked rooms
     * 
     * @return int
     */
    public function deletePast()
    {
        $blockedRooms = $this->getBlockedRooms();
        foreach ($blockedRooms as $key => $blockedRoom) {
            if (strtotime($blockedRoom['date_from']) < strtotime('-1 day') && strtotime($blockedRoom['date_to']) < strtotime('-1 day')) {
                unset($blockedRooms[$key]);
            }
        }

        update_option('mphb_booking_rules_custom', $blockedRooms);
    }

    /**
     * Delete blocked room by listingId, checkin and checkout dates
     * 
     * @param string $listingId
     * @param string $checkin
     * @param string $checkout
     * 
     * @return int
     */
    public function deleteBlockedRoom(string $listingId, string $checkin, string $checkout)
    {
        $rooms = get_posts([
            'post_type'     => 'mphb_room',
            'meta_key'      => 'guesty_id',
            'posts_per_page' => -1,
            'meta_value'    => $listingId
        ]);
        $blockedRooms = $this->getBlockedRooms();
        foreach ($rooms as $room) {
            $keyBlocked = $this->isBlocked($room->ID, $checkin, $checkout);
            if ($keyBlocked !== -1) {
                unset($blockedRooms[$keyBlocked]);
            }
        }
        update_option('mphb_booking_rules_custom', $blockedRooms);
    }


    /**
     * Update blocked rooms by status 
     * https://docs.guesty.com/#retrieve-multiple-calendars
     * 
     * @param string $listingId
     * @param string $checkin
     * @param string $checkout
     * 
     * @return int
     */
    public function update($listingCalendar)
    {
        $first = array_key_first($listingCalendar);
        $last = array_key_last($listingCalendar);
        //We must add a day to the last day of the calendar because it means one more night
        $checkout = date('Y-m-d', strtotime($listingCalendar[$last]['date'] . " +1 day"));
        $data = [
            "listingId" => $listingCalendar[$first]['listingId'],
            "checkInDateLocalized" =>  $listingCalendar[$first]['date'],
            "checkOutDateLocalized" => $checkout,
            "status" =>  'confirmed'
        ];
        $bookedRoomId = '';
        //Search bookedRoom before sync
        if (!empty($listingCalendar[$first]['reservationId'])) {
            $bookings = get_posts([
                'post_type'  => 'mphb_booking',
                'post_status' => 'confirmed',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key'   => 'mphb_reservation_id',
                        'value' => $listingCalendar[$first]['reservationId']
                    ]
                ]
            ]);
           
            foreach ($bookings as $booking) {
                $bookedRooms = get_posts([
                    'post_type'  => 'mphb_reserved_room',
                    'post_parent' => $booking->ID,
                    'posts_per_page' => -1
                ]);
                foreach ($bookedRooms as $bookedRoom) {
                    $bookedRoomId = $bookedRoom->ID;
                    break;
                }
                break;
            }
        }

        //El problema es que cuando hace la búsqueda de los bloqueos, cuenta los que están también por encima de la reserva
        //No puede pasar que yo tenga un bloqueo de depto reserva encima de la reserva 
        if ($listingCalendar[$first]['status'] != 'available') {
            $this->syncOtherRooms($listingCalendar[$first]['listingId'], $bookedRoomId, $data);
        } else {
            $this->deleteBlockedRoom($listingCalendar[$first]['listingId'], $listingCalendar[$first]['date'], $listingCalendar[$last]['date']);
        }
    }

    /**
     * Delete blocked room from datatable 
     * 
     * @param int $room_id
     * @param string $from
     * @param string $to
     * 
     */
    public function deleteBlockedRoomById($room_id, $from, $to)
    {
        $blockedRooms = $this->getBlockedRooms();

        $keyBlocked = $this->isBlocked($room_id, $from, $to);
        if ($keyBlocked !== -1) {
            unset($blockedRooms[$keyBlocked]);
            update_option('mphb_booking_rules_custom', $blockedRooms);
        }
    }

     /**
     * Get status well formated 
     * 
     * @param string $status
     * @return string 
     * 
     */
    public function parseStatus(string $status) : string {
        if (in_array($status, ['cancelled', 'canceled', 'checked_out', 'inquiry', 'closed', 'awaiting_payment'])) {
            return 'cancelled';
        } 

        if (in_array($status, ['reserved', 'confirmed', 'checked_in', 'booked'])) {
            return 'confirmed';
        }

        return $status;
    }

    /**
     * Fill calendar with guesty data
     * @return void
     */
    public function populateCalendar() {
        $guesty = new Guesty();
        $reservationsCounter = $reservationsAvailable = 0;
    
        while ($reservationsCounter == 0 || $reservationsCounter < $reservationsAvailable) {
            $response = $guesty->reservations([
                'fields' => 'listingId guestsCount checkInDateLocalized checkOutDateLocalized status guest.firstName guest.lastName guest.email guest.phone customFields',
                'filter' => '[{"field":"status", "operator":"$in", "value":["confirmed"]}',
                'limit' => 100,
                'skip' => $reservationsCounter
            ]);
            if ($response['status'] == 200 && count($response['result']['results']) > 0) {
                $reservations = $response['result']['results'];
                $reservationsAvailable = $response['result']['count'];
                $reservationsCounter +=  $response['result']['limit'];
    
                foreach ($reservations as $reservation) {
                    $this->syncCalendar($reservation);
                    echo $reservation['_id'] . " done </br>";
                }
            } else {
                break;
            }
        }
    
    }
}
