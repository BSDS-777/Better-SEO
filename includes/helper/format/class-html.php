<?php
/**
 * Better SEO - Helper Format HTML
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Helper\Format
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

namespace Better_SEO\Helper\Format;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\umemo;

use Better_SEO\{
	Data,
	Data\Filter\Sanitize,
};

/**
 * Class Better_SEO\Helper\Format\HTML
 *
 * Provides HTML content processing utilities for Better SEO, including
 * URL stripping, tag-aware content stripping, and description extraction.
 *
 * @since 1.0.0
 */
class HTML {

	/**
	 * Strips bare URLs that appear on their own line from the given content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The HTML content to process.
	 * @return string The content with bare newline URLs removed.
	 */
	public static function strip_newline_urls( string $content ): string {
		return preg_replace( '/^(?!\r|\n)\s*?(https?:\/\/[^\s<>"]+)(\s*)$/mi', '', $content );
	}

	/**
	 * Strips paragraph elements that contain only a bare URL from the given content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The HTML content to process.
	 * @return string The content with bare URL paragraphs removed.
	 */
	public static function strip_paragraph_urls( string $content ): string {
		return preg_replace( '/<p\b[^>]*>\s*https?:\/\/[^\s<>"]+\s*<\/p\s*>/i', '', $content );
	}

	/**
	 * Strips HTML tags from content with context-sensitive handling.
	 *
	 * Supports configurable space (replace with space), clear (remove entirely),
	 * and strip (apply strip_tags) behaviors per element type.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $input The HTML content to process.
	 * @param array<string, mixed> $args  Optional processing arguments (space, clear, strip, passes).
	 * @return string The processed content.
	 */
	public static function strip_tags_cs( string $input, array $args = [] ): string {

		if ( ! str_contains( $input, '<' ) ) {
			return $input;
		}

		$default_args = [
			'space'  =>
				[ 'address', 'article', 'aside', 'br', 'blockquote', 'details', 'dd', 'div', 'dl', 'dt', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'li', 'ol', 'p', 'section', 'ul' ],
			'clear'  =>
				[ 'area', 'audio', 'button', 'canvas', 'code', 'datalist', 'del', 'dialog', 'fieldset', 'form', 'iframe', 'input', 'label', 'map', 'menu', 'meter', 'nav', 'noscript', 'object', 'output', 'pre', 'progress', 's', 'script', 'select', 'style', 'svg', 'table', 'template', 'textarea', 'video' ],
			'strip'  => true,
			'passes' => 1,
		];

		if ( ! $args ) {
			$args = $default_args;
		} else {
			foreach ( [ 'clear', 'space' ] as $type ) {
				$args[ $type ] = (array) ( $args[ $type ] ?? [] );
			}

			$args['strip']  ??= $default_args['strip'];
			$args['passes'] ??= $default_args['passes'];
		}

		$parse = umemo( __METHOD__ . '/parse', null, $args['space'], $args['clear'] );

		if ( ! $parse ) {
			$void = [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'wbr' ];

			$phrase = [ 'a', 'area', 'abbr', 'audio', 'b', 'bdo', 'bdi', 'body', 'button', 'canvas', 'cite', 'code', 'data', 'datalist', 'del', 'dfn', 'em', 'embed', 'i', 'iframe', 'img', 'input', 'ins', 'link', 'kbd', 'label', 'map', 'mark', 'meta', 'math', 'meter', 'noscript', 'object', 'output', 'picture', 'progress', 'q', 'ruby', 's', 'samp', 'script', 'select', 'small', 'span', 'strong', 'style', 'sub', 'sup', 'svg', 'textarea', 'time', 'u', 'var', 'video', 'wbr' ];

			$marked_for_parsing = array_merge( $args['space'], $args['clear'] );

			$void_elements = array_intersect( $marked_for_parsing, $void );
			$flow_elements = array_diff( $marked_for_parsing, $void );

			$clear_elements = array_intersect( $flow_elements, $args['clear'] );

			$parse = umemo(
				__METHOD__ . '/parse',
				[
					'void_query'  => [
						'phrase' => array_intersect( $void_elements, $phrase ),
						'block'  => array_diff( $void_elements, $phrase ),
					],
					'clear_query' => [
						'phrase' => array_intersect( $clear_elements, $phrase ),
						'block'  => array_diff( $clear_elements, $phrase ),
					],
					'space_query' => [
						'phrase' => array_intersect( $flow_elements, $args['space'] ),
					],
				],
				$args['space'],
				$args['clear'],
			);
		}

		$nojit = \strlen( $input ) > 1e6 ? '(*NO_JIT)' : '';

		foreach ( $parse as $query_type => $handles ) {
			foreach ( $handles as $flow_type => $elements ) {
				if ( ! $elements ) {
					continue;
				}

				if ( ! str_contains( $input, '<' ) ) {
					break 2;
				}

				switch ( $query_type ) {
					case 'void_query':
						$input = preg_replace(
							\sprintf(
								'/<(?!\/)(?:%s)\b(?:[^=>"\/]*=(?:(?:([\'"])[^$]*?\g{-1})|[\s\/]*))*+[^>]*>/i',
								implode( '|', $elements ),
							),
							'phrase' === $flow_type ? '' : ' ',
							$input
						) ?? '';
						break;

					case 'space_query':
						$passes      = $args['passes'];
						$replacement = ' $4 ';
						// Fall through.
					case 'clear_query':
						$passes      ??= 1;
						$replacement ??= 'phrase' === $flow_type ? '' : ' ';

						$regex = \sprintf(
							"/{$nojit}<(?!\/)(%s)(?!\s*<)\b([^=>\"\/]*=(?:(?:(['\"])[^$]*?\g{-1})|[\s\/]*))*+(?:(?2)++|[^>]*>)((?:[^<]*+(?:<*+(?!\/?\\1\b.*?>)[^<]+)*|(?R))*?)<\/\\1\s*>/i",
							implode( '|', $elements ),
						);

						$i = 0;

						while ( $i++ < $passes ) {
							$pre_pass_input = $input;
							$input          = preg_replace( $regex, $replacement, $input ) ?? '';

							if ( $pre_pass_input === $input || ! str_contains( $input, '<' ) ) {
								break;
							}
						}

						unset( $passes, $replacement );
				}
			}
		}

		return $args['strip'] ? \strip_tags( $input ) : $input;
	}

