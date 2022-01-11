<?php

class TicketDisplay {

    const COVID_BOND_PERCENTAGE = 10;

    public function __construct() {
        // Booking form
        add_filter('em_booking_form_tickets_cols', [$this, 'em_booking_form_tickets_cols'], 10, 2);
        add_action('em_booking_form_tickets_col_covid_bond', [$this, 'em_booking_form_tickets_col_covid_bond'], 10, 2);
        add_action('em_booking_form_tickets_col_refundable', [$this, 'em_booking_form_tickets_col_refundable'], 10, 2);

        // WC Cart & Checkout
        #add_action( 'woocommerce_after_cart_item_name', [$this, 'woocommerce_after_cart_item_name'], 10, 2 );
        #add_filter( 'woocommerce_cart_item_price', [$this, 'woocommerce_cart_item_price'], 10, 3 );

        #add_filter( 'woocommerce_cart_item_name', [$this, 'woocommerce_cart_item_name'], 10, 3 );
        add_filter( 'woocommerce_cart_item_subtotal', [$this, 'woocommerce_cart_item_subtotal'], 10, 3 );

        // WC Order Summary (inc emails)
        add_action('woocommerce_order_item_meta_end', [$this, 'woocommerce_order_item_meta_end'], 20, 4);

        // WC Admin order summary
        add_action( 'woocommerce_admin_order_item_headers', [$this, 'woocommerce_admin_order_item_headers'] );
        add_action( 'woocommerce_admin_order_item_values', [$this, 'woocommerce_admin_order_item_values'], 10, 3);
    }

    public function em_booking_form_tickets_cols( $columns, $EM_Event ) {
        // Check event is setup for Covid Bonds?
        {
            $spaces = $columns['spaces'];
            unset( $columns['spaces'] );
            $columns['refundable'] = __('Refundable Portion','events-manager');
            $columns['covid_bond'] = __('Covid Bond','events-manager');
            $columns['spaces'] = $spaces;
        }
        return $columns;
    }

    public function em_booking_form_tickets_col_covid_bond($EM_Ticket, $EM_Event) {
        if( $this->has_covid_bond( $EM_Ticket ) ) {
            $price = $EM_Ticket->get_price();
            $bond = $price / Self::COVID_BOND_PERCENTAGE;

            ?>
            <td class="em-bookings-ticket-table-covid-bond">
                <?php echo $EM_Ticket->format_price( $bond ) ?>
            </td>
            <?php
        }else{
            echo '<td></td>';
        }
    }

    public function em_booking_form_tickets_col_refundable($EM_Ticket, $EM_Event) {
        if( $this->has_covid_bond( $EM_Ticket ) ) {
            $price = $EM_Ticket->get_price();
            $refundable = ( $price / Self::COVID_BOND_PERCENTAGE ) * 9;

            ?>
            <td class="em-bookings-ticket-table-refundable">
                <?php echo $EM_Ticket->format_price( $refundable ) ?>
            </td>
            <?php
        }else{
            echo '<td></td>';
        }
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
        if( isset( $cart_item['_em_ticket_id'] ) ) {
            $product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
            $EM_Ticket = new EM_Ticket( $cart_item['_em_ticket_id'] );

            // check ticket has covid bond enabled
            if( $EM_Ticket && $this->has_covid_bond( $EM_Ticket ) ) {
                $bond    = ( $product->get_price() * $cart_item['quantity'] ) / Self::COVID_BOND_PERCENTAGE;
                $subtotal .= '&nbsp;<small>(includes '.wc_price( $bond ).' non-refundable covid bond)</small>';
            }
        }
        return $subtotal;
    }

    public function woocommerce_order_item_meta_end( $item_id, $item, $order, $plain_text = false ) {
        if( $ticket_id = $item->get_meta('_em_ticket_id') ) {
            $EM_Ticket  = new EM_Ticket( $ticket_id );
            if( $EM_Ticket && $this->has_covid_bond( $EM_Ticket ) ) {
                #$booking_id = $item->get_meta('_em_booking_id');
                #$EM_Booking = em_get_booking( $booking_id );
                #$ticket_bookings = $EM_Booking->get_tickets_bookings();
                $price = $EM_Ticket->get_price();
                $bond  = ( $price * $item->get_quantity() ) / Self::COVID_BOND_PERCENTAGE;
                echo '<em>Includes '.wc_price( $bond ).' non-refundable covid bond per ticket</em>';
            }
        }
    }

    public function woocommerce_admin_order_item_headers( $order ) {
        echo '<th class="covid_bond sortable" data-sort="float">CB</th>';
    }

    public function woocommerce_admin_order_item_values( $product, $item, $item_id ) {
        $bond = '';
        if( $product && ( $ticket_id = $item->get_meta('_em_ticket_id') ) ) {
            $EM_Ticket = new EM_Ticket( $ticket_id );
            if( $EM_Ticket && $this->has_covid_bond( $EM_Ticket ) ) {
                _dump($item);
                $subtotal = $item->get_order()->get_item_subtotal( $item, false, true );
                $bond  = $subtotal / Self::COVID_BOND_PERCENTAGE;
                $bond = wc_price( $bond );
            }
        }
        echo '<td>'.$bond.'</td>';
    }

    private function has_covid_bond( $EM_Ticket ) {
        return ( isset( $EM_Ticket->ticket_meta['covid_bond'] ) && $EM_Ticket->ticket_meta['covid_bond'] ? true : false );
    }
}