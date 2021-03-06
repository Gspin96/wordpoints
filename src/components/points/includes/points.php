<?php

/**
 * Points API functions.
 *
 * These are the general functions used by the points component. They cover the user
 * points API, the points types API, and the points log meta API.
 *
 * @package WordPoints\Points
 * @since 1.0.0
 */

/**
 * Check if a points type exists by slug.
 *
 * @since 1.0.0
 *
 * @param string $slug Test if this is the slug of a points type.
 *
 * @return bool Whether a points type with the given slug exists.
 */
function wordpoints_is_points_type( $slug ) {

	$points_types = wordpoints_get_points_types();

	return isset( $points_types[ $slug ] );
}

/**
 * Get all points types.
 *
 * Returns a multidimensional array of all the points types, indexed by slug.
 * Each value is an associative array, with the keys 'name', 'prefix', and
 * 'suffix'. Other data may be added by plugins and modules.
 *
 * Example:
 * <code>
 * array(
 *      'points' => array(
 *               'name'   => 'Points',
 *               'prefix' => '$',
 *               'suffix' => '',
 *      ),
 *      'another-points' => array(
 *               'name'   => 'Another Points',
 *               'prefix' => '',
 *               'suffix' => 'points',
 *      ),
 * )
 * </code>
 *
 * @since 1.0.0
 *
 * @return array An array of all points types.
 */
function wordpoints_get_points_types() {

	return wordpoints_get_maybe_network_array_option( 'wordpoints_points_types' );
}

/**
 * Get the settings for a points type by slug.
 *
 * @since 1.0.0
 *
 * @param string $slug The slug of a points type.
 *
 * @return array|false An array of settings for this points type. False on failure.
 */
function wordpoints_get_points_type( $slug ) {

	$points_types = wordpoints_get_points_types();

	if ( ! isset( $points_types[ $slug ] ) ) {
		return false;
	}

	return $points_types[ $slug ];
}

/**
 * Get a setting for a type of points.
 *
 * Examples of points type settings are 'prefix', 'suffix', etc. Custom settings
 * may be added as well.
 *
 * @since 1.0.0
 *
 * @param string $slug    The points type to retrieve a setting for.
 * @param string $setting The setting to retrieve.
 *
 * @return string|null The value of the setting if it exists, otherwise null.
 */
function wordpoints_get_points_type_setting( $slug, $setting ) {

	$points_type = wordpoints_get_points_type( $slug );

	if ( isset( $points_type[ $setting ] ) ) {
		return $points_type[ $setting ];
	}

	return null;
}

/**
 * Create a new type of points.
 *
 * This adds a new entry to the array of points types saved in the database.
 *
 * @since 1.0.0
 *
 * @uses sanitize_key() To generate the slug.
 *
 * @param array $settings The data for this points type.
 *
 * @return string|false The slug on success. False on failure.
 */
function wordpoints_add_points_type( $settings ) {

	if ( ! is_array( $settings ) || ! isset( $settings['name'] ) ) {
		return false;
	}

	$slug = $settings['name'];
	$slug = preg_replace( '/\s+/', '-', $slug );
	$slug = preg_replace( '/-+/', '-', $slug );
	$slug = trim( $slug, '-' );
	$slug = sanitize_key( $slug );

	$points_types = wordpoints_get_points_types();

	if ( empty( $slug ) || isset( $points_types[ $slug ] ) ) {
		return false;
	}

	/**
	 * Points type settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $settings The settings for a points type.
	 * @param string $slug     The slug for this points type.
	 * @param bool   $is_new   Whether this points type is new, or being updated.
	 */
	$points_types[ $slug ] = apply_filters( 'wordpoints_points_settings', $settings, $slug, true );

	if ( ! wordpoints_update_maybe_network_option( 'wordpoints_points_types', $points_types ) ) {
		return false;
	}

	return $slug;
}

/**
 * Update the settings for a type of points.
 *
 * @since 1.0.0
 *
 * @param string $slug     The slug for the points type to update.
 * @param array  $settings The new settings for this points type.
 *
 * @return bool True, or false on failure, or if this points type does not exist.
 */
function wordpoints_update_points_type( $slug, $settings ) {

	$points_types = wordpoints_get_points_types();

	if ( ! is_array( $settings ) || ! isset( $points_types[ $slug ], $settings['name'] ) ) {
		return false;
	}

	/**
	 * @see wordpoints_add_points_type()
	 */
	$points_types[ $slug ] = apply_filters( 'wordpoints_points_settings', $settings, $slug, false );

	return wordpoints_update_maybe_network_option( 'wordpoints_points_types', $points_types );
}

/**
 * Delete a points type.
 *
 * This function will deregister this points type slug and delete all associated
 * logs, log meta, user points, and points hooks.
 *
 * @since 1.0.0
 *
 * @param string $slug The slug of the points type to delete.
 *
 * @return bool Whether the points type was deleted successfully.
 */
