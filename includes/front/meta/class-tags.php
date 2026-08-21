<?php
/**
 * Better SEO - Front Meta Tags
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Front\Meta
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

namespace Better_SEO\Front\Meta;

\defined( 'BETTER_SEO_PRESENT' ) or die;

/**
 * Class Better_SEO\Front\Meta\Tags
 *
 * Manages the registration, population, and rendering of Better SEO meta tags.
 * Provides static references to the tag generator and render data stores,
 * and handles secure attribute escaping for all tag output.
 *
 * @since 1.0.0
 */
final class Tags {

	/**
	 * Default values for tag render data entries.
	 *
	 * @since 1.0.0
	 * @var   array<string, mixed>
	 */
	private const DATA_DEFAULTS = [
		'attributes' => [],
		'tag'        => 'meta',
		'content'    => null,
	];

	/**
	 * Registered tag generator callbacks.
	 *
	 * @since 1.0.0
	 * @var   array<string, callable>
	 */
	private static array $tag_generators = [];

	/**
	 * Collected tag render data from all registered generators.
	 *
	 * @since 1.0.0
	 * @var   array<string, array<string, mixed>>
	 */
	private static array $tags_render_data = [];

	/**
	 * Returns a reference to the tag generators array.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, callable> Reference to the tag generators array.
	 */
	public static function &tag_generators(): array {
		return self::$tag_generators;
	}

	/**
	 * Returns a reference to the tags render data array.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>> Reference to the tags render data array.
	 */
	public static function &tags_render_data(): array {
		return self::$tags_render_data;
	}

	/**
	 * Populates the tags render data from all registered generator callbacks.
	 *
	 * Iterates through registered generators, calls each callback, and merges
	 * the resulting tag data into the render data store.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function fill_render_data_from_registered_generators(): void {

		$tags_render_data = &self::$tags_render_data;
		$i                = 0;

		foreach ( self::$tag_generators as $callback ) {
			foreach ( \call_user_func( $callback ) as $id => $data ) {
				$tags_render_data[ $id ?: ++$i ] = $data;
			}
		}
	}

	/**
	 * Renders all registered meta tags from the tags render data store.
	 *
	 * Skips already-rendered tags and marks each tag as rendered after output.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function render_tags(): void {

		$data_defaults      = self::DATA_DEFAULTS;
		$default_attributes = $data_defaults['attributes'];
		$default_tag        = $data_defaults['tag'];
		$default_content    = $data_defaults['content'];

		foreach ( self::$tags_render_data as &$tagdata ) {
			if ( $tagdata['rendered'] ?? false ) {
				continue;
			}

			self::render(
				$tagdata['attributes'] ??= $default_attributes,
				$tagdata['tag']        ??= $default_tag,
				$tagdata['content']    ??= $default_content,
			);

			$tagdata['rendered'] = true;
		}
	}

	/**
	 * Renders a single meta tag with the given attributes, tag name, and content.
	 *
	 * Applies secure escaping to all attribute values. URL attributes (href, src,
	 * xlink:href) are sanitized via sanitize_url(). Event handler attributes (on*)
	 * are silently skipped. All other attributes are escaped via esc_attr().
	 *
	 * Self-closing tags are rendered as XHTML-compatible void elements.
	 * Tags with content are rendered as open/close element pairs.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string>        $attributes Tag attributes as name => value pairs.
	 * @param string                       $tag        The HTML tag name. Default 'meta'.
	 * @param string|array<string, mixed>|null $content The tag content, or null for self-closing.
	 * @return void
	 */
	public static function render(
		array $attributes = self::DATA_DEFAULTS['attributes'],
		string $tag = self::DATA_DEFAULTS['tag'],
		string|array|null $content = self::DATA_DEFAULTS['content'],
	): void {

		$attr = '';

		foreach ( $attributes as $name => $value ) {
			$name = trim( $name );

			switch ( strtolower( $name ) ) {
				case 'href':
				case 'xlink:href':
				case 'src':
					$_secure_attr_value = strtr(
						\sanitize_url( $value ),
						[
							'"' => '%22',
							"'" => '%27',
							'<' => '%3C',
							'>' => '%3E',
						],
					);
					break;

				default:
					// Skip event handler attributes per HTML spec.
					// @link https://html.spec.whatwg.org/
					if ( 0 === stripos( $name, 'on' ) ) {
						continue 2;
					}

					$_secure_attr_value = \esc_attr( $value );
			}

			$attr .= \sprintf(
				' %s="%s"',
				preg_replace( '/[^a-z\d:_-]+/i', '', $name ),
				$_secure_attr_value,
			);
		}

		// phpcs:disable WordPress.Security.EscapeOutput
		if ( isset( $content ) ) {
			vprintf(
				'<%1$s%2$s>%3$s</%1$s>',
				[
					/** @link https://html.spec.whatwg.org/multipage/ */
					preg_replace( '/[^a-z\d]+/i', '', $tag ), // phpcs:ignore WordPress.Security.EscapeOutput -- this escapes.
					$attr,
					\is_array( $content )
						? (
							( $content['escape'] ?? true )
								? \esc_html( $content['content'] )
								: $content['content']
						)
						: \esc_html( $content ),
				],
			);
		} else {
			printf(
				'<%s%s />', // XHTML compatible.
				/** @link https://www.w3.org/TR/2011/WD-html5-20110525/syntax.html#syntax-tag-name */
				preg_replace( '/[^0-9a-zA-Z]+/', '', $tag ),
				$attr,
			);
		}
		// phpcs:enable WordPress.Security.EscapeOutput

		echo "\n";
	}
}