<?php

function guest_select_behaviour() {
    //Booking done
    if (is_page ('77')) {
        ?>  
            <script>
            jQuery('.mphb_sc_checkout-guests-chooser.mphb_checkout-guests-chooser').on('change', function() {
                var guests = jQuery(this).val();
                jQuery('.mphb_sc_checkout-service-adults.mphb_checkout-service-adults').each(function(){
                    jQuery(this).val(guests).trigger('change');
                });
            });
            </script>
        <?php
    }

    ?>  
        <script>
        if (jQuery('.mphb-rooms-quantity').length > 0) {
            jQuery('.mphb-rooms-quantity').each(function() {
                
                let parent = jQuery(this).parent();
                parent.find('select').first().hide().before("<b>1 </>");
            });
        }
        </script>
    <?php

}
add_action('wp_footer', 'guest_select_behaviour');

