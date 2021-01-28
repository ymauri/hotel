<?php

function all_pages_script() {
    //Booking done
    if (is_page ('80') || is_page ('78')) {
        ?>  
            <script>window.hcFinished=true;</script>
        <?php
    //Booking engine pages
    } else if (is_page ('76') || is_page ('75') || is_page ('84') || is_page('77')) {
        ?>  
            <script>window.hcBooking=true;</script>
        <?php
    }
    ?>  
        <script src="//cdn.hotelchamp.com/app/launcher/rLa9BpLkDE.js"></script>
    <?php
}
add_action('wp_head', 'all_pages_script');

