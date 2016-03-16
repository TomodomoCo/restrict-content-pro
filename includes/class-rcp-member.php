<?php
/**
 * RCP Member class
 *
 * @since 2.1
*/

class RCP_Member extends WP_User {

	/**
	 * Retrieves the status of the member
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function get_status() {

		$status = get_user_meta( $this->ID, 'rcp_status', true );

		// double check that the status and expiration match. Update if needed
		if( $status == 'active' && $this->is_expired() ) {

			$status = 'expired';
			$this->set_status( $status );

		}

		if( empty( $status ) ) {
			$status = 'free';
		}

		return apply_filters( 'rcp_member_get_status', $status, $this->ID, $this );

	}

	/**
	 * Sets the status of a member
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function set_status( $new_status = '' ) {

		$ret        = false;
		$old_status = get_user_meta( $this->ID, 'rcp_status', true );

		if( ! empty( $new_status ) ) {

			update_user_meta( $this->ID, 'rcp_status', $new_status );

			if( 'expired' != $new_status ) {
				delete_user_meta( $this->ID, '_rcp_expired_email_sent');
			}

			if( 'expired' == $new_status || 'cancelled' == $new_status ) {
				$this->set_recurring( false );
			}

			do_action( 'rcp_set_status', $new_status, $this->ID, $old_status );

			// Record the status change
			if( $old_status != $new_status ) {
				rcp_add_member_note( $this->ID, sprintf( __( 'Member\'s status changed from %s to %s', 'rcp' ), $old_status, $new_status ) );
			}

			$ret = true;
		}

		return $ret;

	}

	/**
	 * Retrieves the expiration date of the member
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function get_expiration_date( $formatted = true ) {

		$expiration = get_user_meta( $this->ID, 'rcp_pending_expiration_date', true );

		if( empty( $expiration ) ) {

			$expiration = get_user_meta( $this->ID, 'rcp_expiration', true );

		}

		if( $expiration ) {
			$expiration = $expiration != 'none' ? $expiration : 'none';
		}

		if( $formatted && 'none' != $expiration ) {
			$expiration = date_i18n( get_option( 'date_format' ), strtotime( $expiration ) );
		}

		return apply_filters( 'rcp_member_get_expiration_date', $expiration, $this->ID, $this );

	}

	/**
	 * Retrieves the expiration date of the member as a timestamp
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function get_expiration_time() {

		$expiration = get_user_meta( $this->ID, 'rcp_pending_expiration_date', true );

		if( empty( $expiration ) ) {

			$expiration = get_user_meta( $this->ID, 'rcp_expiration', true );

		}

		return apply_filters( 'rcp_member_get_expiration_time', strtotime( $expiration ), $this->ID, $this );

	}

	/**
	 * Sets the expiration date for a member
	 *
	 * Should be passed as a MYSQL date string.
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function set_expiration_date( $new_date = '' ) {

		$ret      = false;
		$old_date = get_user_meta( $this->ID, 'rcp_expiration', true ); // This calls user meta directly to avoid retrieving the pending date

		if( $old_date !== $new_date ) {

			if( update_user_meta( $this->ID, 'rcp_expiration', $new_date ) ) {

				// Record the status change
				$note = sprintf( __( 'Member\'s expiration changed from %s to %s', 'rcp' ), $old_date, $new_date );
				rcp_add_member_note( $this->ID, $note );

			}

			delete_user_meta( $this->ID, 'rcp_pending_expiration_date' );

			do_action( 'rcp_set_expiration_date', $this->ID, $new_date, $old_date );

			$ret = true;
		}

		return $ret;

	}

	/**
	 * Calculates the new expiration date for a member
	 *
	 * @access  public
	 * @since   2.4
	 * @return  String Date in Y-m-d H:i:s format or "none" if is a lifetime member
	*/
	public function calculate_expiration( $force_now = false ) {

		$pending_exp = get_user_meta( $this->ID, 'rcp_pending_expiration_date', true );

		if( ! empty( $pending_exp ) ) {
			return $pending_exp;
		}

		// Get the member's current expiration date
		$expiration = $this->get_expiration_time();

		// Determine what date to use as the start for the new expiration calculation
		if( ! $force_now && $expiration > current_time( 'timestamp' ) && ! $this->is_expired() && $this->get_status() == 'active' ) {

			$base_timestamp = $expiration;

		} else {

			$base_timestamp = current_time( 'timestamp' );

		}

		$subscription_id = $this->get_pending_subscription_id();

		if( empty( $subscription_id ) ) {
			$subscription_id = $this->get_subscription_id();
		}

		$subscription = rcp_get_subscription_details( $subscription_id );

		if( $subscription->duration > 0 ) {

			$expire_timestamp  = strtotime( '+' . $subscription->duration . ' ' . $subscription->duration_unit . ' 23:59:59', $base_timestamp );
			$extension_days    = array( '29', '30', '31' );

			if( in_array( date( 'j', $expire_timestamp ), $extension_days ) && 'day' !== $subscription->duration_unit ) {

				/*
				 * Here we extend the expiration date by 1-3 days in order to account for "walking" payment dates in PayPal.
				 *
				 * See https://github.com/pippinsplugins/restrict-content-pro/issues/239
				 */

				$month = date( 'n', $expire_timestamp );

				if( $month < 12 ) {
					$month += 1;
					$year   = date( 'Y' );
				} else {
					$month  = 1;
					$year   = date( 'Y' ) + 1;
				}

				$timestamp  = mktime( 0, 0, 0, $month, 1, $year );

				$expiration = date( 'Y-m-d 23:59:59', $timestamp );
			}

			$expiration = date( 'Y-m-d 23:59:59', $expire_timestamp );

		} else {

			$expiration = 'none';

		}

		return apply_filters( 'rcp_member_calculated_expiration', $expiration, $this->ID, $this );

	}

