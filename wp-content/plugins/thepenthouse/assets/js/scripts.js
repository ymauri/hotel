jQuery(document).ready(function() {
    // jQuery('.mphb-rooms-quantity').hide();
    // jQuery('.mphb-available-rooms-count').each(function() { jQuery(this).text(jQuery(this).text().replace('of', '')) })
    console.log("jfg")
    let url = "http://" + window.location.hostname + "/wp-admin/admin-ajax.php?action=thpdtblockedroom ";
    jQuery('#blockedRooms').dataTable({
        "serverSide": true,
        "ajax": {
            "url": url,
            "type": "GET"
        },
        "columns": [{
                "data": "room_type_id"
            },
            {
                "data": "room_id"
            },
            {
                "data": "date_from"
            },
            {
                "data": "date_to"
            }
        ]
    });

    $(document).ready(function() {
        $('#example').dataTable({
            "footerCallback": function(row, data, start, end, display) {
                var api = this.api(),
                    data; // Remove the formatting to get integer data for summation
                var intVal = function(i) { return typeof i === 'string' ? i.replace(/[\$,]/g, '') * 1 : typeof i === 'number' ? i : 0; }; // Total over all pages 
                data = api.column(4).data();
                total = data.length ? data.reduce(function(a, b) { return intVal(a) + intVal(b); }) : 0; // Total over this page 
                data = api.column(4, { page: 'current' }).data();
                pageTotal = data.length ? data.reduce(function(a, b) { return intVal(a) + intVal(b); }) : 0; // Update footer
                $(api.column(4).footer()).html('$' + pageTotal + ' ( $' + total + ' total)');
            }
        });
    });
})