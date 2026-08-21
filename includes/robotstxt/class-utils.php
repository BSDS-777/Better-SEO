<?php
/**
 * Better SEO - RobotsTXT Utils
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\RobotsTXT
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

namespace Better_SEO\RobotsTXT;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\umemo;

use Better_SEO\{
	Data,
	Helper\Query,
	Meta,
};

/**
 * Class Better_SEO\RobotsTXT\Utils
 *
 * Provides utility methods for Better SEO robots.txt generation, including
 * blocked user agent lists, robots.txt file detection, and URL resolution.
 *
 * @since 1.0.0
 */
class Utils {

	/**
	 * Returns the list of blocked user agents for the given type.
	 *
	 * Applies the better_seo_robots_blocked_user_agents filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The agent type: 'ai' or 'seo'.
	 * @return array<string, array<string, string>> Map of user agent name to ['by', 'link'] metadata.
	 */
	public static function get_blocked_user_agents( string $type ): array {

		$agents = match ( $type ) {
			'ai'  => [
				'Amazonbot'          => [
					'by'   => 'Amazon',
					'link' => 'https://developer.amazon.com/amazonbot',
				],
				'Applebot-Extended'  => [
					'by'   => 'Apple',
					'link' => 'https://support.apple.com/en-us/119829',
				],
				'CCBot'              => [
					'by'   => 'Common Crawl',
					'link' => 'https://commoncrawl.org/ccbot',
				],
				'ClaudeBot'          => [
					'by'   => 'Anthropic',
					'link' => 'https://support.anthropic.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler',
				],
				'GPTBot'             => [
					'by'   => 'OpenAI',
					'link' => 'https://platform.openai.com/docs/bots',
				],
				'Google-Extended'    => [
					'by'   => 'Google',
					'link' => 'https://developers.google.com/search/docs/crawling-indexing/overview-google-crawlers',
				],
				'GoogleOther'        => [
					'by'   => 'Google',
					'link' => 'https://developers.google.com/search/docs/crawling-indexing/overview-google-crawlers',
				],
				'Meta-ExternalAgent' => [
					// Note: Meta documentation uses lowercase 'meta-externalagent'.
					'by'   => 'Meta',
					'link' => 'https://developers.facebook.com/docs/sharing/webmasters/web-crawlers/',
				],
				'FacebookBot'        => [
					// Does not impede social sharing — Meta uses FacebookExternalHit for that.
					'by'   => 'Meta',
					'link' => 'https://developers.facebook.com/docs/sharing/bot',
				],
			],
			'seo' => [
				'AhrefsBot'       => [
					'by'   => 'Ahrefs',
					'link' => 'https://ahrefs.com/robot',
				],
				'AhrefsSiteAudit' => [
					'by'   => 'Ahrefs',
					'link' => 'https://ahrefs.com/robot/site-audit',
				],
				'barkrowler'      => [
					'by'   => 'Babbar',
					'link' => 'https://www.babbar.tech/crawler',
				],
				'DataForSeoBot'   => [
					'by'   => 'DataForSEO',
					'link' => 'https://dataforseo.com/dataforseo-bot',
				],
				'dotbot'          => [
					'by'   => 'Moz',
					'link' => 'https://moz.com/help/moz-procedures/crawlers/dotbot',
				],
				'rogerbot'        => [
					'by'   => 'Moz',
					'link' => 'https://moz.com/help/moz-procedures/crawlers/rogerbot',
				],
				'SemrushBot'      => [
					'by'   => 'SEMrush',
					'link' => 'https://www.semrush.com/bot/',
				],
				'SiteAuditBot'    => [
					'by'   => 'SEMrush',
					'link' => 'https://www.semrush.com/bot/',
				],
				'SemrushBot-BA'   => [
					'by'   => 'SEMrush',
					'link' => 'https://www.semrush.com/bot/',
				],
			],
			default => [],
		};

		/**
		 * Filters the Better SEO blocked user agents list.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array<string, string>> $agents The blocked user agents map.
		 * @param string                               $type   The agent type ('ai' or 'seo').
		 */
		return (array) \apply_filters(
			'better_seo_robots_blocked_user_agents',
			$agents,
			$type,
		);
	}

	/**
	 * Returns whether a physical robots.txt file exists at the site root, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if a robots.txt file exists at the WordPress home path.
	 */
	public static function has_root_robots_txt(): bool {

		if ( null !== $memo = umemo( __METHOD__ ) ) {
			return $memo;
		}

		if ( ! \function_exists( 'get_home_path' ) ) {
			require_once \ABSPATH . 'wp-admin/includes/file.php';
		}

		$path = \get_home_path() . 'robots.txt';

		return umemo( __METHOD__, file_exists( $path ) );
	}

	/**
	 * Returns the URL to the robots.txt file for the current site, memoized.
	 *
	 * Returns empty string if no robots.txt URL can be determined.
	 *
	 * @since 1.0.0
	 *
	 * @return string The robots.txt URL string, or empty string if not available.
	 */
	public static function get_robots_txt_url(): string {

		if ( null !== $memo = umemo( __METHOD__ ) ) {
			return $memo;
		}

		if ( $GLOBALS['wp_rewrite']->using_permalinks() && ! Data\Blog::is_subdirectory_installation() ) {
			$home = \trailingslashit( Meta\URI\Utils::set_preferred_url_scheme( Meta\URI\Utils::get_site_host() ) );
			$path = "{$home}robots.txt";
		} elseif ( self::has_root_robots_txt() ) {
			$home = \trailingslashit( Meta\URI\Utils::set_preferred_url_scheme( \get_option( 'home' ) ) );
			$path = "{$home}robots.txt";
		} else {
			$path = '';
		}

		return umemo( __METHOD__, $path );
	}
}