	/**
	 * Renews a member's membership by updating status and expiration date
	 *
	 * Does NOT handle payment processing for the renewal. This should be called after receiving a renewal payment
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function renew( $recurring = false, $status = 'active' ) {

		if( ! $this->get_subscription_id() ) {
			return false;
		}

		$subscription_id = $this->get_pending_subscription_id();

		if( empty( $subscription_id ) ) {
			$subscription_id = $this->get_subscription_id();
		}

		$subscription = rcp_get_subscription_details( $subscription_id );
		$expiration   = apply_filters( 'rcp_member_renewal_expiration', $this->calculate_expiration(), $subscription, $this->ID );

		do_action( 'rcp_member_pre_renew', $this->ID, $expiration, $this );

		$this->set_expiration_date( $expiration );
		$this->set_status( $status );
		$this->set_recurring( $recurring );

		delete_user_meta( $this->ID, '_rcp_expired_email_sent' );

		do_action( 'rcp_member_post_renew', $this->ID, $expiration, $this );

	}

	/**
	 * Sets a member's membership as cancelled by updating status
	 *
	 * Does NOT handle actual cancellation of subscription payments, that is done in rcp_process_member_cancellation(). This should be called after a member is successfully cancelled.
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function cancel() {

		do_action( 'rcp_member_pre_cancel', $this->ID, $this );

		$this->set_status( 'cancelled' );

		do_action( 'rcp_member_post_cancel', $this->ID, $this );

	}

	/**
	 * Retrieves the profile ID of the member.
	 *
	 * This is used by payment gateways to store customer IDs and other identifiers for payment profiles
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function get_payment_profile_id() {

		$profile_id = get_user_meta( $this->ID, 'rcp_payment_profile_id', true );

		return apply_filters( 'rcp_member_get_payment_profile_id', $profile_id, $this->ID, $this );

	}

	/**
	 * Sets the payment profile ID for a member
	 *
	 * This is used by payment gateways to store customer IDs and other identifiers for payment profiles
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function set_payment_profile_id( $profile_id = '' ) {

		do_action( 'rcp_member_pre_set_profile_payment_id', $this->ID, $profile_id, $this );

		update_user_meta( $this->ID, 'rcp_payment_profile_id', $profile_id );

		do_action( 'rcp_member_post_set_profile_payment_id', $this->ID, $profile_id, $this );

	}

	/**
	 * Retrieves the subscription ID of the member
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function get_subscription_id() {

		$subscription_id = get_user_meta( $this->ID, 'rcp_subscription_level', true );

		return apply_filters( 'rcp_member_get_subscription_id', $subscription_id, $this->ID, $this );

	}

	/**
	 * Retrieves the pending subscription ID of the member
	 *
	 * @access  public
	 * @since   2.4.12
	*/
	public function get_pending_subscription_id() {

		return get_user_meta( $this->ID, 'rcp_pending_subscription_level', true );

	}

	/**
	 * Retrieves the subscription key of the member
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function get_subscription_key() {

		$subscription_key = get_user_meta( $this->ID, 'rcp_subscription_key', true );

		return apply_filters( 'rcp_member_get_subscription_key', $subscription_key, $this->ID, $this );

	}

	/**
	 * Retrieves the pending subscription key of the member
	 *
	 * @access  public
	 * @since   2.4.12
	*/
	public function get_pending_subscription_key() {

		return get_user_meta( $this->ID, 'rcp_pending_subscription_key', true );

	}

	/**
	 * Retrieves the current subscription name of the member
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function get_subscription_name() {

		$sub_name = rcp_get_subscription_name( $this->get_subscription_id() );

		return apply_filters( 'rcp_member_get_subscription_name', $sub_name, $this->ID, $this );

	}

	/**
	 * Retrieves the pending subscription name of the member
	 *
	 * @access  public
	 * @since   2.4.12
	*/
	public function get_pending_subscription_name() {

		$sub_name = rcp_get_subscription_name( $this->get_pending_subscription_id() );

		return apply_filters( 'rcp_member_get_subscription_name', $sub_name, $this->ID, $this );

	}

