<?php

class ReservationNotifier
{
    private $booking;
    private $relatedBookings;

    public function __construct()
    {
    }

    public function setBooking(int $booking)
    {
        $this->booking = $booking;
    }

    public function getBooking()
    {
        return $this->booking;
    }

    public function setRelatedBookins(array $relatedBookings)
    {
        $this->relatedBookings = $relatedBookings;
    }

    public function getRelatedBookings()
    {
        return $this->relatedBookings;
    }

    public function addRelatedBooking(int $booking)
    {
        $this->relatedBookings[] = $booking;
    }

    public function renderBody()
    {
        $related = "";
        foreach ($this->relatedBookings as $item) {
            $related .= "
                <li>$item</li>
            ";
        }

        $body = "
        <h3><a href='" . admin_url() . "post.php?post=" . $this->booking . "&action=edit'></a><h3>
        <ul> $related </ul>
        ";

        return $body;
    }
}
