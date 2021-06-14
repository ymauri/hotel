<?php

add_action('mphb_booking_confirmed', 'update_reservation');
add_action('mphb_booking_cancelled', 'update_reservation');
add_action('mphb_payment_completed', 'update_payment_detail', 8);

function update_reservation($booking)
{
    try {
        $reservation = new Reservation();
        $reservation->setBooking($booking);
        $reservation->put();
    } catch (Exception $e) {
        throw $e;
    }
}


function update_payment_detail($payment)
{
    $postId = $payment->getId();
    try {
            $bookingID = get_post_meta($postId, '_mphb_booking_id', true);            
            $reservation = new Reservation();
            $booking = MPHB()->getBookingRepository()->findById($bookingID);  
            $reservation->setBooking($booking);
            $reservation->put();
    } catch (Exception $e) {
        throw $e;
    }
}


add_action('trashed_post', 'delete_reservation');

function delete_reservation($postId)
{
    try {
        if (get_post_type($postId) == "mphb_booking") {
            $reservation = new Reservation();
            $reservation->delete((int)$postId);
        }
    } catch (Exception $e) {
        throw $e;
    }
}
