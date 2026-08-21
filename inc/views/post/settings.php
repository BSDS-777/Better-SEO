<?php
/**
 * Better SEO - View: Post Settings
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\Post
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

namespace Better_SEO;

if ( ! \defined( 'BETTER_SEO_PRESENT' ) || ! Helper\Template::verify_secret( $secret ) ) {
	die;
}

use const Better_SEO\{
	ROBOTS_IGNORE_SETTINGS,
	ROBOTS_IGNORE_PROTECTION,
};

use function Better_SEO\coalesce_strlen;

use Better_SEO\{
	Data\Filter\Sanitize,
	Helper\Post_Type,
	Helper\Query,
	Helper\Taxonomy,
};
use Better_SEO\Admin\Settings\Layout\{
	Form,
	HTML,
	Input,
};

[ $instance ] = $view_args;

$post_id = Query::get_the_real_id();
$meta    = Data\Plugin\Post::get_meta( $post_id );

$generator_args = [ 'id' => $post_id ];

$is_static_front_page = Query::is_static_front_page( $post_id );

switch ( $instance ) :
	case 'main':
		$default_tabs = [
			'general'    => [
				'name'     => \__( 'General', 'better-seo' ),
				'callback' => [ Admin\Settings\Post::class, 'general_tab' ],
				'dashicon' => 'admin-generic',
			],
			'social'     => [
				'name'     => \__( 'Social', 'better-seo' ),
				'callback' => [ Admin\Settings\Post::class, 'social_tab' ],
				'dashicon' => 'share',
			],
			'visibility' => [
				'name'     => \__( 'Visibility', 'better-seo' ),
				'callback' => [ Admin\Settings\Post::class, 'visibility_tab' ],
				'dashicon' => 'visibility',
			],
		];

		/**
		 * Filters the Better SEO post settings tabs.
		 *
		 * @since 1.0.0
		 * @param array<string, array<string, mixed>> $tabs The default tab definitions.
		 * @param null                                $args Reserved for future use.
		 */
		$tabs = (array) \apply_filters( 'better_seo_inpost_settings_tabs', $default_tabs, null );

		echo '<div class="better-seo-flex better-seo-flex-inside-wrap">';
		Admin\Settings\Post::flex_nav_tab_wrapper( 'inpost', $tabs );
		echo '</div>';
		break;

	case 'general':
		if ( Data\Plugin::get_option( 'display_seo_bar_metabox' ) ) {
			?>
			<div class="better-seo-flex-setting better-seo-flex" id="better-seo-doing-it-right-wrap">
				<div class="better-seo-flex-setting-label better-seo-flex">
					<div class="better-seo-flex-setting-label-inner-wrap better-seo-flex">
						<div class="better-seo-flex-setting-label-item better-seo-flex">
							<div><strong><?php \esc_html_e( 'SEO Status', 'better-seo' ); ?></strong></div>
							<div><span class="better-seo-ajax"></span></div>
						</div>
					</div>
				</div>
				<div class="better-seo-flex-setting-input better-seo-flex">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput -- generate_bar() escapes.
					echo Admin\SEOToolbar\Builder::generate_bar( $generator_args );
					?>
				</div>
			</div>
			<?php
		}

		if ( $is_static_front_page ) {
			$_has_home_title = (bool) \strlen( Data\Plugin::get_option( 'homepage_title' ) );
			$_has_home_desc  = (bool) \strlen( Data\Plugin::get_option( 'homepage_description' ) );

			// When a homepage title is configured, retrieve the custom field value directly.
			$default_title     = $_has_home_title
				? Meta\Title::get_custom_title( $generator_args )
				: Meta\Title::get_bare_generated_title( $generator_args );
			$title_ref_locked  = $_has_home_title;
			$title_additions   = Meta\Title::get_addition_for_front_page();
			$title_seplocation = Meta\Title::get_addition_location_for_front_page();

			// When a homepage description is configured, retrieve the custom field value directly.
			$default_description    = $_has_home_desc
				? Meta\Description::get_custom_description( $generator_args )
				: Meta\Description::get_generated_description( $generator_args );
			$description_ref_locked = $_has_home_desc;
		} else {
			$default_title     = Meta\Title::get_bare_generated_title( $generator_args );
			$title_ref_locked  = false;
			$title_additions   = Meta\Title::get_addition();
			$title_seplocation = Meta\Title::get_addition_location();

			$default_description    = Meta\Description::get_generated_description( $generator_args );
			$description_ref_locked = false;
		}

		?>
		<div class="better-seo-flex-setting better-seo-flex">
			<div class="better-seo-flex-setting-label better-seo-flex">
				<div class="better-seo-flex-setting-label-inner-wrap better-seo-flex">
					<label for="better-seo-title" class="better-seo-flex-setting-label-item better-seo-flex">
						<div><strong><?php \esc_html_e( 'Meta Title', 'better-seo' ); ?></strong></div>
						<div>
							<?php
							HTML::make_info(
								\__( 'The meta title controls how your page title appears in search engine results. A well-crafted title improves click-through rates and helps search engines understand your content.', 'better-seo' ),
								'https://developers.google.com/search/docs/advanced/appearance/title-link',
							);
							?>
						</div>
					</label>
					<?php
					if ( Data\Plugin::get_option( 'display_character_counter' ) ) {
						Form::output_character_counter_wrap( 'better-seo-title' );
					}
					if ( Data\Plugin::get_option( 'display_pixel_counter' ) ) {
						Form::output_pixel_counter_wrap( 'better-seo-title', 'title' );
					}
					?>
				</div>
			</div>
			<div class="better-seo-flex-setting-input better-seo-flex">
				<div class="better-seo-title-wrap">
					<input class="large-text" type="text" name="better-seo[_genesis_title]" id="better-seo-title" value="<?php echo \esc_html( Sanitize::metadata_content( $meta['_genesis_title'] ) ); ?>" autocomplete="off">
					<?php
					Input::output_js_title_data(
						'better-seo-title',
						[
							'state' => [
								'refTitleLocked'    => $title_ref_locked,
								'defaultTitle'      => \esc_html( $default_title ),
								'addAdditions'      => Meta\Title\Conditions::use_branding( $generator_args ),
								'useSocialTagline'  => Meta\Title\Conditions::use_branding( $generator_args, true ),
								'additionValue'     => \esc_html( $title_additions ),
								'additionPlacement' => 'left' === $title_seplocation ? 'before' : 'after',
							],
						],
					);
					?>
				</div>

				<div class="better-seo-checkbox-wrapper">
					<label for="better-seo-title-no-blogname">
						<?php
						$title_no_blogname_value = $meta['_better_seo_title_no_blogname'];
						if ( $is_static_front_page ) {
							// Homepage title branding is managed globally on the SEO Settings page.
							?>
							<input type="checkbox" id="better-seo-title-no-blogname" value="1" <?php \checked( $title_no_blogname_value ); ?> disabled>
							<input type="hidden" name="better-seo[_better_seo_title_no_blogname]" value="<?php echo (int) $title_no_blogname_value; ?>">
							<?php
							\esc_html_e( 'Remove the site title?', 'better-seo' );
							echo ' ';
							HTML::make_info( \__( 'This option is managed globally on the SEO Settings page when this post is set as the static homepage.', 'better-seo' ) );
						} else {
							?>
							<input type="checkbox" name="better-seo[_better_seo_title_no_blogname]" id="better-seo-title-no-blogname" value="1" <?php \checked( $title_no_blogname_value ); ?>>
							<?php
							\esc_html_e( 'Remove the site title?', 'better-seo' );
							echo ' ';
							HTML::make_info( \__( 'Enable this option when you want to control the title format manually, without the site name appended or prepended.', 'better-seo' ) );
						}
						?>
					</label>
				</div>
			</div>
		</div>

		<div class="better-seo-flex-setting better-seo-flex">
			<div class="better-seo-flex-setting-label better-seo-flex">
				<div class="better-seo-flex-setting-label-inner-wrap better-seo-flex">
					<label for="better-seo-description" class="better-seo-flex-setting-label-item better-seo-flex">
						<div><strong><?php \esc_html_e( 'Meta Description', 'better-seo' ); ?></strong></div>
						<div>
							<?php
							HTML::make_info(
								\__( 'The meta description appears beneath your title in search results. A compelling description encourages users to click through to your page.', 'better-seo' ),
								'https://developers.google.com/search/docs/advanced/appearance/snippet',
							);
							?>
						</div>
					</label>
					<?php
					if ( Data\Plugin::get_option( 'display_character_counter' ) ) {
						Form::output_character_counter_wrap( 'better-seo-description' );
					}
					if ( Data\Plugin::get_option( 'display_pixel_counter' ) ) {
						Form::output_pixel_counter_wrap( 'better-seo-description', 'description' );
					}
					?>
				</div>
			</div>
			<div class="better-seo-flex-setting-input better-seo-flex">
				<textarea class="large-text" name="better-seo[_genesis_description]" id="better-seo-description" rows="4" cols="4" autocomplete="off"><?php echo \esc_html( Sanitize::metadata_content( $meta['_genesis_description'] ) ); ?></textarea>
				<?php
				Input::output_js_description_data(
					'better-seo-description',
					[
						'state' => [
							'defaultDescription'   => \esc_html( Sanitize::metadata_content( $default_description ) ),
							'refDescriptionLocked' => $description_ref_locked,
						],
					],
				);
				?>
			</div>
		</div>
		<?php
		break;

	endswitch;
