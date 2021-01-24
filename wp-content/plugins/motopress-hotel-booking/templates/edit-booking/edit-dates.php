<?php

/**
 * Available variables:
 *     string $actionUrl
 *     string $nextStep
 *     string $checkInDate Date in human-readable format.
 *     string $checkOutDate Date in human-readable format.
 *
 * @since 3.8
 */

if (!defined('ABSPATH')) {
    exit;
}

$dateFormat = MPHB()->settings()->dateTime()->getDateFormatJS();

?>
<div class="mphb-search-form-wrapper">
    <form class="mphb-search-form" action="<?php echo esc_attr($actionUrl); ?>" method="POST">
        <input type="hidden" name="step" value="<?php echo esc_attr($nextStep); ?>">

        <h2><?php _e('Edit Dates', 'motopress-hotel-booking'); ?></h2>
		<p class="description"><?php _e('Choose new dates to check availability of reserved accommodations in the original booking.', 'motopress-hotel-booking'); ?></p>

        <p class="mphb-check-in-date">
            <label for="check_in_date">
                <?php _e('Check-in', 'motopress-hotel-booking'); ?>
                <abbr title="<?php printf(_x('Formatted as %s', 'Date format tip', 'motopress-hotel-booking'), $dateFormat); ?>">*</abbr>
            </label>
            <br>
            <input type="text" id="mphb_check_in_date" name="check_in_date" class="mphb-datepick" value="<?php echo esc_attr($checkInDate); ?>" placeholder="<?php _e('Check-in Date', 'motopress-hotel-booking'); ?>" required="required" autocomplete="off">
        </p>

        <p class="mphb-check-out-date">
            <label for="check_out_date">
                <?php _e('Check-out', 'motopress-hotel-booking'); ?>
                <abbr title="<?php printf(_x('Formatted as %s', 'Date format tip', 'motopress-hotel-booking'), $dateFormat); ?>">*</abbr>
            </label>
            <br>
            <input type="text" id="mphb_check_out_date" name="check_out_date" class="mphb-datepick" value="<?php echo esc_attr($checkOutDate); ?>" placeholder="<?php _e('Check-out Date', 'motopress-hotel-booking'); ?>" required="required" autocomplete="off">
        </p>

        <p class="mphb-submit-button-wrapper">
            <input type="submit" name="change-dates" class="button" value="<?php _e('Check Availability', 'motopress-hotel-booking'); ?>">
        </p>
    </form>
</div>
<hr/>
<?php
