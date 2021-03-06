<?php

class Reservation
{
    private $booking;
    private $blockedRooms;

    public function __construct()
    {
        $this->guesty = new Guesty();
        $this->blockedRooms = new BlockedRoom();
    }

    public function setBooking($booking)
    {
        $this->booking = $booking;
    }

    public function put()
    {
        $status = $this->booking->getStatus() == "confirmed" ? "confirmed" : "canceled";
        $reservationId = get_post_meta($this->booking->getId(), 'mphb_reservation_id', true);

        foreach ($this->booking->getReservedRooms() as $item) {
            $rateId = get_post_meta( $item->getId(), '_mphb_rate_id', true );
            $listingId = get_post_meta($item->getRoomId(), 'guesty_id', true);
            $roomTypeId = get_post_meta($rateId, 'mphb_room_type_id', true);
            $isPackage = is_package($roomTypeId);
            $services = get_post_meta($item->getId(), '_mphb_services');
            $note = "";

            if ($status == 'confirmed') {
                if ($isPackage) {
                    $note .= " Package: " . get_the_title($roomTypeId).". ";
                }
                if (isset($services[0]) && count($services[0])) {
                    $note .= " Services: ";
                    foreach ( $services[0] as $service) {
                        $note .= get_the_title($service['id'])." [".$service['quantity']."x], ";
                    }
                }

                $payments = get_posts([
                    'post_type'  => 'mphb_payment',
                    'posts_per_page' => -1,
                    'meta_key'      => '_mphb_booking_id',
                    'meta_value'    => $this->booking->getId(),
                    'post_status'   => 'mphb-p-completed'
                ]);

                $paid = 0;
                foreach ($payments as $payment) {
                    $paid += get_post_meta( $payment->ID, '_mphb_amount', true);
                }
                if ($paid < $this->booking->getTotalPrice()) {
                    $note.= "DEPOSIT ";
                }
                $note .= "PAID: €". $paid;    
            }

            if (!empty($listingId)) {
                $checkin = $this->booking->getCheckInDate()->format('Y-m-d');
                $checkout = $this->booking->getCheckOutDate()->format('Y-m-d');
                $isFromGuesty = get_post_meta($this->booking->getId(), 'mphb_is_from_guesty', true);
                $guestsCount = get_post_meta($item->getId(), '_mphb_adults', true);

                $data = [
                    "listingId" => $listingId,
                    "checkInDateLocalized" => $checkin,
                    "checkOutDateLocalized" => $checkout,
                    "status" =>  $status,
                    "guestsCount" => $guestsCount,
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
                    "notes" => [
                        "other" => $note
                    ]
                ];

                if (empty($isFromGuesty)) {
                    if (empty($reservationId)) {
                        $response = $this->guesty->createReservation($data);
                    } else {
                        $response = $this->guesty->updateReservation($reservationId, $data);
                    }

                    if (!empty($response['result']['_id'])) {                      
                        update_post_meta($this->booking->getId(), 'mphb_reservation_id', $response['result']['_id']);
                        $this->log($response['result']['_id'], $this->booking->getId());
                    }
                }
                
                $data['_id'] = get_post_meta($this->booking->getId(), 'mphb_reservation_id') ?? "";
                $calendar = new Calendar();
                $calendar->populateChanges($data, $item->getRoomId(), $status);
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
        $checkout = date('Y-m-d', strtotime($checkout.' -1 day'));

        if (is_array($children) && count($children) > 0) {
            foreach ($children as $child) {
                $roomId = get_post_meta( $child->ID, '_mphb_room_id', true);
                if (!empty($roomId)) {
                    $this->blockedRooms->delete($roomId, $checkin, $checkout);
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
