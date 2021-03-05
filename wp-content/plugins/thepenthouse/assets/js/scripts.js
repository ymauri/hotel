jQuery(document).ready(function() {
    jQuery("#date_from, #date_to").datepicker({ format: 'YYYY-MM-DD' });
    let table;
    jQuery('body').on('click', '#search-btn', function(e) {
        e.preventDefault();
        table.ajax.reload(null, false);
    });
    table = jQuery('#blockedRooms').DataTable({
        "ajax": {
            "url": `${ajaxurl}?action=fill_datatable`,
            "beforeSend": function(jqXHR, settings) {
                settings.url += jQuery('#search-form').serialize();
            }
        },
        "processing": true,
        "serverSide": true,
        "searching": false,
        "columns": [{
                "data": "room_type_id",
                "orderable": false,
                "render": function(data, type, row, meta) {
                    return roomTypes[data] ? `(${data}) ${roomTypes[data]}` : data;
                }
            },
            {
                "data": "room_id",
                "orderable": false,
                "render": function(data, type, row, meta) {
                    return rooms[data] ? `(${data}) ${rooms[data]}` : data;
                }
            },
            {
                "data": "date_from",
                "orderable": false,
            },
            {
                "data": "date_to",
                "orderable": false,
            },
            {
                "orderable": false,
                "render": function(data, type, row, meta) {
                    return ` <a href="#"
                                class="btn delete-item"
                                data-date_to="${row.date_to}"
                                data-date_from="${row.date_from}"
                                data-room_id="${row.room_id}"> Delete </a>`;
                }
            }
        ],

    });
    jQuery('body').on('click', '.delete-item', function(e) {
        e.preventDefault();
        var data = {
            'action': 'delete_calendar',
            'room_id': jQuery(this).data('room_id'),
            'date_from': jQuery(this).data('date_from'),
            'date_to': jQuery(this).data('date_to')
        };

        jQuery.post(ajaxurl, data, function() {
            alert('Done');
            table.ajax.reload(null, false);
        });
        return;
    });

    jQuery('#room_type').change(function() {
        if (jQuery(this).val()) {
            jQuery.get(`${ajaxurl}?action=fill_rooms_select`, { 'id': jQuery(this).val() }, function(response) {
                jQuery('#content-accomodations select').remove()
                jQuery('#content-accomodations').append(jQuery(response))
            });
        }
    });

    jQuery('#register-blocked-room').click(function(e) {
        e.preventDefault();
        var data = {
            'action': 'register_blocked_room',
            'room_id': jQuery('#room_id').val(),
            'date_from': jQuery('#date_from').val(),
            'date_to': jQuery('#date_to').val()
        };

        jQuery.post(ajaxurl, data, function(response) {
            if (response == 'ok') {
                alert('Done');
            } else {
                alert(response);
            }
            table.ajax.reload(null, false);
        });
        return;
    });
});