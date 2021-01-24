<?php

class Calendar
{
    public function __construct()
    {
    }

    public function syncCalendar(array $reservation, bool $isFromGuesty = true, bool $isFromPackage = false, int $roomId = null, int $parentBooking = 0)
    {
        global $wpdb;
        $bookingId = "";
        $status = $reservation['status'] == 'canceled' ? 'cancelled' : $reservation['status'];
        if (!empty($reservation)) {

            if (empty($roomId)) {
                $room = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}postmeta where meta_key like 'guesty_id' and meta_value like '{$reservation['listingId']}'");
                $roomId = $room->post_id;
            }

            if (!empty($reservation['customFields']['bookingId'])) {
                $bookingId = (int) $reservation['customFields']['bookingId'];
            } else {
                $calendar = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}calendars where guesty_id like '{$reservation['_id']}' ORDER BY id DESC");
                if (!empty($calendar->id)) {
                    $bookingId = $calendar->booking_id;
                }
            }
            if (!empty($bookingId)) {
                wp_update_post([
                    'ID' => $bookingId,
                    "post_status" => $status
                ]);
            } else if (!empty($roomId)) {
                $bookingId = $this->createBookingPost($reservation, $isFromGuesty, $isFromPackage, $parentBooking);

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
                add_post_meta($bookedRoomId, '_mphb_guest_name', $isFromGuesty ? "Booked on Guesty" : $reservation["firstName"]);
                add_post_meta($bookedRoomId, '_mphb_uid', $reservation["_id"] . "@thepenthouse-apartments.nl");
            }
            mail('ymauri@outlook.com', 'Sync Calendar', "Checkin: {$reservation['checkInDateLocalized']} \n Checkout: {$reservation['checkOutDateLocalized']} \n Listing: {$reservation['listingId']}");
        }
        $this->log($bookingId, $reservation["_id"]);
        return $bookingId;
    }

    private function createBookingPost(array $reservation, bool $isFromGuesty = true, bool $isFromPackage = false, int $parentBooking = 0)
    {
        $bookingId = wp_insert_post([
            "post_type" => "mphb_booking",
            "post_status" => $reservation['status'] == 'canceled' ? 'cancelled' : $reservation['status'],
            "post_parent" => $parentBooking,
            "comment_status" => "closed",
            "ping_status" => "closed",
            "post_name" => 'Booking'
        ], false, false);
        wp_update_post(["ID" => $bookingId, "post_name" => $bookingId]);

        add_post_meta($bookingId, 'mphb_key', "booking_{$bookingId}_{$reservation["_id"]}");
        add_post_meta($bookingId, 'mphb_check_in_date', $reservation['checkInDateLocalized']);
        add_post_meta($bookingId, 'mphb_check_out_date', $reservation['checkOutDateLocalized']);
        add_post_meta($bookingId, 'mphb_note', $isFromGuesty ? "Booked on Guesty" : $reservation['details']);
        add_post_meta($bookingId, 'mphb_email', $isFromGuesty ? ($reservation["guestId"] . "@thepenthouse-apartments.nl") : $reservation['email']);
        add_post_meta($bookingId, 'mphb_first_name', $isFromGuesty ? "Booked on Guesty" : $reservation["firstName"]);
        add_post_meta($bookingId, 'mphb_last_name', $isFromGuesty ?  "Booked on Guesty" : $reservation["lastName"]);
        add_post_meta($bookingId, 'mphb_phone', $isFromGuesty ? "+31666666666" : $reservation["phone"]);
        add_post_meta($bookingId, 'mphb_country', "NL");
        add_post_meta($bookingId, 'mphb_total_price', 0);
        add_post_meta($bookingId, 'mphb_language', "en");
        add_post_meta($bookingId, 'mphb_is_from_guesty', $isFromGuesty);
        add_post_meta($bookingId, 'mphb_is_from_package', $isFromPackage);
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

    public function isRoomAvailable(array $guestyId, string $checkin, string $checkout)
    {
        global $wpdb;

        $checkoutDateTime = new DateTime($checkout);
        $checkoutDateTime->sub(new DateInterval('P1D'));
        $checkout = $checkoutDateTime->format('Y-m-d');
        $roomsIds = [];
        foreach ($guestyId as $item) {
            $room = $wpdb->get_row("SELECT post_id FROM {$wpdb->prefix}postmeta where meta_key like 'guesty_id' and meta_value like '{$item}'");
            if (!empty($room)) {
                $roomsIds[] = $room->post_id;
            }
        }
        if (count($roomsIds) > 0) {
            $bookings = new WP_Query([
                'post_type' => 'mphb_booking',
                'post_status' => ['confirmed', 'pending'],
                'meta_query' => [
                    'relation' => 'OR',
                    [
                        'relation' => 'AND',
                        [
                            'key'     => 'mphb_check_in_date',
                            'value'   => $checkin,
                            'type'    => 'date',
                            'compare' => '<=',
                        ],
                        [
                            'key'     => 'mphb_check_out_date',
                            'value'   => $checkin,
                            'type'    => 'date',
                            'compare' => '>',
                        ],
                    ],
                    [
                        'relation' => 'AND',
                        [
                            'key'     => 'mphb_check_in_date',
                            'value'   => $checkout,
                            'type'    => 'DATE',
                            'compare' => '<=',
                        ],
                        [
                            'key'     => 'mphb_check_out_date',
                            'value'   => $checkout,
                            'type'    => 'DATE',
                            'compare' => '>',
                        ],
                    ]
                ],
            ]);

            if ($bookings->have_posts()) {
                $blokedRooms = [];
                while ($bookings->have_posts()) {
                    $bookings->the_post();
                    foreach ($roomsIds as $room) {
                        $reservedRoom = new WP_Query([
                            'post_type'     => 'mphb_reserved_room',
                            'post_parent'   => get_the_ID(),
                            'meta_query' => [
                                [
                                    'key'     => '_mphb_room_id',
                                    'value'   => $room
                                ],
                            ],
                        ]);
                        if ($reservedRoom->have_posts()) {
                            $blokedRooms[] = $room;
                        }
                    }
                }
            }
            if (!empty($blokedRooms) && count($blokedRooms) != count($roomsIds)) {
                $freeRooms = array_diff($roomsIds, $blokedRooms);
                return get_post_meta($freeRooms[0], 'guesty_id', true);
            }
            if (empty($blokedRooms)) {
                return get_post_meta($roomsIds[0], 'guesty_id', true);
            }
        }
        return false;
    }
}
