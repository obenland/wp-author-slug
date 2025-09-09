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
				'plugin_path'    => __DIR__ . '/wp-author-slug.php',
				'donate_link_id' => 'XVPLJZ3VH4GCN',
			)
		);

		$this->hook( 'pre_user_nicename' );
		$this->hook( 'admin_notices' );
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

	/**
	 * Displays admin notices for slug conflicts.
	 *
	 * @author Konstantin Obenland
	 * @since  5 - 09.09.2025
	 * @access public
	 */
	public function admin_notices() {
		$conflicts = get_option( 'wp_author_slug_conflicts', array() );

		if ( empty( $conflicts ) ) {
			return;
		}

		// Only show to users who can manage users.
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		echo '<div class="notice notice-error">';
		echo '<p><strong>' . esc_html__( 'WP Author Slug: Conflicts Detected', 'wp-author-slug' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'The following author slugs conflict with existing page slugs. This may cause sub-pages to fail loading:', 'wp-author-slug' ) . '</p>';
		echo '<ul>';

		foreach ( $conflicts as $conflict ) {
			$user = get_userdata( $conflict['user_id'] );
			$page = get_post( $conflict['page_id'] );

			if ( $user && $page ) {
				$user_slug = sanitize_title( $user->display_name );
				printf(
					/* translators: 1: User name, 2: User slug, 3: Page edit link, 4: Page title */
					'<li>' . wp_kses_post( __( 'User "%1$s" (slug: <code>%2$s</code>) conflicts with page: <a href="%3$s">%4$s</a>.', 'wp-author-slug' ) ) . '</li>',
					esc_html( $user->display_name ),
					esc_html( $user_slug ),
					esc_url( get_edit_post_link( $page->ID ) ),
					esc_html( $page->post_title )
				);
			}
		}

		echo '</ul>';
		echo '<p>' . esc_html__( 'Please change the slug of the conflicting pages, then deactivate and reactivate this plugin to clear the conflicts.', 'wp-author-slug' ) . '</p>';
		echo '</div>';
	}
}
