<?php

class OrderVatCorrectionManager {

    private $invoice_manager;
    private $payment_manager;
    private $wc_logger;

    public function __construct() {
        add_action('admin_menu', [$this, 'onAdminMenu'], 100 );

        $settings              = new \WC_XR_Settings();
        $this->invoice_manager = new \WC_XR_Invoice_Manager( $settings );
        $this->payment_manager = new WC_XR_Payment_Manager( $settings );
        $this->wc_logger       = wc_get_logger();
    }

    public function onAdminMenu() {
        add_submenu_page(
            'edit.php?post_type=event',
            'VAT Corrections',
            'VAT Corrections',
            'manage_options',
            'events-vat-corrections',
            [$this, 'correctVat']
            //[$this, 'resubmitToXero']
        );
    }

    public function correctVat() {

        $orders = $this->loadOrders();

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Item</th>
                    <th>Tax Class</th>
                    <th>Qty</th>
                    <th>Net</th>
                    <th>Tax</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
        <?php

        foreach( $orders as $order ) {
            $updated = false;
            $ticket_line_items_found = false;

            // Loop through each line item
            foreach ( $order->get_items() as $item ) {
                $item_data = $item->get_data();

                // Is this line item for a ticket event
                if( $item->get_meta('_em_ticket_id') && $item_data['tax_class'] == '' ) {
                    $ticket_line_items_found = true;
                    $product = $item->get_product();

                    // Calculations
                    $line_total   = number_format( $item_data['subtotal'] + $item_data['subtotal_tax'], 2 );
                    $subtotal     = $line_total / 1.125;
                    $subtotal_tax = $line_total - $subtotal;

                    // after discount
                    $line_total   = number_format( $item_data['total'] + $item_data['total_tax'], 2 );
                    $total     = $line_total / 1.125;
                    $total_tax = $line_total - $total;

                    ?>
                    <tr>
                        <td><strong>Order #<?php echo $order->get_id() ?></strong></td>
                        <td><?php echo str_replace( '<br>', ' - ', $item_data['name'] ) ?></td>
                        <td><?php echo $item_data['tax_class'] ?></td>
                        <td><?php echo $item_data['quantity'] ?></td>
                        <td><?php echo $item_data['total'] ?></td>
                        <td><?php echo $item_data['total_tax'] ?></td>
                        <td><?php echo $item_data['total'] + $item_data['total_tax'] ?></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>Correction:</td>
                        <td><?php echo $product->get_tax_class() ?></td>
                        <td><?php echo $item_data['quantity'] ?></td>
                        <td><?php echo $total ?></td>
                        <td><?php echo $total_tax ?></td>
                        <td><?php echo $total + $total_tax ?></td>
                    </tr>
                    <?php

                    if( !$this->isDryRun() ) {

                        // Update order item values and save

                        $taxes = $item->get_taxes();
                        $taxes['subtotal'][4] = $subtotal_tax;
                        $taxes['total'][4] = $total_tax;
                        unset( $taxes['subtotal'][2] );
                        unset( $taxes['total'][2] );

                        $item->set_tax_class( $product->get_tax_class() );
                        $item->set_subtotal( $subtotal );
                        $item->set_subtotal_tax( $subtotal_tax );
                        $item->set_total( $total );
                        $item->set_total_tax( $total_tax );
                        $item->set_taxes( $taxes );

                        $item->save();
                        $updated = true;

                        // Log changes
                        $message = "Line Item Tax Correction:\n";
                        $message.= "Order #".$order->get_id().", Item: ".str_replace( '<br>', ' - ', $item_data['name'] ) ."\n";
                        $message.= "Before - total: ".$item_data['total'].", tax: ".$item_data['total_tax']."\n";
                        $message.= "After  - total: ".$total.", tax: ".$total_tax."\n";
                        $this->wc_logger->add( 'vat-correction', $message );
                    }
                }

            }

            // Handle discount coupon line items
            if( $ticket_line_items_found ) {
                // Only process coupons for orders where we're updating the ticket line items
                foreach ( $order->get_items( 'coupon' ) as $item ) {

                    $item_data = $item->get_data();

                    if( $item_data['code'] == 'cqvip22' ) {
                        // Calculations
                        $line_total   = number_format( $item_data['discount'] + $item_data['discount_tax'], 2 );
                        $discount     = $line_total / 1.125;
                        $discount_tax = $line_total - $discount;

                        ?>
                        <tr>
                            <td><strong>Order #<?php echo $order->get_id() ?></strong></td>
                            <td>Coupon <?php echo $item_data['code'] ?></td>
                            <td></td>
                            <td></td>
                            <td><?php echo $item_data['discount'] ?></td>
                            <td><?php echo $item_data['discount_tax'] ?></td>
                            <td><?php echo $item_data['discount'] + $item_data['discount_tax'] ?></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td>Correction:</td>
                            <td></td>
                            <td></td>
                            <td><?php echo $discount ?></td>
                            <td><?php echo $discount_tax ?></td>
                            <td><?php echo $discount + $discount_tax ?></td>
                        </tr>
                        <?php

                        if( !$this->isDryRun() ) {
                            $item->set_discount( $discount );
                            $item->set_discount_tax( $discount_tax );
                            $item->save();
                            $updated = true;

                            // Log changes
                            $message = "Line Item Tax Correction:\n";
                            $message.= "Order #".$order->get_id().", Item: Coupon ". $item_data['code'] ."\n";
                            $message.= "Before - discount: ".$item_data['discount'].", discount tax: ".$item_data['discount_tax']."\n";
                            $message.= "After  - discount: ".$discount.", discount tax: ".$discount_tax."\n";
                            $this->wc_logger->add( 'vat-correction', $message );
                        }
                    }
                }
            }


            if( $updated ) {

                $this->update_taxes( $order );

                $order->add_order_note( 'Tax corrections applied to Event Tickets and Order Tax line items.');

                $this->updateXero( $order );

                //$this->sendInvoiceUpdatedEmail( $order );
                die; // One at a time until we're confident
            }

        }
        echo '</tbody></table>';
    }

