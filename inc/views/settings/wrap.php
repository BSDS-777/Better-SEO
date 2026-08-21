<?php
/**
 * Better SEO - View: Settings Wrap
 *
 * Renders the main Better SEO settings page wrapper, including the form,
 * save/reset/extensions buttons, and metabox layout.
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

use Better_SEO\Admin\Settings\Layout\Input;

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- view file, not global scope.

// Render the Extensions button if the extension manager is active and its page is registered.
if (
	   \function_exists( 'better_seo_extension_manager' )
	&& \in_array(
		\better_seo_extension_manager()->seo_extensions_page_slug ?? null,
		array_column( $GLOBALS['submenu'][ \BETTER_SEO_SITE_OPTIONS_SLUG ] ?? [], 2 ),
		true,
	)
) {
	$_extensions_button = \sprintf(
		'<a href="%s" class="button">%s</a>',
		// phpcs:ignore WordPress.Security.EscapeOutput -- menu_page_url() returns an escaped URL.
		\menu_page_url( \better_seo_extension_manager()->seo_extensions_page_slug, false ),
		\esc_html_x( 'Extensions', 'Plugin extensions', 'better-seo' ),
	);
} else {
	$_extensions_button = Admin\Utils::display_extension_suggestions()
		? \sprintf(
			'<a href="%s" class="button" rel="noreferrer noopener" target="_blank">%s</a>',
			'https://briansmith.design/better-seo/extensions/',
			\esc_html_x( 'Extensions', 'Plugin extensions', 'better-seo' ),
		)
		: '';
}

$_save_button = \get_submit_button(
	\__( 'Save Settings', 'better-seo' ),
	[ 'primary' ],
	'submit',
	false,
	[ 'id' => '' ], // Output twice — no ID to avoid duplicates.
);

$_ays_reset    = \esc_js( \__( 'Are you sure you want to reset all Better SEO settings to their defaults? This cannot be undone.', 'better-seo' ) );
$_reset_button = \get_submit_button(
	\__( 'Reset Settings', 'better-seo' ),
	[ 'secondary' ],
	Input::get_field_name( 'better-seo-settings-reset' ),
	false,
	[
		'id'      => '', // Output twice — no ID to avoid duplicates.
		'onclick' => "return confirm(`{$_ays_reset}`)", // Passes through \esc_attr() unscathed.
	],
);

$hook_name = Admin\Menu::get_page_hook_name();

?>
<div class="wrap better-seo-metaboxes">
	<form id="better-seo-settings" method="post" action="options.php" autocomplete="off" data-form-type="other">
		<?php \wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
		<?php \wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
		<?php \settings_fields( \BETTER_SEO_SITE_OPTIONS ); ?>

		<div class="better-seo-top-wrap">
			<h1><?php echo \esc_html( \get_admin_page_title() ); ?></h1>
			<div class="better-seo-top-buttons better-seo-end">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput -- all button variables are escaped above.
				echo $_save_button, $_reset_button, $_extensions_button;
				?>
			</div>
		</div>

		<hr class="wp-header-end">

		<?php
		/**
		 * Fires where Better SEO settings notices should be rendered.
		 *
		 * @since 1.0.0
		 */
		\do_action( 'better_seo_setting_notices' );
		?>

		<?php
		/**
		 * Fires to render the Better SEO settings page metaboxes.
		 *
		 * @since 1.0.0
		 * @param string $hook_name The current admin page hook name.
		 */
		\do_action( "{$hook_name}_settings_page_boxes", $hook_name );
		?>

		<div class="better-seo-bottom-wrap">
			<div class="better-seo-bottom-buttons better-seo-start">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
				echo $_extensions_button;
				?>
			</div>
			<div class="better-seo-bottom-buttons better-seo-end">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
				echo $_save_button;
				?>
			</div>
		</div>
	</form>
</div>
<script>
	addEventListener( 'load', () => {
		postboxes.add_postbox_toggles( '<?php echo \esc_js( $hook_name ); ?>' );
	} );
</script>