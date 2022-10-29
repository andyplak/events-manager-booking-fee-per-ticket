<?php

class TicketAdmin {

    public function __construct() {
        add_action('em_event_edit_ticket_td', [$this, 'em_event_edit_ticket_td'] );
        add_action('em_ticket_edit_form_fields', [$this, 'em_ticket_edit_form_fields'], 10, 2 );
        add_action('em_ticket_save_pre', [$this, 'em_ticket_save_pre']);
    }

    public function em_ticket_save_pre( $EM_Ticket ) {
        foreach( $_REQUEST[ 'em_tickets' ] as $request_ticket ) {
            if( $request_ticket['ticket_id'] == $EM_Ticket->ticket_id ) {
                if( isset( $request_ticket[ 'ticket_booking_fee' ] ) ) {
                    $EM_Ticket->ticket_meta['booking_fee'] = absint( $request_ticket[ 'ticket_booking_fee' ] );
                }elseif( isset( $EM_Ticket->ticket_meta['booking_fee'] ) && $EM_Ticket->ticket_meta['booking_fee'] == 1 ) {
                    $EM_Ticket->ticket_meta['booking_fee'] = 0;
                }
            }
        }
    }

    public function em_event_edit_ticket_td( $EM_Ticket ) {
        if( $fee = $this->get_booking_fee( $EM_Ticket ) ) {
            echo '<td><small>Booking Fee: '.$EM_Ticket->format_price( $fee ).'</small></td>';
        }
    }

    public function em_ticket_edit_form_fields( $col_count, $EM_Ticket ) {
        ?>
        <div class="booking-fee">
            <label title="<?php esc_attr_e('If checked tickets will have info about the booking fee displayed to users.','events-manager'); ?>">
                <?php esc_html_e('Booking Fee','events-manager') ?>
            </label>
            <input type="text" value="<?php echo $this->get_booking_fee( $EM_Ticket ) ?>" name="em_tickets[<?php echo $col_count; ?>][ticket_booking_fee]" class="booking_fee" />
            <em><?php esc_html_e('Leave blank for no booking fee','events-manager') ?></em>
        </div>
        <?php
    }

    private function get_booking_fee( $EM_Ticket ) {
        return ( isset( $EM_Ticket->ticket_meta['booking_fee'] ) ? $EM_Ticket->ticket_meta['booking_fee'] : null );
    }
}