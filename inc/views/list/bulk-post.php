<?php
/**
 * Better SEO - View: List Bulk Post Edit
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\List
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 * @link       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * POST index: better-seo-bulk
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

use Better_SEO\Admin\Settings\Layout\Form;

[ $post_type, $taxonomy ] = $view_args;

$robots_settings = [
	'noindex'   => [
		'id'        => 'better-seo-bulk[noindex]',
		'name'      => 'better-seo-bulk[noindex]',
		'force_on'  => 'index',
		'force_off' => 'noindex',
		'label'     => \__( 'Indexing', 'better-seo' ),
	],
	'nofollow'  => [
		'id'        => 'better-seo-bulk[nofollow]',
		'name'      => 'better-seo-bulk[nofollow]',
		'force_on'  => 'follow',
		'force_off' => 'nofollow',
		'label'     => \__( 'Link following', 'better-seo' ),
	],
	'noarchive' => [
		'id'        => 'better-seo-bulk[noarchive]',
		'name'      => 'better-seo-bulk[noarchive]',
		'force_on'  => 'archive',
		'force_off' => 'noarchive',
		'label'     => \__( 'Archiving', 'better-seo' ),
	],
];

?>
<div class="better-seo-quick-edit-columns">
	<?php
	\wp_nonce_field(
		Data\Admin\Post::SAVE_NONCES['bulk-edit']['action'],
		Data\Admin\Post::SAVE_NONCES['bulk-edit']['name'],
	);

	/**
	 * Fires before the Better SEO bulk edit fields are output.
	 *
	 * @since 1.0.0
	 * @param string $post_type The current post type slug.
	 * @param string $taxonomy  The current taxonomy slug.
	 */
	\do_action( 'better_seo_before_bulk_edit', $post_type, $taxonomy );
	?>
	<fieldset class="inline-edit-col-left">
		<legend class="inline-edit-legend"><?php \esc_html_e( 'Visibility SEO Settings', 'better-seo' ); ?></legend>
		<div class="inline-edit-col">
			<div class="inline-edit-group wp-clearfix">
				<?php
				$_no_change_i18n      = \__( '&mdash; No Change &mdash;', 'better-seo' );
				$_default_unknown_i18n = \__( 'Default (unknown)', 'better-seo' );

				foreach ( $robots_settings as $_setting ) {
					echo '<label class="clear">';
					printf( '<span class="title">%s</span>', \esc_html( $_setting['label'] ) );
					// phpcs:disable WordPress.Security.EscapeOutput -- make_single_select_form() escapes.
					echo Form::make_single_select_form( [
						'id'       => $_setting['id'],
						'name'     => $_setting['name'],
						'options'  => [
							'nochange' => $_no_change_i18n,
							0          => $_default_unknown_i18n,
							-1         => $_setting['force_on'],
							1          => $_setting['force_off'],
						],
						'selected' => 'nochange',
					] );
					// phpcs:enable WordPress.Security.EscapeOutput
					echo '</label>';
				}
				?>
			</div>
		</div>
	</fieldset>
	<?php
	/**
	 * Fires after the Better SEO bulk edit fields are output.
	 *
	 * @since 1.0.0
	 * @param string $post_type The current post type slug.
	 * @param string $taxonomy  The current taxonomy slug.
	 */
	\do_action( 'better_seo_after_bulk_edit', $post_type, $taxonomy );
	?>
</div>