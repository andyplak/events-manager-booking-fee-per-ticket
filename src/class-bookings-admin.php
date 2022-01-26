<?php

class BookingsAdmin {

    public function __construct() {
        add_filter( 'em_bookings_table_cols_template', [$this, 'em_bookings_table_cols_template'] );
        add_filter( 'em_bookings_table_rows_col_covid_bond_total', [$this, 'em_bookings_table_rows_col_covid_bond_total'], 20, 5);
        add_filter( 'woocommerce_quantity_input_step_admin', [$this, 'woocommerce_quantity_input_step_admin'], 10, 3 );
        add_action( 'admin_head', [$this, 'admin_css'], 100 );
    }

    public function em_bookings_table_cols_template( $cols ) {
        $cols['covid_bond_total'] = __('CIP Total','events-manager');
        return $cols;
    }

    public function em_bookings_table_rows_col_covid_bond_total( $val, $EM_Booking, $em_bookings_table, $format, $object ) {
        if( isset( $EM_Booking->booking_meta['covid_bond_total'] ) ) {
            return $EM_Booking->format_price( $EM_Booking->booking_meta['covid_bond_total'] );
        }
        return $val;
    }

    public function woocommerce_quantity_input_step_admin( $step, $product, $type ) {
        if( $type == 'refund' ) {
            $event = Events_Manager_WooCommerce\Product::is_event_ticket( $product );
            if( $event ) {
                $EM_Ticket = new EM_Ticket( $event['ticket_id'] );
                if( $EM_Ticket ) {
                    // Check if ticket has covid bond
                    if( isset( $EM_Ticket->ticket_meta['covid_bond'] ) && $EM_Ticket->ticket_meta['covid_bond'] ) {
                        return 0.9;
                    }
                }
            }

        }
        return $step;
    }

    function admin_css() {
        ?>
        <style>
            .refund_line_total,
            .refund_line_tax {
                pointer-events: none;
                background-color: #f0f0f1 !important;
            }
        </style>';
        <?php
    }
}

