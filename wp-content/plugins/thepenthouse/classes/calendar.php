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
        $bookingId = "";
        $status = ($reservation['status'] == 'canceled' ? 'cancelled' : $reservation['status']);

        if (empty($roomId)) {
            $room = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}postmeta where meta_key like 'guesty_id' and meta_value like '{$reservation['listingId']}'");
            $roomId = $room->post_id;
        }

        $bookingObj = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}postmeta where meta_key like 'mphb_reservation_id' and meta_value like '{$reservation['_id']}' order by post_id desc");
        if (!empty($bookingObj)) {
            $bookingId = $bookingObj->post_id;
        }

        if (!empty($bookingId) && !empty($roomId)) {
            // Update reservation metadata
            $this->updateBookingMetdata($reservation, $bookingId, $status, $roomId, $oldReservation);
        } else if (!empty($roomId)) {
            // Create new booking 
            $bookingId = $this->createBookingPost($reservation);
            // Create new reserved room
            $this->createReservedRoomPost($bookingId, $roomId, $reservation);
        }

        $this->log($bookingId, $reservation["_id"]);
        $checkout =  date("Y-m-d", strtotime($reservation['checkOutDateLocalized'] . " -1 day"));

        if ($status == 'cancelled') {
            $this->deleteBlockedRoom($reservation['listingId'], $reservation['checkInDateLocalized'], $checkout);
        } else {
            $this->syncOtherRooms($reservation['listingId'], $roomId, $reservation);
        }

        return $bookingId;
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
        add_post_meta($bookedRoomId, '_mphb_uid', $reservation['guest']["_id"]);
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
            "post_status" => $reservation['status'] == 'canceled' ? 'cancelled' : $reservation['status'],
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
        }
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
            if (strtotime($blockedRoom['date_from']) < strtotime('-1 day') && strtotime($blockedRoom['date_to']) < strtotime('-1 day') && empty($blockedRoom['comment'])) {
                unset($blokedRoom[$key]);
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
        $data = [
            "listingId" => $listingCalendar[$first]['listingId'],
            "checkInDateLocalized" =>  $listingCalendar[$first]['date'],
            "checkOutDateLocalized" => $listingCalendar[$last]['date'],
            "status" =>  'confirmed'
        ];
        //Search bookedRoom before sync
        if (!empty($listingCalendar[$first]['reservationId'])) {
            $bookings = get_posts([
                'post_type'  => 'mphb_booking',
                'post_status' => 'confirmed',
                'meta_query' => [
                    [
                        'key'   => 'mphb_reservation_id',
                        'value' => $listingCalendar[$first]['reservationId']
                    ]
                ]
            ]);
            $bookedRoomId = '';
            foreach ($bookings as $booking) {
                $bookedRooms = get_post([
                    'post_type'  => 'mphb_reserved_room',
                    'post_parent' => $booking->ID,
                ]);
                foreach ($bookedRooms as $bookedRoom) {
                    $bookedRoomId = $bookedRoom->ID;
                    break;
                }
                break;
            }
        }

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
}
