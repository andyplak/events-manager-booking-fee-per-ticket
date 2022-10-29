<?php

/**
 * Plugin Name: Events Manager - Ticket Booking Fees
 * Plugin URI: https://github.com/andyplak/events-manager-booking-fee-per-ticket
 * Description: Allows booking fees to be configured per ticket
 * Version: 2.0
 * Author: Andy Place
 * Author URI: http://www.andyplace.co.uk/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

function em_booking_fees_init() {
    require plugin_dir_path( __FILE__ ) . 'src/class-ticket-display.php';
    new TicketDisplay();

    if( is_admin() ) {
        require plugin_dir_path( __FILE__ ) . 'src/class-bookings-admin.php';
        require plugin_dir_path( __FILE__ ) . 'src/class-ticket-admin.php';

        new BookingsAdmin();
        new TicketAdmin();
    }
}
em_booking_fees_init();