function wordpoints_delete_points_type( $slug ) {

	$points_types = wordpoints_get_points_types();

	if ( ! isset( $points_types[ $slug ] ) ) {
		return false;
	}

	/**
	 * Fires when a points type is being deleted.
	 *
	 * @since 2.1.0
	 *
	 * @param string $slug      The slug of the points type being deleted.
	 * @param array  $settings The settings of the points type being deleted.
	 */
	do_action( 'wordpoints_delete_points_type', $slug, $points_types[ $slug ] );

	$meta_key = wordpoints_get_points_user_meta_key( $slug );

	global $wpdb;

	// Delete log meta for this points type.
	$query = new WordPoints_Points_Logs_Query(
		array( 'field' => 'id', 'points_type' => $slug )
	);

	$log_ids = $query->get( 'col' );

	foreach ( $log_ids as $log_id ) {
		wordpoints_points_log_delete_all_metadata( $log_id );
	}

	// Delete logs for this points type.
	$wpdb->delete( $wpdb->wordpoints_points_logs, array( 'points_type' => $slug ) );

	wordpoints_flush_points_logs_caches( array( 'points_type' => $slug ) );

	// Delete all user points of this type.
	delete_metadata( 'user', 0, wp_slash( $meta_key ), '', true );

	// Delete hooks associated with this points type.
	$points_types_hooks = WordPoints_Points_Hooks::get_points_types_hooks();

	unset( $points_types_hooks[ $slug ] );

	WordPoints_Points_Hooks::save_points_types_hooks( $points_types_hooks );

	// Delete reactions associated with this points type.
	foreach ( wordpoints_hooks()->get_reaction_stores( 'points' ) as $reaction_store ) {
		foreach ( $reaction_store->get_reactions() as $reaction ) {
			if ( $slug === $reaction->get_meta( 'points_type' ) ) {
				$reaction_store->delete_reaction( $reaction->get_id() );
			}
		}
	}

	unset( $points_types[ $slug ] );

	wordpoints_update_maybe_network_option(
		'wordpoints_points_types'
		, $points_types
	);

	return true;
}

/**
 * Get the meta key for a points type's user meta.
 *
 * The number of points a user has is stored in the user meta. This function was
 * introduced to allow the meta_key for that value to be retrieved easily internally.
 * If the meta_key setting for the points type is set, that is used. Otherwise the
 * meta key is "wordpoints_points-{$type}" for single sites, and when network
 * active on multisite; and when not network-active on multisite, the key is prefixed
 * with the blog's table prefix, to avoid collisions from different blogs.
 *
 * Note that because it uses is_wordpoints_network_active(), it can only be trusted
 * when the plugin is actually active. It won't work when uninstalling, for example.
 *
 * Also be careful, because if the points type doesn't exist, false will be
 * returned.
 *
 * @since 1.2.0
 * @since 1.3.0 Now checks the meta_key points type setting.
 *
 * @param string $points_type The slug of the points type to get the meta key for.
 *
 * @return string|false The user meta meta_key for a points type, or false.
 */
function wordpoints_get_points_user_meta_key( $points_type ) {

	if ( ! wordpoints_is_points_type( $points_type ) ) {
		return false;
	}

	$setting = wordpoints_get_points_type_setting( $points_type, 'meta_key' );

	if ( ! empty( $setting ) ) {

		$meta_key = $setting;

	} elseif ( ! is_multisite() || is_wordpoints_network_active() ) {

		$meta_key = "wordpoints_points-{$points_type}";

	} else {

		global $wpdb;

		$meta_key = $wpdb->get_blog_prefix() . "wordpoints_points-{$points_type}";
	}

	return $meta_key;
}

/**
 * Get the number of points a user has.
 *
 * If an invalid user ID or points type is passed, false will be returned.
 *
 * @since 1.0.0
 *
 * @param int    $user_id The ID of a user.
 * @param string $type    A points type slug.
 *
 * @return int|false The user's points, or false on failure.
 */
function wordpoints_get_points( $user_id, $type ) {

	if ( ! wordpoints_posint( $user_id ) || ! wordpoints_is_points_type( $type ) ) {
		return false;
	}

	$points = get_user_meta( $user_id, wordpoints_get_points_user_meta_key( $type ), true );

	return (int) wordpoints_int( $points );
}

/**
 * Get the minimum amount for a type of points.
 *
 * This function exists to allow a you to set a minimum number of points. You can
 * set the default minimum to be -100 like this:
 * <code>
 * function my_wordpoints_minimum( $minimum ) {
 *
 *      return -100;
 * }
 * add_filter( 'wordpoints_points_minimum', 'my_wordpoints_minimum' );
 * </code>
 *
 * The default minimum can be overridden for a particular type of points like so:
 * <code>
 * function my_wordpoints_minimum_score( $minimum, $type ) {
 *
 *     if ( 'score' === $type ) {
 *           $minimum = 5;
 *     }
 *
 *     return $minimum;
 * }
 * add_filter( 'wordpoints_points_minimum', 'my_wordpoints_minimum_score', 15, 2 );
 * </code>
 *
 * That would set the minimum for the points type with the slug 'score' to 5.
 *
 * The minimum is cached, so it will only be generated once per points type per
 * script execution.
 *
 * @since 1.0.0
 *
 * @uses apply_filters() To apply the 'wordpoints_points_minimum' filter.
 *
 * @param string $type The slug for a points type.
 *
 * @return int|false The minimum for this type of points. False if $type is bad.
 */
function wordpoints_get_points_minimum( $type ) {

	if ( ! wordpoints_is_points_type( $type ) ) {
		return false;
	}

	/**
	 * The minimum number of points.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $minimum The minimum number of points.
	 * @param string $type    The points type slug.
	 */
	return apply_filters( 'wordpoints_points_minimum', 0, $type );
}

