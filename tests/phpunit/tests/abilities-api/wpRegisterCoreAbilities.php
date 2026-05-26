<?php

declare( strict_types=1 );

/**
 * Tests for the core abilities shipped with the Abilities API.
 *
 * @covers wp_register_core_ability_categories
 * @covers wp_register_core_abilities
 *
 * @group abilities-api
 */
class Tests_Abilities_API_WpRegisterCoreAbilities extends WP_UnitTestCase {

	/**
	 * Set up before the class.
	 *
	 * @since 6.9.0
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// Ensure core abilities are registered for these tests.
		// Temporarily remove the unhook functions so we can register core abilities.
		remove_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		remove_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		// Add the core registration hooks and fire the actions.
		add_action( 'wp_abilities_api_categories_init', 'wp_register_core_ability_categories' );
		add_action( 'wp_abilities_api_init', 'wp_register_core_abilities' );
		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );
	}

	/**
	 * Tear down after the class.
	 *
	 * @since 6.9.0
	 */
	public static function tear_down_after_class(): void {
		// Re-add the unhook functions for subsequent tests.
		add_action( 'wp_abilities_api_categories_init', '_unhook_core_ability_categories_registration', 1 );
		add_action( 'wp_abilities_api_init', '_unhook_core_abilities_registration', 1 );

		// Remove the core abilities and their categories.
		foreach ( wp_get_abilities() as $ability ) {
			wp_unregister_ability( $ability->get_name() );
		}
		foreach ( wp_get_ability_categories() as $ability_category ) {
			wp_unregister_ability_category( $ability_category->get_slug() );
		}

		parent::tear_down_after_class();
	}

