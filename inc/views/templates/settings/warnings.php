<?php
/**
 * Better SEO - View: Template Settings Warnings
 *
 * Underscore.js templates for contextual warning tooltips displayed in the
 * Better SEO settings page when options are disabled or have limited effect.
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\Templates\Settings
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

use Better_SEO\Admin\Settings\Layout\{
	HTML,
	Input,
};

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- view file, not global scope.

?>
<script type="text/html" id="tmpl-better-seo-disabled-post-type-help">
	<span class="better-seo-post-type-warning">
		<?php
		HTML::make_info(
			\esc_html__( 'This post type has been excluded from Better SEO. Settings for this post type will have no effect.', 'better-seo' ),
		);
		?>
	</span>
</script>

<script type="text/html" id="tmpl-better-seo-disabled-taxonomy-help">
	<span class="better-seo-taxonomy-warning">
		<?php
		HTML::make_info(
			\esc_html__( 'This taxonomy has been excluded from Better SEO. Settings for this taxonomy will have no effect.', 'better-seo' ),
		);
		?>
	</span>
</script>

<script type="text/html" id="tmpl-better-seo-disabled-taxonomy-from-pt-help">
	<span class="better-seo-taxonomy-from-pt-warning">
		<?php
		HTML::make_info(
			\esc_html__( 'All post types associated with this taxonomy have been excluded from Better SEO. This setting will have no effect.', 'better-seo' ),
		);
		?>
	</span>
</script>

<script type="text/html" id="tmpl-better-seo-disabled-title-additions-help-social">
	<span class="better-seo-title-additions-warning-social">
		<?php
		HTML::make_info(
			\esc_html__( 'The site title has been removed from meta titles globally. This option only affects the homepage title.', 'better-seo' ),
		);
		?>
	</span>
</script>

<script type="text/html" id="tmpl-better-seo-robots-pt-help">
	<span class="better-seo-taxonomy-from-pt-robots-warning">
		<?php
		HTML::make_info(
			\esc_html__( 'This taxonomy has inherited its robots settings from the associated post type. Changing this option will have no additional effect.', 'better-seo' ),
		);
		?>
	</span>
</script>

<script type="text/html" id="tmpl-better-seo-disabled-title-additions-help">
	<span class="better-seo-title-additions-warning">
		<?php
		HTML::make_info(
			\esc_html__( 'Site title additions have been disabled globally. This option will have no effect until global title additions are re-enabled.', 'better-seo' ),
		);
		?>
	</span>
</script>