/**
 * Format points value for display.
 *
 * This function should always be used when displaying points. It will return the
 * integer value of $points formatted for display as desired by the user (with the
 * prefix and suffix, for instance). If $points or $type are invalid, $points will
 * be returned unformatted.
 *
 * @since 1.0.0
 *
 * @uses apply_filters() To filter the points with 'wordpoints_format_points'.
 *
 * @param int    $points  The point value.
 * @param string $type    The type of points.
 * @param string $context The context in which the points will be displayed.
 *
 * @return string The integer value of $points formatted for display.
 */
function wordpoints_format_points( $points, $type, $context ) {

	$_points = $points;
	wordpoints_int( $_points );

	if ( false === $_points || ! wordpoints_is_points_type( $type ) ) {
		return (string) $points;
	}

	/**
	 * Format points for display.
	 *
	 * @since 1.0.0
	 *
	 * @param string $formatted The formatted value.
	 * @param int    $points    The raw points value.
	 * @param string $type      The type of points.
	 * @param string $context   The context in which the points will be displayed.
	 */
	return apply_filters( 'wordpoints_format_points', $_points, $_points, $type, $context );
}

/**
 * Get a user's points pre-formatted for display.
 *
 * @since 1.0.0
 *
 * @uses wordpoints_get_points()    To get the users points.
 * @uses wordpoints_format_points() To format the users points for display.
 *
 * @param int    $user_id The ID of the user whose points to get.
 * @param string $type    The type of points to retrieve.
 * @param string $context The context in which the users points will be displayed.
 *
 * @return string|false The user's points formatted for display, or false on failure.
 */
function wordpoints_get_formatted_points( $user_id, $type, $context ) {

	$points = wordpoints_get_points( $user_id, $type );

	if ( false === $points ) {
		return false;
	}

	return wordpoints_format_points( $points, $type, $context );
}

/**
 * Display a user's points properly formatted.
 *
 * If $type is not a valid points type, then nothing will be displayed.
 *
 * @since 1.0.0
 *
 * @param int    $user_id The ID of the user whose points to display.
 * @param string $type    The type of points to display.
 * @param string $context The context in which the points will be displayed.
 */
function wordpoints_display_points( $user_id, $type, $context ) {

	echo wordpoints_get_formatted_points( $user_id, $type, $context ); // XSS OK, WPCS
}

/**
 * Get the number of points a user has more than the minimum.
 *
 * Using this function is the proper way to determine how many 'usable' points a
 * user has. It is *not* safe just to assume that 0 is the minimum {@see
 * wordpoints_get_minimum_points()}.
 *
 * Note that, although in some rare situations it is conceivable that a user
 * could have less than the minimum, the smallest number returned by this
 * function will always be 0.
 *
 * @since 1.0.0
 *
 * @uses wordpoints_get_points_minimum() To get the minimum.
 *
 * @param int    $user_id The ID of the user.
 * @param string $type    The type of points.
 *
 * @return int|false False on failure.
 */
function wordpoints_get_points_above_minimum( $user_id, $type ) {

	$minimum = wordpoints_get_points_minimum( $type );

	if ( false === $minimum ) {
		return false;
	}

	$points = wordpoints_get_points( $user_id, $type );

	if ( false === $points ) {
		return false;
	}

	return max( 0, $points - $minimum );
}

/**
 * Set points.
 *
 * This function may be used to set the points of a user to a given amount.
 *
 * @since 1.0.0
 *
 * @uses wordpoints_get_points()   To get the points of the user.
 * @uses wordpoints_alter_points() To alter the user's points.
 *
 * @param int    $user_id     The ID of the user to set the points of.
 * @param int    $points      The number of points to the user should have.
 * @param string $points_type The type of points to alter.
 * @param string $log_type    The type of transaction.
 * @param array  $meta        The metadata for the transaction.
 * @param string $log_text    The log text for the transaction.
 *
 * @return bool Whether the transaction was successful.
 */
function wordpoints_set_points( $user_id, $points, $points_type, $log_type, $meta = array(), $log_text = '' ) {

	if ( false === wordpoints_int( $points ) ) {
		return false;
	}

	$current = wordpoints_get_points( $user_id, $points_type );

	if ( false === $current ) {
		return false;
	}

	return wordpoints_alter_points( $user_id, $points - $current, $points_type, $log_type, $meta, $log_text );
}

/**
 * Alter points and add to logs.
 *
 * This function should be used to alter the points of a user by a given amount.
 * Add points by passing a positive integer, subtract by passing a negative
 * integer.
 *
 * If, at any time, this function detects that the user's points are going to be
 * set to less than the minimum amount, it will set the user's points to the
 * minimum. This may be undesirable in certain situations, such as when a user
 * is making a purchase using points. In such a case it is important to use {@see
 * wordpoints_get_points_above_minimum()} to determine whether the user has
 * sufficient points before calling this function. Note that this still leaves open
 * the possibility of a race condition, and in such instances the behavior of this
 * function is currently undefined. Do not rely on the current implementation.
 *
 * This function will return true if the user's points have been set, even if
 * logging failed.
 *
 * Note that in the interest of avoiding race conditions where possible, we do not
 * use update_user_meta().
 *
 * @since 1.0.0
 * @since 2.0.0 Now returns the log ID instead of boolean true on success when the
 *              transaction is logged.
 *
 * @uses apply_filters()         To let plugins hook into this function.
 * @uses wordpoints_get_points() To get the user's current points.
 * @uses do_action()             To call 'wordpoints_points_alter'.
 *
 * @param int    $user_id     The ID of the user to alter the points of.
 * @param int    $points      The number of points to add/subtract.
 * @param string $points_type The type of points to alter.
 * @param string $log_type    The type of transaction.
 * @param array  $meta        The metadata for this transaction. Default: array().
 * @param string $log_text    The log text for this transaction.
 *
 * @return int|bool On success, the log ID if the transaction is logged, or true if
 *                  it is not. False on failure.
 */
