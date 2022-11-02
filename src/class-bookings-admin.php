<?php

class BookingsAdmin {

    public function __construct() {
        add_filter( 'em_bookings_table_cols_template', [$this, 'em_bookings_table_cols_template'] );
        add_filter( 'em_bookings_table_rows_col_booking_fee_total', [$this, 'em_bookings_table_rows_col_booking_fee_total'], 20, 5);
        #add_filter( 'woocommerce_quantity_input_step_admin', [$this, 'woocommerce_quantity_input_step_admin'], 10, 3 );
        #add_action( 'admin_head', [$this, 'admin_css'], 100 );
    }

    public function em_bookings_table_cols_template( $cols ) {
        $cols['booking_fee_total'] = __('Booking Fee Total','events-manager');
        return $cols;
    }

    public function em_bookings_table_rows_col_booking_fee_total( $val, $EM_Booking, $em_bookings_table, $format, $object ) {
        if( isset( $EM_Booking->booking_meta['booking_fee_total'] ) ) {
            return $EM_Booking->format_price( $EM_Booking->booking_meta['booking_fee_total'] );
        }
        return $val;
    }

#    public function woocommerce_quantity_input_step_admin( $step, $product, $type ) {
#        if( $type == 'refund' ) {
#
#            $event = Events_Manager_WooCommerce\Product::is_event_ticket( $product );
#            if( $event ) {
#                $EM_Ticket = new EM_Ticket( $event['ticket_id'] );
#                if( $EM_Ticket ) {
#                    // Check if ticket has booking fee
#                    if( isset( $EM_Ticket->ticket_meta['booking_fee'] ) && $EM_Ticket->ticket_meta['booking_fee'] > 0 ) {
#                        return $EM_Ticket->ticket_meta['booking_fee'];
#                    }
#                }
#            }
#
#        }
#        return $step;
#    }

/*
#    function admin_css() {
#        ?>
#        <style>
#            .refund_line_total,
#            .refund_line_tax {
#                pointer-events: none;
#                background-color: #f0f0f1 !important;
#            }
#        </style>';
#        <?php
#    }
*/
}

