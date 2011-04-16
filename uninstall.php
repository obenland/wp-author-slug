<?php
//Don't uninstall unless you absolutely want to!
if ( ! defined( 'WP_UNINSTALL_PLUGIN' )){
	wp_die('WP_UNINSTALL_PLUGIN undefined.');
}

global $wpdb;
		
$users = $wpdb->get_results( "
	SELECT ID, user_login 
	FROM $wpdb->users
" );

foreach ( $users as $user ) {
	$wpdb->update(
		$wpdb->users,
		array( 'user_nicename'	=>	sanitize_title($user->user_login) ),
		array( 'ID'				=>	$user->ID )
	);
}


/* Goodbye! Thank you for having me! */


/* End of file uninstall.php */
/* Location: ./wp-content/plugins/wp-author-slug/uninstall.php */