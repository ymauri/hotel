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
                parent.find('select').first().hide().before("<b>1 </b>");
            });
        }
        </script>
    <?php

}
add_action('wp_footer', 'guest_select_behaviour');

function phone_field() {
   
    ?>  
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/css/intlTelInput.css" integrity="sha512-gxWow8Mo6q6pLa1XH/CcH8JyiSDEtiwJV78E+D+QP0EVasFs8wKXq16G8CLD4CJ2SnonHr4Lm/yY2fSI2+cbmw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

       <!-- Phone field -->
       <style type="text/css">
            .iti__flag {
                background-image: url("https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/img/flags.png");
            }

            #mphb_phone {
                padding-left: 57px !important;
            }

            .iti.iti--allow-dropdown {
                width: 100% !important;
            }
            @media (-webkit-min-device-pixel-ratio: 2),
            (min-resolution: 192dpi) {
                .iti__flag {
                    background-image: url("https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/img/flags@2x.png");
                }
            }
       </style>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.13/js/intlTelInput.min.js" integrity="sha512-QMUqEPmhXq1f3DnAVdXvu40C8nbTgxvBGvNruP6RFacy3zWKbNTmx7rdQVVM2gkd2auCWhlPYtcW2tHwzso4SA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

        <script>
            jQuery('form.mphb_sc_checkout-form').attr('autocomplete', 'off');
            if (jQuery("#mphb_phone").length) {  
                jQuery("#mphb_phone").attr("name", '');          
                var input = document.querySelector("#mphb_phone");
                var phoneField = window.intlTelInput(input, {
                    onlyCountries: ["al", "ad", "at", "by", "be", "ba", "bg", "hr", "cz", "dk",
                                    "ee", "fo", "fi", "fr", "de", "gi", "gr", "va", "hu", "is", "ie", "it", "lv",
                                    "li", "lt", "lu", "mk", "mt", "md", "mc", "me", "nl", "no", "pl", "pt", "ro",
                                    "ru", "sm", "rs", "sk", "si", "es", "se", "ch", "ua", "gb", "us"],
                                    utilsScript: "../../build/js/utils.js?1613236686837",
                        initialCountry: 'nl',
                        autoPlaceholder: 'on',
                        preferredCountries: ["nl"],
                        separateDialCode: false,
                        hiddenInput: "mphb_phone",
                        utilsScript: 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/16.0.8/js/utils.js'
                });
            }
        </script>
    <?php

}
add_action('wp_footer', 'phone_field');

