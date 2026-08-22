<?php
/**
 * Better SEO - Data Plugin Setup
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Data\Plugin
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

namespace Better_SEO\Data\Plugin;

\defined( 'BETTER_SEO_PRESENT' ) or exit;

use Better_SEO\{
	Data,
	Helper,
	Traits\Property_Refresher,
};

/**
 * Class Better_SEO\Data\Plugin\Setup
 *
 * Provides default and warned plugin option definitions, site cache defaults,
 * and option reset functionality for Better SEO.
 *
 * @since 1.0.0
 */
class Setup {
	use Property_Refresher;

	/**
	 * Memoized default options array.
	 *
	 * @since 1.0.0
	 * @var   array<string, mixed>|null
	 */
	private static ?array $default_options = null;

	/**
	 * Memoized warned options array.
	 *
	 * @since 1.0.0
	 * @var   array<string, mixed>|null
	 */
	private static ?array $warned_options = null;

	/**
	 * Resets all plugin options to their defaults.
	 *
	 * Refreshes the static properties cache on success.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the options were successfully reset, false otherwise.
	 */
	public static function reset_options(): bool {

		$success = \update_option( \BETTER_SEO_SITE_OPTIONS, static::get_default_options(), true );

		if ( $success ) {
			Data\Plugin::refresh_static_properties();
		}

		return $success;
	}

	/**
	 * Returns a specific default option value by key, or null if not found.
	 *
	 * Additional parameters traverse nested array values.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed ...$key Option key(s). Additional keys traverse nested arrays.
	 * @return mixed The default option value, or null if not found.
	 */
	public static function get_default_option( mixed ...$key ): mixed {

		$default = static::$default_options ?? static::get_default_options();

		foreach ( $key as $k ) {
			$default = $default[ $k ] ?? null;
		}

		return $default;
	}

	/**
	 * Returns a specific warned option value by key, or null if not found.
	 *
	 * Additional parameters traverse nested array values.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed ...$key Option key(s). Additional keys traverse nested arrays.
	 * @return mixed The warned option value, or null if not found.
	 */
	public static function get_warned_option( mixed ...$key ): mixed {

		$warned = static::$warned_options ?? static::get_warned_options();

		foreach ( $key as $k ) {
			$warned = $warned[ $k ] ?? null;
		}

		return $warned;
	}

