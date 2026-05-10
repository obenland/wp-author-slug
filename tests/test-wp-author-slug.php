<?php
/**
 * WP Author Slug tests.
 *
 * @package wp-author-slug
 */

/**
 * Tests for Obenland_Wp_Author_Slug + the activation/deactivation helpers.
 */
class Test_WP_Author_Slug extends WP_UnitTestCase {

	/**
	 * Reset $_REQUEST and the conflicts option between tests so global state
	 * does not leak.
	 */
	public function tear_down() {
		unset( $_REQUEST['display_name'] );
		delete_option( 'wp_author_slug_conflicts' );
		parent::tear_down();
	}

	/**
	 * Tests that `pre_user_nicename` overrides the input with the sanitized
	 * `display_name` from the request.
	 */
	public function test_pre_user_nicename_uses_request_display_name() {
		$_REQUEST['display_name'] = 'Jane Doe';

		$this->assertSame(
			'jane-doe',
			Obenland_Wp_Author_Slug::get_instance()->pre_user_nicename( 'jane' )
		);
	}

	/**
	 * Tests that `pre_user_nicename` sanitizes special characters and casing.
	 */
	public function test_pre_user_nicename_sanitizes_special_chars() {
		$_REQUEST['display_name'] = 'Über Café  &  More!';

		$this->assertSame(
			'uber-cafe-more',
			Obenland_Wp_Author_Slug::get_instance()->pre_user_nicename( 'fallback' )
		);
	}

	/**
	 * Tests that `pre_user_nicename` passes the input through untouched when
	 * no `display_name` is in the request.
	 */
	public function test_pre_user_nicename_passes_through_when_request_unset() {
		$this->assertSame(
			'fallback-name',
			Obenland_Wp_Author_Slug::get_instance()->pre_user_nicename( 'fallback-name' )
		);
	}

	/**
	 * Tests that `pre_user_nicename` passes through when `display_name` is
	 * present but empty (the `! empty()` guard).
	 */
	public function test_pre_user_nicename_passes_through_when_request_empty() {
		$_REQUEST['display_name'] = '';

		$this->assertSame(
			'fallback-name',
			Obenland_Wp_Author_Slug::get_instance()->pre_user_nicename( 'fallback-name' )
		);
	}

	/**
	 * Tests that the class registers `pre_user_nicename` on the filter chain
	 * during bootstrap (via Obenland_Wp_Plugins_V5::hook()).
	 */
	public function test_pre_user_nicename_filter_is_registered() {
		$this->assertNotFalse(
			has_filter(
				'pre_user_nicename',
				array( Obenland_Wp_Author_Slug::get_instance(), 'pre_user_nicename' )
			)
		);
	}

