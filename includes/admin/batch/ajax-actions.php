<?php
/**
 * @author John Parris <public@johnparris.com>
 * @copyright 2017 John Parris
 */
namespace RCP\Utils;
/**
 * Processes ajax batch jobs.
 *
 * @since 3.0
 */
function ajax_process_batch() {

	if( ! current_user_can( 'rcp_manage_settings' ) ) {
		wp_die();
	}

	check_ajax_referer( 'rcp_batch_nonce', 'rcp_batch_nonce' );

	$step = ! empty( $_POST['step'] ) ? absint( $_POST['step'] ) : 0;

	$job_id = ! empty( $_POST['job_id'] ) ? sanitize_text_field( $_POST['job_id'] ) : false;

	try {
		$job = new Job( $job_id );
	} catch( \InvalidArgumentException $exception ) {
		$job = false;
	}

	if( ! $job_id || empty( $job ) || ! is_callable( $job->callback() ) ) {
		wp_send_json_error( array(
			'message' => __( 'Invalid job ID provided.', 'rcp' )
		) );
	}

	$result = process_batch( $job, $step );

	if( true === $result ) {
		wp_send_json_success( array(
			'step'             => $step,
			'next_step'        => $job->completed() ? false : ++ $step,
			'job_name'         => $job->name(),
			'job_description'  => $job->description(),
			'percent_complete' => $job->percent_complete(),
			'items_processed'  => $job->current_count(),
			'complete'         => $job->completed(),
		) );
	}

	// An error occurred during process. Return the error message.
	if( is_wp_error( $result ) ) {
		// TODO: what to do with failed jobs?
		wp_send_json_error( array(
			'message'         => $result->get_error_message(),
			'step'            => $step,
			'job_name'        => $job->name(),
			'job_description' => $job->description()
		) );
	}

	// We didn't get an expected return.
	wp_send_json_error( array(
		'message'         => sprintf( __( 'An unknown error occurred processing %s.', 'rcp' ), $job->name() ),
		'step'            => $step,
		'job_name'        => $job->name(),
		'job_description' => $job->description()
	) );

}
add_action( 'wp_ajax_rcp_process_batch', __NAMESPACE__ . '\ajax_process_batch' );