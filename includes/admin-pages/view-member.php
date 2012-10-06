<?php
$user = get_userdata( urldecode( $_GET['view_member'] ) );
?>
<h2>
	<?php _e( 'View Member Details:', 'rcp' ); echo ' ' . $user->display_name; ?> - 
	<a href="<?php echo get_bloginfo('wpurl') . '/wp-admin/admin.php?page=rcp-members'; ?>" class="button-secondary">
		<?php _e( 'Go Back', 'rcp' ); ?>
	</a>	
</h2>
<table class="form-table">
	<tbody>
		<tr class="form-field">
			<th scope="row" valign="top">
				<?php _e( 'Status', 'rcp' ); ?>
			</th>
			<td>
				<?php echo rcp_get_status( $user->ID) ; ?>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top">
				<?php _e( 'Subscription Level', 'rcp' ); ?>
			</th>
			<td>
				<?php echo rcp_get_subscription( $user->ID ); ?>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top">
				<?php _e( 'Expiration date', 'rcp' ); ?>
			</th>
			<td>
				<?php echo rcp_get_expiration_date( $user->ID ); ?>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top">
				<?php _e( 'Subscription key', 'rcp' ); ?>
			</th>
			<td>
				<?php echo rcp_get_subscription_key( $user->ID ) ? rcp_get_subscription_key( $user->ID ) : 'None'; ?>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top">
				<?php _e( 'Signup Method', 'rcp' ); ?>
			</th>
			<td>
				<?php 
				$method = get_user_meta( $user->ID, 'rcp_signup_method', true );
				if( $method ) {
					switch( $method ) {
						case 'live' :
							_e( 'Regular user signup', 'rcp' );
						break;
						case 'manual';
							_e( 'Manually added by an admin', 'rcp' );
						break;
					}
				}				
				?>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top">
				<?php _e( 'Discount codes used', 'rcp' ); ?>
			</th>
			<td>
				<?php
				$discounts = get_user_meta( $user->ID, 'rcp_user_discounts', true );
				if( $discounts ) {
					foreach( $discounts as $discount ) {
						echo $discount . '<br/>';
					}
				} else {
					_e( 'None', 'rcp' );
				}
				?>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row" valign="top">
				<?php _e( 'Payments', 'rcp' ); ?>
			</th>
			<td>
				<?php echo rcp_print_user_payments( $user->ID ); ?>
			</td>
		</tr>	
	</tbody>
</table>
