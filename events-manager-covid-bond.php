<?php

/**
 * Plugin Name: Events Manager Covid Bonds
 * Plugin URI: https://github.com/andyplak/events-manager-covid-bonds
 * Description: Addition of Covid Bonds to Events Manager Tickets
 * Version: 1.1
 * Author: Andy Place
 * Author URI: http://www.andyplace.co.uk/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

function ad_ac_init() {
    require plugin_dir_path( __FILE__ ) . 'src/class-ticket-display.php';
    new TicketDisplay();

    if( is_admin() ) {
        require plugin_dir_path( __FILE__ ) . 'src/class-bookings-admin.php';
        require plugin_dir_path( __FILE__ ) . 'src/class-ticket-admin.php';

        new BookingsAdmin();
        new TicketAdmin();
    }
}
ad_ac_init();