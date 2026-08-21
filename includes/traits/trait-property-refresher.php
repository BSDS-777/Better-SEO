<?php
/**
 * Better SEO - Trait: Property Refresher
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Traits
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 * @link       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Better SEO plugin
 * Copyright (C) 2026 Brian Smith
 * Licensed under the GNU General Public License v2.0.
 *
 * This program is free software: you may redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is provided without any warranty; without even the
 * implied warranty of merchantability or fitness for a particular purpose.
 * See the GNU General Public License for more details.
 */

declare( strict_types=1 );

namespace Better_SEO\Traits;

\defined( 'BETTER_SEO_PRESENT' ) or die;

/**
 * Trait Better_SEO\Traits\Property_Refresher
 *
 * Provides automated static property refresh functionality for Better SEO data classes.
 * Classes using this trait can register specific static properties to be automatically
 * reset to their default values when WordPress switches blogs (multisite) or when
 * refresh_static_properties() is called manually.
 *
 * @since 1.0.0
 */
trait Property_Refresher {

	/**
	 * Whether the switch_blog action hook has been registered for this class.
	 *
	 * Stores the return value of add_action() to indicate registration status.
	 *
	 * @since 1.0.0
	 * @var   mixed|null
	 */
	protected static mixed $registered_refresh = null;

	/**
	 * Map of static property names registered for automated refresh.
	 *
	 * Keys are property names; values are always true (used as a set).
	 *
	 * @since 1.0.0
	 * @var   array<string, bool>
	 */
	protected static array $registered_for_refresh = [];

	/**
	 * Registers a static property for automated refresh on blog switch.
	 *
	 * Adds the property name to the refresh registry and registers the
	 * switch_blog action hook on first call. Subsequent calls for the same
	 * property are no-ops due to ??= assignment.
	 *
	 * @since 1.0.0
	 *
	 * @param string $property The static property name to register for refresh.
	 * @return void
	 */
	protected static function register_automated_refresh( string $property ): void {

		static::$registered_for_refresh[ $property ] ??= true;

		if ( ! isset( static::$registered_refresh ) ) {
			static::$registered_refresh = \add_action(
				'switch_blog',
				[ __CLASS__, '_do_switch_blog_flush' ],
				10,
				2,
			);
		}
	}

	/**
	 * Resets all registered static properties to their class-defined default values.
	 *
	 * Reads the default values from get_class_vars() and assigns them back to
	 * each registered property on the calling class via late static binding.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function refresh_static_properties(): void {

		$class_vars = get_class_vars( __CLASS__ );

		foreach ( static::$registered_for_refresh as $property => $marked ) {
			static::${$property} = $class_vars[ $property ];
		}
	}

	/**
	 * Flushes static properties when WordPress switches to a different blog.
	 *
	 * Hooked to the switch_blog action. Only refreshes when the blog ID
	 * actually changes to avoid unnecessary resets.
	 *
	 * @hook switch_blog 10
	 * @since 1.0.0
	 *
	 * @param int $new_site_id The ID of the blog being switched to.
	 * @param int $old_site_id The ID of the blog being switched from.
	 * @return void
	 */
	public static function _do_switch_blog_flush( int $new_site_id, int $old_site_id ): void {

		if ( $new_site_id === $old_site_id ) {
			return;
		}

		static::refresh_static_properties();
	}
}