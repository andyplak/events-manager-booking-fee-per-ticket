<?php

class TicketDisplay {

    public function __construct() {

        // Booking form
        add_filter('em_booking_form_tickets_cols',           [$this, 'em_booking_form_tickets_cols'], 10, 2 );
        add_action('em_booking_form_tickets_col_bf_type',    [$this, 'em_booking_form_tickets_col_bf_type'] );
        add_action('em_booking_form_tickets_col_booking_fee', [$this, 'em_booking_form_tickets_col_booking_fee'] );
        add_action('em_booking_form_tickets_col_refundable', [$this, 'em_booking_form_tickets_col_refundable'] );

        // Store booking fee data in booking meta
        add_action('woocommerce_checkout_update_order_meta', [$this, 'woocommerce_checkout_update_order_meta'], 20, 1);

        // WC Cart & Checkout
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
        // Check event is setup for Booking Fees?
        {
            $spaces = $columns['spaces'];
            unset( $columns['price'] );
            unset( $columns['spaces'] );
            unset( $columns['type'] );
            $columns['bf_type']     = __('Ticket Type', 'events-manager');
            $columns['refundable']  = __('Ticket Price','events-manager');
            $columns['booking_fee'] = __('Booking Fee','events-manager');
            $columns['price']       = __('Total Price','events-manager');
            $columns['spaces']      = $spaces;
        }
        return $columns;
    }

    /**
     * This replaces the default type column in the ticket list.
     * Allows us to add extra info at the end of the description
     */
    public function em_booking_form_tickets_col_bf_type($EM_Ticket) {
        $fee_info = '';
        if( $fee = $this->get_booking_fee( $EM_Ticket ) ) {
            $fee_info = '<br /><span class="booking-fee-xs-summary">(includes '
                .$EM_Ticket->format_price( $fee ).' non refundable Booking Fee)</span>';
        }
        ?>
        <td class="em-bookings-ticket-table-type">
            <strong><?php echo wp_kses_data($EM_Ticket->ticket_name); ?></strong>
            <?php if(!empty($EM_Ticket->ticket_description)) :?><br>
                <span class="ticket-desc"><?php echo $EM_Ticket->ticket_description ?></span>
            <?php endif; ?>
            <?php echo $fee_info ?>
        </td>
        <?php
    }

    public function em_booking_form_tickets_col_booking_fee($EM_Ticket) {
        if( $fee = $this->get_booking_fee( $EM_Ticket ) ) {
            ?>
            <td class="em-bookings-ticket-table-booking_fee">
                <?php echo $EM_Ticket->format_price( $fee ) ?>
            </td>
            <?php
        }else{
            echo '<td class="em-bookings-ticket-table-booking_fee"></td>';
        }
    }

    public function em_booking_form_tickets_col_refundable($EM_Ticket) {
        $fee = $this->get_booking_fee( $EM_Ticket );
        $price = $EM_Ticket->get_price();
        $refundable = $price - $fee;
        ?>
        <td class="em-bookings-ticket-table-refundable">
            <?php echo $EM_Ticket->format_price( $refundable ) ?>
        </td>
        <?php
    }

    // Save booking fee totals into booking_meta after checkout
    public function woocommerce_checkout_update_order_meta( $order_id ) {

        // We need to store the totals in an array per booking,
        // as a single order 'could' contain line items from different bookings
        $fee_totals = [];
        $order = wc_get_order($order_id);

        foreach ( $order->get_items() as $item ) {

            if( class_exists( '\Events_Manager_WooCommerce\Product' ) ) {

                $is_event_ticket = Events_Manager_WooCommerce\Product::is_event_ticket( $item->get_product() );

                if( $is_event_ticket ) {
                    $EM_Ticket = new EM_Ticket( $is_event_ticket['ticket_id'] );
                    if( $EM_Ticket ) {
                        // Check if ticket has booking fee
                        if( $fee = $this->get_booking_fee( $EM_Ticket ) ) {
                            $fee = $fee * $item->get_quantity();
                            if( isset( $fee_totals[ $item->get_meta('_em_booking_id') ] ) ) {
                                $fee_totals[ $item->get_meta('_em_booking_id') ] += $fee;
                            }else{
                                $fee_totals[ $item->get_meta('_em_booking_id') ] = $fee;
                            }
                        }
                    }
                }
            }
        }

        foreach( $fee_totals as $booking_id => $fee_total ) {
            if( $fee_total > 0 ) {
                $EM_Booking = em_get_booking( $booking_id );
                $EM_Booking->update_meta( 'booking_fee_total', $fee_total );
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

            // check ticket has booking fee enabled
            if( $EM_Ticket ) {
                if( $fee = $this->get_booking_fee( $EM_Ticket ) ) {
                    $fee = $fee * $cart_item['quantity'];
                    $subtotal .= '&nbsp;<small>(includes '.wc_price( $fee ).' non refundable booking fee)</small>';
                }
            }
        }
        return $subtotal;
    }

    public function woocommerce_totals_before_order_total() {
        $total_fee = 0;

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if( isset( $cart_item['_em_ticket_id'] ) ) {
                $EM_Ticket = new EM_Ticket( $cart_item['_em_ticket_id'] );
                if( $EM_Ticket ) {
                    if( $fee = $this->get_booking_fee( $EM_Ticket ) ) {
                        $total_fee += ( $fee * $cart_item['quantity'] );
                    }
                }
            }
        }

        if( $total_fee > 0 ) {
            ?>
            <tr class="booking-fee-total">
                <th>Includes non refundable Booking Fee</th>
                <td data-title="Includes non refundable Booking Fee">
                    <span class="woocommerce-Price-amount amount"><bdi><?php echo wc_price( $total_fee ) ?></bdi></span>
                </td>
            </tr>
            <?php
        }
    }

    public function woocommerce_order_item_meta_end( $item_id, $item, $order, $plain_text = false ) {
        if( $ticket_id = $item->get_meta('_em_ticket_id') ) {
            $EM_Ticket  = new EM_Ticket( $ticket_id );

            if( $EM_Ticket ) {
                if( $fee = $this->get_booking_fee( $EM_Ticket ) ) {
                    $fee = $fee * $item->get_quantity();
                    echo '<br /><em>Includes '.wc_price( $fee ).' non refundable Booking Fee</em>';
                }
            }
        }
    }

    public function woocommerce_admin_order_item_headers( $order ) {
        echo '<th class="booking_fee sortable" data-sort="float">Booking Fee</th>';
    }

    public function woocommerce_admin_order_item_values( $product, $item, $item_id ) {
        $fee = '';
        if( $product && ( $ticket_id = $item->get_meta('_em_ticket_id') ) ) {
            $EM_Ticket = new EM_Ticket( $ticket_id );
            if( $EM_Ticket ) {
                if( $fee = $this->get_booking_fee( $EM_Ticket ) ) {
                    $fee = wc_price( $fee );
                }
            }
        }
        echo '<td>'.$fee.'</td>';
    }

    private function get_booking_fee( $EM_Ticket ) {
        return ( isset( $EM_Ticket->ticket_meta['booking_fee'] ) && $EM_Ticket->ticket_meta['booking_fee'] > 0 ? $EM_Ticket->ticket_meta['booking_fee'] : null );
    }

}