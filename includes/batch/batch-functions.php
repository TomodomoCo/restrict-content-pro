<?php
/**
 * @author John Parris <public@johnparris.com>
 * @copyright 2017 John Parris
 */
namespace RCP\Utils;

/**
 *
 * Adds a batch job for later processing.
 *
 * @since 3.0
 *
 * @param array $config {
 *     @type string $name        Job name.
 *     @type string $description Job description.
 *     @type string $callback    Callback used to process the job.
 * }
 * @return \WP_Error|bool True if the job was registered, false if not, WP_Error if an invalid configuration was given.
 */
function add_batch_job( array $config ) {

	if( empty( $config ) || empty( $config['name'] ) || empty( $config['description'] ) || empty( $config['callback'] ) || ! is_callable( $config['callback'] ) ) {
		return new \WP_Error( __( 'Invalid job configuration', 'rcp' ), __( 'You must supply a valid name, description, and callback when registering a batch job.', 'rcp' ) );
	}

	/**
	 * Return early if the job already exists.
	 * We don't want to overwrite any progress that may have been made.
	 */
	if( get_option( 'rcp_job_' . sanitize_key( $config['name'] ), false ) ) {
		return false;
	}

	/** This could easily be swapped out later for a custom table */
	return update_option( 'rcp_job_' . sanitize_key( $config['name'] ), $config, false );
}

/**
 * Deletes a job by name.
 *
 * @since 3.0
 *
 * @param string $name Name of job to delete.
 * @return bool True if the job is deleted, false if not.
 */
function delete_batch_job( $name ) {
	/** This could easily be swapped out later for a custom table */
	return delete_option( 'rcp_job_' . sanitize_key( $name ) );
}

/**
 * Processes the specified batch job.
 *
 * @since 3.0
 *
 * @param JobInterface $job The job to process.
 * @param int $step The step to process.
 * @return bool|\WP_Error True if the batch was successful, WP_Error if not.
 */
function process_batch( JobInterface $job, $step = 0 ) {
	return call_user_func( $job->callback(), $job, $step );
}
