<?php
/**
 * Better SEO - Sitemap Cron
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Sitemap
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

namespace Better_SEO\Sitemap;

\defined( 'BETTER_SEO_PRESENT' ) or die;

/**
 * Class Better_SEO\Sitemap\Cron
 *
 * Schedules WordPress cron events for Better SEO sitemap prerendering.
 * Three sequential single events are scheduled 1 second apart to allow
 * before/during/after hooks to fire in order.
 *
 * @since 1.0.0
 */
class Cron {

	/**
	 * Schedules three sequential single cron events for sitemap prerendering.
	 *
	 * Events are staggered 1 second apart starting 30 seconds from now,
	 * firing the before, main, and after sitemap cron hooks in sequence.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if all three events were scheduled successfully, false otherwise.
	 */
	public static function schedule_single_event(): bool {

		$when = time() + 30;

		return \wp_schedule_single_event( ++$when, 'better_seo_sitemap_cron_hook_before' )
			&& \wp_schedule_single_event( ++$when, 'better_seo_sitemap_cron_hook' )
			&& \wp_schedule_single_event( ++$when, 'better_seo_sitemap_cron_hook_after' );
	}
}