<?php
/**
 * Plugin Name: WP Author Slug
 * Plugin URI:  http://en.wp.obenland.it/wp-author-slug/?utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-author-slug
 * Description: Rewrites the author url to NOT display the username but the display name
 * Version:     3
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
 *
 * @author Konstantin Obenland
 * @since  1.0 - 19.02.2011
 * @access public
 * @static
 */
function wp_author_slug_activation() {
	$users = get_users(
		array(
			'blog_id' => '',
			'fields'  => array( 'ID', 'display_name' ),
		)
	);

	foreach ( $users as $user ) {
		if ( ! empty( $user->display_name ) ) {
			wp_update_user(
				array(
					'ID'            => $user->ID,
					'user_nicename' => sanitize_title( $user->display_name ),
				)
			);
		}
	}
}
register_activation_hook( __FILE__, 'wp_author_slug_activation' );
