<?php

class Configs {
    public function datatable_content() {
        $this->renderStaticVars();
        ?>
        <div class="container" style="width: 98%;">
            <h3>Blocked Rooms</h3>
            <form name="search-form" id="search-form" autocomplete="off" method="POST">
                <table id="blockedRooms" class="wp-list-table widefat fixed striped table-view-excerpt pages" width="100%">
                    <thead>
                        <tr>
                            <th>Accommodation Type Id</th>
                            <th>Accommodation</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Comment</th>
                            <th>Action</th>
                        </tr>
                        <tr>
                            <th><input type="text" name="room_type_id" placeholder="Accommodation Type" style="width: 90%;"></th>
                            <th><input type="text" name="room_id" placeholder="Accommodation" style="width: 90%;"></th>
                            <th><input type="text" name="date_from" placeholder="From" style="width: 90%;"></th>
                            <th><input type="text" name="date_to" placeholder="To" style="width: 90%;"></th>
                            <th></th>
                            <th><button id="search-btn" >Search</button></th>
                        </tr>
                    </thead>
                </table>
            </form>
        </div>
        <br><br>
        
                    
        <form name="add-form" id="add-form" autocomplete="off" method="POST">
            <div class="form-inline-fields">
                <label>Accommodation Type</label>
                <?php echo $this->renderAccommodationTypes();?>
            </div>
            <div class="form-inline-fields" id="content-accomodations">
                <label>Accommodation</label>
                <?php echo $this->renderAccommodationByType();?>
            </div>                
            <div class="form-inline-fields">
                <label>From</label>
                <input class="datepicker" name="date_from" id="date_from">
            </div>                
            <div class="form-inline-fields">
                <label>To</label>
                <input class="datepicker" name="date_to" id="date_to">
            </div>
            <div class="form-inline-fields">
                <button id='register-blocked-room'>Add new one</button>
            </div>
        </form>      
    <?php
    
    }

    public function fill_datatable() {
        $arrayData = get_option( 'mphb_booking_rules_custom', array() );
        $total = count($arrayData);
        $filterData = [];
        $hasFilter = false;
        $request= $_GET;
        
        if (!empty($request['room_type_id']) || 
            !empty($request['room_id']) ||
            !empty($request['date_from']) || 
            !empty($request['date_to'])  ){
            $hasFilter = true;
            $filterData = array_filter($arrayData,function($item)  use($request){
                return ($item['room_type_id'] == $request['room_type_id']) ||
                        ($item['room_id'] == $request['room_id']) ||
                        ($item['date_from'] == $request['date_from']) ||
                        ($item['date_to'] == $request['date_to']);
            });
        }        

        $returnData = array_slice($hasFilter ? $filterData : $arrayData,  $request['start'],  $request['length']);
        $data = [
            "draw" => intval($request['draw']) ??  $request['length'],
            "recordsTotal" => $total,
            "recordsFiltered" => $hasFilter ? count($filterData) : $total,
            "data" => $returnData
        ];
        echo json_encode($data);
        wp_die();
    }

    private function renderAccommodationTypes() {
        $select = '<select name="room_type" id="room_type" style="width: 200px;"> <option></option>';

        $at = new WP_Query([
            'post_type' => 'mphb_room_type',
            'posts_per_page'=>-1
        ]);
        
        if ($at->have_posts()) {
            while ($at->have_posts()) {
                $at->the_post();
                $select .= ("<option value='".get_the_ID()."'>".get_the_title()."</option>");

                // $isPackage = count(wp_get_post_terms(get_the_ID(), 'package', true)) > 0;
                // if ($isPackage) {                    
                //     $select .= ("<option value='".get_the_ID()."'>".get_the_title()."</option>");
                // }
            }
        }
        wp_reset_query();
        $select .= '</select>';
        return $select;
    }

    private function renderAccommodationByType($id = null) {
        if (!empty($id)) {
            $select = '<select name="room_id" id="room_id" style="width: 200px;"><option></option>';

            $rooms = new WP_Query([
                'post_type' => 'mphb_room',
                'posts_per_page'=>-1,
                'meta_query' => [
                    [
                        'key'     => 'mphb_room_type_id',
                        'value'   => $id,
                        'compare' => '=',
                    ]
                ],          
            ]);
            
            if ($rooms->have_posts()) {
                while ($rooms->have_posts()) {
                    $rooms->the_post();
                    $select .= ("<option value='".get_the_ID()."'>".get_the_title()."</option>");
                }
            }
            wp_reset_query();
            $select .= '</select>';
        } else {
            $select = '<select name="room" id="room" style="width: 200px;"></select>';
        }
        
        return $select;
    }

    public function fill_rooms_select() {
        echo $this->renderAccommodationByType($_GET['id']);
        wp_die();
    }

    public function renderStaticVars() {
        $at = new WP_Query([
            'post_type' => 'mphb_room_type',
            'posts_per_page'=>-1
        ]);
        $roomsTypes = $rooms = [];
        if ($at->have_posts()) {
            while ($at->have_posts()) {
                $at->the_post();
                $roomsTypes[get_the_ID()] = get_the_title();
            }
        }
        wp_reset_query();

        $roomsQuery = new WP_Query([
            'post_type' => 'mphb_room',
            'posts_per_page'=>-1
        ]);

        if ($roomsQuery->have_posts()) {
            while ($roomsQuery->have_posts()) {
                $roomsQuery->the_post();
                $rooms[get_the_ID()] = get_the_title();
            }
        }
        wp_reset_query();

        ?>
        <script>
            const roomTypes = <?php echo json_encode($roomsTypes)?>;
            const rooms = <?php echo json_encode($rooms)?>;
        </script>
        <?php
    }

 }