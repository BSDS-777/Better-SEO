<?php
/**
 * Better SEO - View: XML Sitemap
 *
 * Generates and outputs the Better SEO XML sitemap for the requested sitemap endpoint.
 * Includes optional debug memory and timing comments when BETTER_SEO_DEBUG is enabled.
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

[ $sitemap_id ] = $view_args;

if ( \BETTER_SEO_DEBUG ) {
	$timer_start = hrtime( true );
}

Sitemap\Registry::output_sitemap_header();

if ( \BETTER_SEO_DEBUG ) {
	echo '<!-- Site estimated peak memory usage prior to generation: ', number_format( memory_get_peak_usage() / MB_IN_BYTES, 3 ), ' MB -->' . "\n";
	echo '<!-- System estimated peak memory usage prior to generation: ', number_format( memory_get_peak_usage( true ) / MB_IN_BYTES, 3 ), ' MB -->' . "\n";
}

Sitemap\Registry::output_sitemap_urlset_open_tag();

$sitemap_base = new Sitemap\Optimized\Base();
echo $sitemap_base->generate_sitemap( $sitemap_id );

Sitemap\Registry::output_sitemap_urlset_close_tag();

if ( $sitemap_base->base_is_regenerated ) {
	echo "\n<!-- ", \esc_html__( 'This sitemap was freshly generated for this request.', 'better-seo' ), ' -->';
} else {
	echo "\n<!-- ", \esc_html__( 'This sitemap was served from the transient cache.', 'better-seo' ), ' -->';
}

$sitemap_base = null;

if ( \BETTER_SEO_DEBUG ) {
	echo "\n<!-- Site estimated current memory usage: ", number_format( memory_get_usage() / MB_IN_BYTES, 3 ), ' MB -->';
	echo "\n<!-- System estimated current memory usage: ", number_format( memory_get_usage( true ) / MB_IN_BYTES, 3 ), ' MB -->';
	echo "\n<!-- Site estimated peak memory usage: ", number_format( memory_get_peak_usage() / MB_IN_BYTES, 3 ), ' MB -->';
	echo "\n<!-- System estimated peak memory usage: ", number_format( memory_get_peak_usage( true ) / MB_IN_BYTES, 3 ), ' MB -->';
	echo "\n<!-- Memory freed prior to generation: ", number_format( Sitemap\Registry::get_freed_memory( true ) / KB_IN_BYTES, 3 ), ' kB -->';
	echo "\n<!-- Sitemap generation time: ", number_format( ( hrtime( true ) - $timer_start ) / 1e9, 6 ), ' seconds -->';
	echo "\n<!-- Sitemap caching enabled: ", ( Data\Plugin::get_option( 'cache_sitemap' ) ? 'yes' : 'no' ), ' -->';
	echo "\n<!-- Sitemap transient key: ", \esc_html( Sitemap\Cache::get_sitemap_cache_key( $sitemap_id ) ), ' -->';
}