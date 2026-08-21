<?php
/**
 * Better SEO - View: Post Wrap Content
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

[ $id, $tabs ] = $view_args;

$show_tabs = \count( $tabs ) > 1;
$tab_index = 1;

foreach ( $tabs as $tab => $args ) {

	$radio_id    = "better-seo-flex-{$id}-tab-{$tab}-content";
	$radio_class = "better-seo-flex-{$id}-tabs-content";

	// Mark the first tab as active on initial render.
	$current_class = 1 === $tab_index ? ' better-seo-flex-tab-content-active' : '';

	?>
	<div class="better-seo-flex better-seo-flex-tab-content <?php echo \esc_attr( $radio_class . $current_class ); ?>" id="<?php echo \esc_attr( $radio_id ); ?>">
		<?php
		// No-JS fallback: render tab label inline with content.
		if ( $show_tabs ) {
			$dashicon   = $args['dashicon'] ?? '';
			$label_name = $args['name'] ?? '';

			?>
			<div class="better-seo-flex better-seo-flex-hide-if-js better-seo-flex-tabs-content-no-js">
				<div class="better-seo-flex better-seo-flex-nav-tab better-seo-flex-tab-no-js">
					<span class="better-seo-flex better-seo-flex-nav-tab">
						<?php if ( $dashicon ) : ?>
							<span class="better-seo-flex dashicons dashicons-<?php echo \esc_attr( $dashicon ); ?> better-seo-flex-nav-dashicon"></span>
						<?php endif; ?>
						<?php if ( $label_name ) : ?>
							<span class="better-seo-flex better-seo-flex-nav-name"><?php echo \esc_html( $label_name ); ?></span>
						<?php endif; ?>
					</span>
				</div>
			</div>
			<?php
		}

		if ( ! empty( $args['callback'] ) ) {
			\call_user_func_array( $args['callback'], ( $args['args'] ?? [] ) );
		}

		/**
		 * Fires after the Better SEO flex tab content is rendered.
		 *
		 * @since 1.0.0
		 * @param array<string, mixed> $context The tab context (id, tab, args).
		 */
		\do_action(
			'better_seo_flex_tab_content',
			[
				'id'   => $id,
				'tab'  => $tab,
				'args' => $args,
			],
		);
		?>
	</div>
	<?php
	++$tab_index;
}