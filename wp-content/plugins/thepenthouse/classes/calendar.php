<?php

class Calendar
{
    private $blockedRooms;

    public function __construct()
    {
        $this->blockedRooms = new BlockedRoom();
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
    public function sync(array $reservation, array $oldReservation = []) //, bool $isFromGuesty = true, bool $isFromPackage = false, int $roomId = null, int $parentBooking = 0, bool $syncOtherRooms = true)
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
            // $bookingId = $booking->post_id;
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
            }

            $this->populateChanges($reservation, $roomId, $status);

            if ($status == 'confirmed') {
                //Delete blocked room if the reservation is overlaped with a booking rule
                $checkin = $reservation['checkInDateLocalized'];
                $checkout = $reservation['checkOutDateLocalized'];
                if (strtotime($checkout) > strtotime($checkin)) {
                    $checkout = date("Y-m-d", strtotime($checkout . " -1 day"));
                }
                $this->blockedRooms->deleteByRoomId($roomId, $checkin, $checkout);
            }
        }

        //When we want to update a reservation and the final listing_id is not in BD
        //we must delete the booking
        else {
            if (!empty($bookingId)) {
                wp_delete_post($bookingId, true);
            }
        }

        $this->resolveConflicts($oldReservation);

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
            $this->blockedRooms->delete($reservation['listingId'], $reservation['checkInDateLocalized'], $checkout);
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
        if (count($oldReservation)) {
            $this->resolveConflicts($oldReservation);
        }

        //Delete any blocked room associated to the current reservation
        //This is because Guesty generates many bloks before change the reservation from one listing to another.
        $checkout = $reservation['checkOutDateLocalized'];
        if (strtotime($reservation['checkOutDateLocalized']) > strtotime($reservation['checkInDateLocalized'])) {
            $checkout = date("Y-m-d", strtotime($reservation['checkOutDateLocalized'] . " -1 day"));
        }
        $this->blockedRooms->deleteByRoomId($roomId, $reservation['checkInDateLocalized'], $checkout);
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
                $this->blockedRooms->add($room->ID, $reservation['checkInDateLocalized'], $reservation['checkOutDateLocalized'], "");
            }
        }
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
            $this->blockedRooms->delete($listingCalendar[$first]['listingId'], $listingCalendar[$first]['date'], $listingCalendar[$last]['date']);
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
                    $this->sync($reservation);
                    echo $reservation['_id'] . " done </br>";
                }
            } else {
                break;
            }
        }
    }


    /**
     * Delete blocked rule if we have a oldReservation
     * @param array $reservation
     *
     * @return void
     */
    private function resolveConflicts(array $reservation) : void
    {
        //Verificar si hay conflictos con el cambio de reserva de un día a otra o de un depto a otro
        if (
            isset($reservation['listingId']) &&
            isset($reservation['checkInDateLocalized']) &&
            isset($reservation['checkOutDateLocalized'])
        ) {
            $checkin = $reservation['checkInDateLocalized'];
            $checkout = $reservation['checkOutDateLocalized'];
            if (strtotime($checkout) > strtotime($checkin)) {
                $checkout =  date("Y-m-d", strtotime($checkout . " -1 day"));
            }
            $this->blockedRooms->delete($reservation['listingId'], $checkin, $checkout);
        }
    }

    public function deleteBookings(array $roomIds, string $checkin, string $checkout) {
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
                    'compare'   => '<=',
                    'type'      => 'DATE',
                ]
            ]
        ]);

        //Search rooms included into this bookings
        foreach ($bookings as $booking) {
            $canDeleteThisBooking = false;
            $rooms = get_posts([
                'post_type'  => 'mphb_reserved_room',
                'posts_per_page' => -1,
                'post_parent' => $booking->ID
            ]);
            foreach ($rooms as $room) {
                if (in_array($room->ID, $roomIds)){
                    $canDeleteThisBooking = true;
                }
            }
            if ($canDeleteThisBooking) {
                wp_delete_post( $booking->ID, true);
            }
        }
    }

    /**
     * Retrieve calendar from guesty
     * @param string $listingId
     * @param string $from
     * @param string $to
     *
     * @return void
     */
    public function retrieveCalendarDays(string $listingId, string $from, string $to)
    {
        $guesty = new Guesty();
        $response = $guesty->getListingCalendar($listingId, $from, $to);
        $calendars = $response['result']['data']['days'] ?? [];
        $blockRefId = $reservationId = $initDate = "";
        global $wpdb;
        foreach ($calendars as $key => $day) {
            $details = $day['blockRefs'][0] ?? [];
            if (in_array($day['status'], ['unavailable', 'reserved'])
                && isset($details['_id'])
                && $blockRefId != $details['_id']){
                //Crear bloqueo en calendario
                $startDate = explode("T",$details["startDate"]);
                $endDate = explode("T",$details["endDate"]);
                $endDate = date('Y-m-d', strtotime($endDate[0] . " +1 day"));
                $data = [
                    'checkInDateLocalized' => $startDate[0],
                    'checkOutDateLocalized' => $endDate,
                ];
                $this->syncOtherRooms($listingId, null, $data);
                $initDate = $endDate;
            } else if ($day['status'] == 'booked'
                        && isset($day['reservation'])
                        && $reservationId != $day['reservation']['_id']) {
                //Crear o sincronizar la reserva
                $this->sync($day['reservation']);
                $initDate = $day['reservation']['checkOutDateLocalized'];
            } else {
                //noche libre

                $nextCalendarDay = $calendars[$key+1] ?? $day;
                if (!empty($nextCalendarDay['status'])
                    && strtotime($nextCalendarDay['date']) > strtotime($initDate) // Proxima fecha mayor q el inicio
                    && (in_array($nextCalendarDay['status'], ['unavailable', 'reserved', 'booked']) || $day['date'] == $nextCalendarDay['date'])) { //Proxima fecha ocupada
                    //Eliminar los bloqueos en el rango de fechas
                    $this->blockedRooms->deleteByRange($listingId, $initDate, $day['date']);
                    //Eliminar las reservas en el rango de fechas
                    $roomsIds = $wpdb->get_results("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = 'guesty_id' AND meta_value = '$listingId';", ARRAY_A);
                    $roomsIds = array_values($roomsIds);
                    if (count($roomsIds)) {
                        $this->deleteBookings($roomsIds, $initDate, $day['date']);
                    }

                }
            }
            $blockRefId = $details['_id'] ?? "";
            $reservationId = $day['reservation']['_id'] ?? "";
        }
    }
}
