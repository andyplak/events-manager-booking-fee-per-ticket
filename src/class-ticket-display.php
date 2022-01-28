<?php

class TicketDisplay {

    const COVID_BOND_PERCENTAGE = 10;

    public function __construct() {
        // Booking form
        add_filter('em_booking_form_tickets_cols',           [$this, 'em_booking_form_tickets_cols'], 10, 2 );
        add_action('em_booking_form_tickets_col_cb_type',    [$this, 'em_booking_form_tickets_col_cb_type'] );
        add_action('em_booking_form_tickets_col_covid_bond', [$this, 'em_booking_form_tickets_col_covid_bond'] );
        add_action('em_booking_form_tickets_col_refundable', [$this, 'em_booking_form_tickets_col_refundable'] );

        // Store covid bond data in booking meta
        add_action('woocommerce_checkout_update_order_meta', [$this, 'woocommerce_checkout_update_order_meta'], 20, 1);

        // WC Cart & Checkout
        #add_action( 'woocommerce_after_cart_item_name', [$this, 'woocommerce_after_cart_item_name'], 10, 2 );
        #add_filter( 'woocommerce_cart_item_price', [$this, 'woocommerce_cart_item_price'], 10, 3 );

        #add_filter( 'woocommerce_cart_item_name', [$this, 'woocommerce_cart_item_name'], 10, 3 );
        add_filter( 'woocommerce_cart_item_subtotal', [$this, 'woocommerce_cart_item_subtotal'], 10, 3 );
        add_filter( 'woocommerce_cart_totals_before_order_total', [$this, 'woocommerce_totals_before_order_total'] );
        add_filter( 'woocommerce_review_order_before_order_total', [$this, 'woocommerce_totals_before_order_total'] );

        // WC User Account Menu
        add_filter( 'woocommerce_account_menu_items', [$this, 'woocommerce_account_menu_items'], 30 );

        // WC Order Summary (inc emails)
        add_action('woocommerce_order_item_meta_end', [$this, 'woocommerce_order_item_meta_end'], 20, 4);

        // WC Admin order summary
        add_action( 'woocommerce_admin_order_item_headers', [$this, 'woocommerce_admin_order_item_headers'] );
        add_action( 'woocommerce_admin_order_item_values', [$this, 'woocommerce_admin_order_item_values'], 10, 3);

    }

    public function em_booking_form_tickets_cols( $columns, $EM_Event ) {
        // Check event is setup for Covid Bonds?
        {
            $price  = $columns['price'];
            $spaces = $columns['spaces'];
            unset( $columns['price'] );
            unset( $columns['spaces'] );
            unset( $columns['type'] );
            $columns['cb_type']    = __('Ticket Type', 'events-manager');
            $columns['refundable'] = __('Refundable Portion','events-manager');
            $columns['covid_bond'] = __('Non refundable CIP','events-manager');
            $columns['price']      = $price;
            $columns['spaces']     = $spaces;
        }
        return $columns;
    }

    /**
     * This replaces the default type column in the ticket list. Allows us to add extra info at the end of the description
     */
    public function em_booking_form_tickets_col_cb_type($EM_Ticket) {
        $bond_info = '';
        if( $this->has_covid_bond( $EM_Ticket ) ) {
            $price = $EM_Ticket->get_price();
            $bond = $price / Self::COVID_BOND_PERCENTAGE;
            $bond_info = '<br /><span class="covid-bond-xs-summary">(includes '
                .$EM_Ticket->format_price( $bond ).' non refundable CIP)</span>';
        }
        ?>
        <td class="em-bookings-ticket-table-type">
            <strong><?php echo wp_kses_data($EM_Ticket->ticket_name); ?></strong>
            <?php if(!empty($EM_Ticket->ticket_description)) :?><br>
                <span class="ticket-desc"><?php echo $EM_Ticket->ticket_description ?></span>
            <?php endif; ?>
            <?php echo $bond_info ?>
        </td>
        <?php
    }

    public function em_booking_form_tickets_col_covid_bond($EM_Ticket) {
        if( $this->has_covid_bond( $EM_Ticket ) ) {
            $price = $EM_Ticket->get_price();
            $bond = $price / Self::COVID_BOND_PERCENTAGE;

            ?>
            <td class="em-bookings-ticket-table-covid_bond">
                <?php echo $EM_Ticket->format_price( $bond ) ?>
            </td>
            <?php
        }else{
            echo '<td class="em-bookings-ticket-table-covid_bond"></td>';
        }
    }

