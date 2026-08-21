<?php
/**
 * Better SEO - View: Template List Primary Term Selector
 *
 * Underscore.js templates for the primary term selector dropdowns used in
 * the WordPress post list quick edit and bulk edit panels.
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\Templates\List
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

?>
<script type="text/html" id="tmpl-better-seo-primary-term-selector-quick">
	<div id="{{data.wrapId}}" class="better-seo-primary-term-selector-wrap">
		<label for="{{data.selectId}}">{{data.i18n.selectPrimary}}</label>
		<select id="{{data.selectId}}" name="{{data.selectName}}"></select>
	</div>
</script>
<script type="text/html" id="tmpl-better-seo-primary-term-selector-bulk">
	<div id="{{data.wrapId}}" class="better-seo-primary-term-selector-wrap">
		<label for="{{data.selectId}}">{{data.i18n.selectPrimary}}</label>
		<select id="{{data.selectId}}" name="{{data.selectName}}">
			{{! No Change option — label is passed via data.i18n from the JS side. }}
			<option value="nochange">— <?php \esc_html_e( 'No Change', 'better-seo' ); ?> —</option>
		</select>
	</div>
</script>