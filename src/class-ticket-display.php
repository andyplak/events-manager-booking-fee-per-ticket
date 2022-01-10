<?php

class TicketDisplay {

    const COVID_BOND_PERCENTAGE = 10;

    public function __construct() {
        // Booking form
        add_filter('em_booking_form_tickets_cols', [$this, 'em_booking_form_tickets_cols'], 10, 2);
        add_action('em_booking_form_tickets_col_covid_bond', [$this, 'em_booking_form_tickets_col_covid_bond'], 10, 2);

        // WC Cart & Checkout
        #add_action( 'woocommerce_after_cart_item_name', [$this, 'woocommerce_after_cart_item_name'], 10, 2 );
        #add_filter( 'woocommerce_cart_item_price', [$this, 'woocommerce_cart_item_price'], 10, 3 );

        #add_filter( 'woocommerce_cart_item_name', [$this, 'woocommerce_cart_item_name'], 10, 3 );
        add_filter( 'woocommerce_cart_item_subtotal', [$this, 'woocommerce_cart_item_subtotal'], 10, 3 );

    }

    public function em_booking_form_tickets_cols( $columns, $EM_Event ) {
        // Check event is setup for Covid Bonds?
        {
            $spaces = $columns['spaces'];
            unset( $columns['spaces'] );
            $columns['covid_bond'] = __('Covid Bond','events-manager');
            $columns['spaces'] = $spaces;
        }
        return $columns;
    }

    public function em_booking_form_tickets_col_covid_bond($EM_Ticket, $EM_Event) {
        $price = $EM_Ticket->get_price();
        $bond = $price / Self::COVID_BOND_PERCENTAGE;

        ?>
        <td class="em-bookings-ticket-table-covid-bond">
            <?php echo $EM_Ticket->format_price( $bond ) ?>
        </td>
        <?php
    }

    #public function woocommerce_after_cart_item_name( $cart_item, $cart_item_key ) {
    #    // check ticket has covid bond enabled
    #    {
    #        echo '<div><em>Includes Non Refundbable Covid Bond</em></div>';
    #    }
    #}

    #public function woocommerce_cart_item_price( $price, $cart_item, $cart_item_key ) {
    #    // check ticket has covid bond enabled
    #    {
    #        $product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
    #        $bond    = $product->get_price() / Self::COVID_BOND_PERCENTAGE;
    #        $price  .= '<br /><em>'. wc_price( $bond ) .'</em>';
    #    }
    #    return $price;
    #}

    public function woocommerce_cart_item_subtotal( $subtotal, $cart_item, $cart_item_key ) {
        // check ticket has covid bond enabled
        {
            $product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
            $bond    = ( $product->get_price() * $cart_item['quantity'] ) / Self::COVID_BOND_PERCENTAGE;
            $subtotal .= '&nbsp;<small>(includes '.wc_price( $bond ).' non-refundable covid bond)</small>';
        }
        return $subtotal;
    }
}