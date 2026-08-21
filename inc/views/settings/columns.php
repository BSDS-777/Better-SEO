<?php
/**
 * Better SEO - View: Settings Columns
 *
 * Renders the two-column metabox layout for the Better SEO settings page.
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

$hook_name = Admin\Menu::get_page_hook_name();

?>
<div class="metabox-holder columns-2">
	<div class="postbox-container-1">
		<?php
		/**
		 * Fires before the main Better SEO settings metaboxes are rendered.
		 *
		 * @since 1.0.0
		 * @param string $hook_name The current admin page hook name.
		 */
		\do_action( 'better_seo_before_siteadmin_metaboxes', $hook_name );

		\do_meta_boxes( $hook_name, 'main', null );

		if ( isset( $GLOBALS['wp_meta_boxes'][ $hook_name ]['main_extra'] ) ) {
			\do_meta_boxes( $hook_name, 'main_extra', null );
		}

		/**
		 * Fires after the main Better SEO settings metaboxes are rendered.
		 *
		 * @since 1.0.0
		 * @param string $hook_name The current admin page hook name.
		 */
		\do_action( 'better_seo_after_siteadmin_metaboxes', $hook_name );
		?>
	</div>
	<div class="postbox-container-2">
		<?php
		/**
		 * Fires before the sidebar Better SEO settings metaboxes are rendered.
		 *
		 * @since 1.0.0
		 * @param string $hook_name The current admin page hook name.
		 */
		\do_action( 'better_seo_before_siteadmin_metaboxes_side', $hook_name );

		/**
		 * Fires after the sidebar Better SEO settings metaboxes are rendered.
		 *
		 * @since 1.0.0
		 * @param string $hook_name The current admin page hook name.
		 */
		\do_action( 'better_seo_after_siteadmin_metaboxes_side', $hook_name );
		?>
	</div>
</div>