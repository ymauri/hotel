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
                    ]
                ];

                if (empty($isFromGuesty)) {
                    if (empty($reservationId)) {
                        $response = $this->guesty->createReservation($data);
                    } else {
                        $response = $this->guesty->updateReservation($reservationId, $data);
                    }

                    if (!empty($response['result']['id'])) {
                        if (empty($reservationId)) {
                            add_post_meta($this->booking->getId(), 'mphb_reservation_id', $response['result']['id']);
                        } else {
                            update_post_meta($this->booking->getId(), 'mphb_reservation_id', $response['result']['id']);
                        }
                        $this->log($response['result']['id'], $this->booking->getId());
                    }                    
                }

                if ($status == 'canceled') {
                    $this->calendar->deleteBlockedRoom($listingId, $checkin, $checkout);
                } else {
                    $data['_id'] = get_post_meta($this->booking->getId(), 'mphb_reservation_id') ?? "";
                    $this->calendar->syncOtherRooms($listingId, $item->getRoomId(), $data);
                }    
            }
        }
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

    public function delete(int $bookingId)
    {
        $children = get_posts([
            'post_type'      => 'mphb_booking',
            'post_parent'    => $bookingId,
            'posts_per_page' => -1 
        ]);

        $checkin = get_post_meta( $bookingId, 'mphb_check_in_date', true );
        $checkout = get_post_meta( $bookingId, 'mphb_check_out_date', true );

        if (is_array($children) && count($children) > 0) {
            foreach ($children as $child) {
                $roomId = get_post_meta( $child->ID, '_mphb_room_id', true);
                if (!empty($roomId)) {
                    $this->calendar->deleteBlockedRoom($roomId, $checkin, $checkout);
                }
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
