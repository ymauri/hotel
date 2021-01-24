<?php

add_action('mphb_booking_confirmed', 'update_reservation');
add_action('mphb_booking_cancelled', 'update_reservation');

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
