<?php
/**
 * Better SEO - Data Filter Plugin
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Data\Filter
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

namespace Better_SEO\Data\Filter;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\has_run;

use Better_SEO\{
	Data,
	Helper\Taxonomy,
	Helper\Post_Type,
	Meta,
};

/**
 * Class Better_SEO\Data\Filter\Plugin
 *
 * Handles sanitization of Better SEO plugin settings on save.
 * Registers per-option sanitizer callbacks and applies them during settings updates.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Registered sanitizer callbacks keyed by option name.
	 *
	 * @since 1.0.0
	 * @var   array<string, array<int, callable>>
	 */
	private static array $sanitizers = [];

	/**
	 * Filters and sanitizes the plugin settings array before saving to the database.
	 *
	 * Applies registered sanitizer callbacks to each option value.
	 * Falls back to the original value if the submitted value is empty or invalid.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value          The new settings value being saved.
	 * @param string $option         The option name.
	 * @param mixed  $original_value The previous option value.
	 * @return array<string, mixed> The sanitized settings array.
	 */
	public static function filter_settings_update( mixed $value, string $option, mixed $original_value ): array {

		if ( empty( $value ) || ! \is_array( $value ) ) {
			return $original_value;
		}

		self::register_sanitizers_jit();

		// Use filterable options as fallback instead of raw DB value.
		$original_value = array_merge(
			Data\Plugin\Setup::get_default_options(),
			Data\Plugin::get_options(),
		);

		/**
		 * Filters the Better SEO settings sanitizer callbacks.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array<int, callable>> $sanitizers Map of option keys to sanitizer callbacks.
		 */
		$sanitizers = \apply_filters(
			'better_seo_settings_update_sanitizers',
			self::$sanitizers,
		);

		$store = [];

		foreach ( $sanitizers as $suboption => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$store[ $suboption ] = \call_user_func_array(
					$callback,
					[
						$value[ $suboption ] ?? '',       // Empty string if field was not submitted.
						$original_value[ $suboption ],    // Error if not registered — intentional.
						$suboption,
					],
				);
			}
		}

		return $store;
	}

	/**
	 * Registers sanitizer callbacks for one or more options.
	 *
	 * Accepts a map of option keys to callback arrays or single callbacks.
	 * Uses ??= to prevent overwriting already-registered sanitizers.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, callable|array<int, callable>> $filters Map of option keys to sanitizer callbacks.
	 * @return void
	 */
	public static function register_sanitizers( array $filters ): void {

		$_sanitizers = &self::$sanitizers;

		foreach ( $filters as $option => $callbacks ) {
			if ( \is_array( $callbacks[0] ) ) {
				$_sanitizers[ $option ] ??= $callbacks;
			} else {
				$_sanitizers[ $option ] ??= [ $callbacks ];
			}
		}
	}

	/**
	 * Registers all built-in sanitizers just-in-time before settings are processed.
	 *
	 * Uses has_run() to ensure registration only happens once per request.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_sanitizers_jit(): void {

		if ( has_run( __METHOD__ ) ) {
			return;
		}

		$filters = [
			'advanced_query_protection'    => 'checkbox',
			'alter_archive_query_type'     => 'alter_query_type',
			'alter_archive_query'          => 'checkbox',
			'alter_search_query_type'      => 'alter_query_type',
			'alter_search_query'           => 'checkbox',
			'author_noarchive'             => 'checkbox',
			'author_nofollow'              => 'checkbox',
			'author_noindex'               => 'checkbox',
			'auto_description_html_method' => 'auto_description_method',
			'auto_description'             => 'checkbox',
			'baidu_verification'           => 'verification_code',
			'bing_verification'            => 'verification_code',
			'breadcrumb_use_meta_title'    => 'checkbox',
			'cache_sitemap'                => 'checkbox',
			'canonical_scheme'             => 'canonical_scheme',
			'date_noarchive'               => 'checkbox',
			'date_nofollow'                => 'checkbox',
			'date_noindex'                 => 'checkbox',
			'disabled_post_types'          => [ 'disabled_post_types', 'checkbox_array' ],
			'disabled_taxonomies'          => [ 'disabled_taxonomies', 'checkbox_array' ],
			'display_character_counter'    => 'checkbox',
			'display_list_edit_options'    => 'checkbox',
			'display_pixel_counter'        => 'checkbox',
			'display_seo_bar_metabox'      => 'checkbox',
			'display_seo_bar_tables'       => 'checkbox',
			'display_term_edit_options'    => 'checkbox',
			'display_user_edit_options'    => 'checkbox',
			'excerpt_the_feed'             => 'checkbox',
			'facebook_author'              => 'facebook_profile_link',
			'facebook_publisher'           => 'facebook_profile_link',
			'facebook_tags'                => 'checkbox',
			'google_verification'          => 'verification_code',
			'home_paged_noindex'           => 'checkbox',
			'home_title_location'          => 'title_location',
			'homepage_canonical'           => 'fully_qualified_url',
			'homepage_description'         => 'metadata_text',
			'homepage_noarchive'           => 'checkbox',
			'homepage_nofollow'            => 'checkbox',
			'homepage_noindex'             => 'checkbox',
			'homepage_og_description'      => 'metadata_text',
			'homepage_og_title'            => 'metadata_text',
			'homepage_redirect'            => 'fully_qualified_url',
			'homepage_social_image_id'     => 'absolute_integer',
			'homepage_social_image_url'    => 'fully_qualified_url',
			'homepage_tagline'             => 'checkbox',
			'homepage_title_tagline'       => 'metadata_text',
			'homepage_title'               => 'metadata_text',
			'homepage_twitter_card_type'   => 'homepage_twitter_card',
			'homepage_twitter_description' => 'metadata_text',
			'homepage_twitter_title'       => 'metadata_text',
			'index_the_feed'               => 'checkbox',
			'knowledge_facebook'           => 'fully_qualified_url',
			'knowledge_instagram'          => 'fully_qualified_url',
			'knowledge_linkedin'           => 'fully_qualified_url',
			'knowledge_logo_id'            => 'absolute_integer',
			'knowledge_logo_url'           => 'fully_qualified_url',
			'knowledge_logo'               => 'checkbox',
			'knowledge_name'               => 'metadata_text',
			'knowledge_output'             => 'checkbox',
			'knowledge_pinterest'          => 'fully_qualified_url',
			'knowledge_soundcloud'         => 'fully_qualified_url',
			'knowledge_tumblr'             => 'fully_qualified_url',
			'knowledge_twitter'            => 'fully_qualified_url',
			'knowledge_type'               => 'knowledge_type',
			'knowledge_youtube'            => 'fully_qualified_url',
			'ld_json_breadcrumbs'          => 'checkbox',
			'ld_json_enabled'              => 'checkbox',
			'ld_json_searchbox'            => 'checkbox',
			'max_image_preview'            => 'copyright_image_size',
			'max_snippet_length'           => 'copyright_content_length',
			'max_video_preview'            => 'copyright_content_length',
			'multi_og_image'               => 'checkbox',
			'oembed_remove_author'         => 'checkbox',
			'oembed_scripts'               => 'checkbox',
			'oembed_use_og_title'          => 'checkbox',
			'oembed_use_social_image'      => 'checkbox',
			'og_tags'                      => 'checkbox',
			'paged_noindex'                => 'checkbox',
			'pint_verification'            => 'verification_code',
			'post_modify_time'             => 'checkbox',
			'post_publish_time'            => 'checkbox',
			'prev_next_archives'           => 'checkbox',
			'prev_next_frontpage'          => 'checkbox',
			'prev_next_posts'              => 'checkbox',
			'pta'                          => 'pta_meta',
			'robotstxt_block_ai'           => 'checkbox',
			'robotstxt_block_seo'          => 'checkbox',
			'search_noarchive'             => 'checkbox',
			'search_nofollow'              => 'checkbox',
			'search_noindex'               => 'checkbox',
			'seo_bar_low_contrast'         => 'checkbox',
			'seo_bar_symbols'              => 'checkbox',
			'set_copyright_directives'     => 'checkbox',
			'shortlink_tag'                => 'checkbox',
			'site_noarchive'               => 'checkbox',
			'site_nofollow'                => 'checkbox',
			'site_noindex'                 => 'checkbox',
			'site_title'                   => 'metadata_text',
			'sitemap_color_accent'         => 'rgb_hex',
			'sitemap_color_main'           => 'rgb_hex',
			'sitemap_cron_prerender'       => 'checkbox',
			'sitemap_logo_id'              => 'absolute_integer',
			'sitemap_logo_url'             => 'fully_qualified_url',
			'sitemap_logo'                 => 'checkbox',
			'sitemap_query_limit'          => 'sitemap_query_limit',
			'sitemap_styles'               => 'checkbox',
			'sitemaps_modified'            => 'checkbox',
			'sitemaps_output'              => 'checkbox',
			'sitemaps_robots'              => 'checkbox',
			'social_image_fb_id'           => 'absolute_integer',
			'social_image_fb_url'          => 'fully_qualified_url',
			'social_title_rem_additions'   => 'checkbox',
			'source_the_feed'              => 'checkbox',
			'theme_color'                  => 'rgb_hex',
			'timestamps_format'            => 'checkbox',
			'title_location'               => 'title_location',
			'title_rem_additions'          => 'checkbox',
			'title_rem_prefixes'           => 'checkbox',
			'title_separator'              => 'title_separator',
			'title_strip_tags'             => 'checkbox',
			'twitter_card'                 => 'twitter_card',
			'twitter_creator'              => 'twitter_profile_handle',
			'twitter_site'                 => 'twitter_profile_handle',
			'twitter_tags'                 => 'checkbox',
			'yandex_verification'          => 'verification_code',

			// Dynamic robots option keys for post types and taxonomies.
			Data\Plugin\Helper::get_robots_option_index( 'post_type', 'noarchive' ) => 'checkbox_array',
			Data\Plugin\Helper::get_robots_option_index( 'post_type', 'nofollow' )  => 'checkbox_array',
			Data\Plugin\Helper::get_robots_option_index( 'post_type', 'noindex' )   => 'checkbox_array',
			Data\Plugin\Helper::get_robots_option_index( 'taxonomy', 'noarchive' )  => 'checkbox_array',
			Data\Plugin\Helper::get_robots_option_index( 'taxonomy', 'nofollow' )   => 'checkbox_array',
			Data\Plugin\Helper::get_robots_option_index( 'taxonomy', 'noindex' )    => 'checkbox_array',
		];

		$sanitizer_class = self::class;

		foreach ( $filters as &$callbacks ) {
			if ( \is_array( $callbacks ) ) {
				foreach ( $callbacks as &$cb ) {
					$cb = [ $sanitizer_class, $cb ];
				}
			} else {
				$callbacks = [ $sanitizer_class, $callbacks ];
			}
		}

		self::register_sanitizers( $filters );
	}

	/**
	 * Sanitizes a checkbox value to a boolean integer.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The checkbox value.
	 * @return int 1 if checked, 0 otherwise.
	 */
	public static function checkbox( mixed $value ): int {
		return Sanitize::boolean_integer( $value );
	}

	/**
	 * Sanitizes an alter query type option value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value     The submitted value.
	 * @param mixed $old_value The previous value.
	 * @return string The sanitized value, or old value if invalid.
	 */
	public static function alter_query_type( mixed $value, mixed $old_value ): string {
		return match ( $value ) {
			'in_query', 'post_query' => $value,
			default                  => $old_value,
		};
	}

	/**
	 * Sanitizes an auto description method option value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value     The submitted value.
	 * @param mixed $old_value The previous value.
	 * @return string The sanitized value, or old value if invalid.
	 */
	public static function auto_description_method( mixed $value, mixed $old_value ): string {
		return match ( $value ) {
			'fast', 'accurate', 'thorough' => $value,
			default                        => $old_value,
		};
	}

	/**
	 * Sanitizes a search engine verification code.
	 *
	 * Extracts the content attribute value if a full meta tag is submitted.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The raw verification code or meta tag string.
	 * @return string The sanitized verification code.
	 */
	public static function verification_code( mixed $value ): string {

		// Extract content attribute if a full meta tag was submitted (not yet extracted by JS).
		if ( str_contains( '<', (string) $value ) ) {
			$value = preg_match(
				'/\bcontent=(?:([\'"])([^$]*?)\g{-2}|([^\s\/>]+))/i',
				'$2',
				$matches,
			);

			// 3 = unquoted content, 2 = quoted content.
			$value = $matches[3] ?? $matches[2] ?? '';
		}

		return preg_replace( '/[^a-z\d_-]+/i', '', (string) $value );
	}

	/**
	 * Sanitizes a canonical scheme option value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value     The submitted value.
	 * @param mixed $old_value The previous value.
	 * @return string The sanitized value, or old value if invalid.
	 */
	public static function canonical_scheme( mixed $value, mixed $old_value ): string {
		return match ( $value ) {
			'automatic', 'https', 'http' => $value,
			default                      => $old_value,
		};
	}

	/**
	 * Sanitizes the disabled post types option.
	 *
	 * Removes any forced-supported post types from the disabled list.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The submitted disabled post types array.
	 * @return array<string, mixed> The sanitized disabled post types array.
	 */
	public static function disabled_post_types( mixed $value ): array {

		if ( empty( $value ) || ! \is_array( $value ) ) {
			return [];
		}

		foreach ( Post_Type::get_all_forced_supported() as $forced ) {
			unset( $value[ $forced ] );
		}

		return $value;
	}

	/**
	 * Sanitizes the disabled taxonomies option.
	 *
	 * Removes any forced-supported taxonomies from the disabled list.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The submitted disabled taxonomies array.
	 * @return array<string, mixed> The sanitized disabled taxonomies array.
	 */
	public static function disabled_taxonomies( mixed $value ): array {

		if ( empty( $value ) || ! \is_array( $value ) ) {
			return [];
		}

		foreach ( Taxonomy::get_all_forced_supported() as $forced ) {
			unset( $value[ $forced ] );
		}

		return $value;
	}

	/**
	 * Sanitizes an array of checkbox values to boolean integers.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The submitted checkbox array.
	 * @return array<string, int> The sanitized boolean integer array.
	 */
	public static function checkbox_array( mixed $value ): array {

		if ( empty( $value ) || ! \is_array( $value ) ) {
			return [];
		}

		foreach ( $value as &$val ) {
			$val = Sanitize::boolean_integer( $val );
		}

		return $value;
	}

	/**
	 * Sanitizes a Facebook profile link.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The raw Facebook profile URL.
	 * @return string The sanitized Facebook profile URL.
	 */
	public static function facebook_profile_link( mixed $value ): string {
		return Sanitize::facebook_profile_link( (string) $value );
	}

	/**
	 * Sanitizes a title location option value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value     The submitted value.
	 * @param mixed $old_value The previous value.
	 * @return string The sanitized value, or old value if invalid.
	 */
	public static function title_location( mixed $value, mixed $old_value ): string {
		return match ( $value ) {
			'left', 'right' => $value,
			default         => $old_value,
		};
	}

	/**
	 * Sanitizes metadata text content.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The raw metadata text.
	 * @return string The sanitized metadata content.
	 */
	public static function metadata_text( mixed $value ): string {
		return Sanitize::metadata_content( $value );
	}

	/**
	 * Sanitizes a value to an absolute integer.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value to sanitize.
	 * @return int The absolute integer value.
	 */
	public static function absolute_integer( mixed $value ): int {
		return \absint( $value );
	}

	/**
	 * Sanitizes a URL to a fully qualified absolute URL.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The raw URL value.
	 * @return string The sanitized absolute URL, or empty string if invalid.
	 */
	public static function fully_qualified_url( mixed $value ): string {

		if ( empty( $value ) ) {
			return '';
		}

		return \sanitize_url(
			Meta\URI\Utils::make_absolute_current_scheme_url( (string) $value ),
			[ 'https', 'http' ],
		);
	}

	/**
	 * Sanitizes a knowledge graph type option value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value     The submitted value.
	 * @param mixed $old_value The previous value.
	 * @return string The sanitized value, or old value if invalid.
	 */
	public static function knowledge_type( mixed $value, mixed $old_value ): string {
		return match ( $value ) {
			'person', 'organization' => $value,
			default                  => $old_value,
		};
	}

	/**
	 * Sanitizes a copyright image size directive option value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value     The submitted value.
	 * @param mixed $old_value The previous value.
	 * @return string The sanitized value, or old value if invalid.
	 */
	public static function copyright_image_size( mixed $value, mixed $old_value ): string {
		return match ( $value ) {
			'none', 'standard', 'large' => $value,
			default                     => $old_value,
		};
	}

	/**
	 * Sanitizes a copyright content length value.
	 *
	 * Clamps the value to the range -1 to 600.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The submitted length value.
	 * @return int The clamped integer value.
	 */
	public static function copyright_content_length( mixed $value ): int {
		return max( -1, min( 600, (int) $value ) );
	}

	/**
	 * Sanitizes a color value to a valid RGB hex string.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The raw color value.
	 * @return string The sanitized hex color string.
	 */
	public static function rgb_hex( mixed $value ): string {
		return Sanitize::rgb_hex( (string) $value );
	}

	/**
	 * Sanitizes the sitemap query limit option.
	 *
	 * Clamps the value to the range 1 to 50000.
	 * Falls back to old value if the submitted value is 0.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value     The submitted limit value.
	 * @param mixed $old_value The previous limit value.
	 * @return int The clamped sitemap query limit.
	 */
	public static function sitemap_query_limit( mixed $value, mixed $old_value ): int {
		return max(
			1,
			min(
				50000,
				\absint( $value ) ?: $old_value,
			),
		);
	}

	/**
	 * Sanitizes a value to a numeric string.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value to sanitize.
	 * @return string The integer value as a string.
	 */
	public static function numeric_string( mixed $value ): string {
		return Sanitize::numeric_string( $value );
	}

	/**
	 * Sanitizes a title separator option value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value     The submitted separator value.
	 * @param mixed $old_value The previous separator value.
	 * @return string The sanitized separator, or old value if not in the allowed list.
	 */
	public static function title_separator( mixed $value, mixed $old_value ): string {

		if ( \array_key_exists( $value, Meta\Title\Utils::get_separator_list() ) ) {
			return $value;
		}

		return $old_value;
	}

	/**
	 * Sanitizes the homepage Twitter card type option.
	 *
	 * Returns empty string for empty values (resets to default).
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value     The submitted card type value.
	 * @param mixed $old_value The previous card type value.
	 * @return string The sanitized card type, empty string for default, or old value if invalid.
	 */
	public static function homepage_twitter_card( mixed $value, mixed $old_value ): string {

		if ( \in_array( $value, Meta\Twitter::get_supported_cards(), true ) ) {
			return $value;
		}

		if ( empty( $value ) ) {
			return ''; // Reset to default.
		}

		return $old_value;
	}

	/**
	 * Sanitizes a Twitter card type option value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value     The submitted card type value.
	 * @param mixed $old_value The previous card type value.
	 * @return string The sanitized card type, or old value if invalid.
	 */
	public static function twitter_card( mixed $value, mixed $old_value ): string {

		if ( \in_array( $value, Meta\Twitter::get_supported_cards(), true ) ) {
			return $value;
		}

		return $old_value;
	}

	/**
	 * Sanitizes a Twitter profile handle.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The raw Twitter handle or profile URL.
	 * @return string The sanitized @handle string, or empty string if invalid.
	 */
	public static function twitter_profile_handle( mixed $value ): string {
		return Sanitize::twitter_profile_handle( (string) $value );
	}

	/**
	 * Sanitizes the PTA (Post Type Archive) meta option array.
	 *
	 * Applies per-key sanitization to each PTA entry's meta fields.
	 * Removes unrecognized keys from each entry.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The raw PTA meta array.
	 * @return array<string, array<string, mixed>> The sanitized PTA meta array.
	 */
	public static function pta_meta( mixed $value ): array {

		if ( empty( $value ) ) {
			return [];
		}

		foreach ( $value as &$meta ) {
			foreach ( $meta as $key => &$val ) {
				switch ( $key ) {
					case 'doctitle':
					case 'og_title':
					case 'tw_title':
					case 'description':
					case 'og_description':
					case 'tw_description':
						$val = Sanitize::metadata_content( $val );
						break;

					case 'canonical':
					case 'social_image_url':
						$val = \sanitize_url( $val, [ 'https', 'http' ] );
						break;

					case 'social_image_id':
						// Bound to social_image_url — reset to 0 if URL is empty.
						$val = $meta['social_image_url'] ? \absint( $val ) : 0;
						break;

					case 'noindex':
					case 'nofollow':
					case 'noarchive':
						$val = Sanitize::qubit( $val );
						break;

					case 'redirect':
						$val = Sanitize::redirect_url( $val );
						break;

					case 'title_no_blog_name':
						$val = Sanitize::boolean_integer( $val );
						break;

					case 'tw_card_type':
						if ( ! \in_array( $val, Meta\Twitter::get_supported_cards(), true ) ) {
							$val = ''; // Reset to default.
						}
						break;

					default:
						// Remove unrecognized keys to prevent unsupported meta storage.
						unset( $meta[ $key ] );
				}
			}
		}

		return $value;
	}
}