	/**
	 * Returns the full default plugin options array.
	 *
	 * Registers an automated refresh hook and applies the better_seo_default_site_options filter.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The default plugin options array.
	 */
	public static function get_default_options(): array {

		if ( isset( static::$default_options ) ) {
			return static::$default_options;
		}

		static::register_automated_refresh( 'default_options' );

		$titleloc = \is_rtl() ? 'left' : 'right';

		/**
		 * Filters the Better SEO default site options.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $options The default options array.
		 */
		return static::$default_options = (array) \apply_filters(
			'better_seo_default_site_options',
			[
				// General — Performance.
				'alter_search_query'  => 1, // Search query adjustments.
				'alter_archive_query' => 1, // Archive query adjustments.

				'alter_archive_query_type' => 'in_query', // Archive query type.
				'alter_search_query_type'  => 'in_query', // Search query type.

				// General — Layout.
				'display_seo_bar_tables'  => 1, // SEO Bar post-list tables.
				'display_seo_bar_metabox' => 0, // SEO Bar post SEO Settings.
				'seo_bar_low_contrast'    => 0, // SEO Bar contrast display settings.
				'seo_bar_symbols'         => 0, // SEO Bar symbol display settings.

				'display_list_edit_options' => 1, // Quick/bulk edit fields in tables.
				'display_term_edit_options' => 1, // Term edit fields.
				'display_user_edit_options' => 1, // User edit fields.

				'display_pixel_counter'     => 1, // Pixel counter.
				'display_character_counter' => 1, // Character counter.

				'canonical_scheme' => 'automatic', // Canonical URL scheme.

				'timestamps_format' => 1, // Timestamp format, numeric string.

				'disabled_post_types' => [], // Post Type support.
				'disabled_taxonomies' => [], // Taxonomy support.

				// Title.
				'site_title'          => '',        // Blog name.
				'title_separator'     => 'hyphen',  // Title separator, radio selection.
				'title_location'      => $titleloc, // Title separation location.
				'title_rem_additions' => 0,         // Remove title additions.
				'title_rem_prefixes'  => 0,         // Remove title prefixes from archives.
				'title_strip_tags'    => 1,         // Apply strip_tags on titles.

				// Description.
				'auto_description'             => 1,      // Enables auto description.
				'auto_description_html_method' => 'fast', // Auto description HTML passes.

				// Robots — Index.
				'author_noindex' => 0, // Author Archive robots noindex.
				'date_noindex'   => 1, // Date Archive robots noindex.
				'search_noindex' => 1, // Search Page robots noindex.
				'site_noindex'   => 0, // Site Page robots noindex.
				Helper::get_robots_option_index( 'post_type', 'noindex' ) => [
					'attachment' => 1,
				], // Post Type noindex support.
				Helper::get_robots_option_index( 'taxonomy', 'noindex' ) => [
					'post_format' => 1,
				], // Taxonomy noindex support.

				// Robots — Follow.
				'author_nofollow' => 0, // Author Archive robots nofollow.
				'date_nofollow'   => 0, // Date Archive robots nofollow.
				'search_nofollow' => 0, // Search Page robots nofollow.
				'site_nofollow'   => 0, // Site Page robots nofollow.
				Helper::get_robots_option_index( 'post_type', 'nofollow' ) => [], // Post Type nofollow support.
				Helper::get_robots_option_index( 'taxonomy', 'nofollow' )  => [], // Taxonomy nofollow support.

				// Robots — Archive.
				'author_noarchive' => 0, // Author Archive robots noarchive.
				'date_noarchive'   => 0, // Date Archive robots noarchive.
				'search_noarchive' => 0, // Search Page robots noarchive.
				'site_noarchive'   => 0, // Site Page robots noarchive.
				Helper::get_robots_option_index( 'post_type', 'noarchive' ) => [], // Post Type noarchive support.
				Helper::get_robots_option_index( 'taxonomy', 'noarchive' )  => [], // Taxonomy noarchive support.

				// Robots — Query protection.
				'advanced_query_protection' => 1,

				// Robots — Pagination index.
				'paged_noindex'      => 0, // Every second or later page noindex.
				'home_paged_noindex' => 0, // Every second or later homepage noindex.

				// Robots — Copyright directives.
				'set_copyright_directives' => 1,       // Allow copyright directive settings.
				'max_snippet_length'       => -1,      // Max text-snippet length. -1 = unlimited, 0 = disabled, R>0 = characters.
				'max_image_preview'        => 'large', // Max image-preview size. 'none', 'standard', 'large'.
				'max_video_preview'        => -1,      // Max video-preview size. -1 = unlimited, 0 = disabled, R>0 = seconds.

				// Robots.txt blocks.
				'robotstxt_block_ai'  => 0, // Block large learning models from training on site content.
				'robotstxt_block_seo' => 0, // Block SEO crawlers like Ahrefs, Moz, and SEMRush.

				// Homepage — Visibility.
				'homepage_noindex'   => 0, // Homepage robots noindex.
				'homepage_nofollow'  => 0, // Homepage robots nofollow.
				'homepage_noarchive' => 0, // Homepage robots noarchive.

				'homepage_canonical' => '', // Homepage canonical URL.
				'homepage_redirect'  => '', // Homepage redirect URL.

				// Homepage — Meta.
				'homepage_title'         => '',        // Homepage Title string.
				'homepage_tagline'       => 1,         // Homepage add blog Tagline.
				'homepage_description'   => '',        // Homepage Description string.
				'homepage_title_tagline' => '',        // Homepage Tagline string.
				'home_title_location'    => $titleloc, // Title separation location.

				// Homepage — Social.
				'homepage_og_title'            => '',
				'homepage_og_description'      => '',
				'homepage_twitter_card_type'   => '',
				'homepage_twitter_title'       => '',
				'homepage_twitter_description' => '',

				'homepage_social_image_url' => '',
				'homepage_social_image_id'  => 0,

				// Post Type Archives (PTA).
				'pta' => Data\Plugin\PTA::get_all_default_meta(),

				// Relationships.
				'shortlink_tag'       => 0, // Adds shortlink tag.
				'prev_next_posts'     => 1, // Adds next/prev tags.
				'prev_next_archives'  => 1, // Adds next/prev tags.
				'prev_next_frontpage' => 1, // Adds next/prev tags.

				// Facebook.
				'facebook_publisher' => '', // Facebook Business URL.
				'facebook_author'    => '', // Facebook User URL.

				// Dates.
				'post_publish_time' => 1, // Article Published Time.
				'post_modify_time'  => 1, // Article Modified Time.

				// Twitter.
				'twitter_card'    => 'summary_large_image', // Twitter Card layout. Falls back to 'summary' if no image found.
				'twitter_site'    => '', // Twitter business @username.
				'twitter_creator' => '', // Twitter user @username.

				// oEmbed.
				'oembed_use_og_title'     => 0, // Use custom meta titles in oEmbeds.
				'oembed_use_social_image' => 1, // Use social images in oEmbeds.
				'oembed_remove_author'    => 1, // Remove author from oEmbeds.

				// Social — On/off.
				'og_tags'        => 1, // Output Open Graph meta tags.
				'facebook_tags'  => 1, // Output Facebook meta tags.
				'twitter_tags'   => 1, // Output Twitter meta tags.
				'oembed_scripts' => 1, // Enable WordPress oEmbed scripts.

				// Social — Title settings.
				'social_title_rem_additions' => 1, // Remove social title additions.

				// Social — Image settings.
				'multi_og_image' => 0, // Allow multiple images to be generated.

				// Theme color.
				'theme_color' => '', // Theme color meta tag, default none.

				// Social fallback images (fb = fallback).
				'social_image_fb_url' => '', // Fallback image URL.
				'social_image_fb_id'  => 0,  // Fallback image ID.

				// Webmasters.
				'google_verification' => '', // Google Verification Code.
				'bing_verification'   => '', // Bing Verification Code.
				'yandex_verification' => '', // Yandex Verification Code.
				'baidu_verification'  => '', // Baidu Verification Code.
				'pint_verification'   => '', // Pinterest Verification Code.

				// Schema.org.
				'ld_json_enabled'           => 1, // LD+JSON toggle for Schema.
				'ld_json_searchbox'         => 1, // LD+JSON Sitelinks Search Box.
				'ld_json_breadcrumbs'       => 1, // LD+JSON Breadcrumbs.
				'breadcrumb_use_meta_title' => 0, // Whether to use meta titles for breadcrumbs.
				'knowledge_output'          => 1, // Output the Knowledge SEO block.

				'knowledge_type' => 'organization', // Organization or Person.

				// Knowledge — Business logo.
				'knowledge_logo' => 1,  // Use Knowledge Logo.
				'knowledge_name' => '', // Person or Organization name.

				// Knowledge — Logo image.
				'knowledge_logo_url' => '',
				'knowledge_logo_id'  => 0,

				// Knowledge — sameAs locations.
				'knowledge_facebook'   => '', // Facebook Account.
				'knowledge_twitter'    => '', // Twitter Account.
				'knowledge_instagram'  => '', // Instagram Account.
				'knowledge_youtube'    => '', // YouTube Account.
				'knowledge_linkedin'   => '', // LinkedIn Account.
				'knowledge_pinterest'  => '', // Pinterest Account.
				'knowledge_soundcloud' => '', // SoundCloud Account.
				'knowledge_tumblr'     => '', // Tumblr Account.

				// Sitemaps.
				'sitemaps_output'        => 1,   // Output of sitemap.
				'sitemap_query_limit'    => 250, // Sitemap post limit.
				'cache_sitemap'          => 1,   // Sitemap transient cache.
				'sitemap_cron_prerender' => 0,   // Sitemap cron-ping prerender.

				'sitemaps_modified' => 1, // Add sitemap modified time.
				'sitemaps_robots'   => 1, // Add sitemap location to robots.txt.

				'sitemap_styles'       => 1,        // Whether to style the sitemap.
				'sitemap_logo'         => 1,        // Whether to add logo to sitemap.
				'sitemap_logo_url'     => '',       // Sitemap logo URL.
				'sitemap_logo_id'      => 0,        // Sitemap logo ID.
				'sitemap_color_main'   => '1a1a2e', // Sitemap main color.
				'sitemap_color_accent' => 'c9a84c', // Sitemap accent color.

				// Feed.
				'excerpt_the_feed' => 1, // Generate feed Excerpts.
				'source_the_feed'  => 1, // Add backlink to the end of the feed.
				'index_the_feed'   => 0, // Index the feed.
			],
		);
	}