	/**
	 * Tests that `admin_notices` outputs nothing when there are no recorded
	 * conflicts (the early-return branch).
	 */
	public function test_admin_notices_silent_when_no_conflicts() {
		// No conflicts option set.
		ob_start();
		Obenland_Wp_Author_Slug::get_instance()->admin_notices();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Tests that `admin_notices` outputs nothing when conflicts exist but the
	 * current user lacks the `edit_users` capability.
	 */
	public function test_admin_notices_silent_for_users_without_cap() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		update_option(
			'wp_author_slug_conflicts',
			array(
				array(
					'user_id' => $user_id,
					'page_id' => $page_id,
				),
			)
		);

		wp_set_current_user( $user_id );

		ob_start();
		Obenland_Wp_Author_Slug::get_instance()->admin_notices();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * Tests that `admin_notices` outputs the conflict notice when conflicts
	 * exist AND the current user can `edit_users`.
	 *
	 * Asserts on the user-visible content: error class, conflicting user's
	 * display name, the proposed slug, and a link to edit the conflicting
	 * page.
	 */
	public function test_admin_notices_renders_conflict_for_admin() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = self::factory()->user->create(
			array(
				'display_name' => 'About Us',
				'role'         => 'author',
			)
		);
		$page_id  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'About Us Page',
				'post_name'   => 'about-us',
			)
		);

		update_option(
			'wp_author_slug_conflicts',
			array(
				array(
					'user_id' => $user_id,
					'page_id' => $page_id,
				),
			)
		);

		wp_set_current_user( $admin_id );

		// On multisite the `edit_users` cap is restricted to super admins.
		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}

		ob_start();
		Obenland_Wp_Author_Slug::get_instance()->admin_notices();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'WP Author Slug: Conflicts Detected', $output );
		$this->assertStringContainsString( 'About Us', $output );
		$this->assertStringContainsString( '<code>about-us</code>', $output );
		$this->assertStringContainsString( 'About Us Page', $output );
	}

	/**
	 * Tests that `wp_author_slug_activation` rewrites `user_nicename` to a
	 * sanitized version of `display_name` for users with one.
	 */
	public function test_activation_rewrites_user_nicename() {
		$user_id = self::factory()->user->create(
			array(
				'user_login'   => 'someone',
				'display_name' => 'Jane Doe',
			)
		);

		// Pre-condition: nicename is the auto-generated default (login).
		$user_before = get_userdata( $user_id );
		$this->assertSame( 'someone', $user_before->user_nicename );

		wp_author_slug_activation();

		$user_after = get_userdata( $user_id );
		$this->assertSame( 'jane-doe', $user_after->user_nicename );
	}

	/**
	 * Tests that `wp_author_slug_activation` records a conflict when a user's
	 * proposed slug collides with an existing page slug.
	 */
	public function test_activation_records_page_slug_conflict() {
		$user_id = self::factory()->user->create(
			array(
				'display_name' => 'About Us',
			)
		);
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'About Us',
				'post_name'   => 'about-us',
			)
		);

		wp_author_slug_activation();

		$conflicts = get_option( 'wp_author_slug_conflicts' );

		$this->assertIsArray( $conflicts );
		$found = false;
		foreach ( $conflicts as $entry ) {
			if ( (int) $entry['user_id'] === $user_id && (int) $entry['page_id'] === $page_id ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Expected the conflict between the user and the page to be recorded.' );
	}

	/**
	 * Tests that `wp_author_slug_activation` does not record a conflict when
	 * there is no page with the same slug.
	 */
	public function test_activation_no_conflict_when_no_page_collides() {
		self::factory()->user->create(
			array(
				'display_name' => 'Unique Person Without Page Conflict',
			)
		);

		wp_author_slug_activation();

		$this->assertFalse(
			(bool) get_option( 'wp_author_slug_conflicts' ),
			'Expected no conflicts when no page has the same slug.'
		);
	}

	/**
	 * Tests that `wp_author_slug_deactivation` restores `user_nicename` to a
	 * sanitized version of `user_login` for all users.
	 */
	public function test_deactivation_restores_nicename_to_login() {
		$user_id = self::factory()->user->create(
			array(
				'user_login'   => 'janedoe',
				'display_name' => 'Jane Doe',
			)
		);

		// First activate to set the display-name-based nicename.
		wp_author_slug_activation();
		$user_after_activate = get_userdata( $user_id );
		$this->assertSame( 'jane-doe', $user_after_activate->user_nicename );

		// Then deactivate; nicename should fall back to the sanitized login,
		// which differs from the display-name slug so a no-op deactivation
		// would not pass this assertion.
		wp_author_slug_deactivation();

		$user_after_deactivate = get_userdata( $user_id );
		$this->assertSame( 'janedoe', $user_after_deactivate->user_nicename );
	}

	/**
	 * Tests that `wp_author_slug_deactivation` clears any recorded conflicts.
	 */
	public function test_deactivation_clears_conflicts() {
		update_option(
			'wp_author_slug_conflicts',
			array(
				array(
					'user_id' => 1,
					'page_id' => 2,
				),
			)
		);

		wp_author_slug_deactivation();

		$this->assertFalse(
			(bool) get_option( 'wp_author_slug_conflicts' ),
			'Expected the conflicts option to be cleared on deactivation.'
		);
	}

	/**
	 * Tests that `wp_author_slug_activation` skips users whose `display_name`
	 * is empty (the `! empty( $user->display_name )` guard).
	 */
	public function test_activation_skips_users_with_empty_display_name() {
		global $wpdb;

		$user_id = self::factory()->user->create(
			array(
				'user_login'   => 'emptydisplay',
				'display_name' => 'Will Be Erased',
			)
		);

		/*
		 * wp_update_user() falls back to user_login when display_name is empty,
		 * so write the empty value directly to bypass that guard.
		 */
		$wpdb->update( $wpdb->users, array( 'display_name' => '' ), array( 'ID' => $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_user_cache( $user_id );

		$nicename_before = get_userdata( $user_id )->user_nicename;

		wp_author_slug_activation();

		$nicename_after = get_userdata( $user_id )->user_nicename;
		$this->assertSame( $nicename_before, $nicename_after );
	}

	/**
	 * Tests that `wp_author_slug_activation` does not record a conflict when
	 * the colliding slug belongs to a non-page post type. The conflict check
	 * uses `get_page_by_path()`, which is page-only by default.
	 */
	public function test_activation_ignores_post_with_same_slug_as_proposed_user_slug() {
		self::factory()->user->create(
			array(
				'display_name' => 'Some Article',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Some Article',
				'post_name'   => 'some-article',
			)
		);

		wp_author_slug_activation();

		$this->assertFalse(
			(bool) get_option( 'wp_author_slug_conflicts' ),
			'Expected no conflicts when only a non-page post shares the proposed slug.'
		);
	}

	/**
	 * Tests that `admin_notices` skips conflict entries whose user or page no
	 * longer exists, rather than emitting a malformed list item or fataling on
	 * a null user/page.
	 */
	public function test_admin_notices_skips_stale_conflict_entries() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		update_option(
			'wp_author_slug_conflicts',
			array(
				array(
					'user_id' => 999999,
					'page_id' => 999998,
				),
			)
		);

		wp_set_current_user( $admin_id );

		// On multisite the `edit_users` cap is restricted to super admins.
		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}

		ob_start();
		Obenland_Wp_Author_Slug::get_instance()->admin_notices();
		$output = ob_get_clean();

		// Wrapper still renders (early-return guards passed).
		$this->assertStringContainsString( 'notice-error', $output );
		// But no <li> should be rendered for the stale conflict entry.
		$this->assertStringNotContainsString( '<li>', $output );
	}
}
