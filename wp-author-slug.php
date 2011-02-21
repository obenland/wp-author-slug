<?php
/** wp-author-slug.php
 * 
 * Plugin Name:	WP Author Slug
 * Plugin URI:	http://www.obenlands.de/portfolio/plugins/wp-author-slug/
 * Description:	Rewrites the author url to NOT display the username but the display name
 * Version:		1.0
 * Author:		Konstantin Obenland
 * Author URI:	http://www.obenlands.de/
 * License:		GPL2
 */


/**
 * Overwrites the users' nicenames with the users' display name
 * 
 * Only runs on activation of plugin
 * 
 * @author	Konstantin Obenland
 * @since	1.0 - 19.02.2011
 * @global	$wpdb
 * 
 * @return	void
 */
function wp_author_slug_activation() {
	global $wpdb;
	
	// Let's be pre-3.1-compatible
	$users = (function_exists('get_users')) ? get_users() : get_users_of_blog();
	
	foreach ( $users as $user ){
		
		if( ! empty($user->display_name) ){
			$wpdb->update(
				$wpdb->users, 
				array( 'user_nicename'	=>	sanitize_title($user->display_name) ),
				array( 'ID'				=>	$user->ID )
			);
		}
	}
}
register_activation_hook( __FILE__, 'wp_author_slug_activation' );


/**
 * Overwrites the user's nicename with the user's display name
 * 
 * Runs every time a user is created or updated
 * 
 * @author	Konstantin Obenland
 * @since	1.0 - 19.02.2011
 * 
 * @param	string	$name	The default nicename
 * 
 * @return	string	The sanitized nicename
 */
function wp_author_slug_pre_user_nicename( $name ){
	
	if( ! empty($_REQUEST['display_name']) ){
		return sanitize_title( $_REQUEST['display_name'] );
	}
	
	return $name;
}
add_filter( 'pre_user_nicename', 'wp_author_slug_pre_user_nicename' );


/* End of file wp-author-slug.php */
/* Location: ./wp-content/plugins/wp-author-slug/wp-author-slug.php */