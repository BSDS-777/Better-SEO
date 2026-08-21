<?php
/**
 * Better SEO - View: Template Inpost Primary Term Selector
 *
 * Underscore.js template for the primary term selector hidden input and nonce field.
 * Rendered client-side by the Better SEO block editor and classic editor scripts.
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\Templates\Inpost
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

use Better_SEO\Admin\Settings\Layout\HTML;

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- view file, not global scope.

?>
<script type="text/html" id="tmpl-better-seo-primary-term-selector">
	<input type="hidden" id="better-seo[_primary_term_{{data.taxonomy.name}}]" name="better-seo[_primary_term_{{data.taxonomy.name}}]" value="{{data.taxonomy.primary}}">
	<?php
	\wp_nonce_field(
		Data\Admin\Post::SAVE_NONCES['post-edit']['action'] . '_pt',
		Data\Admin\Post::SAVE_NONCES['post-edit']['name'] . '_pt_{{data.taxonomy.name}}',
	);
	?>
</script>