	/**
	 * Retrieves all payments belonging to the member
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function get_payments() {

		$payments = new RCP_Payments;
		$payments = $payments->get_payments( array( 'user_id' => $this->ID ) );

		return apply_filters( 'rcp_member_get_payments', $payments, $this->ID, $this );
	}

	/**
	 * Retrieves the notes on a member
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function get_notes() {

		$notes = get_user_meta( $this->ID, 'rcp_notes', true );

		return apply_filters( 'rcp_member_get_notes', $notes, $this->ID, $this );

	}

	/**
	 * Adds a new note to a member
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function add_note( $note = '' ) {

		$notes = $this->get_notes();

		if( empty( $notes ) ) {
			$notes = '';
		}

		$note = apply_filters( 'rcp_member_pre_add_note', $note, $this->ID, $this );

		$notes .= "\n\n" . date_i18n( 'F j, Y H:i:s', current_time( 'timestamp' ) ) . ' - ' . $note;

		update_user_meta( $this->ID, 'rcp_notes', wp_kses( $notes, array() ) );

		do_action( 'rcp_member_add_note', $note, $this->ID, $this );

		return true;

	}

	/**
	 * Determines if a member has an active subscription, or is cancelled but has not reached EOT
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function is_active() {

		$ret = false;

		if( user_can( $this->ID, 'manage_options' ) ) {
			$ret = true;
		} else if( ! $this->is_expired() && ( $this->get_status() == 'active' || $this->get_status() == 'cancelled' ) ) {
			$ret = true;
		}

		return apply_filters( 'rcp_is_active', $ret, $this->ID, $this );

	}

	/**
	 * Determines if a member has a recurring subscription
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function is_recurring() {

		$ret       = false;
		$recurring = get_user_meta( $this->ID, 'rcp_recurring', true );

		if( $recurring == 'yes' ) {
			$ret = true;
		}

		return apply_filters( 'rcp_member_is_recurring', $ret, $this->ID, $this );

	}

	/**
	 * Sets whether a member is recurring
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function set_recurring( $yes = true ) {

		if( $yes ) {
			update_user_meta( $this->ID, 'rcp_recurring', 'yes' );
		} else {
			delete_user_meta( $this->ID, 'rcp_recurring' );
		}

		do_action( 'rcp_member_set_recurring', $yes, $this->ID, $this );

	}

	/**
	 * Determines if the member is expired
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function is_expired() {

		$ret        = false;
		$expiration = get_user_meta( $this->ID, 'rcp_expiration', true );

		if( $expiration && strtotime( 'NOW' ) > strtotime( $expiration ) ) {
			$ret = true;
		}

		if( $expiration == 'none' ) {
			$ret = false;
		}

		return apply_filters( 'rcp_member_is_expired', $ret, $this->ID, $this );

	}

	/**
	 * Determines if the member is currently trailing
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function is_trialing() {

		$ret      = false;
		$trialing = get_user_meta( $this->ID, 'rcp_is_trialing', true );

		if( $trialing == 'yes' && $this->is_active() ) {
			$ret = true;
		}

		// Old filter for backwards compatibility
		$ret = apply_filters( 'rcp_is_trialing', $ret, $this->ID );

		return apply_filters( 'rcp_member_is_trialing', $ret, $this->ID, $this );

	}

	/**
	 * Determines if the member has used a trial
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function has_trialed() {

		$ret = false;

		if( get_user_meta( $this->ID, 'rcp_has_trialed', true ) == 'yes' ) {
			$ret = true;
		}

		$ret = apply_filters( 'rcp_has_used_trial', $ret, $this->ID );

		return apply_filters( 'rcp_member_has_trialed', $ret, $this->ID );

	}

	/**
	 * Determines if the member can access current content
	 *
	 * @access  public
	 * @since   2.1
	*/
	public function can_access( $post_id = 0 ) {

		$subscription_levels = rcp_get_content_subscription_levels( $post_id );
		$access_level        = get_post_meta( $post_id, 'rcp_access_level', true );

		// Assume the user can until proven false
		$ret = true;

		if ( rcp_is_paid_content( $post_id ) && ! $this->is_active() ) {

			$ret = false;

		}

		if( ! rcp_user_has_access( $this->ID, $access_level ) && $access_level > 0 ) {

			$ret = false;

		}

		if ( ! empty( $subscription_levels ) ) {

			if ( ! in_array( $this->get_subscription_id(), $subscription_levels ) && ! user_can( $this->ID, 'manage_options' ) ) {

				$ret = false;

			}

		}

		return apply_filters( 'rcp_member_can_access', $ret, $this->ID, $post_id, $this );

	}

	/**
	 * Gets the URL to switch to the user
	 * if the User Switching plugin is active
	 *
	 * @access public
	 * @since 2.1
	*/
	public function get_switch_to_url() {

		if( ! class_exists( 'user_switching' ) ) {
		   	return false;
		}

		$link = user_switching::maybe_switch_url( $this );
		if ( $link ) {
			$link = add_query_arg( 'redirect_to', urlencode( home_url() ), $link );
			return $link;
		} else {
			return false;
		}
	}

}
