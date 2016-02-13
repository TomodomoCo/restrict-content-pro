<?php

/*******************************************
* Restrict Content Pro Content Filters for
* User Level Checks
*******************************************/

// filter the content based upon the "Restrict this content" metabox configuration
function rcp_filter_restricted_content( $content ) {
	global $post, $user_ID, $rcp_options;

	if ( rcp_is_paid_content( $post->ID ) ) {

		$message = ! empty( $rcp_options['paid_message'] ) ? $rcp_options['paid_message'] : false; // message shown for premium content

	} else {

		$message = ! empty( $rcp_options['free_message'] ) ? $rcp_options['free_message'] : false; // message shown for free content

	}

	if( empty( $message ) ) {
		$message = __( 'This content is restricted to subscribers', 'rcp' );
	}

	if ( ! rcp_user_can_access( $user_ID, $post->ID ) ) {
		return rcp_format_teaser( $message );
	}

	return $content;

}
add_filter( 'the_content', 'rcp_filter_restricted_content', 100 );

/**
 * Filter restricted content based on category restrictions
 *
 * @access      public
 * @since       2.0
 * @return      $content
 */
function rcp_filter_restricted_category_content( $content ) {
	global $post, $rcp_options;

	$restricted = false;

	foreach( rcp_get_restricted_taxonomies() as $taxonomy ) {
		if ( $restricted = rcp_is_post_taxonomy_restricted( $post->ID, $taxonomy ) ) {
			break;
		}
	}

	if ( $restricted ) {

		$message = ! empty( $rcp_options['paid_message'] ) ? $rcp_options['paid_message'] : __( 'You need to have an active subscription to view this content.', 'rcp' );

		return rcp_format_teaser( $message );

	}

	return $content;

}
add_filter( 'the_content', 'rcp_filter_restricted_category_content', 101 );

/**
 * Check the provided taxonomy along with the given post id to see if any restrictions are found
 *
 * @since      4.5
 * @param      $post_id
 * @param      $taxonomy
 * @param null $user_id
 *
 * @return bool|mixed|void
 */
function rcp_is_post_taxonomy_restricted( $post_id, $taxonomy, $user_id = null ) {

	// make sure this post supports the supplied taxonomy
	$post_taxonomies = get_post_taxonomies( $post_id );
	if ( ! in_array( $taxonomy, (array) $post_taxonomies ) ) {
		return false;
	}

	$terms = get_the_terms( $post_id, $taxonomy );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return false;
	}

	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	$restricted = false;

	// Loop through the categories and determine if one has restriction options
	foreach( $terms as $term ) {

		$term_meta = rcp_get_term_restrictions( $term->term_id );
		if( ! empty( $term_meta ) ) {

			/**
			 * Check that the user has a paid subscription
			 */

			$paid_only = ! empty( $term_meta['paid_only'] );

			if( $paid_only && ! rcp_is_paid_user( $user_id ) ) {

				$restricted = true;

			}

			/**
			 * If restricted to one or more subscription levels, make sure that the user is a member of one of the levls
			 */

			$subscriptions = ! empty( $term_meta['subscriptions'] ) ? array_map( 'absint', $term_meta['subscriptions'] ) : false;

			if( $subscriptions && ! in_array( rcp_get_subscription_id( $user_id ), $subscriptions ) ) {

				$restricted = true;

			}

			/**
			 * If restricted to one or more access levels, make sure that the user is a member of one of the levls
			 */

			$access_level = ! empty( $term_meta['access_level'] ) ? absint( $term_meta['access_level'] ) : 0;

			if( $access_level > 0 && ! rcp_user_has_access( $user_id, $access_level ) ) {

				$restricted = true;

			}

		}

	}

	return apply_filters( 'rcp_is_post_taxonomy_restricted', $restricted, $taxonomy, $post_id, $user_id );
}

function rcp_user_level_checks() {
	if ( current_user_can( 'read' ) ) {
		if ( current_user_can( 'edit_posts' ) ) {
			if ( current_user_can( 'upload_files' ) ) {
				if ( current_user_can( 'moderate_comments' ) ) {
					if ( current_user_can( 'switch_themes' ) ) {
						//do nothing here for admin
					} else {
						add_filter( 'the_content', 'rcp_display_message_to_editors' );
					}
				} else {
					add_filter( 'the_content', 'rcp_display_message_authors' );
				}
			} else {
				add_filter( 'the_content', 'rcp_display_message_to_contributors' );
			}
		} else {
			add_filter( 'the_content', 'rcp_display_message_to_subscribers' );
		}
	} else {
		add_filter( 'the_content', 'rcp_display_message_to_non_loggged_in_users' );
	}
}
add_action( 'loop_start', 'rcp_user_level_checks' );