    public function resubmitToXero() {

        if( isset( $_REQUEST['id'] ) ) {
            $order = wc_get_order( $_REQUEST['id'] );
            $this->updateXero( $order );
            echo 'Order '.$_REQUEST['id'].' resubmitted to Xero</li>';
        }else{

            $order_ids = [

            ];

            foreach( $order_ids as $id ) {
                $order = wc_get_order( $id );
                $this->updateXero( $order );
                echo '<li>Order '.$id.' resubmitted to Xero</li>';
                sleep(5);
            }
        }
    }

    private function loadOrders() {

        $orders = [];

        if( isset( $_REQUEST['start'] ) && isset( $_REQUEST['end'] ) ) {
            $start = sanitize_text_field( $_REQUEST['start'] );
            $end   = sanitize_text_field( $_REQUEST['end'] );

            // End date
            $orders = wc_get_orders( array(
                'date_paid' => $start.'...'.$end,
                'limit' => -1,
            ) );
        }

        return $orders;
    }

    /**
    * Update tax lines for the order based on the line item taxes themselves.
    * Lifted from WC_Abstract_Order class, but with raw SQL placed in where data-store had no reference to the tax line item
    */
    private function update_taxes( $order ) {
        global $wpdb;

        $cart_taxes     = array();
        $shipping_taxes = array();
        $existing_taxes = $order->get_taxes();
        $saved_rate_ids = array();

        foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item_id => $item ) {
            $taxes = $item->get_taxes();
            foreach ( $taxes['total'] as $tax_rate_id => $tax ) {
                $tax_amount = (float) $tax; //$order->round_line_tax( $tax, false );

                $cart_taxes[ $tax_rate_id ] = isset( $cart_taxes[ $tax_rate_id ] ) ? (float) $cart_taxes[ $tax_rate_id ] + $tax_amount : $tax_amount;
            }
        }

