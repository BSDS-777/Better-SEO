<?php
/**
 * Better SEO - Admin Script Loader
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\Script
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

namespace Better_SEO\Admin\Script;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Data,
	Meta,
};
use Better_SEO\Helper\{
	Compatibility,
	Guidelines,
	Format\Arrays,
	Query,
	Taxonomy,
	Template,
};

/**
 * Class Better_SEO\Admin\Script\Loader
 *
 * Builds and registers all Better SEO admin scripts and styles
 * based on the current admin screen context.
 *
 * @since 1.0.0
 */
final class Loader {

	/**
	 * Initializes and registers all scripts for the current admin screen.
	 *
	 * Builds a context-aware list of script definitions and passes them
	 * to the Registry for registration.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init(): void {

		$scripts = [
			self::get_common_scripts(),
		];

		if ( Query::is_post_edit() ) {
			self::prepare_media_scripts();

			$scripts[] = self::get_post_edit_scripts();
			$scripts[] = self::get_tabs_scripts();
			$scripts[] = self::get_media_scripts();
			$scripts[] = self::get_title_scripts();
			$scripts[] = self::get_description_scripts();
			$scripts[] = self::get_social_scripts();
			$scripts[] = self::get_canonical_scripts();
			$scripts[] = self::get_primaryterm_scripts();
			$scripts[] = self::get_ays_scripts();

			if ( Data\Plugin::get_option( 'display_pixel_counter' ) || Data\Plugin::get_option( 'display_character_counter' ) ) {
				$scripts[] = self::get_counter_scripts();
			}

			if ( Query::is_block_editor() ) {
				$scripts[] = self::get_gutenberg_compat_scripts();
			}
		} elseif ( Query::is_term_edit() ) {
			if ( Data\Plugin::get_option( 'display_term_edit_options' ) ) {
				self::prepare_media_scripts();

				$scripts[] = self::get_term_edit_scripts();
				$scripts[] = self::get_media_scripts();
				$scripts[] = self::get_title_scripts();
				$scripts[] = self::get_description_scripts();
				$scripts[] = self::get_social_scripts();
				$scripts[] = self::get_canonical_scripts();
				$scripts[] = self::get_ays_scripts();

				if ( Data\Plugin::get_option( 'display_pixel_counter' ) || Data\Plugin::get_option( 'display_character_counter' ) ) {
					$scripts[] = self::get_counter_scripts();
				}
			}
		} elseif ( Query::is_wp_lists_edit() ) {
			if ( Data\Plugin::get_option( 'display_list_edit_options' ) ) {
				$scripts[] = self::get_list_edit_scripts();
				$scripts[] = self::get_title_scripts();
				$scripts[] = self::get_description_scripts();
				$scripts[] = self::get_canonical_scripts();

				if ( Query::is_singular_admin() ) {
					$scripts[] = self::get_primaryterm_scripts();
				}

				if ( Data\Plugin::get_option( 'display_pixel_counter' ) || Data\Plugin::get_option( 'display_character_counter' ) ) {
					$scripts[] = self::get_counter_scripts();
				}
			}
		} elseif ( Query::is_seo_settings_page() ) {
			self::prepare_media_scripts();
			self::prepare_metabox_scripts();

			$scripts[] = self::get_seo_settings_scripts();
			$scripts[] = self::get_tabs_scripts();
			$scripts[] = self::get_media_scripts();
			$scripts[] = self::get_title_scripts();
			$scripts[] = self::get_description_scripts();
			$scripts[] = self::get_social_scripts();
			$scripts[] = self::get_canonical_scripts();
			$scripts[] = self::get_ays_scripts();

			// Always load unconditionally — options may enable counters dynamically.
			$scripts[] = self::get_counter_scripts();
		}

		/**
		 * Filters the Better SEO script definitions before registration.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $scripts  The CSS and JS script definitions.
		 * @param string $registry The Registry class name.
		 * @param string $loader   The Loader class name.
		 */
		$scripts = \apply_filters(
			'better_seo_scripts',
			Arrays::flatten_list( $scripts ),
			Registry::class,
			Loader::class,
		);