function rcp_display_message_to_editors( $content ) {
	global $rcp_options, $post, $user_ID;

	$message = $rcp_options['free_message'];
	$paid_message = $rcp_options['paid_message'];
	if ( rcp_is_paid_content( $post->ID ) ) {
		$message = $paid_message;
	}

	$user_level = get_post_meta( $post->ID, 'rcp_user_level', true );
	$access_level = get_post_meta( $post->ID, 'rcp_access_level', true );

	$has_access = false;
	if ( rcp_user_has_access( $user_ID, $access_level ) ) {
		$has_access = true;
	}

	if ( $user_level == 'Administrator' && $has_access ) {
		return rcp_format_teaser( $message );
	}
	return $content;
}

function rcp_display_message_authors( $content ) {
	global $rcp_options, $post, $user_ID;

	$message = $rcp_options['free_message'];
	$paid_message = $rcp_options['paid_message'];
	if ( rcp_is_paid_content( $post->ID ) ) {
		$message = $paid_message;
	}

	$user_level = get_post_meta( $post->ID, 'rcp_user_level', true );
	$access_level = get_post_meta( $post->ID, 'rcp_access_level', true );

	$has_access = false;
	if ( rcp_user_has_access( $user_ID, $access_level ) ) {
		$has_access = true;
	}

	if ( ( $user_level == 'Administrator' || $user_level == 'Editor' )  && $has_access ) {
		return rcp_format_teaser( $message );
	}
	// return the content unfilitered
	return $content;
}

function rcp_display_message_to_contributors( $content ) {
	global $rcp_options, $post, $user_ID;

	$message = $rcp_options['free_message'];
	$paid_message = $rcp_options['paid_message'];
	if ( rcp_is_paid_content( $post->ID ) ) {
		$message = $paid_message;
	}

	$user_level = get_post_meta( $post->ID, 'rcp_user_level', true );
	$access_level = get_post_meta( $post->ID, 'rcp_access_level', true );

	$has_access = false;
	if ( rcp_user_has_access( $user_ID, $access_level ) ) {
		$has_access = true;
	}

	if ( ( $user_level == 'Administrator' || $user_level == 'Editor' || $user_level == 'Author' ) && $has_access ) {
		return rcp_format_teaser( $message );
	}
	// return the content unfilitered
	return $content;
}

function rcp_display_message_to_subscribers( $content ) {
	global $rcp_options, $post, $user_ID;

	$message = $rcp_options['free_message'];
	$paid_message = $rcp_options['paid_message'];
	if ( rcp_is_paid_content( $post->ID ) ) {
		$message = $paid_message;
	}

	$user_level = get_post_meta( $post->ID, 'rcp_user_level', true );
	$access_level = get_post_meta( $post->ID, 'rcp_access_level', true );

	$has_access = false;
	if ( rcp_user_has_access( $user_ID, $access_level ) ) {
		$has_access = true;
	}
	if ( $user_level == 'Administrator' || $user_level == 'Editor' || $user_level == 'Author' || $user_level == 'Contributor' || !$has_access ) {
		return rcp_format_teaser( $message );
	}
	// return the content unfilitered
	return $content;
}

// this is the function used to display the error message to non-logged in users
function rcp_display_message_to_non_loggged_in_users( $content ) {
	global $rcp_options, $post, $user_ID;

	$message      = isset( $rcp_options['free_message'] ) ? $rcp_options['free_message'] : '';
	$paid_message = isset( $rcp_options['paid_message'] ) ? $rcp_options['paid_message'] : '';

	if ( rcp_is_paid_content( $post->ID ) ) {
		$message = $paid_message;
	}

	$user_level   = get_post_meta( $post->ID, 'rcp_user_level', true );
	$access_level = get_post_meta( $post->ID, 'rcp_access_level', true );
	$has_access   = false;

	if ( rcp_user_has_access( $user_ID, $access_level ) ) {
		$has_access = true;
	}

	if ( ! is_user_logged_in() && ( $user_level == 'Administrator' || $user_level == 'Editor' || $user_level == 'Author' || $user_level == 'Contributor' || $user_level == 'Subscriber' ) && $has_access ) {
		return rcp_format_teaser( $message );
	}

	// return the content unfilitered
	return $content;
}

// formats the teaser message
function rcp_format_teaser( $message ) {
	global $post;
	if ( get_post_meta( $post->ID, 'rcp_show_excerpt', true ) ) {
		$excerpt_length = 50;
		if ( has_filter( 'rcp_filter_excerpt_length' ) ) {
			$excerpt_length = apply_filters( 'rcp_filter_excerpt_length', $excerpt_length );
		}
		$excerpt = rcp_excerpt_by_id( $post, $excerpt_length );
		$message = apply_filters( 'rcp_restricted_message', $message );
		$message = $excerpt . $message;
	} else {
		$message = apply_filters( 'rcp_restricted_message', $message );
	}
	return $message;
}

// wraps the restricted message in paragraph tags. This is the default filter
function rcp_restricted_message_filter( $message ) {
	return do_shortcode( wpautop( $message ) );
}
add_filter( 'rcp_restricted_message', 'rcp_restricted_message_filter', 10, 1 );
