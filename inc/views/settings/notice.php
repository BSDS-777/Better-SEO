<?php
/**
 * Better SEO - View: Settings Notice
 *
 * Renders the appropriate admin notice after Better SEO settings are saved, reset, or unchanged.
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

$notice = Data\Plugin::get_site_cache( 'settings_notice' );

if ( ! $notice ) {
	return;
}

[ $message, $type ] = match ( $notice ) {
	'updated'   => [
		\__( 'Your Better SEO settings have been saved and all caches have been cleared.', 'better-seo' ),
		'updated',
	],
	'unchanged' => [
		\__( 'No settings were changed. All caches have been cleared.', 'better-seo' ),
		'info',
	],
	'reset'     => [
		\__( 'Your Better SEO settings have been reset to their defaults and all caches have been cleared.', 'better-seo' ),
		'warning',
	],
	'error'     => [
		\__( 'An unexpected error occurred while saving your Better SEO settings. Please try again.', 'better-seo' ),
		'error',
	],
	default     => [ '', '' ],
};

Data\Plugin::update_site_cache( 'settings_notice', '' );

if ( $message ) {
	Admin\Notice::output_notice( $message, [ 'type' => $type ] );
}