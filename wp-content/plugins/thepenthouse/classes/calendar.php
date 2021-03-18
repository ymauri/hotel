<?php

class Calendar
{
    public function __construct()
    {
    }

    public function syncCalendar(array $reservation) //, bool $isFromGuesty = true, bool $isFromPackage = false, int $roomId = null, int $parentBooking = 0, bool $syncOtherRooms = true)
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

        if (!empty($bookingId)) {
            wp_update_post([
                'ID' => $bookingId,
                "post_status" => $status
            ]);
        } else if (!empty($roomId)) {
            $bookingId = $this->createBookingPost($reservation);

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

            $roomId = $bookingId;
        }
        $this->log($bookingId, $reservation["_id"]);
        $checkout =  date("Y-m-d", strtotime($reservation['checkoutDateLocalized'] . " -1 day"));
        $reservation['checkoutDateLocalized'] = $checkout;

        if ($status == 'cancelled') {
            $this->deleteBlockedRoom($reservation['listingId'], $reservation['checkInDateLocalized'], $checkout);
        } else {
            $this->syncOtherRooms($reservation['listingId'], $roomId, $reservation);
        }

        return $bookingId;
    }

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
        add_post_meta($bookingId, 'mphb_note', $reservation['details']);
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

    public function log($bookingId, $reservationId)
    {
        global $wpdb;
        $row = [
            'booking_id' => $bookingId,
            'guesty_id' => $reservationId
        ];
        $wpdb->insert($wpdb->prefix . 'calendars', $row, ['%d', '%s']);
    }

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

    public function getBlockedRooms()
    {
        return  get_option('mphb_booking_rules_custom', array());
    }

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
        if ($listingCalendar[$first]['status'] != 'available') {
            $this->syncOtherRooms($listingCalendar[$first]['listingId'], '', $data);
        } else {
            $this->deleteBlockedRoom($listingCalendar[$first]['listingId'], $listingCalendar[$first]['date'], $listingCalendar[$last]['date']);
        }
    }

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
