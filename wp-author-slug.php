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
 * @package WP Author Slug
 */

if ( ! class_exists( 'Obenland_Wp_Plugins_V4' ) ) {
	require_once 'obenland-wp-plugins.php';
}

register_activation_hook( __FILE__, array(
	'Obenland_Wp_Author_Slug',
	'activation',
) );

/**
 * Class Obenland_Wp_Author_Slug.
 */
class Obenland_Wp_Author_Slug extends Obenland_Wp_Plugins_V4 {

	/**
	 * Constructor.
	 *
	 * @author Konstantin Obenland
	 * @since  1.1 - 03.04.2011
	 * @access public
	 */
	public function __construct() {
		parent::__construct( array(
			'textdomain'     => 'wp-author-slug',
			'plugin_path'    => __FILE__,
			'donate_link_id' => 'XVPLJZ3VH4GCN',
		) );

		$this->hook( 'pre_user_nicename' );
	}


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
	public static function activation() {
		$users = get_users( array(
			'blog_id' => '',
			'fields'  => array( 'ID', 'display_name' ),
		) );

		foreach ( $users as $user ) {
			if ( ! empty( $user->display_name ) ) {
				@wp_update_user( array(
					'ID'            => $user->ID,
					'user_nicename' => sanitize_title( $user->display_name ),
				) );
			}
		}
	}


	/**
	 * Overwrites the user's nicename with the user's display name.
	 *
	 * Runs every time a user is created or updated.
	 *
	 * @author Konstantin Obenland
	 * @since  1.0 - 19.02.2011
	 * @access public
	 *
	 * @param string $name The default nicename.
	 * @return string The sanitized nicename.
	 */
	public function pre_user_nicename( $name ) {
		// phpcs:disable WordPress.VIP.ValidatedSanitizedInput, WordPress.CSRF.NonceVerification.NoNonceVerification
		if ( ! empty( $_REQUEST['display_name'] ) ) {
			$name = sanitize_title( $_REQUEST['display_name'] );
		}
		// phpcs:enable WordPress.VIP.ValidatedSanitizedInput, WordPress.CSRF.NonceVerification.NoNonceVerification

		return $name;
	}
}  // End of class Obenland_Wp_Author_Slug.


new Obenland_Wp_Author_Slug();
