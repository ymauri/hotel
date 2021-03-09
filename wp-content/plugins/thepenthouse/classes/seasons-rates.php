<?php

class SeasonsRates
{
    public function __construct()
    {
        $this->guesty = new Guesty();
    }

    /**
     * Generate season post
     * @param string $date
     * 
     * @return int
     */
    public function cresteSeason(string $date)
    {
        $seasonId = $this->searchSeason($date, $date);
        if (empty($seasonId)) {
            $seasonId = wp_insert_post([
                "post_type" => "mphb_season",
                "post_status" => 'publish',
                "comment_status" => "closed",
                "ping_status" => "closed",
                "post_name" => 'season' . $date . 'to' . $date,
                "post_title" => 'season' . $date . 'to' . $date
            ], false, false);

            add_post_meta($seasonId, 'mphb_start_date', $date);
            add_post_meta($seasonId, 'mphb_end_date', $date);
            add_post_meta($seasonId, 'mphb_days', [0, 1, 2, 3, 4, 5, 6]);
        }
        return $seasonId;
    }

    /**
     * Generate rates
     * @param int $roomType
     * @param string $seasonId
     * @param int $price
     * 
     * @return void
     */
    private function createRates(int $roomType, string $seasonId, int $price)
    {
        $rateId = $this->searchRate($roomType);
        if (empty($rateId)) {
            $rateId = wp_insert_post([
                "post_type" => "mphb_rate",
                "post_status" => 'publish',
                "comment_status" => "closed",
                "ping_status" => "closed",
                "post_name" => 'roomType-' . $roomType,
                "post_title" => 'roomType-' . $roomType
            ], false, false);

            add_post_meta($rateId, 'mphb_room_type_id', $roomType);
        }

        $rateSeasons = get_post_meta($rateId, 'mphb_season_prices', true);

        if (!empty($rateSeasons)) {
            $key = array_search($seasonId, array_column($rateSeasons, 'season'));
            if ($key !== false) {
                $rateSeasons[$key]['price']['prices'] = [$price];
                update_post_meta($rateId, 'mphb_season_prices', $rateSeasons);
                return;
            }
        }
        if (empty($rateSeasons)) {
            $rateSeasons = [];
        }
        $rateSeasons[] = [
            'season'    => $seasonId,
            'price'     => [
                'periods'   => [1],
                'prices'    => [$price],
                'enable_variations' => false,
                'variations' => []
            ]
        ];
        update_post_meta($rateId, 'mphb_season_prices', $rateSeasons);
        return;
    }