        foreach ( $order->get_shipping_methods() as $item_id => $item ) {
            $taxes = $item->get_taxes();
            foreach ( $taxes['total'] as $tax_rate_id => $tax ) {
                $tax_amount = (float) $tax;

                if ( 'yes' !== get_option( 'woocommerce_tax_round_at_subtotal' ) ) {
                    $tax_amount = wc_round_tax_total( $tax_amount );
                }

                $shipping_taxes[ $tax_rate_id ] = isset( $shipping_taxes[ $tax_rate_id ] ) ? $shipping_taxes[ $tax_rate_id ] + $tax_amount : $tax_amount;
            }
        }

        foreach ( $existing_taxes as $tax ) {
            // Remove taxes which no longer exist for cart/shipping.
            if ( ( ! array_key_exists( $tax->get_rate_id(), $cart_taxes ) && ! array_key_exists( $tax->get_rate_id(), $shipping_taxes ) ) || in_array( $tax->get_rate_id(), $saved_rate_ids, true ) ) {

                // Issue us here. !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                // Item is not in data store
                #$order->remove_item( $tax->get_id() );

                // Just do raw SQL here instead?
                $wpdb->delete( $wpdb->prefix . 'woocommerce_order_items', array( 'order_item_id' => $tax->get_id() ) );
                $wpdb->delete( $wpdb->prefix . 'woocommerce_order_itemmeta', array( 'order_item_id' => $tax->get_id() ) );

                $this->wc_logger->add( 'vat-correction', 'Removing tax line item for tax rate #'.$tax->get_rate_id() );
                continue;
            }

            $saved_rate_ids[] = $tax->get_rate_id();
            $tax->set_rate( $tax->get_rate_id() );
            $tax->set_tax_total( isset( $cart_taxes[ $tax->get_rate_id() ] ) ? $cart_taxes[ $tax->get_rate_id() ] : 0 );
            $tax->set_label( WC_Tax::get_rate_label( $tax->get_rate_id() ) );
            $tax->set_shipping_tax_total( ! empty( $shipping_taxes[ $tax->get_rate_id() ] ) ? $shipping_taxes[ $tax->get_rate_id() ] : 0 );
            $tax->save();
        }

        $new_rate_ids = wp_parse_id_list( array_diff( array_keys( $cart_taxes + $shipping_taxes ), $saved_rate_ids ) );

        // New taxes.
        foreach ( $new_rate_ids as $tax_rate_id ) {
            $item = new WC_Order_Item_Tax();
            $item->set_rate( $tax_rate_id );
            $item->set_tax_total( isset( $cart_taxes[ $tax_rate_id ] ) ? $cart_taxes[ $tax_rate_id ] : 0 );
            $item->set_shipping_tax_total( ! empty( $shipping_taxes[ $tax_rate_id ] ) ? $shipping_taxes[ $tax_rate_id ] : 0 );
            $order->add_item( $item );
            $this->wc_logger->add( 'vat-correction', 'Adding new tax line item for tax rate #'.$tax_rate_id );
        }

        $order->set_shipping_tax( array_sum( $shipping_taxes ) );
        $order->set_cart_tax( array_sum( $cart_taxes ) );

        $order->save();
    }

    /**
     * Send invoice and payments to Xero after nullifying _xero_invoice_id (these have all been voided)
     * Create new invoice with -2 appended to the order / invoice number.
     */
    private function updateXero( $order ) {

        $order_id = version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();

        // Unset meta to detach from voided invoice
        $order->delete_meta_data( '_xero_invoice_id' );

        add_filter( 'woocommerce_order_number', [$this, 'onWoocommerceOrderNumber'] );
        $this->invoice_manager->send_invoice( $order_id );
        $this->payment_manager->send_payment( $order_id );
        remove_filter( 'woocommerce_order_number', [$this, 'onWoocommerceOrderNumber'] );
    }

    public function onWooCommerceOrderNumber( $order_id ) {
        return $order_id.'-2';
    }

    private function isDryRun() {
        return !isset( $_REQUEST['commit_changes'] );
    }

    private function sendInvoiceUpdatedEmail( $order ) {

    }

}
