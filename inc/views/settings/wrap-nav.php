<?php
/**
 * Better SEO - View: Settings Wrap Navigation
 *
 * Renders the tab navigation bar for the Better SEO settings page tab wrapper.
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

// Navigation is only rendered when there are two or more tabs to display.
if ( \count( $tabs ) > 1 ) {
	?>
	<div class="better-seo-nav-tab-wrapper hide-if-no-better-seo-js" id="<?php echo \esc_attr( "{$id}-tabs-wrapper" ); ?>">
		<?php
		$tab_index = 1;

		foreach ( $tabs as $tab => $args ) {
			$dashicon = $args['dashicon'] ?? '';
			$name     = $args['name'] ?? '';

			$input_id   = \esc_attr( "better-seo-{$id}-tab-{$tab}" );
			$input_name = \esc_attr( "better-seo-{$id}-tabs" );
			$checked    = 1 === $tab_index ? 'checked' : '';

			$dashicon_html = $dashicon
				? '<span class="dashicons dashicons-' . \esc_attr( $dashicon ) . ' better-seo-dashicons-tabs"></span>'
				: '';

			$name_html = $name
				? '<span class="better-seo-nav-desktop">' . \esc_html( $name ) . '</span>'
				: '';

			printf(
				'<div class="better-seo-tab">%s</div>',
				vsprintf(
					'<input type="radio" class="better-seo-nav-tab-radio better-seo-input-not-saved" id="%1$s" name="%2$s" %3$s><label for="%1$s" class="better-seo-nav-tab-label">%4$s</label>',
					[
						$input_id,
						$input_name,
						$checked, // phpcs:ignore WordPress.Security.EscapeOutput -- static string 'checked' or ''.
						// phpcs:ignore WordPress.Security.EscapeOutput -- both variables are escaped above via esc_attr/esc_html.
						\sprintf( '%s%s', $dashicon_html, $name_html ),
					],
				),
			);

			++$tab_index;
		}
		?>
	</div>
	<?php
}