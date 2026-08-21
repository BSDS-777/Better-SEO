<?php
/**
 * Better SEO - View: XSL Stylesheet
 *
 * Outputs the XSL stylesheet used to style the Better SEO XML sitemap
 * when viewed directly in a browser.
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\Sitemap
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

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";

?>
<xsl:stylesheet version="2.0"
				xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
				xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
	<xsl:template match="/">
		<html xmlns="http://www.w3.org/1999/xhtml" <?php \language_attributes( 'html' ); ?>>
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
				<meta name="viewport" content="width=device-width, initial-scale=1" />
				<?php
				/**
				 * Fires to output content in the Better SEO XSL stylesheet <head>.
				 *
				 * @since 1.0.0
				 * @param \Better_SEO\Load $instance The Better SEO plugin instance.
				 */
				\do_action( 'better_seo_xsl_head', better_seo() );
				?>
			</head>
			<body class="<?php echo \is_rtl() ? 'rtl' : 'ltr'; ?>">
				<div id="description">
					<div class="wrap">
						<?php
						/**
						 * Fires to output the Better SEO XSL sitemap description section.
						 *
						 * @since 1.0.0
						 * @param \Better_SEO\Load $instance The Better SEO plugin instance.
						 */
						\do_action( 'better_seo_xsl_description', better_seo() );
						?>
					</div>
				</div>
				<div id="content">
					<div class="wrap">
						<?php
						/**
						 * Fires to output the Better SEO XSL sitemap content/table section.
						 *
						 * @since 1.0.0
						 * @param \Better_SEO\Load $instance The Better SEO plugin instance.
						 */
						\do_action( 'better_seo_xsl_content', better_seo() );
						?>
					</div>
				</div>
				<div id="footer">
					<div class="wrap">
						<?php
						/**
						 * Fires to output the Better SEO XSL sitemap footer section.
						 *
						 * @since 1.0.0
						 * @param \Better_SEO\Load $instance The Better SEO plugin instance.
						 */
						\do_action( 'better_seo_xsl_footer', better_seo() );
						?>
					</div>
				</div>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>