	/**
	 * Tests that the `core/get-site-info` ability is registered with the expected schema.
	 * @ticket 64146
	 */
	public function test_core_get_site_info_ability_is_registered(): void {
		$ability = wp_get_ability( 'core/get-site-info' );

		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ) );

		$input_schema  = $ability->get_input_schema();
		$output_schema = $ability->get_output_schema();

		$this->assertSame( 'object', $input_schema['type'] );
		$this->assertArrayHasKey( 'default', $input_schema );
		$this->assertSame( array(), $input_schema['default'] );

		// Input schema should have optional fields array.
		$this->assertArrayHasKey( 'fields', $input_schema['properties'] );
		$this->assertSame( 'array', $input_schema['properties']['fields']['type'] );
		$this->assertContains( 'name', $input_schema['properties']['fields']['items']['enum'] );

		// Output schema should have all fields documented.
		$this->assertArrayHasKey( 'name', $output_schema['properties'] );
		$this->assertArrayHasKey( 'url', $output_schema['properties'] );
		$this->assertArrayHasKey( 'version', $output_schema['properties'] );
	}

	/**
	 * Tests executing the `core/get-site-info` ability returns all fields by default.
	 * @ticket 64146
	 */
	public function test_core_get_site_info_executes(): void {
		// Requires manage_options.
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability = wp_get_ability( 'core/get-site-info' );

		// Test without fields parameter - should return all fields.
		$result = $ability->execute();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'description', $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertArrayHasKey( 'version', $result );
		$this->assertSame( get_bloginfo( 'name' ), $result['name'] );

		// Test with fields parameter - should return only requested fields.
		$result = $ability->execute(
			array(
				'fields' => array( 'name', 'url' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertSame( get_bloginfo( 'name' ), $result['name'] );
		$this->assertSame( get_bloginfo( 'url' ), $result['url'] );
	}

	/**
	 * Tests `core/get-site-info` language field returns the site locale, not the current user's locale.
	 * @ticket 64977
	 */
	public function test_core_get_site_info_language_uses_site_locale(): void {
		$admin_id = self::factory()->user->create(
			array(
				'role'   => 'administrator',
				'locale' => 'de_DE',
			)
		);
		wp_set_current_user( $admin_id );

		$ability = wp_get_ability( 'core/get-site-info' );
		$result  = $ability->execute(
			array(
				'fields' => array( 'language' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'language', $result );
		// Should return the site locale (en-US), not the user locale (de-DE).
		$this->assertSame( 'en-US', $result['language'] );
	}

	/**
	 * Tests that executing the current user info ability requires authentication.
	 * @ticket 64146
	 */
	public function test_core_get_current_user_info_requires_authentication(): void {
		$ability = wp_get_ability( 'core/get-user-info' );

		$this->assertFalse( $ability->check_permissions() );

		$result = $ability->execute();
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Tests executing the current user info ability as an authenticated user.
	 * @ticket 64146
	 */
	public function test_core_get_current_user_info_returns_user_data(): void {
		$user_id = self::factory()->user->create(
			array(
				'role'        => 'subscriber',
				'locale'      => 'fr_FR',
				'first_name'  => 'Jane',
				'last_name'   => 'Doe',
				'nickname'    => 'janed',
				'description' => 'Site contributor.',
				'user_url'    => 'https://example.com',
			)
		);

		wp_set_current_user( $user_id );

		$ability = wp_get_ability( 'core/get-user-info' );

		$this->assertTrue( $ability->check_permissions() );

		$result = $ability->execute();
		$this->assertSame( $user_id, $result['id'] );
		$this->assertSame( 'fr_FR', $result['locale'] );
		$this->assertSame( 'subscriber', $result['roles'][0] );
		$this->assertSame( get_userdata( $user_id )->display_name, $result['display_name'] );

		// New profile fields should be present by default.
		$this->assertSame( 'Jane', $result['first_name'] );
		$this->assertSame( 'Doe', $result['last_name'] );
		$this->assertSame( 'janed', $result['nickname'] );
		$this->assertSame( 'Site contributor.', $result['description'] );
		$this->assertSame( 'https://example.com', $result['user_url'] );
	}

	/**
	 * Tests that the `core/get-user-info` ability is registered with the expected schema.
	 * @ticket 65234
	 */
	public function test_core_get_user_info_ability_is_registered(): void {
		$ability = wp_get_ability( 'core/get-user-info' );

		$this->assertInstanceOf( WP_Ability::class, $ability );

		$input_schema  = $ability->get_input_schema();
		$output_schema = $ability->get_output_schema();

		// Input schema should expose an optional `fields` array with an enum of valid field names.
		$this->assertSame( 'object', $input_schema['type'] );
		$this->assertArrayHasKey( 'default', $input_schema );
		$this->assertSame( array(), $input_schema['default'] );
		$this->assertArrayHasKey( 'fields', $input_schema['properties'] );
		$this->assertSame( 'array', $input_schema['properties']['fields']['type'] );

		$enum = $input_schema['properties']['fields']['items']['enum'];
		foreach ( array( 'id', 'display_name', 'first_name', 'last_name', 'nickname', 'description', 'user_url' ) as $field ) {
			$this->assertContains( $field, $enum );
		}

		// Output schema should document the original and new profile fields with title + description.
		foreach ( array( 'id', 'display_name', 'first_name', 'last_name', 'nickname', 'description', 'user_url' ) as $field ) {
			$this->assertArrayHasKey( $field, $output_schema['properties'] );
			$this->assertArrayHasKey( 'title', $output_schema['properties'][ $field ] );
			$this->assertArrayHasKey( 'description', $output_schema['properties'][ $field ] );
		}
	}

	/**
	 * Tests that the `core/get-user-info` ability filters its output by the `fields` input parameter.
	 * @ticket 65234
	 */
	public function test_core_get_user_info_filters_fields(): void {
		$user_id = self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'first_name' => 'Jane',
				'last_name'  => 'Doe',
			)
		);
		wp_set_current_user( $user_id );

		$ability = wp_get_ability( 'core/get-user-info' );

		$result = $ability->execute(
			array(
				'fields' => array( 'display_name', 'first_name', 'last_name' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		$this->assertArrayHasKey( 'display_name', $result );
		$this->assertArrayHasKey( 'first_name', $result );
		$this->assertArrayHasKey( 'last_name', $result );
		$this->assertArrayNotHasKey( 'id', $result );
		$this->assertArrayNotHasKey( 'roles', $result );
		$this->assertSame( 'Jane', $result['first_name'] );
		$this->assertSame( 'Doe', $result['last_name'] );
	}

	/**
	 * Tests that the `core/get-user-info` ability rejects unknown field names via schema validation.
	 * @ticket 65234
	 */
	public function test_core_get_user_info_rejects_invalid_fields(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$ability = wp_get_ability( 'core/get-user-info' );

		$result = $ability->execute(
			array(
				'fields' => array( 'display_name', 'not_a_real_field' ),
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Tests executing the environment info ability.
	 * @ticket 64146
	 */
	public function test_core_get_environment_info_executes(): void {
		// Requires manage_options.
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability      = wp_get_ability( 'core/get-environment-info' );
		$environment  = wp_get_environment_type();
		$ability_data = $ability->execute();

		$this->assertIsArray( $ability_data );
		$this->assertArrayHasKey( 'environment', $ability_data );
		$this->assertArrayHasKey( 'php_version', $ability_data );
		$this->assertArrayHasKey( 'db_server_info', $ability_data );
		$this->assertArrayHasKey( 'wp_version', $ability_data );
		$this->assertSame( $environment, $ability_data['environment'] );
	}

	/**
	 * Tests that the `core/get-active-theme` ability is registered with the expected schema.
	 */
	public function test_core_get_active_theme_ability_is_registered(): void {
		$ability = wp_get_ability( 'core/get-active-theme' );

		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest', false ) );

		$input_schema  = $ability->get_input_schema();
		$output_schema = $ability->get_output_schema();

		$this->assertSame( 'object', $input_schema['type'] );
		$this->assertArrayHasKey( 'default', $input_schema );
		$this->assertSame( array(), $input_schema['default'] );

		// Input schema should expose an optional `fields` array with enum.
		$this->assertArrayHasKey( 'fields', $input_schema['properties'] );
		$this->assertSame( 'array', $input_schema['properties']['fields']['type'] );
		foreach ( array( 'stylesheet', 'template', 'name', 'version', 'parent', 'tags' ) as $field ) {
			$this->assertContains( $field, $input_schema['properties']['fields']['items']['enum'] );
			$this->assertArrayHasKey( $field, $output_schema['properties'] );
		}
	}

	/**
	 * Tests executing the `core/get-active-theme` ability returns all fields by default.
	 */
	public function test_core_get_active_theme_executes(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability = wp_get_ability( 'core/get-active-theme' );

		$result = $ability->execute();
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'stylesheet', $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayHasKey( 'version', $result );
		$this->assertSame( wp_get_theme()->get_stylesheet(), $result['stylesheet'] );

		// Filter fields.
		$result = $ability->execute(
			array(
				'fields' => array( 'stylesheet', 'name' ),
			)
		);
		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );
		$this->assertArrayHasKey( 'stylesheet', $result );
		$this->assertArrayHasKey( 'name', $result );
		$this->assertArrayNotHasKey( 'version', $result );
	}

	/**
	 * Tests that `core/get-active-theme` requires the `switch_themes` capability.
	 */
	public function test_core_get_active_theme_requires_switch_themes(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$ability = wp_get_ability( 'core/get-active-theme' );
		$this->assertFalse( $ability->check_permissions() );

		$result = $ability->execute();
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Tests executing the `core/get-theme-info` ability.
	 */
	public function test_core_get_theme_info_executes(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability    = wp_get_ability( 'core/get-theme-info' );
		$stylesheet = wp_get_theme()->get_stylesheet();

		$result = $ability->execute( array( 'stylesheet' => $stylesheet ) );

		$this->assertIsArray( $result );
		$this->assertSame( $stylesheet, $result['stylesheet'] );
		$this->assertArrayHasKey( 'name', $result );

		// Filter fields.
		$result = $ability->execute(
			array(
				'stylesheet' => $stylesheet,
				'fields'     => array( 'stylesheet', 'name' ),
			)
		);
		$this->assertCount( 2, $result );
	}

	/**
	 * Tests that `core/get-theme-info` returns an error for a missing theme.
	 */
	public function test_core_get_theme_info_returns_error_for_missing_theme(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability = wp_get_ability( 'core/get-theme-info' );

		$result = $ability->execute( array( 'stylesheet' => 'this-theme-does-not-exist' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'theme_not_found', $result->get_error_code() );
	}

	/**
	 * Tests that `core/get-theme-info` requires the `stylesheet` input.
	 */
	public function test_core_get_theme_info_requires_stylesheet(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability = wp_get_ability( 'core/get-theme-info' );

		$result = $ability->execute( array() );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Tests executing the `core/get-themes` ability.
	 */
	public function test_core_get_themes_executes(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability = wp_get_ability( 'core/get-themes' );

		$result = $ability->execute();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'active', $result );
		$this->assertArrayHasKey( 'themes', $result );
		$this->assertSame( wp_get_theme()->get_stylesheet(), $result['active'] );
		$this->assertNotEmpty( $result['themes'] );

		$active_slug   = $result['active'];
		$found_active  = false;
		foreach ( $result['themes'] as $theme ) {
			$this->assertArrayHasKey( 'stylesheet', $theme );
			$this->assertArrayHasKey( 'name', $theme );
			if ( $theme['stylesheet'] === $active_slug ) {
				$found_active = true;
			}
		}
		$this->assertTrue( $found_active, 'The active theme should appear in the list.' );
	}

	/**
	 * Tests filtering `core/get-themes` output via the `fields` parameter.
	 */
	public function test_core_get_themes_filters_fields(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability = wp_get_ability( 'core/get-themes' );

		$result = $ability->execute(
			array(
				'fields' => array( 'stylesheet' ),
			)
		);

		$this->assertIsArray( $result );
		foreach ( $result['themes'] as $theme ) {
			$this->assertSame( array( 'stylesheet' ), array_keys( $theme ) );
		}
	}

	/**
	 * Tests that `core/get-themes` filters by the `status` input.
	 */
	public function test_core_get_themes_filters_by_status(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability           = wp_get_ability( 'core/get-themes' );
		$active_stylesheet = wp_get_theme()->get_stylesheet();

		// Filter: active only.
		$result = $ability->execute( array( 'status' => 'active' ) );
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['themes'] );
		$this->assertSame( $active_stylesheet, $result['themes'][0]['stylesheet'] );
		$this->assertSame( 'active', $result['themes'][0]['status'] );

		// Filter: inactive only.
		$result = $ability->execute( array( 'status' => 'inactive' ) );
		$this->assertIsArray( $result );
		// The active theme must be absent and every returned theme must be inactive.
		foreach ( $result['themes'] as $theme ) {
			$this->assertNotSame( $active_stylesheet, $theme['stylesheet'] );
			$this->assertSame( 'inactive', $theme['status'] );
		}

		// Top-level `active` field is preserved regardless of filter.
		$this->assertSame( $active_stylesheet, $result['active'] );
	}

	/**
	 * Tests that `core/get-themes` rejects an invalid `status` value at the
	 * schema layer.
	 */
	public function test_core_get_themes_rejects_invalid_status(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability = wp_get_ability( 'core/get-themes' );
		$result  = $ability->execute( array( 'status' => 'publish' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Tests that the theme `status` field exposes the active/inactive state of
	 * the theme rather than the raw `Status:` header value.
	 */
	public function test_core_theme_abilities_status_reflects_active_state(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$active_stylesheet = wp_get_theme()->get_stylesheet();

		// `core/get-active-theme` always reports active.
		$result = wp_get_ability( 'core/get-active-theme' )->execute();
		$this->assertSame( 'active', $result['status'] );

		// `core/get-theme-info` reports active for the current theme.
		$result = wp_get_ability( 'core/get-theme-info' )->execute( array( 'stylesheet' => $active_stylesheet ) );
		$this->assertSame( 'active', $result['status'] );

		// `core/get-themes` reports active only for the current theme.
		$result = wp_get_ability( 'core/get-themes' )->execute();
		$saw_active = false;
		foreach ( $result['themes'] as $theme ) {
			if ( $theme['stylesheet'] === $active_stylesheet ) {
				$this->assertSame( 'active', $theme['status'] );
				$saw_active = true;
			} else {
				$this->assertSame( 'inactive', $theme['status'] );
			}
		}
		$this->assertTrue( $saw_active, 'The active theme should appear in the list.' );
	}

	/**
	 * Tests that the `core/activate-theme` ability is registered with destructive annotation.
	 */
	public function test_core_activate_theme_is_destructive(): void {
		$ability = wp_get_ability( 'core/activate-theme' );

		$this->assertInstanceOf( WP_Ability::class, $ability );
		$annotations = $ability->get_meta_item( 'annotations', array() );
		$this->assertFalse( $annotations['readonly'] );
		$this->assertTrue( $annotations['destructive'] );
	}

	/**
	 * Tests that `core/activate-theme` requires the `switch_themes` capability.
	 */
	public function test_core_activate_theme_requires_switch_themes(): void {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$ability = wp_get_ability( 'core/activate-theme' );
		$this->assertFalse( $ability->check_permissions() );

		$result = $ability->execute( array( 'stylesheet' => wp_get_theme()->get_stylesheet() ) );
		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * Tests that `core/activate-theme` returns an error for a missing theme.
	 */
	public function test_core_activate_theme_returns_error_for_missing_theme(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability = wp_get_ability( 'core/activate-theme' );

		$result = $ability->execute( array( 'stylesheet' => 'this-theme-does-not-exist' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'theme_not_found', $result->get_error_code() );
	}

	/**
	 * Tests that `core/activate-theme` is a no-op when activating the already-active theme.
	 */
	public function test_core_activate_theme_no_op_when_already_active(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability    = wp_get_ability( 'core/activate-theme' );
		$stylesheet = wp_get_theme()->get_stylesheet();

		$result = $ability->execute( array( 'stylesheet' => $stylesheet ) );

		$this->assertIsArray( $result );
		$this->assertSame( $stylesheet, $result['stylesheet'] );
		$this->assertSame( $stylesheet, $result['previous_stylesheet'] );
		$this->assertFalse( $result['activated'] );
	}

	/**
	 * Tests that `core/activate-theme` switches the theme.
	 */
	public function test_core_activate_theme_switches_theme(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}

		$themes = wp_get_themes();
		if ( count( $themes ) < 2 ) {
			$this->markTestSkipped( 'At least two installed themes are required to test theme switching.' );
		}

		$previous_stylesheet = wp_get_theme()->get_stylesheet();
		$target              = null;
		foreach ( $themes as $theme ) {
			if ( $theme->get_stylesheet() !== $previous_stylesheet ) {
				$target = $theme->get_stylesheet();
				break;
			}
		}
		$this->assertNotNull( $target );

		// On multisite, themes must be network-enabled to be allowed for activation.
		$allow_target = static function ( $allowed ) use ( $target ) {
			$allowed[ $target ] = true;
			return $allowed;
		};
		add_filter( 'allowed_themes', $allow_target );

		try {
			$ability = wp_get_ability( 'core/activate-theme' );
			$result  = $ability->execute( array( 'stylesheet' => $target ) );

			$this->assertIsArray( $result );
			$this->assertSame( $target, $result['stylesheet'] );
			$this->assertSame( $previous_stylesheet, $result['previous_stylesheet'] );
			$this->assertTrue( $result['activated'] );
			$this->assertSame( $target, wp_get_theme()->get_stylesheet() );
		} finally {
			remove_filter( 'allowed_themes', $allow_target );
			// Restore the previously active theme so other tests are unaffected.
			switch_theme( $previous_stylesheet );
		}
	}

	/**
	 * Tests that `core/get-theme-info` and `core/activate-theme` reject
	 * stylesheet inputs that contain filesystem-reserved characters before
	 * reaching the execute callback.
	 *
	 * @dataProvider data_invalid_stylesheet_inputs
	 *
	 * @param string $ability_name Ability name to invoke.
	 * @param string $stylesheet   Invalid stylesheet input.
	 */
	public function test_core_theme_abilities_reject_invalid_stylesheet( string $ability_name, string $stylesheet ): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$ability = wp_get_ability( $ability_name );
		$result  = $ability->execute( array( 'stylesheet' => $stylesheet ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	/**
	 * Data provider for invalid stylesheet inputs.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function data_invalid_stylesheet_inputs(): array {
		return array(
			'get-theme-info: empty string'    => array( 'core/get-theme-info', '' ),
			'get-theme-info: pipe character'  => array( 'core/get-theme-info', 'theme|name' ),
			'get-theme-info: wildcard'        => array( 'core/get-theme-info', 'theme*' ),
			'get-theme-info: angle bracket'   => array( 'core/get-theme-info', 'theme<name' ),
			'activate-theme: empty string'    => array( 'core/activate-theme', '' ),
			'activate-theme: colon character' => array( 'core/activate-theme', 'theme:name' ),
			'activate-theme: question mark'   => array( 'core/activate-theme', 'theme?' ),
		);
	}

	/**
	 * Tests that `core/activate-theme` returns an error for a broken theme
	 * (e.g., missing style.css).
	 */
	public function test_core_activate_theme_returns_error_for_broken_theme(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		if ( is_multisite() ) {
			grant_super_admin( $admin_id );
		}

		// Register the test themes directory, which contains a `broken-theme` fixture
		// (an empty directory missing style.css).
		$test_theme_root  = realpath( DIR_TESTDATA . '/themedir1' );
		$orig_directories = $GLOBALS['wp_theme_directories'];

		$GLOBALS['wp_theme_directories'] = array( WP_CONTENT_DIR . '/themes', $test_theme_root );

		$filter_root = static function () use ( $test_theme_root ) {
			return $test_theme_root;
		};
		add_filter( 'theme_root', $filter_root );
		add_filter( 'stylesheet_root', $filter_root );
		add_filter( 'template_root', $filter_root );

		// On multisite, `is_allowed()` runs before the broken-theme check, so the
		// fixture must be network-enabled to reach the code under test.
		$allow_broken = static function ( $allowed ) {
			$allowed['broken-theme'] = true;
			return $allowed;
		};
		add_filter( 'allowed_themes', $allow_broken );

		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );

		try {
			$ability = wp_get_ability( 'core/activate-theme' );
			$result  = $ability->execute( array( 'stylesheet' => 'broken-theme' ) );

			$this->assertWPError( $result );
			$this->assertSame( 'theme_broken', $result->get_error_code() );
			$this->assertSame( 409, $result->get_error_data()['status'] );
		} finally {
			remove_filter( 'theme_root', $filter_root );
			remove_filter( 'stylesheet_root', $filter_root );
			remove_filter( 'template_root', $filter_root );
			remove_filter( 'allowed_themes', $allow_broken );
			$GLOBALS['wp_theme_directories'] = $orig_directories;
			wp_clean_themes_cache();
			unset( $GLOBALS['wp_themes'] );
		}
	}

	/**
	 * Tests that `core/activate-theme` returns an error for a theme that is not
	 * allowed on the site.
	 *
	 * `WP_Theme::is_allowed()` only returns false on multisite, so this test
	 * requires a multisite installation.
	 *
	 * @group ms-required
	 */
	public function test_core_activate_theme_returns_error_for_disallowed_theme(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		grant_super_admin( $admin_id );

		$themes = wp_get_themes();
		$target = null;
		foreach ( $themes as $theme ) {
			if ( $theme->get_stylesheet() !== wp_get_theme()->get_stylesheet() ) {
				$target = $theme->get_stylesheet();
				break;
			}
		}
		if ( null === $target ) {
			$this->markTestSkipped( 'At least two installed themes are required for this test.' );
		}

		// Force the theme to be disallowed on both network and site levels.
		$deny_all = '__return_empty_array';
		add_filter( 'allowed_themes', $deny_all );
		add_filter( 'network_allowed_themes', $deny_all );
		add_filter( 'site_allowed_themes', $deny_all );

		try {
			$ability = wp_get_ability( 'core/activate-theme' );
			$result  = $ability->execute( array( 'stylesheet' => $target ) );

			$this->assertWPError( $result );
			$this->assertSame( 'theme_not_allowed', $result->get_error_code() );
			$this->assertSame( 403, $result->get_error_data()['status'] );
		} finally {
			remove_filter( 'allowed_themes', $deny_all );
			remove_filter( 'network_allowed_themes', $deny_all );
			remove_filter( 'site_allowed_themes', $deny_all );
		}
	}

	/**
	 * Tests that all core ability schemas only use valid JSON Schema keywords.
	 *
	 * This prevents regressions where invalid keywords like 'examples' are used
	 * in schema properties (not valid in JSON Schema draft-04 used by WordPress).
	 *
	 * @ticket 64384
	 */
	public function test_core_abilities_schemas_use_only_valid_keywords(): void {
		$allowed_keywords = rest_get_allowed_schema_keywords();
		// Add 'required' which is valid at the property level for draft-04.
		$allowed_keywords[] = 'required';

		$abilities = wp_get_abilities();

		$this->assertNotEmpty( $abilities, 'Core abilities should be registered.' );

		foreach ( $abilities as $ability ) {
			$this->assert_schema_uses_valid_keywords(
				$ability->get_input_schema(),
				$allowed_keywords,
				$ability->get_name() . ' input_schema'
			);
			$this->assert_schema_uses_valid_keywords(
				$ability->get_output_schema(),
				$allowed_keywords,
				$ability->get_name() . ' output_schema'
			);
		}
	}

	/**
	 * Recursively validates that a schema only uses allowed keywords.
	 *
	 * @param array|null $schema           The schema to validate.
	 * @param string[]   $allowed_keywords List of allowed schema keywords.
	 * @param string     $context          Context for error messages.
	 */
	private function assert_schema_uses_valid_keywords( ?array $schema, array $allowed_keywords, string $context ): void {
		if ( null === $schema ) {
			return;
		}

		foreach ( $schema as $key => $value ) {
			// Skip integer keys (array indices).
			if ( is_int( $key ) ) {
				continue;
			}

			// These keywords contain nested schemas that we recurse into.
			$nesting_keywords = array( 'properties', 'items', 'additionalProperties', 'patternProperties', 'anyOf', 'oneOf' );

			if ( ! in_array( $key, $nesting_keywords, true ) && ! in_array( $key, $allowed_keywords, true ) ) {
				$this->fail( "Invalid schema keyword '{$key}' found in {$context}. Valid keywords are: " . implode( ', ', $allowed_keywords ) );
			}

			// Recursively check nested schemas.
			if ( 'properties' === $key && is_array( $value ) ) {
				foreach ( $value as $prop_name => $prop_schema ) {
					$this->assert_schema_uses_valid_keywords(
						$prop_schema,
						$allowed_keywords,
						"{$context}.properties.{$prop_name}"
					);
				}
			} elseif ( 'items' === $key && is_array( $value ) ) {
				$this->assert_schema_uses_valid_keywords(
					$value,
					$allowed_keywords,
					"{$context}.items"
				);
			} elseif ( ( 'anyOf' === $key || 'oneOf' === $key ) && is_array( $value ) ) {
				foreach ( $value as $index => $sub_schema ) {
					$this->assert_schema_uses_valid_keywords(
						$sub_schema,
						$allowed_keywords,
						"{$context}.{$key}[{$index}]"
					);
				}
			} elseif ( 'additionalProperties' === $key && is_array( $value ) ) {
				$this->assert_schema_uses_valid_keywords(
					$value,
					$allowed_keywords,
					"{$context}.additionalProperties"
				);
			}
		}
	}
}