	/**
	 * Returns the full warned plugin options array.
	 *
	 * Warned options are those that, when enabled, may negatively impact SEO.
	 * Registers an automated refresh hook and applies the better_seo_warned_site_options filter.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The warned plugin options array.
	 */
	public static function get_warned_options(): array {

		if ( isset( static::$warned_options ) ) {
			return static::$warned_options;
		}

		static::register_automated_refresh( 'warned_options' );

		/**
		 * Filters the Better SEO warned site options.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $options The warned options array.
		 */
		return static::$warned_options = (array) \apply_filters(
			'better_seo_warned_site_options',
			[
				'title_rem_additions' => 1, // Title remove additions.
				'site_noindex'        => 1, // Site Page robots noindex.
				'site_nofollow'       => 1, // Site Page robots nofollow.
				'homepage_noindex'    => 1, // Homepage robots noindex.
				'homepage_nofollow'   => 1, // Homepage robots nofollow.
				Helper::get_robots_option_index( 'post_type', 'noindex' ) => [
					'post' => 1,
					'page' => 1,
				],
				Helper::get_robots_option_index( 'post_type', 'nofollow' ) => [
					'post' => 1,
					'page' => 1,
				],
			],
		);
	}

	/**
	 * Returns the default site cache array.
	 *
	 * Returns an empty array — the cache is populated on demand.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The default site cache array.
	 */
	public static function get_default_site_caches(): array {
		return [];
	}
}