    public function em_booking_form_tickets_col_refundable($EM_Ticket) {
        if( $this->has_covid_bond( $EM_Ticket ) ) {
            $price = $EM_Ticket->get_price();
            $refundable = ( $price / Self::COVID_BOND_PERCENTAGE ) * 9;

            ?>
            <td class="em-bookings-ticket-table-refundable">
                <?php echo $EM_Ticket->format_price( $refundable ) ?>
            </td>
            <?php
        }else{
            echo '<td class="em-bookings-ticket-table-refundable"></td>';
        }
    }


    #public function woocommerce_after_cart_item_name( $cart_item, $cart_item_key ) {
    #    // check ticket has covid bond enabled
    #    {
    #        echo '<div><em>Includes Non refundable CIP</em></div>';
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

    // Save CIP totals into booking_meta after checkout
    public function woocommerce_checkout_update_order_meta( $order_id ) {

        // We need to store the totals in an array per booking, as an single order 'could' contain line items from different bookings
        $bond_totals = [];
        $order = wc_get_order($order_id);

        foreach ( $order->get_items() as $item ) {
            $is_event_ticket = Events_Manager_WooCommerce\Product::is_event_ticket( $item->get_product() );

            if( $is_event_ticket ) {
                $EM_Ticket = new EM_Ticket( $is_event_ticket['ticket_id'] );
                if( $EM_Ticket ) {
                    // Check if ticket has covid bond
                    if( isset( $EM_Ticket->ticket_meta['covid_bond'] ) && $EM_Ticket->ticket_meta['covid_bond'] ) {
                        $bond = ( $item->get_total() + $item->get_total_tax() ) / TicketDisplay::COVID_BOND_PERCENTAGE;
                        if( isset( $bond_totals[ $item->get_meta('_em_booking_id') ] ) ) {
                            $bond_totals[ $item->get_meta('_em_booking_id') ] += $bond;
                        }else{
                            $bond_totals[ $item->get_meta('_em_booking_id') ] = $bond;
                        }
                    }
                }
            }
        }

        foreach( $bond_totals as $booking_id => $bond_total ) {
            if( $bond_total > 0 ) {
                $EM_Booking = em_get_booking( $booking_id );
                $EM_Booking->update_meta( 'covid_bond_total', $bond_total );
            }
        }
    }

    public function woocommerce_account_menu_items( $items ) {
        if( isset( $items['my-bookings'] ) ) {
            unset( $items['my-bookings'] );
        }
        return $items;
    }

    public function woocommerce_cart_item_subtotal( $subtotal, $cart_item, $cart_item_key ) {
        if( isset( $cart_item['_em_ticket_id'] ) ) {
            $product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
            $EM_Ticket = new EM_Ticket( $cart_item['_em_ticket_id'] );

            // check ticket has covid bond enabled
            if( $EM_Ticket && $this->has_covid_bond( $EM_Ticket ) ) {
                $bond    = ( $product->get_price() * $cart_item['quantity'] ) / Self::COVID_BOND_PERCENTAGE;
                $subtotal .= '&nbsp;<small>(includes '.wc_price( $bond ).' non refundable CIP)</small>';
            }
        }
        return $subtotal;
    }

    public function woocommerce_totals_before_order_total() {
        $total_bond = 0;

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if( isset( $cart_item['_em_ticket_id'] ) ) {
                $EM_Ticket = new EM_Ticket( $cart_item['_em_ticket_id'] );

                if( $EM_Ticket && $this->has_covid_bond( $EM_Ticket ) ) {
                    $total_bond += ( $cart_item['line_total'] + $cart_item['line_tax'] ) / Self::COVID_BOND_PERCENTAGE;
                }
            }
        }

        if( $total_bond > 0 ) {
            ?>
            <tr class="covid-bond-total">
                <th>Includes non refundable CIP</th>
                <td data-title="Includes non refundable CIP">
                    <span class="woocommerce-Price-amount amount"><bdi><?php echo wc_price( $total_bond ) ?></bdi></span>
                </td>
            </tr>
            <?php
        }
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
                echo '<br /><em>Includes '.wc_price( $bond ).' non refundable CIP</em>';
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