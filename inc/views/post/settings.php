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
					echo Admin\SEOBar\Builder::generate_bar( $generator_args );
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
					<input class="large-text" type="text" name="better-seo[_genesis_title]" id="better-seo-title" value="<?php echo \esc_html( Sanitize::metadata_content( $meta['_genesis_title'] ) ); ?>" autocomplete="off" data-form-type="other">
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

	case 'social':
		$show_og = (bool) Data\Plugin::get_option( 'og_tags' );
		$show_tw = (bool) Data\Plugin::get_option( 'twitter_tags' );

		if ( $is_static_front_page ) {
			$_social_title       = [
				'og' => coalesce_strlen( Data\Plugin::get_option( 'homepage_og_title' ) )
						?? coalesce_strlen( Data\Plugin::get_option( 'homepage_title' ) )
						?? Meta\Open_Graph::get_generated_title( $generator_args ),
				'tw' => coalesce_strlen( Data\Plugin::get_option( 'homepage_twitter_title' ) )
						?? coalesce_strlen( Data\Plugin::get_option( 'homepage_og_title' ) )
						?? coalesce_strlen( Data\Plugin::get_option( 'homepage_title' ) )
						?? Meta\Twitter::get_generated_title( $generator_args ),
			];
			$_social_description = [
				'og' => coalesce_strlen( Data\Plugin::get_option( 'homepage_og_description' ) )
						?? coalesce_strlen( Data\Plugin::get_option( 'homepage_description' ) )
						?? Meta\Open_Graph::get_generated_description( $generator_args ),
				'tw' => coalesce_strlen( Data\Plugin::get_option( 'homepage_twitter_description' ) )
						?? coalesce_strlen( Data\Plugin::get_option( 'homepage_og_description' ) )
						?? coalesce_strlen( Data\Plugin::get_option( 'homepage_description' ) )
						?? Meta\Twitter::get_generated_description( $generator_args ),
			];

			$_twitter_card = Data\Plugin::get_option( 'homepage_twitter_card_type' )
						  ?: Meta\Twitter::get_generated_card_type( $generator_args );
		} else {
			$_social_title       = [
				'og' => Meta\Open_Graph::get_generated_title( $generator_args ),
				'tw' => Meta\Twitter::get_generated_title( $generator_args ),
			];
			$_social_description = [
				'og' => Meta\Open_Graph::get_generated_description( $generator_args ),
				'tw' => Meta\Twitter::get_generated_description( $generator_args ),
			];

			$_twitter_card = Meta\Twitter::get_generated_card_type( $generator_args );
		}

		Input::output_js_social_data(
			'better-seo-social-singular',
			[
				'og' => [
					'state' => [
						'defaultTitle' => \esc_html( Sanitize::metadata_content( $_social_title['og'] ) ),
						'addAdditions' => Meta\Title\Conditions::use_branding( $generator_args, 'og' ),
						'defaultDesc'  => \esc_html( Sanitize::metadata_content( $_social_description['og'] ) ),
						'titleLock'    => $is_static_front_page && \strlen( Data\Plugin::get_option( 'homepage_og_title' ) ),
						'descLock'     => $is_static_front_page && \strlen( Data\Plugin::get_option( 'homepage_og_description' ) ),
					],
				],
				'tw' => [
					'state' => [
						'defaultTitle' => \esc_html( Sanitize::metadata_content( $_social_title['tw'] ) ),
						'addAdditions' => Meta\Title\Conditions::use_branding( $generator_args, 'twitter' ),
						'defaultDesc'  => \esc_html( Sanitize::metadata_content( $_social_description['tw'] ) ),
						'titleLock'    => $is_static_front_page && \strlen( Data\Plugin::get_option( 'homepage_twitter_title' ) ),
						'descLock'     => $is_static_front_page && \strlen( Data\Plugin::get_option( 'homepage_twitter_description' ) ),
					],
				],
			],
		);

		?>
		<div class="better-seo-flex-setting better-seo-flex" <?php echo $show_og ? '' : 'style="display:none"'; ?>>
			<div class="better-seo-flex-setting-label better-seo-flex">
				<div class="better-seo-flex-setting-label-inner-wrap better-seo-flex">
					<label for="better-seo-og-title" class="better-seo-flex-setting-label-item better-seo-flex">
						<div><strong><?php \esc_html_e( 'Open Graph Title', 'better-seo' ); ?></strong></div>
					</label>
					<?php
					if ( Data\Plugin::get_option( 'display_character_counter' ) ) {
						Form::output_character_counter_wrap( 'better-seo-og-title' );
					}
					?>
				</div>
			</div>
			<div class="better-seo-flex-setting-input better-seo-flex">
				<div id="better-seo-og-title-wrap">
					<input class="large-text" type="text" name="better-seo[_open_graph_title]" id="better-seo-og-title" value="<?php echo \esc_html( Sanitize::metadata_content( $meta['_open_graph_title'] ) ); ?>" autocomplete="off" data-form-type="other" data-better-seo-social-group="better-seo-social-singular" data-better-seo-social-type="ogTitle">
				</div>
			</div>
		</div>

		<div class="better-seo-flex-setting better-seo-flex" <?php echo $show_og ? '' : 'style="display:none"'; ?>>
			<div class="better-seo-flex-setting-label better-seo-flex">
				<div class="better-seo-flex-setting-label-inner-wrap better-seo-flex">
					<label for="better-seo-og-description" class="better-seo-flex-setting-label-item better-seo-flex">
						<div><strong><?php \esc_html_e( 'Open Graph Description', 'better-seo' ); ?></strong></div>
					</label>
					<?php
					if ( Data\Plugin::get_option( 'display_character_counter' ) ) {
						Form::output_character_counter_wrap( 'better-seo-og-description' );
					}
					?>
				</div>
			</div>
			<div class="better-seo-flex-setting-input better-seo-flex">
				<textarea class="large-text" name="better-seo[_open_graph_description]" id="better-seo-og-description" rows="3" cols="4" autocomplete="off" data-better-seo-social-group="better-seo-social-singular" data-better-seo-social-type="ogDesc"><?php echo \esc_html( Sanitize::metadata_content( $meta['_open_graph_description'] ) ); ?></textarea>
			</div>
		</div>

		<div class="better-seo-flex-setting better-seo-flex" <?php echo $show_tw ? '' : 'style="display:none"'; ?>>
			<div class="better-seo-flex-setting-label better-seo-flex">
				<div class="better-seo-flex-setting-label-inner-wrap better-seo-flex">
					<label for="better-seo-twitter-title" class="better-seo-flex-setting-label-item better-seo-flex">
						<div><strong><?php \esc_html_e( 'X (Twitter) Title', 'better-seo' ); ?></strong></div>
					</label>
					<?php
					if ( Data\Plugin::get_option( 'display_character_counter' ) ) {
						Form::output_character_counter_wrap( 'better-seo-twitter-title' );
					}
					?>
				</div>
			</div>
			<div class="better-seo-flex-setting-input better-seo-flex">
				<div id="better-seo-twitter-title-wrap">
					<input class="large-text" type="text" name="better-seo[_twitter_title]" id="better-seo-twitter-title" value="<?php echo \esc_html( Sanitize::metadata_content( $meta['_twitter_title'] ) ); ?>" autocomplete="off" data-form-type="other" data-better-seo-social-group="better-seo-social-singular" data-better-seo-social-type="twTitle">
				</div>
			</div>
		</div>

		<div class="better-seo-flex-setting better-seo-flex" <?php echo $show_tw ? '' : 'style="display:none"'; ?>>
			<div class="better-seo-flex-setting-label better-seo-flex">
				<div class="better-seo-flex-setting-label-inner-wrap better-seo-flex">
					<label for="better-seo-twitter-description" class="better-seo-flex-setting-label-item better-seo-flex">
						<div><strong><?php \esc_html_e( 'X (Twitter) Description', 'better-seo' ); ?></strong></div>
					</label>
					<?php
					if ( Data\Plugin::get_option( 'display_character_counter' ) ) {
						Form::output_character_counter_wrap( 'better-seo-twitter-description' );
					}
					?>
				</div>
			</div>
			<div class="better-seo-flex-setting-input better-seo-flex">
				<textarea class="large-text" name="better-seo[_twitter_description]" id="better-seo-twitter-description" rows="3" cols="4" autocomplete="off" data-better-seo-social-group="better-seo-social-singular" data-better-seo-social-type="twDesc"><?php // phpcs:ignore Squiz.PHP.EmbeddedPhp -- textarea content must not contain whitespace before the closing tag.
					echo \esc_html( Sanitize::metadata_content( $meta['_twitter_description'] ) );
				?></textarea>
			</div>
		</div>

		<div class="better-seo-flex-setting better-seo-flex" <?php echo $show_tw ? '' : 'style="display:none"'; ?>>
			<div class="better-seo-flex-setting-label better-seo-flex">
				<div class="better-seo-flex-setting-label-inner-wrap better-seo-flex">
					<label for="better-seo-twitter-card-type" class="better-seo-flex-setting-label-item better-seo-flex">
						<div><strong><?php \esc_html_e( 'X (Twitter) Card Type', 'better-seo' ); ?></strong></div>
						<div>
							<?php
							HTML::make_info(
								\__( 'The card type controls how your content appears when shared on X (Twitter) and other platforms like Discord. Use "Summary with Large Image" for maximum visual impact, or "Summary" for a compact card with a small thumbnail.', 'better-seo' ),
								'https://developer.twitter.com/en/docs/twitter-for-websites/cards/overview/abouts-cards',
							);
							?>
						</div>
					</label>
				</div>
			</div>
			<div class="better-seo-flex-setting-input better-seo-flex">
				<?php
				/* translators: %s = the currently active default card type value */
				$_default_i18n     = \__( 'Default (%s)', 'better-seo' );
				$tw_supported_cards = Meta\Twitter::get_supported_cards();

				// phpcs:ignore WordPress.Security.EscapeOutput -- make_single_select_form() escapes.
				echo Form::make_single_select_form( [
					'id'       => 'better-seo-twitter-card-type',
					'class'    => 'better-seo-select-block',
					'name'     => 'better-seo[_better_seo_twitter_card_type]',
					'label'    => '',
					'options'  => array_merge(
						[ '' => \sprintf( $_default_i18n, $_twitter_card ) ],
						array_combine( $tw_supported_cards, $tw_supported_cards ),
					),
					'selected' => $meta['_better_seo_twitter_card_type'],
				] );
				?>
			</div>
		</div>
		<?php

		// Determine the social image placeholder from the homepage setting or generated image.
		if ( $is_static_front_page ) {
			$image_placeholder = Data\Plugin::get_option( 'homepage_social_image_url' )
							  ?: Meta\Image::get_first_generated_image_url( $generator_args, 'social' );
		} else {
			$image_placeholder = Meta\Image::get_first_generated_image_url( $generator_args, 'social' );
		}

		?>
		<div class="better-seo-flex-setting better-seo-flex">
			<div class="better-seo-flex-setting-label better-seo-flex">
				<div class="better-seo-flex-setting-label-inner-wrap better-seo-flex">
					<label for="better-seo-socialimage-url" class="better-seo-flex-setting-label-item better-seo-flex">
						<div><strong><?php \esc_html_e( 'Social Sharing Image', 'better-seo' ); ?></strong></div>
						<div>
							<?php
							HTML::make_info(
								\__( 'This image is displayed when your page is shared on social networks and in search results. For best results, use an image with a 1.91:1 aspect ratio that is at least 1200px wide.', 'better-seo' ),
								'https://developers.facebook.com/docs/sharing/best-practices#images',
							);
							?>
						</div>
					</label>
				</div>
			</div>
			<div class="better-seo-flex-setting-input better-seo-flex">
				<input class="large-text" type="url" name="better-seo[_social_image_url]" id="better-seo-socialimage-url" placeholder="<?php echo \esc_url( $image_placeholder ); ?>" value="<?php echo \esc_url( $meta['_social_image_url'] ); ?>" autocomplete="off">
				<input type="hidden" name="better-seo[_social_image_id]" id="better-seo-socialimage-id" value="<?php echo \absint( $meta['_social_image_id'] ); ?>" disabled class="better-seo-enable-media-if-js">
				<div class="hide-if-no-better-seo-js better-seo-social-image-buttons">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput -- get_image_uploader_form() escapes.
					echo Form::get_image_uploader_form( [ 'id' => 'better-seo-socialimage' ] );
					?>
				</div>
			</div>
		</div>
		<?php
		break;

	case 'visibility':
		if ( $is_static_front_page ) {
			$_has_home_canonical = (bool) \strlen( Data\Plugin::get_option( 'homepage_canonical' ) );

			// When a homepage canonical URL is configured, retrieve the custom field value directly.
			$default_canonical    = $_has_home_canonical
				? Meta\URI::get_custom_canonical_url( $generator_args )
				: Meta\URI::get_generated_url( $generator_args );
			$canonical_ref_locked = $_has_home_canonical;
		} else {
			$default_canonical    = Meta\URI::get_generated_url( $generator_args );
			$canonical_ref_locked = false;
		}

		// Retrieve the current robots defaults, ignoring per-post settings and password protection.
		$r_defaults = Meta\Robots::get_generated_meta(
			$generator_args,
			[ 'noindex', 'nofollow', 'noarchive' ],
			ROBOTS_IGNORE_SETTINGS | ROBOTS_IGNORE_PROTECTION,
		);
		$r_settings = [
			'noindex'   => [
				'id'        => 'better-seo-noindex',
				'option'    => '_genesis_noindex',
				'force_on'  => 'index',
				'force_off' => 'noindex',
				'label'     => \__( 'Indexing', 'better-seo' ),
				'_default'  => empty( $r_defaults['noindex'] ) ? 'index' : 'noindex',
			],
			'nofollow'  => [
				'id'        => 'better-seo-nofollow',
				'option'    => '_genesis_nofollow',
				'force_on'  => 'follow',
				'force_off' => 'nofollow',
				'label'     => \__( 'Link Following', 'better-seo' ),
				'_default'  => empty( $r_defaults['nofollow'] ) ? 'follow' : 'nofollow',
			],
			'noarchive' => [
				'id'        => 'better-seo-noarchive',
				'option'    => '_genesis_noarchive',
				'force_on'  => 'archive',
				'force_off' => 'noarchive',
				'label'     => \__( 'Archiving', 'better-seo' ),
				'_default'  => empty( $r_defaults['noarchive'] ) ? 'archive' : 'noarchive',
			],
		];

		?>
		<div class="better-seo-flex-setting better-seo-flex">
			<div class="better-seo-flex-setting-label better-seo-flex">
				<div class="better-seo-flex-setting-label-inner-wrap better-seo-flex">
					<label for="better-seo-canonical" class="better-seo-flex-setting-label-item better-seo-flex">
						<div><strong><?php \esc_html_e( 'Canonical URL', 'better-seo' ); ?></strong></div>
						<div>
						<?php
							HTML::make_info(
								\__( 'The canonical URL tells search engines which version of this page is the authoritative source, helping to prevent duplicate content issues.', 'better-seo' ),
								'https://developers.google.com/search/docs/advanced/crawling/consolidate-duplicate-urls',
							);
						?>
						</div>
					</label>
				</div>
			</div>
			<div class="better-seo-flex-setting-input better-seo-flex">
				<input class="large-text" type="url" name="better-seo[_genesis_canonical_uri]" id="better-seo-canonical" placeholder="<?php echo \esc_url( $default_canonical ); ?>" value="<?php echo \esc_url( $meta['_genesis_canonical_uri'] ); ?>" autocomplete="off">
				<?php
				$post_type   = Query::get_admin_post_type();
				$permastruct = Meta\URI\Utils::get_url_permastruct( $generator_args );

				$parent_post_slugs         = [];
				$is_post_type_hierarchical = \is_post_type_hierarchical( $post_type );

				// Build parent post slug chain for hierarchical post types using %postname%.
				if ( $is_post_type_hierarchical && str_contains( $permastruct, '%postname%' ) ) {
					// The current post's slug is filled in by JS — only parent slugs are needed here.
					foreach ( Data\Post::get_post_parents( $post_id ) as $parent_post ) {
						$parent_post_slugs[] = [
							'id'   => $parent_post->ID,
							'slug' => $parent_post->post_name,
						];
					}
				}

				// Build parent term slug chains for hierarchical taxonomies used in the permalink structure.
				// post_tag is excluded as it is non-hierarchical and not used in permalink structures.
				$taxonomies = array_diff(
					$post_type ? Taxonomy::get_hierarchical( 'names', $post_type ) : [],
					[ 'post_tag' ],
				);
				$parent_term_slugs_by_tax = [];

				foreach ( $taxonomies as $taxonomy ) {
					if ( str_contains( $permastruct, "%{$taxonomy}%" ) ) {
						foreach (
							Data\Term::get_term_parents(
								Data\Plugin\Post::get_primary_term_id( $post_id, $taxonomy ),
								$taxonomy,
								true,
							)
							as $parent_term
						) {
							$parent_term_slugs_by_tax[ $taxonomy ][] = [
								'id'   => $parent_term->term_id,
								'slug' => $parent_term->slug,
							];
						}
					}
				}

				if ( str_contains( $permastruct, '%author%' ) ) {
					$author_id = Query::get_post_author_id( $post_id );

					if ( $author_id ) {
						$author_slugs = [
							[
								'id'   => $author_id,
								'slug' => Data\User::get_userdata( $author_id, 'user_nicename' ),
							],
						];
					}
				}

				Input::output_js_canonical_data(
					'better-seo-canonical',
					[
						'state' => [
							'refCanonicalLocked'  => $canonical_ref_locked,
							'defaultCanonical'    => \esc_url( $default_canonical ),
							'preferredScheme'     => Meta\URI\Utils::get_preferred_url_scheme(),
							'urlStructure'        => $permastruct,
							'parentPostSlugs'     => $parent_post_slugs ?? [],
							'parentTermSlugs'     => $parent_term_slugs_by_tax,
							'supportedTaxonomies' => $taxonomies,
							'authorSlugs'         => $author_slugs ?? [],
							'isHierarchical'      => $is_post_type_hierarchical,
							'publishDate'         => date( 'c', strtotime( \get_post( $post_id )->post_date ?? 'now' ) ),
						],
					],
				);
				?>
			</div>
		</div>

		<div class="better-seo-flex-setting better-seo-flex">
			<div class="better-seo-flex-setting-label better-seo-flex">
				<div class="better-seo-flex-setting-label-inner-wrap better-seo-flex">
					<div class="better-seo-flex-setting-label-item better-seo-flex">
						<div><strong><?php \esc_html_e( 'Robots Meta Settings', 'better-seo' ); ?></strong></div>
						<div>
						<?php
							HTML::make_info(
								\__( 'These directives instruct search engine robots whether to index this page, follow its links, or store a cached copy. Changes here override the global defaults for this post only.', 'better-seo' ),
								'https://developers.google.com/search/docs/advanced/robots/robots_meta_tag#directives',
							);
						?>
						</div>
					</div>
					<?php
					if ( $is_static_front_page ) {
						printf(
							'<div class="better-seo-flex-setting-label-sub-item"><span class="description attention">%s</span></div>',
							\esc_html__( 'Important: Applying "noindex" or "nofollow" to your homepage will prevent search engines from indexing your entire site. This is almost never the right choice for a public website.', 'better-seo' ),
						);
						printf(
							'<div class="better-seo-flex-setting-label-sub-item"><span class="description">%s</span></div>',
							\esc_html__( 'Note: Any non-default selection here will override the global homepage robots settings configured on the SEO Settings page.', 'better-seo' ),
						);
					}
					?>
				</div>
			</div>
			<div class="better-seo-flex-setting-input better-seo-flex">
				<?php
				foreach ( $r_settings as $_s ) {
					?>
					<div class="better-seo-flex-setting better-seo-flex">
						<div class="better-seo-flex-setting-label better-seo-flex">
							<div class="better-seo-flex-setting-label-inner-wrap better-seo-flex">
								<label for="<?php echo \esc_attr( $_s['id'] ); ?>" class="better-seo-flex-setting-label-item better-seo-flex">
									<div><strong><?php echo \esc_html( $_s['label'] ); ?></strong></div>
								</label>
							</div>
						</div>
						<div class="better-seo-flex-setting-input better-seo-flex">
						<?php
							/* translators: %s = the currently active default robots value (e.g. "index" or "noindex") */
							$_default_i18n = \__( 'Default (%s)', 'better-seo' );

							// phpcs:ignore WordPress.Security.EscapeOutput -- make_single_select_form() escapes.
							echo Form::make_single_select_form( [
								'id'       => $_s['id'],
								'class'    => 'better-seo-select-block',
								'name'     => \sprintf( 'better-seo[%s]', $_s['option'] ),
								'label'    => '',
								'options'  => [
									0  => \sprintf( $_default_i18n, $_s['_default'] ),
									-1 => $_s['force_on'],
									1  => $_s['force_off'],
								],
								'selected' => Data\Plugin\Post::get_meta_item( $_s['option'] ),
								'data'     => [
									'defaultUnprotected' => $_s['_default'],
									'defaultI18n'        => $_default_i18n,
								],
							] );
						?>
						</div>
					</div>
					<?php
				}
				?>
			</div>
		</div>

		<?php
		$can_do_archive_query = Post_Type::supports_taxonomies() && Data\Plugin::get_option( 'alter_archive_query' );
		$can_do_search_query  = (bool) Data\Plugin::get_option( 'alter_search_query' );
		?>

		<?php if ( $can_do_archive_query || $can_do_search_query ) : ?>
		<div class="better-seo-flex-setting better-seo-flex">
			<div class="better-seo-flex-setting-label better-seo-flex">
				<div class="better-seo-flex-setting-label-inner-wrap better-seo-flex">
					<div class="better-seo-flex-setting-label-item better-seo-flex">
						<div><strong><?php \esc_html_e( 'Archive Visibility', 'better-seo' ); ?></strong></div>
					</div>
				</div>
			</div>
			<div class="better-seo-flex-setting-input better-seo-flex">
				<?php if ( $can_do_search_query ) : ?>
				<div class="better-seo-checkbox-wrapper">
					<label for="better-seo-exclude-local-search"><input type="checkbox" name="better-seo[exclude_local_search]" id="better-seo-exclude-local-search" value="1" <?php \checked( $meta['exclude_local_search'] ); ?>>
						<?php \esc_html_e( 'Exclude this page from internal search results.', 'better-seo' ); ?>
					</label>
				</div>
				<?php endif; ?>
				<?php if ( $can_do_archive_query ) : ?>
				<div class="better-seo-checkbox-wrapper">
					<label for="better-seo-exclude-from-archive"><input type="checkbox" name="better-seo[exclude_from_archive]" id="better-seo-exclude-from-archive" value="1" <?php \checked( $meta['exclude_from_archive'] ); ?>>
						<?php \esc_html_e( 'Exclude this page from archive and category listings.', 'better-seo' ); ?>
					</label>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>

		<div class="better-seo-flex-setting better-seo-flex">
			<div class="better-seo-flex-setting-label better-seo-flex">
				<div class="better-seo-flex-setting-label-inner-wrap better-seo-flex">
					<label for="better-seo-redirect" class="better-seo-flex-setting-label-item better-seo-flex">
						<div>
							<strong><?php \esc_html_e( '301 Redirect URL', 'better-seo' ); ?></strong>
						</div>
						<div>
							<?php
							HTML::make_info(
								\__( 'Permanently redirect all visitors and search engines from this page to another URL. Use this when content has moved and you want to preserve SEO value.', 'better-seo' ),
								'https://developers.google.com/search/docs/crawling-indexing/301-redirects',
							);
							?>
						</div>
					</label>
				</div>
			</div>
			<div class="better-seo-flex-setting-input better-seo-flex">
				<input class="large-text" type="url" name="better-seo[redirect]" id="better-seo-redirect" value="<?php echo \esc_url( $meta['redirect'] ); ?>" autocomplete="off">
			</div>
		</div>
		<?php
endswitch;