	/**
	 * Extracts clean text content from HTML for use as a meta description.
	 *
	 * Applies shortcode stripping, tag-aware HTML stripping, optional clamping,
	 * and metadata sanitization. Applies the better_seo_extract_content_strip_args
	 * and better_seo_allow_excerpt_shortcode_tags filters.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $html The HTML content to extract from.
	 * @param array<string, mixed> $args Optional extraction arguments (allow_shortcodes, sanitize, clamp).
	 * @return string The extracted and sanitized text content.
	 */
	public static function extract_content( string $html, array $args = [] ): string {

		if ( empty( $html ) ) {
			return '';
		}

		$args += [
			'allow_shortcodes' => true,
			'sanitize'         => true,
			'clamp'            => false,
		];

		$passes = match ( Data\Plugin::get_option( 'auto_description_html_method' ) ) {
			'thorough' => 12,
			'accurate' => 6,
			default    => 2,
		};

		/**
		 * Filters the HTML strip arguments for Better SEO content extraction.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $strip_args The strip arguments array.
		 */
		$strip_args = (array) \apply_filters(
			'better_seo_extract_content_strip_args',
			[
				'space'  =>
					[ 'article', 'br', 'blockquote', 'details', 'div', 'hr', 'p', 'section' ],
				'clear'  =>
					[ 'address', 'area', 'aside', 'audio', 'blockquote', 'button', 'canvas', 'code', 'datalist', 'del', 'dialog', 'dl', 'fieldset', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'iframe', 'input', 'label', 'map', 'menu', 'meter', 'nav', 'noscript', 'ol', 'object', 'output', 'pre', 'progress', 's', 'script', 'select', 'style', 'svg', 'table', 'template', 'textarea', 'ul', 'video' ],
				'passes' => $passes,
			]
		);

		/**
		 * Filters whether shortcode tags are allowed in Better SEO excerpt extraction.
		 *
		 * @since 1.0.0
		 *
		 * @param bool                 $allow Whether to allow shortcode tags. Default false.
		 * @param array<string, mixed> $args  The extraction arguments.
		 */
		if ( ! $args['allow_shortcodes'] || ! \apply_filters( 'better_seo_allow_excerpt_shortcode_tags', false, $args ) ) {
			$html = \strip_shortcodes( $html );
		}

		$html = self::strip_tags_cs( $html, $strip_args );

		if ( \is_int( $args['clamp'] ) ) {
			$html = Strings::clamp_sentence( $html, 1, $args['clamp'] );
		}

		return $args['sanitize'] ? Sanitize::metadata_content( $html ) : $html;
	}
}