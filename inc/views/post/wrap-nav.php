<?php
/**
 * Better SEO - View: Post Wrap Navigation
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

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- view file, not global scope.

// See flex_nav_tab_wrapper for the calling context.
[ $id, $tabs ] = $view_args;

/**
 * Render the tab navigation bar.
 * Navigation is only output when there are two or more tabs to display.
 */
if ( \count( $tabs ) > 1 ) {
	?>
	<div class="better-seo-flex better-seo-flex-nav-tab-wrapper better-seo-flex-hide-if-no-js" id="<?php echo \esc_attr( "better-seo-flex-{$id}-tabs-wrapper" ); ?>">
		<div class="better-seo-flex better-seo-flex-nav-tab-inner">
			<?php
			$tab_index = 1;

			foreach ( $tabs as $tab => $value ) {
				$dashicon   = $value['dashicon'] ?? '';
				$label_name = $value['name'] ?? '';

				$wrapper_id     = \esc_attr( "better-seo-flex-nav-tab-{$tab}" );
				$wrapper_active = 1 === $tab_index ? 'better-seo-flex-nav-tab-active' : '';

				$input_checked = 1 === $tab_index ? 'checked' : '';
				$input_id      = \esc_attr( "better-seo-flex-{$id}-tab-{$tab}" );
				$input_name    = \esc_attr( "better-seo-flex-{$id}-tabs" );

				$dashicon_html   = '';
				$label_name_html = '';

				if ( $dashicon ) {
					$dashicon_html = \sprintf(
						'<span class="better-seo-flex dashicons %s better-seo-flex-nav-dashicon"></span>',
						\esc_attr( "dashicons-{$dashicon}" ),
					);
				}

				if ( $label_name ) {
					$label_name_html = \sprintf(
						'<span class="better-seo-flex better-seo-flex-nav-name">%s</span>',
						\esc_html( $label_name ),
					);
				}

				?>
				<div class="better-seo-flex better-seo-flex-nav-tab <?php echo \esc_attr( $wrapper_active ); ?>" id="<?php echo $wrapper_id; // phpcs:ignore WordPress.Security.EscapeOutput -- already escaped above. ?>">
					<input type="radio" class="better-seo-flex-nav-tab-radio better-seo-input-not-saved" id="<?php echo $input_id; // phpcs:ignore WordPress.Security.EscapeOutput -- already escaped above. ?>" name="<?php echo $input_name; // phpcs:ignore WordPress.Security.EscapeOutput -- already escaped above. ?>" <?php echo $input_checked; // phpcs:ignore WordPress.Security.EscapeOutput -- static string 'checked' or ''. ?>>
					<label for="<?php echo $input_id; // phpcs:ignore WordPress.Security.EscapeOutput -- already escaped above. ?>" class="better-seo-flex better-seo-flex-nav-tab-label">
						<?php
						// phpcs:disable WordPress.Security.EscapeOutput -- both variables are escaped above via esc_attr/esc_html.
						echo $dashicon_html;
						echo $label_name_html;
						// phpcs:enable WordPress.Security.EscapeOutput
						?>
					</label>
				</div>
				<?php

				++$tab_index;
			}
			?>
		</div>
	</div>
	<?php
}