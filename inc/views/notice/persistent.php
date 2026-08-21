<?php
/**
 * Better SEO - View: Persistent Notice
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\Notice
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 * @link       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Better SEO plugin
 * Copyright (C) 2026 Brian Smith
 * Licensed under the GNU General Public License v2.0.
 *
 * This program is free software: you may redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is provided without any warranty; without even the
 * implied warranty of merchantability or fitness for a particular purpose.
 * See the GNU General Public License for more details.
 */

declare( strict_types=1 );

namespace Better_SEO;

if ( ! \defined( 'BETTER_SEO_PRESENT' ) || ! Helper\Template::verify_secret( $secret ) ) {
	die;
}

use Better_SEO\Admin\Settings\Layout\HTML;

[ $message, $key, $args ] = $view_args;

if ( ! $message ) {
	return;
}

$sanitized_key = \sanitize_key( $key );

Admin\Script\Registry::register_scripts_and_hooks();

// Normalize warning/info types to WordPress notice class format.
$args['type'] = match ( $args['type'] ) {
	'warning', 'info' => "notice-{$args['type']}",
	default           => $args['type'],
};

$dismiss_title_i18n = \__( 'Dismiss this notice', 'better-seo' );

$nonce_action = Admin\Notice\Persistent::_get_dismiss_nonce_action( $sanitized_key );

$button_js = \sprintf(
	'<a class="hide-if-no-better-seo-js better-seo-dismiss" href="javascript:;" title="%s" %s></a>',
	\esc_attr( $dismiss_title_i18n ),
	HTML::make_data_attributes( [
		'key'   => $sanitized_key,
		'nonce' => \wp_create_nonce( $nonce_action ),
	] ),
);

$button_nojs = vsprintf(
	'<form action="%s" method="post" id="better-seo-dismiss-notice[%s]" class="hide-if-better-seo-js">%s</form>',
	[
		\esc_attr( \add_query_arg( [ 'better-seo-dismissed-notice' => $sanitized_key ] ) ),
		$sanitized_key,
		implode(
			'',
			[
				\wp_nonce_field( $nonce_action, 'better_seo_notice_nonce', true, false ),
				vsprintf(
					'<button class="better-seo-dismiss" type="submit" name="better-seo-notice-submit" id="better-seo-notice-submit[%s]" value="%s" title="%s">%s</button>',
					[
						$sanitized_key,
						$sanitized_key,
						\esc_attr( $dismiss_title_i18n ),
						\sprintf( '<span class="screen-reader-text">%s</span>', \esc_html( $dismiss_title_i18n ) ),
					],
				),
			],
		),
	],
);

vprintf(
	'<div class="notice %s better-seo-notice %s">%s%s</div>',
	[
		\esc_attr( $args['type'] ),
		( $args['icon'] ? 'better-seo-show-icon' : '' ),
		\sprintf(
			! $args['escape'] && 0 === stripos( $message, '<p' )
				? '%s'
				: '<p>%s</p>',
			$args['escape']
				? \esc_html( $message )
				: $message,
		),
		$button_js . $button_nojs,
	],
);

