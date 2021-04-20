<?php

class BlockedRoom
{
    public function __construct()
    {
    }

    /**
     * Get bloked rooms from configuration variable
     * 
     * @return array
     */
    public function getAll()
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
        $blockedRooms = $this->getAll();
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
    public function add(int $roomId, string $checkin, string $checkout, string $comment = "", $key = false)
    {
        if (strtotime($checkout) >= strtotime($checkin)) {
            // Do not add new blocked room if there is any reservation which start_date is on the range
            //Search booking by dates
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
                        'compare'   => '<',
                        'type'      => 'DATE',
                    ]
                ]
            ]);

            //Search rooms include into this bookings
            foreach ($bookings as $booking) {
                $rooms = get_posts([
                    'post_type'  => 'mphb_reserved_room',
                    'posts_per_page' => -1,
                    'post_parent' => $booking->ID,
                    'meta_query' => [
                        [
                            'key'   => '_mphb_room_id',
                            'value' => $roomId
                        ]
                    ]
                ]);
                if (count($rooms)) {
                    //If i found any room i cant add the blocked room 
                    //There is at least one reservation starting on the range date you gave            
                    return false;
                }
            } 

            $blockedRooms = $this->getAll();
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
                return true;
            }
        }
        return false;
    }

    /**
     * Delete past blocked rooms
     * 
     * @return int
     */
    public function deletePast()
    {
        $blockedRooms = $this->getAll();
        foreach ($blockedRooms as $key => $blockedRoom) {
            if (strtotime($blockedRoom['date_from']) < strtotime('-1 day') && strtotime($blockedRoom['date_to']) < strtotime('-1 day')) {
                unset($blockedRooms[$key]);
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
    public function delete(string $listingId, string $checkin, string $checkout)
    {
        $rooms = get_posts([
            'post_type'     => 'mphb_room',
            'meta_key'      => 'guesty_id',
            'posts_per_page' => -1,
            'meta_value'    => $listingId
        ]);
        $blockedRooms = $this->getAll();
        foreach ($rooms as $room) {
            $keyBlocked = $this->isBlocked($room->ID, $checkin, $checkout);
            if ($keyBlocked !== -1) {
                unset($blockedRooms[$keyBlocked]);
            }
        }
        update_option('mphb_booking_rules_custom', $blockedRooms);
    }


    /**
     * Delete blocked room from datatable 
     * 
     * @param int $room_id
     * @param string $from
     * @param string $to
     * 
     */
    public function deleteByRoomId($room_id, $from, $to)
    {
        $blockedRooms = $this->getAll();

        $keyBlocked = $this->isBlocked($room_id, $from, $to);
        if ($keyBlocked !== -1) {
            unset($blockedRooms[$keyBlocked]);
            update_option('mphb_booking_rules_custom', $blockedRooms);
        }
    }
}
