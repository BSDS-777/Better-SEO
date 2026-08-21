<?php
/**
 * Better SEO - Admin Settings Layout HTML
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\Settings\Layout
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

namespace Better_SEO\Admin\Settings\Layout;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Data\Filter\Escape;

/**
 * Class Better_SEO\Admin\Settings\Layout\HTML
 *
 * Provides HTML output utilities for the Better SEO admin settings UI,
 * including headings, descriptions, tooltips, field wrappers, and data attributes.
 *
 * @since 1.0.0
 */
class HTML {

	/**
	 * Returns an H4 heading element with the given title.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title The heading text (must be pre-escaped).
	 * @return string The generated H4 HTML.
	 */
	public static function get_header_title( string $title ): string {
		return "<h4>{$title}</h4>";
	}

	/**
	 * Outputs an H4 heading element with the given title, escaped.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title The heading text to escape and output.
	 * @return void
	 */
	public static function header_title( string $title ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html applied before passing to get_header_title.
		echo self::get_header_title( \esc_html( $title ) );
	}

	/**
	 * Returns a code element wrapping the given content, escaped.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The content to escape and wrap.
	 * @return string The generated code element HTML.
	 */
	public static function code_wrap( string $content ): string {
		return self::code_wrap_noesc( \esc_html( $content ) );
	}

	/**
	 * Returns a code element wrapping the given content without escaping.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The pre-escaped content to wrap.
	 * @return string The generated code element HTML.
	 */
	public static function code_wrap_noesc( string $content ): string {
		return "<code>{$content}</code>";
	}

	/**
	 * Outputs a description span, escaped, optionally wrapped in a paragraph.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The description text to escape and output.
	 * @param bool   $block   Whether to wrap in a paragraph tag. Default true.
	 * @return void
	 */
	public static function description( string $content, bool $block = true ): void {
		self::description_noesc( \esc_html( $content ), $block );
	}

	/**
	 * Outputs a description span without escaping, optionally wrapped in a paragraph.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The pre-escaped content to output.
	 * @param bool   $block   Whether to wrap in a paragraph tag. Default true.
	 * @return void
	 */
	public static function description_noesc( string $content, bool $block = true ): void {
		printf(
			( $block ? '<p>%s</p>' : '%s' ),
			// phpcs:ignore WordPress.Security.EscapeOutput -- Method name clearly states content is not escaped. Caller is responsible.
			"<span class=description>{$content}</span>",
		);
	}

	/**
	 * Outputs an attention span, escaped, optionally wrapped in a paragraph.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The attention text to escape and output.
	 * @param bool   $block   Whether to wrap in a paragraph tag. Default true.
	 * @return void
	 */
	public static function attention( string $content, bool $block = true ): void {
		self::attention_noesc( \esc_html( $content ), $block );
	}

	/**
	 * Outputs an attention span without escaping, optionally wrapped in a paragraph.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The pre-escaped content to output.
	 * @param bool   $block   Whether to wrap in a paragraph tag. Default true.
	 * @return void
	 */
	public static function attention_noesc( string $content, bool $block = true ): void {
		printf(
			( $block ? '<p>%s</p>' : '%s' ),
			"<span class=attention>{$content}</span>",
		);
	}

	/**
	 * Outputs a combined description and attention span, escaped, optionally wrapped in a paragraph.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The content to escape and output.
	 * @param bool   $block   Whether to wrap in a paragraph tag. Default true.
	 * @return void
	 */
	public static function attention_description( string $content, bool $block = true ): void {
		self::attention_description_noesc( \esc_html( $content ), $block );
	}

	/**
	 * Outputs a combined description and attention span without escaping, optionally wrapped in a paragraph.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The pre-escaped content to output.
	 * @param bool   $block   Whether to wrap in a paragraph tag. Default true.
	 * @return void
	 */
	public static function attention_description_noesc( string $content, bool $block = true ): void {
		printf(
			( $block ? '<p>%s</p>' : '%s' ),
			"<span class=\"description attention\">{$content}</span>",
		);
	}

	/**
	 * Wraps input fields in a Better SEO fields container div.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array<int, string> $input The field HTML string or array of strings.
	 * @param bool                      $echo  Whether to echo or return the output. Default false.
	 * @return string|void The wrapped HTML if $echo is false, void if true.
	 */
	public static function wrap_fields( string|array $input = '', bool $echo = false ): string|null {

		if ( \is_array( $input ) ) {
			$input = implode( "\n", $input );
		}

		$output = "<div class=better-seo-fields>{$input}</div>";

		if ( $echo ) {
			// phpcs:ignore WordPress.Security.EscapeOutput -- Caller is responsible for escaping $input prior to passing.
			echo $output;
			return null;
		}

		return $output;
	}

	/**
	 * Generates or outputs a tooltip info element with optional link.
	 *
	 * @since 1.0.0
	 *
	 * @param string $description The tooltip description text.
	 * @param string $link        Optional. URL to link the tooltip to. Default empty.
	 * @param bool   $echo        Whether to echo or return the output. Default true.
	 * @return string|void The tooltip HTML if $echo is false, void if true.
	 */
	public static function make_info( string $description = '', string $link = '', bool $echo = true ): string|null {

		if ( $link ) {
			$output = \sprintf(
				'<a href="%1$s" class="better-seo-tooltip-item better-seo-help" target="_blank" rel="nofollow noreferrer noopener" title="%2$s" data-desc="%2$s">[?]</a>',
				\esc_url( $link, [ 'https', 'http' ] ),
				\esc_attr( $description ),
			);
		} else {
			$output = \sprintf(
				'<span class="better-seo-tooltip-item better-seo-help" title="%1$s" data-desc="%1$s" tabindex="0">[?]</span>',
				\esc_attr( $description ),
			);
		}

		$output = \sprintf( '<span class=better-seo-tooltip-wrap>%s</span>', $output );

		if ( $echo ) {
			// phpcs:ignore WordPress.Security.EscapeOutput -- Output is fully escaped above via esc_url and esc_attr.
			echo $output;
			return null;
		}

		return $output;
	}

	/**
	 * Generates HTML data attributes from an associative array.
	 *
	 * Converts camelCase keys to dash-case (e.g. 'inputType' → 'data-input-type').
	 * Scalar values are escaped with esc_attr(); arrays/objects are JSON-encoded.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Key-value pairs to convert to data attributes.
	 * @return string The generated data attribute string, or empty string if no data.
	 */
	public static function make_data_attributes( array $data ): string {

		$ret = [];

		foreach ( $data as $k => $v ) {
			$ret[] = \sprintf(
				'data-%s="%s"',
				strtolower( preg_replace(
					'/([A-Z])/',
					'-$1',
					preg_replace( '/[^a-z\d_-]/i', '', $k ),
				) ),
				\is_scalar( $v )
					? \esc_attr( $v )
					: Escape::json_encode_attribute( $v ),
			);
		}

		return $ret ? ' ' . implode( ' ', $ret ) : '';
	}
}
