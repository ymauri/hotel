<?php

class Reservation
{
    private $booking;
    private $calendar;
    private $notifier;

    public function __construct()
    {
        $this->guesty = new Guesty();
        $this->calendar = new Calendar();
        $this->notifier = new ReservationNotifier();
    }

    public function setBooking($booking)
    {
        $this->booking = $booking;
    }

    public function put()
    {
        global $wpdb;
        $status = $this->booking->getStatus() == "confirmed" ? "confirmed" : "canceled";
        $this->notifier->setBooking($this->booking->getId());
        $reservationId = get_post_meta($this->booking->getId(), 'mphb_reservation_id', true);
        foreach ($this->booking->getReservedRooms() as $item) {
            $listingId = get_post_meta($item->getRoomId(), 'guesty_id', true);

            if (!empty($listingId)) {
                $checkin = $this->booking->getCheckInDate()->format('Y-m-d');
                $checkout = $this->booking->getCheckOutDate()->format('Y-m-d');
                $isFromGuesty = get_post_meta($this->booking->getId(), 'mphb_is_from_guesty', true);
                $isFromPackage = get_post_meta($this->booking->getId(), 'mphb_is_from_package', true);
                if (/*$this->isFreeCalendarDays($listingId, $checkin, $checkout, $reservationId) &&*/empty($isFromGuesty) && empty($isFromPackage)) {
                    $data = [
                        "listingId" => $listingId,
                        "checkInDateLocalized" => $checkin,
                        "checkOutDateLocalized" => $checkout,
                        "status" =>  $status,
                        "money" => [
                            "fareAccommodation" => 1,
                            "currency" => "EUR"
                        ],
                        "guest" => [
                            "firstName" => $this->booking->getCustomer()->getFirstName(),
                            "lastName" => $this->booking->getCustomer()->getLastName(),
                            "email" => $this->booking->getCustomer()->getEmail(),
                            "phone" => $this->booking->getCustomer()->getPhone(),
                        ],
                        "customFields" => [
                            "bookingId" => $this->booking->getId()
                        ]
                    ];

                    if (empty($reservationId)) {
                        // $response = $this->guesty->createReservation($data);
                    } else {
                        // $response = $this->guesty->updateReservation($reservationId, $data);
                    }

                    if (!empty($response['result']['_id'])) {
                        if (empty($reservationId)) {
                            add_post_meta($this->booking->getId(), 'mphb_reservation_id', $response['result']['_id']);
                        } else {
                            update_post_meta($this->booking->getId(), 'mphb_reservation_id', $response['result']['_id']);
                        }
                        $this->log($response['result']['_id'], $this->booking->getId());
                    }
                }
                $this->syncOtherRooms($listingId, $item->getRoomId());
            }
        }
        // wp_mail("ymauri@outlook.com", 'A booking has been created!', $this->notifier->renderBody(),  array('Content-Type: text/html; charset=UTF-8'));
    }

    public function isFreeCalendarDays(string $listingId, string $checkin, string $checkout, string $reservationId = null)
    {
        // Do not validate occupation on checkout date
        $checkoutDateTime = new DateTime($checkout);
        $checkoutDateTime->sub(new DateInterval('P1D'));
        $checkout = $checkoutDateTime->format('Y-m-d');

        $listingCalendar = $this->guesty->getListingCalendar($listingId, $checkin, $checkout);
        if (!empty($listingCalendar['result'])) {
            foreach ($listingCalendar['result'] as $calendarDay) {
                if (
                    $calendarDay["status"] != 'available' ||
                    $calendarDay['reservationId'] != $reservationId
                ) {
                    return false;
                }
            }
            return true;
        }
        return false;

        return true;
    }

    private function log(string $reservationId, int $bookingId = null)
    {
        global $wpdb;
        $bookingId = $bookingId ?? $this->booking->getId();
        $row = [
            'order_item_id' => $bookingId,
            'guesty_id' => $reservationId
        ];
        $wpdb->insert($wpdb->prefix . 'reservations', $row, ['%s', '%s']);
    }

    private function syncOtherRooms($listingId, $reservedRoom)
    {
        global $wpdb;
        $rooms = new WP_Query([
            'post_type' => 'mphb_room',
            'post_status' => 'published',
            'meta_query' => [
                [
                    'key'     => 'guesty_id',
                    'value'   => $listingId,
                    'compare' => '=',
                ]
            ],
        ]);

        if ($rooms->have_posts()) {
            while ($rooms->have_posts()) {
                $rooms->the_post();
                if (get_the_ID() != $reservedRoom) {
                    $reservation = [
                        "_id"                   => 'package' . $this->booking->getId(),
                        "status"                => $this->booking->getStatus() == 'cancelled' ? 'cancelled' : 'pending',
                        "listingId"             => $listingId,
                        "guestsCount"           => 1,
                        "checkInDateLocalized"  => $this->booking->getCheckInDate()->format('Y-m-d'),
                        "checkOutDateLocalized" => $this->booking->getCheckOutDate()->format('Y-m-d'),
                        "firstName" => "Created Automatically",
                        "lastName" =>  "Reference Booking #" . $this->booking->getId(),
                        "email" => "this_is_not_@real.client.com",
                        "phone" => "+3166666666",
                        "details" => "Created by The system. Reference Booking #" . $this->booking->getId(),
                    ];

                    $bookingId = $this->calendar->syncCalendar($reservation, false, true, get_the_ID(), $this->booking->getId());
                    $this->notifier->addRelatedBooking($bookingId);
                    $this->calendar->log($bookingId, $reservation['_id']);
                }
            }
        }
    }

    public function delete(int $bookingId)
    {
        $children = get_posts([
            'post_type'      => 'mphb_booking',
            'post_parent'    => $bookingId
        ]);

        if (is_array($children) && count($children) > 0) {
            foreach ($children as $child) {
                wp_delete_post($child->ID, true);
            }
        }
            
        $reservationId = get_post_meta($bookingId, 'mphb_reservation_id', true);
        if (!empty($reservationId)) {
            $this->guesty->updateReservation($reservationId, [
                "status" => 'canceled',
            ]);
        }
        wp_delete_post($bookingId, true);
    }
}
