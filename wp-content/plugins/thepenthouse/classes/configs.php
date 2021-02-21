<?php

class Configs {
    public function datatable_content() {
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
                            <th>Action</th>
                        </tr>
                        <tr>
                            <th><input type="text" name="room_type_id" placeholder="Accommodation Type"></th>
                            <th><input type="text" name="room_id" placeholder="Accommodation"></th>
                            <th><input type="text" name="date_from" placeholder="From"></th>
                            <th><input type="text" name="date_to" placeholder="To"></th>
                            <th><button id="search-btn" >Search</button></th>
                        </tr>
                    </thead>
                </table>
            </form>
        </div>
        
        <script>
            jQuery(document).ready(function() {
                let table;
                jQuery('body').on('click','#search-btn', function (e) {
                    e.preventDefault();
                    table.ajax.reload( null, false );
                });
                table = jQuery('#blockedRooms').DataTable({
                    "ajax": {
                        "url":`${ajaxurl}?action=fill_datatable`,
                        "beforeSend" : function(jqXHR, settings){
                            settings.url += jQuery('#search-form').serialize();
                        }
                    },
                    "processing": true,
                    "serverSide": true,
                    "searching": false,
                    "columns": [{
                            "data": "room_type_id",
                            "orderable" : false,
                        },
                        {
                            "data": "room_id",
                            "orderable" : false,
                        },
                        {
                            "data": "date_from",
                            "orderable" : false,
                        },
                        {
                            "data": "date_to",
                            "orderable" : false,
                        },                        
                        {
                            "orderable" : false,
                            "render": function(data, type, row, meta) {
                                return `<a href="#" class="btn delete-item" data-date_to="${row.date_to}" data-date_from="${row.date_from}" data-room_id="${row.room_id}">Delete</a>`;
                            }
                        }
                    ],

                });
                    jQuery('body').on('click', '.delete-item', function(e){
                        e.preventDefault();
                        var data = {
                            'action': 'delete_calendar',
                            'room_id': jQuery(this).data('room_id'),
                            'date_from': jQuery(this).data('date_from'),
                            'date_to': jQuery(this).data('date_to')
                        };

                        jQuery.post(ajaxurl, data, function() {
                            alert('Done');     
                            table.ajax.reload( null, false );
                        });
                        return;
                    });
                });

        </script>

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

 }