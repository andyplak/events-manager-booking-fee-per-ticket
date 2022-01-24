<?php

class BookingsAdmin {

    public function __construct() {
        add_filter( 'em_bookings_table_cols_template', [$this, 'em_bookings_table_cols_template'] );
        add_filter( 'em_bookings_table_rows_col_covid_bond_total', [$this, 'em_bookings_table_rows_col_covid_bond_total'], 20, 5);
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
}