		Registry::register( $scripts );
	}

	/**
	 * Enqueues WordPress media scripts for the current admin context.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function prepare_media_scripts(): void {

		$args = [];

		if ( Query::is_post_edit() ) {
			$args['post'] = Query::get_the_real_admin_id();
		}

		\wp_enqueue_media( $args );
	}

	/**
	 * Enqueues WordPress meta box scripts for the SEO settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function prepare_metabox_scripts(): void {
		\wp_enqueue_script( 'common' );
		\wp_enqueue_script( 'wp-lists' );
		\wp_enqueue_script( 'postbox' );
	}

	/**
	 * Returns the common Better SEO script definitions.
	 *
	 * Loaded on all Better SEO admin screens.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> The script definitions.
	 */
	public static function get_common_scripts(): array {
		return [
			[
				'id'       => 'better-seo-utils',
				'type'     => 'js',
				'deps'     => [],
				'autoload' => true,
				'name'     => 'utils',
				'base'     => \BETTER_SEO_DIR_URL . 'lib/js/',
				'ver'      => \BETTER_SEO_VERSION,
			],
			[
				'id'       => 'better-seo',
				'type'     => 'css',
				'deps'     => [ 'dashicons' ],
				'autoload' => true,
				'name'     => 'better-seo',
				'base'     => \BETTER_SEO_DIR_URL . 'lib/css/',
				'ver'      => \BETTER_SEO_VERSION,
			],
			[
				'id'       => 'better-seo',
				'type'     => 'js',
				'deps'     => [ 'wp-util', 'better-seo-utils' ],
				'autoload' => true,
				'name'     => 'better-seo',
				'base'     => \BETTER_SEO_DIR_URL . 'lib/js/',
				'ver'      => \BETTER_SEO_VERSION,
				'l10n'     => [
					'name' => 'BetterSeoL10n',
					'data' => [
						'nonces' => [
							'manage_options' => Utils::create_ajax_capability_nonce( 'manage_options' ),
							'upload_files'   => Utils::create_ajax_capability_nonce( 'upload_files' ),
							'edit_posts'     => Utils::create_ajax_capability_nonce( 'edit_posts' ),
						],
						'states' => [
							'debug' => \SCRIPT_DEBUG,
						],
					],
				],
			],
			[
				'id'       => 'better-seo-tt',
				'type'     => 'css',
				'deps'     => [],
				'autoload' => true,
				'name'     => 'tt',
				'base'     => \BETTER_SEO_DIR_URL . 'lib/css/',
				'ver'      => \BETTER_SEO_VERSION,
				'inline'   => [
					'.better-seo-tooltip-text-wrap'   => [
						'background-color:{{$bg_accent}}',
						'color:{{$rel_bg_accent}}',
					],
					'.better-seo-tooltip-text-wrap *' => [
						'color:{{$rel_bg_accent}}',
					],
					'.better-seo-tooltip-arrow:after' => [
						'border-top-color:{{$bg_accent}}',
					],
					'.better-seo-tooltip-down .better-seo-tooltip-arrow:after' => [
						'border-bottom-color:{{$bg_accent}}',
					],
					'.better-seo-tooltip-text' => [
						\is_rtl() ? 'direction:rtl' : '',
					],
				],
			],
			[
				'id'       => 'better-seo-tt',
				'type'     => 'js',
				'deps'     => [ 'better-seo' ],
				'autoload' => true,
				'name'     => 'tt',
				'base'     => \BETTER_SEO_DIR_URL . 'lib/js/',
				'ver'      => \BETTER_SEO_VERSION,
			],
			[
				'id'       => 'better-seo-ui',
				'type'     => 'css',
				'deps'     => [ 'better-seo', 'dashicons' ],
				'autoload' => true,
				'name'     => 'ui',
				'base'     => \BETTER_SEO_DIR_URL . 'lib/css/',
				'ver'      => \BETTER_SEO_VERSION,
			],
			[
				'id'       => 'better-seo-ui',
				'type'     => 'js',
				'deps'     => [ 'better-seo', 'better-seo-utils', 'jquery' ],
				'autoload' => true,
				'name'     => 'ui',
				'base'     => \BETTER_SEO_DIR_URL . 'lib/js/',
				'ver'      => \BETTER_SEO_VERSION,
			],
		];
	}

	/**
	 * Returns the Are You Sure (AYS) script definitions.
	 *
	 * Warns users before navigating away from unsaved changes.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> The script definitions.
	 */
	public static function get_ays_scripts(): array {
		return [
			[
				'id'       => 'better-seo-ays',
				'type'     => 'js',
				'deps'     => [ 'better-seo', 'better-seo-utils' ],
				'autoload' => true,
				'name'     => 'ays',
				'base'     => \BETTER_SEO_DIR_URL . 'lib/js/',
				'ver'      => \BETTER_SEO_VERSION,
				'l10n'     => [
					'name' => 'BetterSeoAysL10n',
					'data' => [
						'i18n' => [
							'saveAlert' => \__( 'The changes you made will be lost if you navigate away from this page.', 'better-seo' ),
						],
					],
				],
			],
		];
	}

	/**
	 * Returns the List Edit (LE) script definitions.
	 *
	 * Loaded on post and term list table admin screens.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> The script definitions.
	 */
	public static function get_list_edit_scripts(): array {

		$deps = [
			'better-seo-title',
			'better-seo-description',
			'better-seo-canonical',
			'better-seo-postslugs',
			'better-seo-termslugs',
			'better-seo-authorslugs',
			'better-seo',
			'better-seo-tt',
			'better-seo-utils',
		];

		// better-seo-pt-le is only registered on singular admin (post list) pages, not term list pages.
		if ( Query::is_singular_admin() ) {
			$deps[] = 'better-seo-pt-le';
		}

		return [
			[
				'id'       => 'better-seo-le',
				'type'     => 'css',
				'deps'     => [ 'better-seo' ],
				'autoload' => true,
				'name'     => 'le',
				'base'     => \BETTER_SEO_DIR_URL . 'lib/css/',
				'ver'      => \BETTER_SEO_VERSION,
			],
			[
				'id'       => 'better-seo-le',
				'type'     => 'js',
				'deps'     => $deps,
				'autoload' => true,
				'name'     => 'le',
				'base'     => \BETTER_SEO_DIR_URL . 'lib/js/',
				'ver'      => \BETTER_SEO_VERSION,
			],
		];
	}

	/**
	 * Returns the SEO Settings page script definitions.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> The script definitions.
	 */
	public static function get_seo_settings_scripts(): array {

		$front_id = Query::get_the_front_page_id();

		return [
			[
				'id'       => 'better-seo-settings',
				'type'     => 'css',
				'deps'     => [ 'better-seo', 'better-seo-tt', 'wp-color-picker', 'dashicons' ],
				'autoload' => true,
				'name'     => 'settings',
				'base'     => \BETTER_SEO_DIR_URL . 'lib/css/',
				'ver'      => \BETTER_SEO_VERSION,
			],
			[
				'id'       => 'better-seo-settings',
				'type'     => 'js',
				'deps'     => [
					'jquery',
					'better-seo-ays',
					'better-seo-title',
					'better-seo-description',
					'better-seo-social',
					'better-seo-canonical',
					'better-seo',
					'better-seo-tabs',
					'better-seo-tt',
					'wp-color-picker',
					'wp-util',
				],
				'autoload' => true,
				'name'     => 'settings',
				'base'     => \BETTER_SEO_DIR_URL . 'lib/js/',
				'ver'      => \BETTER_SEO_VERSION,
				'l10n'     => [
					'name' => 'BetterSeoSettingsL10n',
					'data' => [
						'states' => [
							'isFrontPrivate'   => $front_id && Data\Post::is_private( $front_id ),
							'isFrontProtected' => $front_id && Data\Post::is_password_protected( $front_id ),
						],
					],
				],
				'tmpl'     => [
					'file' => Template::get_view_location( 'templates/settings/warnings' ),
				],
			],
		];
	}

	/**
	 * Returns the post edit screen script definitions.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> The script definitions.
	 */
	public static function get_post_edit_scripts(): array {

		$id = Query::get_the_real_id();

		$is_static_front_page = Query::is_static_front_page( $id );
		$is_block_editor      = Query::is_block_editor();

		if ( $is_static_front_page ) {
			$additions_forced_disabled = ! Data\Plugin::get_option( 'homepage_tagline' );
			$additions_forced_enabled  = ! $additions_forced_disabled;
		} else {
			$additions_forced_disabled = (bool) Data\Plugin::get_option( 'title_rem_additions' );
			$additions_forced_enabled  = false;
		}

		return [
			[
				'id'       => 'better-seo-post',
				'type'     => 'css',
				'deps'     => [ 'better-seo-tt', 'better-seo', 'better-seo-ui' ],
				'autoload' => true,
				'name'     => 'post',
				'base'     => \BETTER_SEO_DIR_URL . 'lib/css/',
				'ver'      => \BETTER_SEO_VERSION,
				'inline'   => [
					'.better-seo-flex-nav-tab .better-seo-flex-nav-tab-radio:checked + .better-seo-flex-nav-tab-label' => [
						'box-shadow:0 -2px 0 0 {{$color_accent}} inset, 0 0 0 0 {{$color_accent}} inset',
					],
					'.better-seo-flex-nav-tab .better-seo-flex-nav-tab-radio:focus + .better-seo-flex-nav-tab-label:not(.better-seo-no-focus-ring)' => [
						'box-shadow:0 -2px 0 0 {{$color_accent}} inset, 0 0 0 1px {{$color_accent}} inset',
					],
				],
			],
			[
				'id'       => 'better-seo-post',
				'type'     => 'js',
				'deps'     => [
					'better-seo-ays',
					'better-seo-title',
					'better-seo-description',
					'better-seo-social',
					'better-seo-canonical',
					'better-seo-postslugs',
					'better-seo-termslugs',
					'better-seo-authorslugs',
					'better-seo-tabs',
					'better-seo-tt',
					'better-seo-utils',
					'better-seo-ui',
					'better-seo',
				],
				'autoload' => true,
				'name'     => 'post',
				'base'     => \BETTER_SEO_DIR_URL . 'lib/js/',
				'ver'      => \BETTER_SEO_VERSION,
				'l10n'     => [
					'name' => 'BetterSeoPostL10n',
					'data' => [
						'states' => [
							'isPrivate'       => Data\Post::is_private( $id ),
							'isProtected'     => Data\Post::is_password_protected( $id ),
							'isBlockEditor'   => $is_block_editor,
							'id'              => $id,
						],
						'params' => [
							'id'                      => $id,
							'isBlockEditor'           => $is_block_editor,
							'isFront'                 => $is_static_front_page,
							'additionsForcedDisabled' => $additions_forced_disabled,
							'additionsForcedEnabled'  => $additions_forced_enabled,
						],
					],
				],
			],
		];
	}
}