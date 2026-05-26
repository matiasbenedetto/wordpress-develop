<?php
/**
 * Core Abilities registration.
 *
 * @package WordPress
 * @subpackage Abilities_API
 * @since 6.9.0
 */

declare( strict_types = 1 );

/**
 * Registers the core ability categories.
 *
 * @since 6.9.0
 */
function wp_register_core_ability_categories(): void {
	wp_register_ability_category(
		'site',
		array(
			'label'       => __( 'Site' ),
			'description' => __( 'Abilities that retrieve or modify site information and settings.' ),
		)
	);

	wp_register_ability_category(
		'user',
		array(
			'label'       => __( 'User' ),
			'description' => __( 'Abilities that retrieve or modify user information and settings.' ),
		)
	);
}

/**
 * Registers the default core abilities.
 *
 * @since 6.9.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function wp_register_core_abilities(): void {
	$category_site = 'site';
	$category_user = 'user';

	$site_info_properties = array(
		'name'        => array(
			'type'        => 'string',
			'description' => __( 'The site title.' ),
		),
		'description' => array(
			'type'        => 'string',
			'description' => __( 'The site tagline.' ),
		),
		'url'         => array(
			'type'        => 'string',
			'description' => __( 'The site home URL.' ),
		),
		'wpurl'       => array(
			'type'        => 'string',
			'description' => __( 'The WordPress installation URL.' ),
		),
		'admin_email' => array(
			'type'        => 'string',
			'description' => __( 'The site administrator email address.' ),
		),
		'charset'     => array(
			'type'        => 'string',
			'description' => __( 'The site character encoding.' ),
		),
		'language'    => array(
			'type'        => 'string',
			'description' => __( 'The site language locale code.' ),
		),
		'version'     => array(
			'type'        => 'string',
			'description' => __( 'The WordPress version.' ),
		),
	);
	$site_info_fields     = array_keys( $site_info_properties );

	wp_register_ability(
		'core/get-site-info',
		array(
			'label'               => __( 'Get Site Information' ),
			'description'         => __( 'Returns site information configured in WordPress. By default returns all fields, or optionally a filtered subset.' ),
			'category'            => $category_site,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'fields' => array(
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => $site_info_fields,
						),
						'description' => __( 'Optional: Limit response to specific fields. If omitted, all fields are returned.' ),
					),
				),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => $site_info_properties,
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = array() ) use ( $site_info_fields ): array {
				$input = is_array( $input ) ? $input : array();
				$requested_fields = ! empty( $input['fields'] ) ? $input['fields'] : $site_info_fields;

				$result = array();
				foreach ( $requested_fields as $field ) {
					if ( 'language' === $field ) {
						$result[ $field ] = str_replace( '_', '-', get_locale() );
					} else {
						$result[ $field ] = get_bloginfo( $field );
					}
				}

				return $result;
			},
			'permission_callback' => static function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
			),
		)
	);

	$user_info_properties = array(
		'id'            => array(
			'type'        => 'integer',
			'title'       => __( 'User ID' ),
			'description' => __( 'Unique numeric identifier for the user.' ),
		),
		'display_name'  => array(
			'type'        => 'string',
			'title'       => __( 'Display Name' ),
			'description' => __( 'Public-facing name selected by the user.' ),
		),
		'user_nicename' => array(
			'type'        => 'string',
			'title'       => __( 'User Nicename' ),
			'description' => __( 'URL-friendly slug for the user. Defaults to the username.' ),
		),
		'user_login'    => array(
			'type'        => 'string',
			'title'       => __( 'Username' ),
			'description' => __( 'Login identifier for the user. Cannot be changed once set.' ),
		),
		'roles'         => array(
			'type'        => 'array',
			'title'       => __( 'Roles' ),
			'description' => __( 'Roles assigned to the user, such as administrator, editor, author, contributor, or subscriber.' ),
			'items'       => array(
				'type' => 'string',
			),
		),
		'locale'        => array(
			'type'        => 'string',
			'title'       => __( 'Language' ),
			'description' => __( 'Locale code for the user, such as en_US.' ),
		),
		'first_name'    => array(
			'type'        => 'string',
			'title'       => __( 'First Name' ),
			'description' => __( 'Given name.' ),
		),
		'last_name'     => array(
			'type'        => 'string',
			'title'       => __( 'Last Name' ),
			'description' => __( 'Family name.' ),
		),
		'nickname'      => array(
			'type'        => 'string',
			'title'       => __( 'Nickname' ),
			'description' => __( 'Informal name. Defaults to the username.' ),
		),
		'description'   => array(
			'type'        => 'string',
			'title'       => __( 'Biographical Info' ),
			'description' => __( 'User-authored biography, often shown on author pages.' ),
		),
		'user_url'      => array(
			'type'        => 'string',
			'title'       => __( 'Website' ),
			'description' => __( 'Personal website URL.' ),
		),
	);
	$user_info_fields     = array_keys( $user_info_properties );

	wp_register_ability(
		'core/get-user-info',
		array(
			'label'               => __( 'Get User Information' ),
			'description'         => __( 'Returns profile details for the current authenticated user to support personalization, auditing, and access-aware behavior. By default returns all fields, or optionally a filtered subset.' ),
			'category'            => $category_user,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'fields' => array(
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => $user_info_fields,
						),
						'description' => __( 'Optional: Limit response to specific fields. If omitted, all fields are returned.' ),
					),
				),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => $user_info_properties,
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = array() ) use ( $user_info_fields ): array {
				$input            = is_array( $input ) ? $input : array();
				$requested_fields = ! empty( $input['fields'] ) ? $input['fields'] : $user_info_fields;
				$current_user     = wp_get_current_user();

				$all = array(
					'id'            => $current_user->ID,
					'display_name'  => $current_user->display_name,
					'user_nicename' => $current_user->user_nicename,
					'user_login'    => $current_user->user_login,
					// Ensure roles are encoded as a JSON array, regardless of their array keys.
					'roles'         => array_values( $current_user->roles ),
					'locale'        => get_user_locale( $current_user ),
					'first_name'    => $current_user->first_name,
					'last_name'     => $current_user->last_name,
					'nickname'      => $current_user->nickname,
					'description'   => $current_user->description,
					'user_url'      => $current_user->user_url,
				);

				return array_intersect_key( $all, array_flip( $requested_fields ) );
			},
			'permission_callback' => static function (): bool {
				return is_user_logged_in();
			},
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => false,
			),
		)
	);

	wp_register_ability(
		'core/get-environment-info',
		array(
			'label'               => __( 'Get Environment Info' ),
			'description'         => __( 'Returns core details about the site\'s runtime context for diagnostics and compatibility (environment, PHP runtime, database server info, WordPress version).' ),
			'category'            => $category_site,
			'output_schema'       => array(
				'type'                 => 'object',
				'required'             => array( 'environment', 'php_version', 'db_server_info', 'wp_version' ),
				'properties'           => array(
					'environment'    => array(
						'type'        => 'string',
						'description' => __( 'The site\'s runtime environment classification (can be one of these: production, staging, development, local).' ),
						'enum'        => array( 'production', 'staging', 'development', 'local' ),
					),
					'php_version'    => array(
						'type'        => 'string',
						'description' => __( 'The PHP runtime version executing WordPress.' ),
					),
					'db_server_info' => array(
						'type'        => 'string',
						'description' => __( 'The database server vendor and version string reported by the driver.' ),
					),
					'wp_version'     => array(
						'type'        => 'string',
						'description' => __( 'The WordPress core version running on this site.' ),
					),
				),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function (): array {
				global $wpdb;

				$env          = wp_get_environment_type();
				$php_version  = phpversion();
				$db_server_info  = '';
				if ( method_exists( $wpdb, 'db_server_info' ) ) {
					$db_server_info = $wpdb->db_server_info() ?? '';
				}
				$wp_version   = get_bloginfo( 'version' );

				return array(
					'environment'    => $env,
					'php_version'    => $php_version,
					'db_server_info' => $db_server_info,
					'wp_version'     => $wp_version,
				);
			},
			'permission_callback' => static function (): bool {
				return current_user_can( 'manage_options' );
			},
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
			),
		)
	);

	$theme_properties = array(
		'stylesheet'   => array(
			'type'        => 'string',
			'description' => __( 'The theme directory name (stylesheet slug).' ),
		),
		'template'     => array(
			'type'        => 'string',
			'description' => __( 'The template directory name. For child themes, this is the parent theme slug; for non-child themes, this is the same as `stylesheet`.' ),
		),
		'name'         => array(
			'type'        => 'string',
			'description' => __( 'The display name of the theme.' ),
		),
		'version'      => array(
			'type'        => 'string',
			'description' => __( 'The version of the theme.' ),
		),
		'description'  => array(
			'type'        => 'string',
			'description' => __( 'The description of the theme.' ),
		),
		'author'       => array(
			'type'        => 'string',
			'description' => __( 'The theme author name.' ),
		),
		'author_uri'   => array(
			'type'        => 'string',
			'format'      => 'uri',
			'description' => __( 'The theme author URL.' ),
		),
		'theme_uri'    => array(
			'type'        => 'string',
			'format'      => 'uri',
			'description' => __( 'The theme homepage URL.' ),
		),
		'status'       => array(
			'type'        => 'string',
			'enum'        => array( 'active', 'inactive' ),
			'description' => __( 'Whether the theme is currently active on the site.' ),
		),
		'parent'       => array(
			'type'        => array( 'string', 'null' ),
			'description' => __( 'The parent theme stylesheet slug, or null if this is not a child theme.' ),
		),
		'screenshot'   => array(
			'type'        => 'string',
			'format'      => 'uri',
			'description' => __( 'The URL of the theme screenshot, or an empty string if none.' ),
		),
		'tags'         => array(
			'type'        => 'array',
			'items'       => array( 'type' => 'string' ),
			'description' => __( 'The theme tags declared in the theme header.' ),
		),
		'requires_wp'  => array(
			'type'        => 'string',
			'description' => __( 'The minimum WordPress version required by the theme.' ),
		),
		'requires_php' => array(
			'type'        => 'string',
			'description' => __( 'The minimum PHP version required by the theme.' ),
		),
		'text_domain'  => array(
			'type'        => 'string',
			'description' => __( 'The theme text domain.' ),
		),
	);
	$theme_fields     = array_keys( $theme_properties );

	$prepare_theme_data = static function ( WP_Theme $theme, array $field_keys, bool $is_active ): array {
		$parent     = $theme->parent();
		$screenshot = $theme->get_screenshot();
		$tags       = $theme->get( 'Tags' );

		$all = array(
			'stylesheet'   => $theme->get_stylesheet(),
			'template'     => $theme->get_template(),
			'name'         => (string) $theme->get( 'Name' ),
			'version'      => (string) $theme->get( 'Version' ),
			'description'  => (string) $theme->get( 'Description' ),
			'author'       => (string) $theme->get( 'Author' ),
			'author_uri'   => (string) $theme->get( 'AuthorURI' ),
			'theme_uri'    => (string) $theme->get( 'ThemeURI' ),
			'status'       => $is_active ? 'active' : 'inactive',
			'parent'       => $parent ? $parent->get_stylesheet() : null,
			'screenshot'   => $screenshot ? (string) $screenshot : '',
			'tags'         => is_array( $tags ) ? array_values( $tags ) : array(),
			'requires_wp'  => (string) $theme->get( 'RequiresWP' ),
			'requires_php' => (string) $theme->get( 'RequiresPHP' ),
			'text_domain'  => (string) $theme->get( 'TextDomain' ),
		);

		return array_intersect_key( $all, $field_keys );
	};

	wp_register_ability(
		'core/get-active-theme',
		array(
			'label'               => __( 'Get Active Theme' ),
			'description'         => __( 'Returns information about the currently active theme. By default returns all fields, or optionally a filtered subset.' ),
			'category'            => $category_site,
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'fields' => array(
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => $theme_fields,
						),
						'description' => __( 'Optional: Limit response to specific fields. If omitted, all fields are returned.' ),
					),
				),
				'additionalProperties' => false,
				'default'              => array(),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => $theme_properties,
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = array() ) use ( $theme_fields, $prepare_theme_data ): array {
				$input            = is_array( $input ) ? $input : array();
				$requested_fields = ! empty( $input['fields'] ) ? $input['fields'] : $theme_fields;

				return $prepare_theme_data( wp_get_theme(), array_flip( $requested_fields ), true );
			},
			'permission_callback' => static function (): bool {
				return current_user_can( 'switch_themes' );
			},
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
			),
		)
	);

	wp_register_ability(
		'core/get-theme-info',
		array(
			'label'               => __( 'Get Theme Information' ),
			'description'         => __( 'Returns information about a specific installed theme by stylesheet slug. By default returns all fields, or optionally a filtered subset.' ),
			'category'            => $category_site,
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'stylesheet' ),
				'properties'           => array(
					'stylesheet' => array(
						'type'        => 'string',
						'minLength'   => 1,
						'pattern'     => '^[^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?$',
						'description' => __( 'The stylesheet slug (directory name) of the theme to look up.' ),
					),
					'fields'     => array(
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => $theme_fields,
						),
						'description' => __( 'Optional: Limit response to specific fields. If omitted, all fields are returned.' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => $theme_properties,
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input ) use ( $theme_fields, $prepare_theme_data ) {
				$input = is_array( $input ) ? $input : array();
				$theme = wp_get_theme( $input['stylesheet'] );

				if ( ! $theme->exists() ) {
					return new WP_Error(
						'theme_not_found',
						/* translators: %s: Theme stylesheet slug. */
						sprintf( __( 'The theme "%s" was not found.' ), $input['stylesheet'] ),
						array( 'status' => 404 )
					);
				}

				$requested_fields = ! empty( $input['fields'] ) ? $input['fields'] : $theme_fields;
				$is_active        = $theme->get_stylesheet() === wp_get_theme()->get_stylesheet();

				return $prepare_theme_data( $theme, array_flip( $requested_fields ), $is_active );
			},
			'permission_callback' => static function (): bool {
				return current_user_can( 'switch_themes' );
			},
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
			),
		)
	);

	wp_register_ability(
		'core/get-themes',
		array(
			'label'               => __( 'List Installed Themes' ),
			'description'         => __( 'Returns a list of all installed themes along with the slug of the currently active one.' ),
			'category'            => $category_site,
			'input_schema'        => array(
				'default' => (object) array(),
				'oneOf'   => array(
					// Branch 1: No filter (optionally with `fields` projection).
					array(
						'type'                 => 'object',
						'properties'           => array(
							'fields' => array(
								'type'        => 'array',
								'items'       => array(
									'type' => 'string',
									'enum' => $theme_fields,
								),
								'description' => __( 'Optional: Limit each theme entry to specific fields. If omitted, all fields are returned.' ),
							),
						),
						'additionalProperties' => false,
					),
					// Branch 2: Filter by status.
					array(
						'type'                 => 'object',
						'properties'           => array(
							'fields' => array(
								'type'        => 'array',
								'items'       => array(
									'type' => 'string',
									'enum' => $theme_fields,
								),
								'description' => __( 'Optional: Limit each theme entry to specific fields. If omitted, all fields are returned.' ),
							),
							'status' => array(
								'type'        => 'string',
								'enum'        => array( 'active', 'inactive' ),
								'description' => __( 'Filter the list to only include themes matching the given status.' ),
							),
						),
						'required'             => array( 'status' ),
						'additionalProperties' => false,
					),
					// Branch 3: Filter by stylesheets.
					array(
						'type'                 => 'object',
						'properties'           => array(
							'fields'      => array(
								'type'        => 'array',
								'items'       => array(
									'type' => 'string',
									'enum' => $theme_fields,
								),
								'description' => __( 'Optional: Limit each theme entry to specific fields. If omitted, all fields are returned.' ),
							),
							'stylesheets' => array(
								'type'        => 'array',
								'items'       => array(
									'type'      => 'string',
									'minLength' => 1,
									'pattern'   => '^[^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?$',
								),
								'minItems'    => 1,
								'description' => __( 'Filter the list to only include themes whose stylesheet slug matches one of the given values.' ),
							),
						),
						'required'             => array( 'stylesheets' ),
						'additionalProperties' => false,
					),
				),
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'required'             => array( 'active', 'themes' ),
				'properties'           => array(
					'active' => array(
						'type'        => 'string',
						'description' => __( 'The stylesheet slug of the currently active theme.' ),
					),
					'themes' => array(
						'type'        => 'array',
						'description' => __( 'The list of installed themes.' ),
						'items'       => array(
							'type'                 => 'object',
							'properties'           => $theme_properties,
							'additionalProperties' => false,
						),
					),
				),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input = array() ) use ( $theme_fields, $prepare_theme_data ): array {
				$input              = is_array( $input ) ? $input : array();
				$requested_fields   = ! empty( $input['fields'] ) ? $input['fields'] : $theme_fields;
				$field_keys         = array_flip( $requested_fields );
				$status_filter      = isset( $input['status'] ) ? $input['status'] : null;
				$stylesheets_filter = ! empty( $input['stylesheets'] ) ? array_flip( $input['stylesheets'] ) : null;

				$active_stylesheet = wp_get_theme()->get_stylesheet();

				$themes = array();
				foreach ( wp_get_themes() as $theme ) {
					$stylesheet = $theme->get_stylesheet();
					$is_active  = $stylesheet === $active_stylesheet;

					if ( 'active' === $status_filter && ! $is_active ) {
						continue;
					}
					if ( 'inactive' === $status_filter && $is_active ) {
						continue;
					}
					if ( null !== $stylesheets_filter && ! isset( $stylesheets_filter[ $stylesheet ] ) ) {
						continue;
					}

					$themes[] = $prepare_theme_data( $theme, $field_keys, $is_active );
				}

				return array(
					'active' => $active_stylesheet,
					'themes' => $themes,
				);
			},
			'permission_callback' => static function (): bool {
				return current_user_can( 'switch_themes' );
			},
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
			),
		)
	);

	wp_register_ability(
		'core/activate-theme',
		array(
			'label'               => __( 'Activate Theme' ),
			'description'         => __( 'Activates an installed theme by its stylesheet slug. Returns the previously active stylesheet and the newly active stylesheet.' ),
			'category'            => $category_site,
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'stylesheet' ),
				'properties'           => array(
					'stylesheet' => array(
						'type'        => 'string',
						'minLength'   => 1,
						'pattern'     => '^[^\/:<>\*\?"\|]+(?:\/[^\/:<>\*\?"\|]+)?$',
						'description' => __( 'The stylesheet slug (directory name) of the theme to activate.' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'required'             => array( 'stylesheet', 'previous_stylesheet', 'activated' ),
				'properties'           => array(
					'stylesheet'          => array(
						'type'        => 'string',
						'description' => __( 'The stylesheet slug of the now-active theme.' ),
					),
					'previous_stylesheet' => array(
						'type'        => 'string',
						'description' => __( 'The stylesheet slug of the theme that was active before this call.' ),
					),
					'activated'           => array(
						'type'        => 'boolean',
						'description' => __( 'Whether a theme switch occurred. False if the requested theme was already active.' ),
					),
				),
				'additionalProperties' => false,
			),
			'execute_callback'    => static function ( $input ) {
				$input = is_array( $input ) ? $input : array();
				$theme = wp_get_theme( $input['stylesheet'] );

				if ( ! $theme->exists() ) {
					return new WP_Error(
						'theme_not_found',
						/* translators: %s: Theme stylesheet slug. */
						sprintf( __( 'The theme "%s" was not found.' ), $input['stylesheet'] ),
						array( 'status' => 404 )
					);
				}

				if ( ! $theme->is_allowed() ) {
					return new WP_Error(
						'theme_not_allowed',
						/* translators: %s: Theme stylesheet slug. */
						sprintf( __( 'The theme "%s" is not allowed on this site.' ), $input['stylesheet'] ),
						array( 'status' => 403 )
					);
				}

				if ( $theme->errors() ) {
					return new WP_Error(
						'theme_broken',
						$theme->errors()->get_error_message(),
						array( 'status' => 409 )
					);
				}

				$previous  = wp_get_theme()->get_stylesheet();
				$requested = $theme->get_stylesheet();

				if ( $previous === $requested ) {
					return array(
						'stylesheet'          => $requested,
						'previous_stylesheet' => $previous,
						'activated'           => false,
					);
				}

				switch_theme( $requested );

				return array(
					'stylesheet'          => $requested,
					'previous_stylesheet' => $previous,
					'activated'           => true,
				);
			},
			'permission_callback' => static function (): bool {
				return current_user_can( 'switch_themes' );
			},
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
			),
		)
	);
}
