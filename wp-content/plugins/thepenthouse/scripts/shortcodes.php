<?php
function tph_coupon_shortcode($atts)
{
    if (!isset($atts['changeprices'])) {
        tph_show_coupon_text();
    } else {
        tph_show_coupon_details();
    }
}

function tph_show_coupon_text()
{
    foreach ($_POST['mphb_rooms_details'] as $roomType => $item) {
        $checkin = new DateTime($_POST['mphb_check_in_date']);
        $checkout = new DateTime($_POST['mphb_check_out_date']);

        $days = $checkout->diff($checkin)->format("%a");
        $text = "";

        $coupons = get_posts([
            'post_type'  => 'mphb_coupon',
            'posts_per_page' => -1,
            'post_status' => 'published'
        ]);

        foreach ($coupons as $coupon) {
            $allowedRooms = get_post_meta($coupon->ID, '_mphb_include_room_types', true);
            if (in_array($roomType, $allowedRooms)) {
                $minNights = get_post_meta($coupon->ID, '_mphb_min_nights', true);
                $maxNights = get_post_meta($coupon->ID, '_mphb_max_nights', true);
                if ($days >= $minNights && $days <= $maxNights) {
                    $text = get_the_title($coupon->ID);
                    break;
                }
            }
        }
        if (!empty($text)) { ?>
            <script type="text/javascript">
                jQuery(document).ready(function() {
                    jQuery("label.mphb-coupon-code-title").append('<span style="margin: 0; font-size: 14px; color: #f25f5c; font-weight: 600;text-transform: uppercase;text-decoration: none;"><?php echo $text; ?></span>');
                    jQuery("#mphb_coupon_code").val("<?php echo $text; ?>").prop("placeholder", "<?php echo $text; ?>");
                    setTimeout(function(){ jQuery(".mphb-apply-coupon-code-button").trigger("click"); }, 1000);
                });
            </script><?php
        }
        break;
    }
}

function tph_show_coupon_details()
{
    if (isset($_GET['mphb_check_in_date']) && isset($_GET['mphb_check_out_date'])) {        
        $checkin = new DateTime($_GET['mphb_check_in_date']);
        $checkout = new DateTime($_GET['mphb_check_out_date']);

        $days = $checkout->diff($checkin)->format("%a");
        $coupons = get_posts([
            'post_type'  => 'mphb_coupon',
            'posts_per_page' => -1,
            'post_status' => 'published'
        ]);

        $aceptedCupon = null;
        foreach ($coupons as $coupon) {          
            $minNights = get_post_meta($coupon->ID, '_mphb_min_nights', true);
            $maxNights = get_post_meta($coupon->ID, '_mphb_max_nights', true);
            if ($days >= $minNights && $days <= $maxNights) {
                $aceptedCupon = $coupon;
                break;
            }
        }
        if (!empty($aceptedCupon)) {
            $allowedRooms = get_post_meta($aceptedCupon->ID, '_mphb_include_room_types', true);
            $discount = get_post_meta($aceptedCupon->ID, '_mphb_amount', true); ?>

            <input type="hidden" id="tph_coupon_percent" value="<?php echo $discount;?>"/>
            <input type="hidden" id="tph_coupon_accommodations" value="<?php echo implode(',',$allowedRooms);?>"/>
            <script>
                jQuery(document).ready(function() {
                    const numberFormat = new Intl.NumberFormat('en-US');
                    let percent = parseInt(jQuery('#tph_coupon_percent').val());
                    let accommodations = jQuery('#tph_coupon_accommodations').val().split(",");
                    for (let i = 0; i < accommodations.length; i++) {
                        let accommodation = accommodations[i];
                        let item = jQuery('.mphb-room-type.post-'+accommodation).find('.mphb-price');
                        if (item) {
                            let currency = jQuery(item).find('.mphb-currency').text();
                            let price = parseInt(jQuery(item).text().replace(currency, '').replace(",", ""));
                            let discount = parseInt(percent * price / 100);
                            price = price - discount;
                            jQuery(item).html(`<span class="mphb-currency">${currency}</span>${numberFormat.format(price)}`);
                        }
                    }
                });
            </script> <?php
        }
    }
    
}

add_shortcode('tph_coupon', 'tph_coupon_shortcode');
