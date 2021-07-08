<?php
function tph_coupon_shortcode() 
{
    $roomType = !empty($_POST['mphb_rooms_details']) ? array_key_first($_POST['mphb_rooms_details']) : null;

    if (!empty($roomType)) {           
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
                $minNights = get_post_meta( $coupon->ID, '_mphb_min_nights', true );
                $maxNights = get_post_meta( $coupon->ID, '_mphb_max_nights', true );
                if ($days >= $minNights && $days <= $maxNights) {
                    $text = get_the_title( $coupon->ID );
                    break;
                }
            }
        }
        if (!empty($text)) {
            ?>    
            <script type="text/javascript">
                jQuery(document).ready(function(){
                    jQuery("label.mphb-coupon-code-title").append('<span style="margin: 0; font-size: 14px; color: #f25f5c; font-weight: 600;text-transform: uppercase;text-decoration: none;"><?php echo $text;?></span>');
                });        
            </script>
            <?php
        }
    }
 }
 add_shortcode( 'tph_coupon', 'tph_coupon_shortcode' );