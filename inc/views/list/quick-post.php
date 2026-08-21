<?php
/**
 * Better SEO - View: List Quick Post Edit
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\List
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 * @link       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * POST index: better-seo-quick
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

use Better_SEO\Admin\Settings\Layout\{
	Form,
	Input,
};

[ $post_type, $taxonomy ] = $view_args;

$robots_settings = [
	'noindex'   => [
		'id'        => 'better-seo-quick[noindex]',
		'name'      => 'better-seo-quick[noindex]',
		'force_on'  => 'index',
		'force_off' => 'noindex',
		'label'     => \__( 'Indexing', 'better-seo' ),
	],
	'nofollow'  => [
		'id'        => 'better-seo-quick[nofollow]',
		'name'      => 'better-seo-quick[nofollow]',
		'force_on'  => 'follow',
		'force_off' => 'nofollow',
		'label'     => \__( 'Link following', 'better-seo' ),
	],
	'noarchive' => [
		'id'        => 'better-seo-quick[noarchive]',
		'name'      => 'better-seo-quick[noarchive]',
		'force_on'  => 'archive',
		'force_off' => 'noarchive',
		'label'     => \__( 'Archiving', 'better-seo' ),
	],
];

?>
<div class="better-seo-quick-edit-columns">
	<?php
	\wp_nonce_field(
		Data\Admin\Post::SAVE_NONCES['quick-edit']['action'],
		Data\Admin\Post::SAVE_NONCES['quick-edit']['name'],
	);

	/**
	 * Fires before the Better SEO quick edit fields are output.
	 *
	 * @since 1.0.0
	 * @param string $post_type The current post type slug.
	 * @param string $taxonomy  The current taxonomy slug.
	 */
	\do_action( 'better_seo_before_quick_edit', $post_type, $taxonomy );
	?>
	<fieldset class="better-seo-inline-edit-col-wide">
		<legend class="inline-edit-legend"><?php \esc_html_e( 'General SEO Settings', 'better-seo' ); ?></legend>
		<div class="inline-edit-col better-seo-le-wide-complex-column">
			<label for="better-seo-quick[doctitle]">
				<span class="title"><?php \esc_html_e( 'Meta Title', 'better-seo' ); ?></span>
			</label>
			<?php
			if ( Data\Plugin::get_option( 'display_character_counter' ) ) {
				Form::output_character_counter_wrap( 'better-seo-quick[doctitle]' );
			}
			if ( Data\Plugin::get_option( 'display_pixel_counter' ) ) {
				Form::output_pixel_counter_wrap( 'better-seo-quick[doctitle]', 'title' );
			}
			?>
			<div class="better-seo-pad-input better-seo-title-wrap">
				<input type="text" id="better-seo-quick[doctitle]" name="better-seo-quick[doctitle]">
				<?php Input::output_js_title_data( 'better-seo-quick[doctitle]', [] ); ?>
			</div>
		</div>
		<div class="inline-edit-col better-seo-le-wide-complex-column">
			<label for="better-seo-quick[description]">
				<span class="title"><?php \esc_html_e( 'Meta Description', 'better-seo' ); ?></span>
			</label>
			<?php
			if ( Data\Plugin::get_option( 'display_character_counter' ) ) {
				Form::output_character_counter_wrap( 'better-seo-quick[description]' );
			}
			if ( Data\Plugin::get_option( 'display_pixel_counter' ) ) {
				Form::output_pixel_counter_wrap( 'better-seo-quick[description]', 'description' );
			}
			?>
			<div class="better-seo-pad-input">
				<textarea id="better-seo-quick[description]" name="better-seo-quick[description]" rows="3" cols="22"></textarea>
				<?php Input::output_js_description_data( 'better-seo-quick[description]', [] ); ?>
			</div>
		</div>
	</fieldset>
	<fieldset class="better-seo-inline-edit-col-normal">
		<legend class="inline-edit-legend"><?php \esc_html_e( 'Visibility SEO Settings', 'better-seo' ); ?></legend>
		<div class="inline-edit-col">
			<label>
				<span class="title"><?php \esc_html_e( 'Canonical URL', 'better-seo' ); ?></span>
				<span class="better-seo-inline-input">
					<input type="url" id="better-seo-quick[canonical]" name="better-seo-quick[canonical]">
					<?php Input::output_js_canonical_data( 'better-seo-quick[canonical]', [] ); ?>
				</span>
			</label>
			<div class="inline-edit-group wp-clearfix">
				<?php
				/* translators: %s = default option value */
				$_default_i18n = \__( 'Default (%s)', 'better-seo' );

				foreach ( $robots_settings as $_setting ) {
					echo '<label class="clear">';
					printf( '<span class="title">%s</span>', \esc_html( $_setting['label'] ) );
					// phpcs:disable WordPress.Security.EscapeOutput -- make_single_select_form() escapes.
					echo Form::make_single_select_form( [
						'id'       => $_setting['id'],
						'name'     => $_setting['name'],
						'options'  => [
							0  => $_default_i18n,
							-1 => $_setting['force_on'],
							1  => $_setting['force_off'],
						],
						'selected' => 0,
						'data'     => [
							'defaultI18n' => $_default_i18n,
						],
					] );
					// phpcs:enable WordPress.Security.EscapeOutput
					echo '</label>';
				}
				?>
			</div>
			<div class="inline-edit-group wp-clearfix">
				<label>
					<span class="title"><?php \esc_html_e( '301 Redirect URL', 'better-seo' ); ?></span>
					<span class="better-seo-inline-input">
						<input type="url" id="better-seo-quick[redirect]" name="better-seo-quick[redirect]">
					</span>
				</label>
			</div>
		</div>
	</fieldset>
	<?php
	/**
	 * Fires after the Better SEO quick edit fields are output.
	 *
	 * @since 1.0.0
	 * @param string $post_type The current post type slug.
	 * @param string $taxonomy  The current taxonomy slug.
	 */
	\do_action( 'better_seo_after_quick_edit', $post_type, $taxonomy );
	?>
</div>