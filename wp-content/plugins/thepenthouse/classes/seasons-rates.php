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
                "post_title" => ('Rate ' . get_the_title($roomType))
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
            'posts_per_page' => -1,
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
            'name'          => 'roomType-' . $roomType,
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
        <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>" autocomplete="off">
            <h4>Use this section for generating the seasons and them prices</h4>
            <label>Packages <a href="#" class="check-all" data-field="accommodations-types">Select/Unselect all</a></label>

            <ul>
                <?php while ($roomTypes->have_posts()) {
                    $roomTypes->the_post();
                    if (is_package(get_the_ID())){?>
                        <li style="list-style: none;"><input class="accommodations-types" type="checkbox" name="roomTypes[]" checked value="<?php echo get_the_ID(); ?>"><?php echo get_the_title(); ?></li>
                <?php }
                } ?>
            </ul>
            <label>Select the initial date</label>
            <input class="datepicker" name="startDate" id="startDate" placeholder="<?php echo date('Y-m-d'); ?>">

            <label>Select the end date</label>
            <input class="datepicker" name="endDate" id="endDate" placeholder="<?php echo date('Y-m-d'); ?>">

            <label>Price €</label>
            <input name="price" id="price" placeholder="99">
            <br /><br />

            <?php
            foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $key=> $day) {?>
                <input name="days[]" id="days" type="checkbox" value="<?php echo $key; ?>"> <?php echo $day?> &nbsp;&nbsp;
            <?php }?>

            <br /><br />
            <input type='hidden' name="action" value='tph_seasons_rates_create' class='button'>
            <input type='submit' name="update" value='Update seasons' class='button'>
        </form>
        </br>
        </br>
        <hr>
        <?php
            global $wpdb;
            $table_name = $wpdb->prefix . "listings";

            $listings = $wpdb->get_results("SELECT id, number, guesty_id from $table_name");
        ?>
        <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
            <h4>Use this section for generating the seasons and them prices with Guesty calendar values</h4>
            <label>Guesty Listings <a href="#" class="check-all" data-field="listing">Select/Unselect all</a></label>
            <ul>
                <?php foreach ($listings as $listing) {?>
                    <li style="list-style: none; display:inline; margin-right: 10px;"><input class="listing" type="checkbox" name="listingsId[]" checked value="<?php echo $listing->guesty_id; ?>"><?php echo $listing->number; ?></li>
                <?php } ?>
            </ul>
            <label>Year</label>
            <input class="datepicker" name="year" id="year" maxlength="4" minlength="4" value="<?php echo date('Y'); ?>">
            </br>
            </br>
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
     * @param array $days
     * @return void
     */
    public function syncSeasons(array $roomTypes, int $price, string $startDate, string $endDate, array $days = [])
    {
        $loopStartDate = $startDate;
        $seasonsIds = [];
        while (new DateTime($loopStartDate) <= new DateTime($endDate)) {
            $day = date('w', strtotime($loopStartDate));
            // If ther is not restriction for any day of the week
            //Or if the day of the week is on the allowed days
            if (count($days) == 0 || count($days) == 7 || in_array($day, $days)) {
                $seasonsIds[] = $this->cresteSeason($loopStartDate);
            }
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
        for ($i = 1; $i <= 100; $i++) {
            $date = date("Y-m-d", strtotime("-$i day"));
            $postTitle = 'season' . $date . 'to' . $date;
            $seasons = get_posts([
                'post_type' => 'mphb_season',
                "s" => $postTitle, //Search in title field
                'post_status' => 'publish',
                'posts_per_page'      => -1
            ]);

            if (count($seasons) == 0) break;

            foreach ($seasons as $season) {
                wp_delete_post($season->ID, true);
            }
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
            $startDate = $year == date('Y') ? date("Y-m")."-01" : $year . "-01-01";
            $endDate = date("Y-m-d", strtotime($startDate . " + 1 month"));
            while (date("Y",strtotime($endDate)) == $year || (int)date("m",strtotime($endDate)) == "01") {
                $response = $guesty->getListingCalendar($listingId, $startDate, $endDate);
                $calendars = !empty($response['result']['data']['days']) ? $response['result']['data']['days'] : [];
                if (count($calendars)) {
                    $this->updatePrice($calendars);
                }
                $startDate = $endDate;
                $endDate = date("Y-m-d", strtotime($startDate . " + 1 month"));
            }
        }
    }


    /**
     * @return void
     */
    public function updateRatesPostName() {      
        $rates = get_posts([
            'post_type'      => 'mphb_rate',
            'posts_per_page' => -1
        ]);
        foreach ($rates as $rate) {
            $roomType = explode('roomtype-', $rate->post_name);
            if ((int)$roomType[1] > 0) {
                wp_update_post([
                    'ID'         => $rate->ID,
                    'post_title' => ('Rate ' . get_the_title($roomType[1]))
                ]);
            }
        }
    }
}
