<?php
/**
 * Plugin Name: WP Author Slug
 * Plugin URI:  http://en.wp.obenland.it/wp-author-slug/?utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-author-slug
 * Description: Rewrites the author url to NOT display the username but the display name
 * Version:     5
 * Author:      Konstantin Obenland
 * Author URI:  http://en.wp.obenland.it/?utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-author-slug
 * Text Domain: wp-author-slug
 * Domain Path: /lang
 * License:     GPLv2
 *
 * @package wp-author-slug
 */

if ( ! class_exists( 'Obenland_Wp_Plugins_V5' ) ) {
	require_once 'class-obenland-wp-plugins-v5.php';
}

require_once 'class-obenland-wp-author-slug.php';
Obenland_Wp_Author_Slug::get_instance();

/**
 * Overwrites the users' nicenames with the users' display name.
 *
 * Only runs on activation of plugin.
 */
function wp_author_slug_activation() {
	$users = get_users(
		array(
			'blog_id' => '',
			'fields'  => array( 'ID', 'display_name' ),
		)
	);

	$conflicts = array();

	foreach ( $users as $user ) {
		if ( ! empty( $user->display_name ) ) {
			$proposed_slug = sanitize_title( $user->display_name );

			// Check for conflicts with existing pages.
			$existing_page = get_page_by_path( $proposed_slug );
			if ( $existing_page ) {
				$conflicts[] = array(
					'user_id' => $user->ID,
					'page_id' => $existing_page->ID,
				);
			}

			wp_update_user(
				array(
					'ID'            => $user->ID,
					'user_nicename' => $proposed_slug,
				)
			);
		}
	}

	// Store conflicts for admin notices.
	if ( ! empty( $conflicts ) ) {
		update_option( 'wp_author_slug_conflicts', $conflicts );
	}
}
register_activation_hook( __FILE__, 'wp_author_slug_activation' );

/**
 * Restores users' nicenames.
 *
 * Only runs on deactivation of plugin.
 */
function wp_author_slug_deactivation() {
	$users = get_users(
		array(
			'blog_id' => '',
			'fields'  => array( 'ID', 'user_login' ),
		)
	);

	foreach ( $users as $user ) {
		wp_update_user(
			array(
				'ID'            => $user->ID,
				'user_nicename' => sanitize_title( $user->user_login ),
			)
		);
	}

	// Clean up stored conflicts.
	delete_option( 'wp_author_slug_conflicts' );
}
register_deactivation_hook( __FILE__, 'wp_author_slug_deactivation' );