function wordpoints_alter_points( $user_id, $points, $points_type, $log_type, $meta = array(), $log_text = '' ) {

	if (
		! wordpoints_posint( $user_id )
		|| ! wordpoints_int( $points )
		|| ! wordpoints_is_points_type( $points_type )
		|| empty( $log_type )
	) {
		return false;
	}

	global $wpdb;

	/**
	 * Number of points to add/subtract.
	 *
	 * If 0 is returned, the transaction will be aborted, but true will still be
	 * returned by wordpoints_alter_points(). If the result is a non-integer value,
	 * wordpoints_alter_points() will return false.
	 *
	 * @since 1.0.0
	 * @since 1.6.0 If the result is a non-integer value, wordpoints_alter_points()
	 *              will return false. Previously it returned true.
	 *
	 * @param int    $points      The number of points.
	 * @param string $points_type The type of points.
	 * @param int    $user_id     The ID of the user.
	 * @param string $log_type    The type of transaction.
	 * @param array  $meta        Metadata for the transaction.
	 */
	$points = apply_filters( 'wordpoints_alter_points', $points, $points_type, $user_id, $log_type, $meta );

	if ( 0 === wordpoints_int( $points ) ) {
		return true;
	} elseif ( false === $points ) {
		return false;
	}

	// Get the current points so we can check this won't go below the minimum.
	$current_points = wordpoints_get_points( $user_id, $points_type );
	$minimum = wordpoints_get_points_minimum( $points_type );

	if ( ( $current_points + $points ) < $minimum ) {

		// The total was less than the minimum, set the number to the minimum.
		$points = $minimum - $current_points;
	}

	$meta_key = wordpoints_get_points_user_meta_key( $points_type );

	if ( '' === get_user_meta( $user_id, $meta_key, true ) ) {

		$result = add_user_meta( $user_id, wp_slash( $meta_key ), (int) $points, true );

	} else {

		$result = $wpdb->query(
			$wpdb->prepare(
				"
					UPDATE {$wpdb->usermeta}
					SET `meta_value` = GREATEST(`meta_value` + %d, %d)
					WHERE `meta_key` = %s
						AND `user_ID` = %d
				",
				$points,
				$minimum,
				$meta_key,
				$user_id
			)
		);

		wp_cache_delete( $user_id, 'user_meta' );
	}

	if ( ! $result ) {
		return false;
	}

	/**
	 * Whether a transaction should be logged.
	 *
	 * @param bool   $log_transaction Whether or not to log this transaction.
	 * @param int    $user_id         The ID of the user.
	 * @param int    $points          The number of points involved.
	 * @param string $points_type     The type of points involved.
	 * @param string $log_type        The type of transaction.
	 * @param array  $meta            The metadata for this transaction.
	 */
	$log_transaction = apply_filters( 'wordpoints_points_log', true, $user_id, $points, $points_type, $log_type, $meta );

	$log_id = false;
	if ( $log_transaction ) {

		$log_text = wordpoints_render_points_log_text( $user_id, $points, $points_type, $log_type, $meta, $log_text );

		if ( 'utf8' === $wpdb->get_col_charset( $wpdb->wordpoints_points_logs, 'text' ) ) {
			$log_text = wp_encode_emoji( $log_text );
		}

		$result = $wpdb->insert(
			$wpdb->wordpoints_points_logs,
			array(
				'user_id'     => $user_id,
				'points'      => $points,
				'points_type' => $points_type,
				'log_type'    => $log_type,
				'text'        => $log_text,
				'date'        => current_time( 'mysql', 1 ),
				'site_id'     => $wpdb->siteid,
				'blog_id'     => $wpdb->blogid,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d' )
		);

		if ( false !== $result ) {

			$log_id = (int) $wpdb->insert_id;

			foreach ( $meta as $meta_key => $meta_value ) {

				wordpoints_add_points_log_meta( $log_id, $meta_key, $meta_value );
			}

			/**
			 * User points transaction logged.
			 *
			 * @since 1.0.0
			 * @since 1.7.0 The $log_id is now passed.
			 *
			 * @param int    $user_id     The ID of the user.
			 * @param int    $points      The number of points.
			 * @param string $points_type The type of points.
			 * @param string $log_type    The type of transaction.
			 * @param array  $meta        Metadata for the transaction.
			 * @param int    $log_id      The ID of the transaction log entry.
			 */
			do_action( 'wordpoints_points_log', $user_id, $points, $points_type, $log_type, $meta, $log_id );
		}

	}  // End if ( $log_transaction ).

	/**
	 * User points altered.
	 *
	 * @since 1.0.0
	 * @since 1.7.0 The $log_id is now passed.
	 *
	 * @param int    $user_id     The ID of the user.
	 * @param int    $points      The number of points.
	 * @param string $points_type The type of points.
	 * @param string $log_type    The type of transaction.
	 * @param array  $meta        Metadata for the transaction.
	 * @param int|false $log_id   The ID of the transaction log, or false if not logged.
	 */
	do_action( 'wordpoints_points_altered', $user_id, $points, $points_type, $log_type, $meta, $log_id );

	if ( $log_id ) {
		return $log_id;
	} else {
		return true;
	}

} // End function wordpoints_alter_points().

/**
 * Add points.
 *
 * This function is an alias of wordpoints_alter_points(). The only difference
 * is that it will only add points to a user. It will not subtract if passed a
 * negative points value.
 *
 * @see wordpoints_alter_points()
 *
 * @param int    $user_id     The ID of the user to alter the points of.
 * @param int    $points      The number of points to add.
 * @param string $points_type The type of points to alter.
 * @param string $log_type    The type of transaction.
 * @param array  $meta        The metadata for the transaction.
 * @param string $log_text    The log text for the transaction.
 *
 * @return bool Whether the points were added successfully.
 */
function wordpoints_add_points( $user_id, $points, $points_type, $log_type, $meta = array(), $log_text = '' ) {

	return wordpoints_alter_points( $user_id, wordpoints_posint( $points ), $points_type, $log_type, $meta, $log_text );
}

/**
 * Subtract points.
 *
 * This function is an alias of wordpoints_points_alter(). The only difference is
 * that it will only subtract points from a user. It will not add if passed a
 * positive points value.
 *
 * @see wordpoints_alter_points()
 *
 * @param int    $user_id     The ID of the user to alter the points of.
 * @param int    $points      The number of points to subtract.
 * @param string $points_type The type of points to alter.
 * @param string $log_type    The type of transaction.
 * @param array  $meta        The metadata for the transaction.
 * @param string $log_text    The log text for the transaction.
 *
 * @return bool Whether the points were subtracted successfully.
 */
function wordpoints_subtract_points( $user_id, $points, $points_type, $log_type, $meta = array(), $log_text = '' ) {

	return wordpoints_alter_points( $user_id, -wordpoints_posint( $points ), $points_type, $log_type, $meta, $log_text );
}

/**
 * Correct the name of the points log meta table's log_id column.
 *
 * @since 1.8.0
 *
 * @param string $column The column name.
 *
 * @return string The corrected column name.
 */
function _wordpoints_points_log_meta_column( $column ) {

	if ( 'wordpoints_points_log_id' === $column ) {
		$column = 'log_id';
	}

	return $column;
}

/**
 * Add metadata for a points transaction.
 *
 * Note that it does not check whether $log_id is real.
 *
 * @since 1.0.0
 * @since 2.1.0 $meta_key and $meta_value are no longer expected slashed.
 * @since 2.1.0 $unique parameter was added.
 *
 * @see add_metadata()
 *
 * @param int    $log_id     The ID of the transaction log to add metadata for.
 * @param string $meta_key   The meta key. Not expected slashed.
 * @param mixed  $meta_value The meta value. Not expected slashed.
 * @param bool   $unique     Whether this meta key should be unique per-object.
 *
 * @return bool Whether the metadata was added successfully.
 */
function wordpoints_add_points_log_meta( $log_id, $meta_key, $meta_value, $unique = false ) {

	global $wpdb;

	add_filter( 'sanitize_key', '_wordpoints_points_log_meta_column' );
	$wpdb->wordpoints_points_logmeta = $wpdb->wordpoints_points_log_meta;

	$result = add_metadata(
		'wordpoints_points_log'
		, $log_id
		, wp_slash( $meta_key )
		, wp_slash( $meta_value )
		, $unique
	);

	unset( $wpdb->wordpoints_points_logmeta );
	remove_filter( 'sanitize_key', '_wordpoints_points_log_meta_column' );

	return $result;
}

/**
 * Get metadata for a points transaction.
 *
 * @since 1.0.0
 * @since 1.8.0 When $meta_key is empty, $single is always false.
 *
 * @see get_metadata()
 *
 * @param int    $log_id   The ID of the transaction.
 * @param string $meta_key The key for the metadata value to return.
 * @param bool   $single   Whether to return multiple results.
 *
 * @return mixed The meta key, or null on failure.
 */
function wordpoints_get_points_log_meta( $log_id, $meta_key = '', $single = false ) {

	global $wpdb;

	add_filter( 'sanitize_key', '_wordpoints_points_log_meta_column' );
	$wpdb->wordpoints_points_logmeta = $wpdb->wordpoints_points_log_meta;
	$result = get_metadata( 'wordpoints_points_log', $log_id, $meta_key, $single );
	unset( $wpdb->wordpoints_points_logmeta );
	remove_filter( 'sanitize_key', '_wordpoints_points_log_meta_column' );

	return $result;
}

/**
 * Update metadata for a points transaction.
 *
 * @since 1.0.0
 * @since 2.1.0 $meta_key and $meta_value are no longer expected slashed.
 *
 * @see update_metadata()
 *
 * @param int    $log_id     The ID of the transaction.
 * @param string $meta_key   The meta key to update. Not expected slashed.
 * @param mixed  $meta_value The new value for this meta key. Not expected slashed.
 * @param mixed  $previous   The previous meta value to update. Not set by default.
 *
 * @return bool Whether any rows were updated.
 */
function wordpoints_update_points_log_meta( $log_id, $meta_key, $meta_value, $previous = null ) {

	global $wpdb;

	add_filter( 'sanitize_key', '_wordpoints_points_log_meta_column' );
	$wpdb->wordpoints_points_logmeta = $wpdb->wordpoints_points_log_meta;

	$result = update_metadata(
		'wordpoints_points_log'
		, $log_id
		, wp_slash( $meta_key )
		, wp_slash( $meta_value )
		, $previous
	);

	unset( $wpdb->wordpoints_points_logmeta );
	remove_filter( 'sanitize_key', '_wordpoints_points_log_meta_column' );

	return $result;
}

/**
 * Delete metadata for points transaction.
 *
 * @since 1.0.0
 * @since 2.1.0 $meta_key and $meta_value are no longer expected slashed.
 *
 * @see delete_metadata()
 *
 * @param int    $log_id     The ID of the transaction.
 * @param string $meta_key   The meta key to update. Not expected slashed.
 * @param mixed  $meta_value The new value for this meta key. Not expected slashed.
 * @param bool   $delete_all Whether to delete metadata for all matching logs, or
 *                           only the one specified by $log_id (default).
 *
 * @return bool Whether any rows where deleted.
 */
function wordpoints_delete_points_log_meta( $log_id, $meta_key = '', $meta_value = null, $delete_all = false ) {

	global $wpdb;

	add_filter( 'sanitize_key', '_wordpoints_points_log_meta_column' );
	$wpdb->wordpoints_points_logmeta = $wpdb->wordpoints_points_log_meta;

	$result = delete_metadata(
		'wordpoints_points_log'
		, $log_id
		, wp_slash( $meta_key )
		, wp_slash( $meta_value )
		, $delete_all
	);

	unset( $wpdb->wordpoints_points_logmeta );
	remove_filter( 'sanitize_key', '_wordpoints_points_log_meta_column' );

	return $result;
}

/**
 * Delete all metadata for a points log.
 *
 * @since 1.8.0
 *
 * @param int $log_id The ID of the points log whose metadata to delete.
 */
function wordpoints_points_log_delete_all_metadata( $log_id ) {

	global $wpdb;

	$meta_ids = $wpdb->get_col(
		$wpdb->prepare(
			"
				SELECT `meta_id`
				FROM `{$wpdb->wordpoints_points_log_meta}`
				WHERE `log_id` = %d
			"
			, $log_id
		)
	); // WPCS: cache pass.

	add_filter( 'sanitize_key', '_wordpoints_points_log_meta_column' );
	$wpdb->wordpoints_points_logmeta = $wpdb->wordpoints_points_log_meta;

	foreach ( $meta_ids as $mid ) {
		delete_metadata_by_mid( 'wordpoints_points_log', $mid );
	}

	unset( $wpdb->wordpoints_points_logmeta );
	remove_filter( 'sanitize_key', '_wordpoints_points_log_meta_column' );
}

/**
 * Get the default points type.
 *
 * @since 1.0.0
 *
 * @return string|false The default points type if one exists, or false.
 */
function wordpoints_get_default_points_type() {

	$points_type = wordpoints_get_maybe_network_option(
		'wordpoints_default_points_type'
	);

	if ( ! wordpoints_is_points_type( $points_type ) ) {
		return false;
	}

	return $points_type;
}

/**
 * Generate the text for a log entry.
 *
 * @since 1.0.0
 *
 * @param int    $user_id      The user_id of the affected user.
 * @param int    $points       The number of points involved in the transaction.
 * @param string $points_type  The type of points involved.
 * @param string $log_type     The type of transaction.
 * @param array  $meta         The metadata for this transaction.
 * @param string $default_text The default log text for this transaction.
 *
 * @return string The log text.
 */
function wordpoints_render_points_log_text( $user_id, $points, $points_type, $log_type, $meta, $default_text = '' ) {

	/**
	 * The text for a points log entry.
	 *
	 * @param string $text        The text.
	 * @param int    $user_id     The ID of the user affected.
	 * @param int    $points      The number of points in this transaction.
	 * @param string $points_type The type of points involved.
	 * @param string $log_type    The type of transaction being logged.
	 * @param array  $meta        The metadata for this transaction.
	 */
	$text = apply_filters( "wordpoints_points_log-{$log_type}", $default_text, $user_id, $points, $points_type, $log_type, $meta );

	if ( empty( $text ) ) {
		$text = _x( '(no description)', 'points log', 'wordpoints' );
	}

	return $text;
}

/**
 * Regenerate points logs messages.
 *
 * @since 1.2.0
 * @since 1.6.0 Now expects an array of log objects, instead of an array of log IDs.
 *
 * @param object[] $logs The logs to regenerate the log messages for.
 *
 * @return void
 */
function wordpoints_regenerate_points_logs( $logs ) {

	if ( empty( $logs ) || ! is_array( $logs ) ) {
		return;
	}

	if ( ! is_object( current( $logs ) ) ) {

		_deprecated_argument( __FUNCTION__, '1.6.0', 'The first parameter should be an array of log objects, not log IDs.' );

		$logs = new WordPoints_Points_Logs_Query( array( 'id__in' => $logs ) );
		$logs = $logs->get();

		if ( ! is_array( $logs ) ) {
			return;
		}
	}

	global $wpdb;

	$flushed = array( 'points_types' => array(), 'user_ids' => array() );

	foreach ( $logs as $log ) {

		$meta = wordpoints_get_points_log_meta( $log->id );
		$meta = wp_list_pluck( $meta, 0 );

		$new_log_text = wordpoints_render_points_log_text(
			$log->user_id
			, $log->points
			, $log->points_type
			, $log->log_type
			, $meta
		);

		if ( $new_log_text !== $log->text ) {

			if ( ! isset( $is_utf8 ) ) {
				$is_utf8 = 'utf8' === $wpdb->get_col_charset( $wpdb->wordpoints_points_logs, 'text' );
			}

			if ( $is_utf8 ) {
				$new_log_text = wp_encode_emoji( $new_log_text );
			}

			$wpdb->update(
				$wpdb->wordpoints_points_logs
				, array( 'text' => $new_log_text )
				, array( 'id' => $log->id )
				, array( '%s' )
				, array( '%d' )
			);

			if ( ! isset( $flushed['points_types'][ $log->points_type ], $flushed['user_ids'][ $log->user_id ] ) ) {
				wordpoints_flush_points_logs_caches(
					array( 'user_id' => $log->user_id, 'points_type' => $log->points_type )
				);

				$flushed['points_types'][ $log->points_type ] = true;
				$flushed['user_ids'][ $log->user_id ] = true;
			}
		}

	} // End foreach ( $logs ).
}

/**
 * Get the top users with the most points.
 *
 * Note that $num_users only limits the number of results, and fewer results may be
 * returned.
 *
 * @since 1.0.0
 * @since 2.3.0 Now returns false instead of null on failure.
 *
 * @param int    $num_users   The number of users to retrieve.
 * @param string $points_type The type of points.
 *
 * @return int[]|false The IDs of the users with the most points, or false if the
 *                     args are invalid.
 */
function wordpoints_points_get_top_users( $num_users, $points_type ) {

	if ( ! wordpoints_posint( $num_users ) || ! wordpoints_is_points_type( $points_type ) ) {
		return false;
	}

	$cache = wp_cache_get( $points_type, 'wordpoints_points_top_users' );

	if ( ! is_array( $cache ) ) {
		$cache = array( 'is_max' => false, 'top_users' => array() );
	}

	$cached_users = count( $cache['top_users'] );

	if ( $num_users > $cached_users && ! $cache['is_max'] ) {

		global $wpdb;

		$excluded = wordpoints_get_excluded_users( 'top_users' );

		$exclude_users = '';
		if ( ! empty( $excluded ) ) {
			$exclude_users = 'WHERE `ID` NOT IN (' . wordpoints_prepare__in( $excluded, '%d' ) . ')';
		}

		$multisite_join = '';
		if ( is_multisite() && ! is_wordpoints_network_active() ) {

			$prefix = $wpdb->get_blog_prefix( get_current_blog_id() );

			$multisite_join = "
					INNER JOIN `{$wpdb->usermeta}` AS `cap`
						ON `users`.`ID` = `cap`.`user_ID`
						AND `cap`.`meta_key` = '{$prefix}capabilities'";
		}

		/*
		 * We can't use WP_User_Query here because we need to coalesce the meta value
		 * with 0 for ordering.
		 */
		$top_users = $wpdb->get_col( // WPCS: unprepared SQL OK
			$wpdb->prepare( // WPCS: unprepared SQL OK
				"
					SELECT `users`.`ID`
                    FROM `{$wpdb->users}` AS `users`
                    LEFT JOIN `{$wpdb->usermeta}` AS `meta`
                    	ON `users`.`ID` = `meta`.`user_ID`
                        AND `meta`.`meta_key` = %s
					{$multisite_join}
                    {$exclude_users}
					ORDER BY COALESCE(CONVERT(`meta`.`meta_value`, SIGNED INTEGER), 0) DESC
					LIMIT %d,%d
				",
				wordpoints_get_points_user_meta_key( $points_type ),
				$cached_users,
				$num_users
			)
		);

		if ( ! is_array( $top_users ) ) {
			return array();
		}

		$cache['top_users'] = array_merge( $cache['top_users'], $top_users );

		if ( count( $cache['top_users'] ) < $num_users ) {
			$cache['is_max'] = true;
		}

		$cache['top_users'] = array_map( 'intval', $cache['top_users'] );

		wp_cache_set( $points_type, $cache, 'wordpoints_points_top_users' );

	} // End if ( not cached ).

	return array_slice( $cache['top_users'], 0, $num_users );
}

/**
 * Display the top users.
 *
 * @since 1.7.0
 *
 * @param int    $num_users   The number of users to display.
 * @param string $points_type The type of points.
 * @param string $context     The context in which the table is being displayed.
 */
function wordpoints_points_show_top_users( $num_users, $points_type, $context = 'default' ) {

	wp_enqueue_style( 'wordpoints-top-users' );

	$top_users = wordpoints_points_get_top_users( $num_users, $points_type );

	if ( ! $top_users ) {
		return;
	}

	$column_headers = array(
		'#'        => _x( '#', 'top users table heading', 'wordpoints' ),
		'position' => _x( 'Position', 'top users table heading', 'wordpoints' ),
		'user'     => _x( 'User', 'top users table heading', 'wordpoints' ),
		'points'   => _x( 'Points', 'top users table heading', 'wordpoints' ),
	);

	$points_type_name = wordpoints_get_points_type_setting( $points_type, 'name' );

	if ( ! empty( $points_type_name ) ) {
		$column_headers['points'] = $points_type_name;
	}

	/**
	 * Filter the extra HTML classes for the top users table element.
	 *
	 * @since 1.6.0
	 *
	 * @param string[] $extra_classes The extra classes for the table element.
	 * @param array    $args          The arguments for table display.
	 * @param int[]    $top_users     The IDs of the top users being displayed.
	 */
	$extra_classes = apply_filters(
		'wordpoints_points_top_users_table_extra_classes'
		, array()
		, compact( 'num_users', 'points_type', 'context' )
		, $top_users
	);

	?>

	<table class="wordpoints-points-top-users <?php echo esc_attr( implode( ' ', $extra_classes ) ); ?>">
		<thead>
			<tr>
				<th scope="col">
					<span aria-hidden="true"><?php echo esc_html( $column_headers['#'] ); ?></span>
					<span class="screen-reader-text"><?php echo esc_html( $column_headers['position'] ); ?></span>
				</th>
				<th scope="col"><?php echo esc_html( $column_headers['user'] ); ?></th>
				<th scope="col"><?php echo esc_html( $column_headers['points'] ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php

			$position = 1;

			foreach ( $top_users as $user_id ) {

				$user = get_userdata( $user_id );

				?>

				<tr class="top-<?php echo (int) $position; ?>">
					<td><?php echo esc_html( number_format_i18n( $position ) ); ?></td>
					<td>
						<?php echo get_avatar( $user_id, 32 ); ?>
						<?php

						$name = sanitize_user_field(
							'display_name'
							, $user->display_name
							, $user_id
							, 'display'
						);

						/**
						 * Filter the name of the user in the points top users table.
						 *
						 * The resulting value should be properly escaped for display.
						 *
						 * @since 1.7.0
						 *
						 * @param string $name        The name of the user.
						 * @param int    $user_id     The user ID.
						 * @param string $points_type The points type the table is for.
						 * @param string $context     The context in which the table is being displayed.
						 */
						$name = apply_filters( 'wordpoints_points_top_users_username', $name, $user_id, $points_type, "top_users_{$context}" );

						echo wp_kses( $name, 'wordpoints_top_users_username' );

						?>
					</td>
					<td><?php wordpoints_display_points( $user_id, $points_type, "top_users_{$context}" ); ?></td>
				</tr>

				<?php

				$position++;

			} // End foreach ( $top_users ).

			?>
		</tbody>
		<tfoot>
		<tr>
			<th scope="col">
				<span aria-hidden="true"><?php echo esc_html( $column_headers['#'] ); ?></span>
				<span class="screen-reader-text"><?php echo esc_html( $column_headers['position'] ); ?></span>
			</th>
			<th scope="col"><?php echo esc_html( $column_headers['user'] ); ?></th>
			<th scope="col"><?php echo esc_html( $column_headers['points'] ); ?></th>
		</tr>
		</tfoot>
	</table>

	<?php

} // End function wordpoints_points_show_top_users().

/**
 * Clear the top users cache when a user's points are altered.
 *
 * @since 1.5.0
 *
 * @WordPress\action wordpoints_points_altered
 *
 * @param int    $user_id     The ID of the user being awarded points. Not used.
 * @param int    $points      The number of points. Not used.
 * @param string $points_type The type of points being awarded.
 */
function wordpoints_clean_points_top_users_cache( $user_id, $points, $points_type ) {

	wp_cache_delete( $points_type, 'wordpoints_points_top_users' );
}

/**
 * Clear the top users cache when a new user is added, if needed.
 *
 * @since 1.10.2
 *
 * @WordPress\action user_register
 */
function wordpoints_clean_points_top_users_cache_user_register() {

	foreach ( wordpoints_get_points_types() as $slug => $unused ) {

		$cache = wp_cache_get( $slug, 'wordpoints_points_top_users' );

		// If there aren't fewer users than the cache holds, we don't need to clear it.
		if ( ! is_array( $cache ) || ! $cache['is_max'] ) {
			continue;
		}

		wp_cache_delete( $slug, 'wordpoints_points_top_users' );
	}
}

/**
 * Clear the top users cache when a user is deleted.
 *
 * @since 1.10.2
 *
 * @WordPress\action deleted_user          On non-multisite or when network-active.
 * @WordPress\action remove_user_from_blog When not network-active on multisite.
 *
 * @param int $user_id The ID of the user who was deleted.
 */
function wordpoints_clean_points_top_users_cache_user_deleted( $user_id ) {

	foreach ( wordpoints_get_points_types() as $slug => $unused ) {

		$cache = wp_cache_get( $slug, 'wordpoints_points_top_users' );

		// If this user isn't in the cache, we don't need to clear it.
		if ( ! is_array( $cache ) || ! in_array( (int) $user_id, array_map( 'intval', $cache['top_users'] ), true ) ) {
			continue;
		}

		wp_cache_delete( $slug, 'wordpoints_points_top_users' );
	}
}

/**
 * Register the points hooks.
 *
 * @since 2.1.0
 *
 * @WordPress\action wordpoints_points_hooks_register
 */
function wordpoints_register_points_hooks() {

	WordPoints_Points_Hooks::register( 'WordPoints_Comment_Received_Points_Hook' );
	WordPoints_Points_Hooks::register( 'WordPoints_Comment_Points_Hook' );
	WordPoints_Points_Hooks::register( 'WordPoints_Periodic_Points_Hook' );
	WordPoints_Points_Hooks::register( 'WordPoints_Post_Points_Hook' );
	WordPoints_Points_Hooks::register( 'WordPoints_Registration_Points_Hook' );

	if ( get_site_option( 'wordpoints_post_delete_hook_legacy' ) ) {
		WordPoints_Points_Hooks::register( 'WordPoints_Post_Delete_Points_Hook' );
	}

	if ( get_site_option( 'wordpoints_comment_removed_hook_legacy' ) ) {
		WordPoints_Points_Hooks::register( 'WordPoints_Comment_Removed_Points_Hook' );
	}
}

// EOF
