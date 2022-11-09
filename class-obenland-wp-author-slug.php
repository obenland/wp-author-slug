<?php
/**
 * Obenland_Wp_Author_Slug file.
 *
 * @package wp-author-slug
 */

/**
 * Class Obenland_Wp_Author_Slug.
 */
class Obenland_Wp_Author_Slug extends Obenland_Wp_Plugins_V5 {

	/**
	 * Class instance.
	 *
	 * @since   4 - 08.11.2022
	 * @access  public
	 * @static
	 *
	 * @var Obenland_Wp_Author_Slug
	 */
	public static $instance;

	/**
	 * Constructor.
	 *
	 * @author Konstantin Obenland
	 * @since  1.1 - 03.04.2011
	 * @access public
	 */
	public function __construct() {
		parent::__construct(
			array(
				'textdomain'     => 'wp-author-slug',
				'plugin_path'    => __FILE__,
				'donate_link_id' => 'XVPLJZ3VH4GCN',
			)
		);

		$this->hook( 'pre_user_nicename' );
	}

	/**
	 * Singleton.
	 *
	 * @return Obenland_Wp_Author_Slug
	 */
	public static function get_instance() {
		if ( ! static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
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
		if ( ! empty( $_REQUEST['display_name'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$name = sanitize_title( $_REQUEST['display_name'] ); //phpcs:ignore WordPress.Security
		}

		return $name;
	}
}
