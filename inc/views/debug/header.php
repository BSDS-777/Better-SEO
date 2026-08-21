<?php
/**
 * Better SEO - View: Debug Header
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\Debug
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

$t = hrtime( true );

ob_start();
Front\Meta\Head::print_wrap_and_tags();
$output  = ob_get_clean();
$gentime = number_format( ( hrtime( true ) - $t ) / 1e9, 5 );

$output = strtr(
	str_replace( str_repeat( ' ', 4 ), str_repeat( '&nbsp;', 4 ), \esc_html( $output ) ),
	array_fill_keys( [ "\r\n", "\r", "\n" ], "<br>\n" ),
);

// Highlight quoted attribute values in gold.
$output = preg_replace( '/(&quot;.*?&quot;)(&nbsp;|[\s:])/', '<span style="color:var(--gold)">$1$2</span> ', $output );

$title = \is_admin() ? 'Expected SEO Output' : 'Determined SEO Output';

?>
<style>
	:root {
		--navy:      #1a1a2e;
		--deep-blue: #16213e;
		--mid-blue:  #0f3460;
		--gold:      #c9a84c;
		--gold-lt:   #e8c97a;
		--cream:     #faf8f4;
		--white:     #ffffff;
		--grey-lt:   #f4f4f4;
		--grey-mid:  #888;
		--text-dark: #1a1a2e;
		--text-body: #3d3d3d;
	}
</style>
<div style="font-family:inherit;display:block;width:100%;background:var(--cream);color:var(--text-body);border-bottom:1px solid var(--grey-mid)">
	<div style="display:inline-block;width:100%;padding: 0;margin: 0;border-bottom:1px solid var(--mid-blue);">
		<h2 style="font-family:inherit;color:var(--navy);font-size:22px;padding: 0;margin: 0"><?php echo \esc_html( $title ); ?></h2>
	</div>
	<div style="font-family:inherit;display:inline-block;width:100%;padding: 0;border-bottom:1px solid var(--grey-mid)">Generated in <?php echo \esc_html( $gentime ); ?> seconds</div>
	<div style="display:inline-block;width:100%;padding: 0;font-size:14px;"><?php echo $output; // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped above. ?></div>
</div>