<?php
/**
 * Better SEO - Helper Redirect
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Helper
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

namespace Better_SEO\Helper;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\memo;

/**
 * Class Better_SEO\Helper\Redirect
 *
 * Provides redirect configuration utilities for Better SEO,
 * including external redirect permission checks.
 *
 * @since 1.0.0
 */
final class Redirect {

	/**
	 * Returns whether external redirects are allowed, memoized.
	 *
	 * Applies the better_seo_allow_external_redirect filter.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if external redirects are permitted.
	 */
	public static function allow_external_redirect(): bool {
		/**
		 * Filters whether Better SEO allows external redirects.
		 *
		 * @since 1.0.0
		 * @param bool $allow Whether external redirects are allowed. Default true.
		 */
		return memo() ?? memo( (bool) \apply_filters( 'better_seo_allow_external_redirect', true ) );
	}
}

