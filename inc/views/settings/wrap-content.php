<?php
/**
 * Better SEO - View: Settings Wrap Content
 *
 * Renders the tab content panels for the Better SEO settings page tab wrapper.
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\Settings
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 * @link       https://www.gnu.org/licenses/gpl-2.0.html
 */

declare( strict_types=1 );

namespace Better_SEO;

if ( ! \defined( 'BETTER_SEO_PRESENT' ) || ! Helper\Template::verify_secret( $secret ) ) {
	exit; // Access denied — direct file access is not permitted.
}

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- view file, not global scope.

[ $id, $tabs ] = $view_args;

$show_tabs = \count( $tabs ) > 1;
$tab_index = 1;

foreach ( $tabs as $tab => $args ) {

	$radio_id    = "better-seo-{$id}-tab-{$tab}-content";
	$radio_class = "better-seo-{$id}-tabs-content";

	// Mark the first tab as active on initial render.
	$current_class = 1 === $tab_index ? ' better-seo-nav-tab-content-active' : '';

	?>
	<div class="better-seo-nav-tab-content <?php echo \esc_attr( $radio_class . $current_class ); ?>" id="<?php echo \esc_attr( $radio_id ); ?>">
		<?php
		// No-JS fallback: render the tab label inline with its content panel.
		if ( $show_tabs ) {
			$dashicon = $args['dashicon'] ?? '';
			$name     = $args['name'] ?? '';

			?>
			<div class="hide-if-better-seo-js better-seo-nav-tab-content-no-js">
				<div class="better-seo-tab better-seo-nav-tab-no-js">
					<span class="better-seo-nav-tab-label better-seo-nav-tab-active">
						<?php if ( $dashicon ) : ?>
							<span class="dashicons dashicons-<?php echo \esc_attr( $dashicon ); ?> better-seo-dashicons-tabs"></span>
						<?php endif; ?>
						<?php if ( $name ) : ?>
							<span><?php echo \esc_html( $name ); ?></span>
						<?php endif; ?>
					</span>
				</div>
			</div>
			<?php
		}

		if ( ! empty( $args['callback'] ) ) {
			\call_user_func_array( $args['callback'], [ ( $args['args'] ?? [] ) ] );
		}

		/**
		 * Fires after the Better SEO settings tab content is rendered.
		 *
		 * @since 1.0.0
		 * @param array<string, mixed> $context The tab context (id, tab, args).
		 */
		\do_action(
			'better_seo_tab_content',
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