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
                if( isset( $request_ticket[ 'ticket_covid_bond' ] ) ) {
                    $EM_Ticket->ticket_meta['covid_bond'] = absint( $request_ticket[ 'ticket_covid_bond' ] );
                }elseif( isset( $EM_Ticket->ticket_meta['covid_bond'] ) && $EM_Ticket->ticket_meta['covid_bond'] == 1 ) {
                    $EM_Ticket->ticket_meta['covid_bond'] = 0;
                }
            }
        }
    }

    public function em_event_edit_ticket_td( $EM_Ticket ) {
        if( $this->has_covid_bond( $EM_Ticket ) ) {
            echo '<td><small>Non refundable CIP</small></td>';
        }
    }

    public function em_ticket_edit_form_fields( $col_count, $EM_Ticket ) {
        $checked = ( $this->has_covid_bond( $EM_Ticket ) ? 'checked="checked"' : '' );
        ?>
        <div class="covid-bond">
			<label title="<?php esc_attr_e('If checked tickets will have info about the bond displayed to users.','events-manager'); ?>"><?php esc_html_e('Non refundable CIP?','events-manager') ?></label>
			<input type="checkbox" value="1" name="em_tickets[<?php echo $col_count; ?>][ticket_covid_bond]" <?php echo $checked ?> class="covid_bond" />
		</div>
        <?php
    }

    private function has_covid_bond( $EM_Ticket ) {
        return ( isset( $EM_Ticket->ticket_meta['covid_bond'] ) && $EM_Ticket->ticket_meta['covid_bond'] ? true : false );
    }
}