    /**
     * Search season by dates
     * @param string $startDate
     * @param string $endDate
     * 
     * @return int
     */
    private function searchSeason(string $startDate, string $endDate)
    {
        $seasons = new WP_Query([
            'post_type' => 'mphb_season',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key'     => 'mphb_start_date',
                    'value'   => $startDate,
                    'compare' => '=',
                ],
                [
                    'key'     => 'mphb_end_date',
                    'value'   => $endDate,
                    'compare' => '=',
                ]
            ],
        ]);
        $seasonId = null;
        if ($seasons->have_posts()) {
            while ($seasons->have_posts()) {
                $seasons->the_post();
                $seasonId = get_the_ID();
                break;
            }
        }
        wp_reset_query();
        return $seasonId;
    }

    /**
     * Search rates by post_title (accommodation_type_id)
     * @param int $roomType
     * 
     * @return [type]
     */
    private function searchRate(int $roomType)
    {
        $rates = get_posts([
            'post_type'      => 'mphb_rate',
            'title'          => 'roomType-' . $roomType,
            'posts_per_page' => -1
        ]);
        foreach ($rates as $rate) {
            return $rate->ID;
        }
        return null;
    }

    /**
     * Render view for generating seasons
     * @return void
     */
    public function syncSeasonsView()
    {
        $roomTypes = new WP_Query([
            'post_type' => 'mphb_room_type',
            'posts_per_page' => -1
        ]);
?>
        <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
            <h4>Select the accommodation types</h4>
            <ul>
                <?php while ($roomTypes->have_posts()) {
                    $roomTypes->the_post(); ?>
                    <li style="list-style: none;"><input type="checkbox" name="roomTypes[]" checked value="<?php echo get_the_ID(); ?>"><?php echo get_the_title(); ?></li>
                <?php } ?>
            </ul>
            <label>Select the initial date</label>
            <input class="datepicker" name="startDate" id="startDate">

            <label>Select the end date</label>
            <input class="datepicker" name="endDate" id="endDate">
            <br /><br />

            <label>Price €</label>
            <input name="price" id="price">
            <br /><br />

            <input type='hidden' name="action" value='tph_seasons_rates_create' class='button'>
            <input type='submit' name="update" value='Update seasons' class='button'>
        </form>

        <hr>
        <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
            <h4>Get prices from guesty</h4>
            <label>Listing</label>
            <input class="datepicker" name="listingId" id="listingId" value="60414098eddcc400306ff648">
            <label>Year</label>
            <input class="datepicker" name="year" id="year" value="<?php echo date('Y'); ?>">

            <input type='hidden' name="action" value='tph_seasons_rates_create' class='button'>
            <input type='submit' name="update" value='Sync data' class='button'>
        </form>
<?php
    }

    /**
     * Allows to manage seasons and rates relationships
     * @param array $roomTypes
     * @param int $price
     * @param string $startDate
     * @param string $endDate
     * 
     * @return void
     */
    public function syncSeasons(array $roomTypes, int $price, string $startDate, string $endDate)
    {
        $loopStartDate = $startDate;
        $seasonsIds = [];
        while (new DateTime($loopStartDate) <= new DateTime($endDate)) {
            $seasonsIds[] = $this->cresteSeason($loopStartDate);
            $loopStartDate = date('Y-m-d', strtotime($loopStartDate . ' +1 day'));
        }

        foreach ($roomTypes as $roomType) {
            foreach ($seasonsIds as $season) {
                $this->createRates($roomType, $season, $price);
            }
        }
    }

    /**
     * Update prices that came from guesty platform
     * @param array $dataCalendar
     * 
     * @return void
     */
    public function updatePrice(array $dataCalendar)
    {
        $first = array_key_first($dataCalendar);
        $listingId = $dataCalendar[$first]['listingId'];

        $rooms = get_posts([
            'post_type' => 'mphb_room',
            'meta_key'      => 'guesty_id',
            'posts_per_page'      => -1,
            'meta_value'    => $listingId
        ]);

        $roomTypes = [];
        foreach ($rooms as $room) {
            $roomType =  get_post_meta($room->ID, 'mphb_room_type_id', true);
            if (!empty(get_post_meta($roomType, 'update_price', true))) {
                $roomTypes[] = $roomType;
            }
        }

        foreach ($dataCalendar as $calendar) {
            if ($calendar['status'] != "booked") {
                $this->syncSeasons($roomTypes, $calendar['price'], $calendar['date'], $calendar['date']);
            }
        }
    }

    /**
     * Job task. Delete past mphb_season post_type
     * @return void
     */
    public function deleteOldSeasons()
    {
        $date = date("Y-m-d", strtotime(date("Y-m-d") . " - 1 days"));
        $seasons = get_posts([
            'post_type' => 'mphb_season',
            "post_name" => 'season' . $date . 'to' . $date,
        ]);

        foreach ($seasons as $season) {
            wp_delete_post($season->ID, true);
        }
    }

    /**
     * Retrieve prices from guesty calendar
     * @param string $listingId
     * 
     * @return void
     */
    public function retrievePrices(string $listingId, string $year)
    {
        $guesty = new Guesty();
        if ($year >= date('Y')) {
            $startDate = $year == date('Y') ? date("Y-m-d") : $year . "-01-01";
            $endDate = date("Y-m-d", strtotime($startDate . " + 1 month"));
            do {
                $response = $guesty->getListingCalendar($listingId, $startDate, $endDate);
                $calendars = !empty($response['result']['data']['days']) ? $response['result']['data']['days'] : [];
                if (count($calendars)) {
                    $this->updatePrice($calendars);
                }
                $startDate = $endDate;
                $endDate = date("Y-m-d", strtotime($startDate . " + 1 month"));
            } while (date("m",strtotime($endDate)) <= 12 && date("Y",strtotime($endDate)) == $year);
        }
    }
}
