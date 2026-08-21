<?php
/**
 * Better SEO - Admin Settings Layout Input
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

use Better_SEO\{
	Data,
	Data\Filter\Escape,
};

/**
 * Class Better_SEO\Admin\Settings\Layout\Input
 *
 * Provides input field generation utilities for the Better SEO admin settings UI,
 * including field IDs, checkboxes, and JavaScript data output helpers.
 *
 * @since 1.0.0
 */
class Input {

	/**
	 * Returns the full field ID string for a given option key.
	 *
	 * Builds the field ID by appending each sub-key to the site options constant.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array<int, string> $id The option key or array of sub-keys.
	 * @return string The generated field ID string.
	 */
	public static function get_field_id( string|array $id ): string {

		$field_id = \BETTER_SEO_SITE_OPTIONS;

		foreach ( (array) $id as $subid ) {
			$field_id .= "[{$subid}]";
		}

		return $field_id;
	}

	/**
	 * Outputs the escaped field ID for a given option key.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array<int, string> $id The option key or array of sub-keys.
	 * @return void
	 */
	public static function field_id( string|array $id ): void {
		echo \esc_attr( self::get_field_id( $id ) );
	}

	/**
	 * Returns the full field name string for a given option key.
	 *
	 * Alias of get_field_id() — field names and IDs share the same structure.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array<int, string> $name The option key or array of sub-keys.
	 * @return string The generated field name string.
	 */
	public static function get_field_name( string|array $name ): string {
		return self::get_field_id( $name );
	}

	/**
	 * Outputs the escaped field name for a given option key.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array<int, string> $name The option key or array of sub-keys.
	 * @return void
	 */
	public static function field_name( string|array $name ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- field_id() applies esc_attr internally.
		echo self::field_id( $name );
	}

	/**
	 * Generates a checkbox input field with optional label and description.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args {
	 *     Checkbox arguments.
	 *     @type string|array $id          The option key or sub-keys.
	 *     @type string       $class       Additional CSS class for the checkbox.
	 *     @type string       $label       The checkbox label text.
	 *     @type mixed        $value       The current value. Falls back to plugin option if null.
	 *     @type string       $description Optional description text below the checkbox.
	 *     @type array        $data        Data attributes for the input element.
	 *     @type bool         $escape      Whether to escape label and description. Default true.
	 *     @type bool         $disabled    Whether the checkbox is disabled. Default false.
	 * }
	 * @return string The generated checkbox HTML.
	 */
	public static function make_checkbox( array $args = [] ): string {

		$args += [
			'id'          => '',
			'class'       => '',
			'label'       => '',
			'value'       => null,
			'description' => '',
			'data'        => [],
			'escape'      => true,
			'disabled'    => false,
		];

		if ( $args['escape'] ) {
			$args['description'] = \esc_html( $args['description'] );
			$args['label']       = \esc_html( $args['label'] );
		}

		$field_id   = self::get_field_id( $args['id'] );
		$field_name = $field_id;
		$value      = $args['value'] ?? Data\Plugin::get_option( ...(array) $args['id'] );

		$cb_classes = [];

		if ( $args['class'] ) {
			$cb_classes[] = $args['class'];
		}

		if ( $args['disabled'] ) {
			$cb_classes[] = 'better-seo-disabled';
		} else {
			array_push( $cb_classes, ...self::get_conditional_checked_classes( ...(array) $args['id'] ) );
		}

		return \sprintf(
			'<span class=better-seo-toblock>%s</span>%s',
			vsprintf(
				'<label for="%s"%s>%s</label>',
				[
					Escape::option_name_attribute( $field_id ),
					( $args['disabled'] ? ' class=better-seo-disabled' : '' ),
					vsprintf(
						'<input type=checkbox class="%s" name="%s" id="%s" value=1 %s%s %s /> %s',
						[
							\esc_attr( implode( ' ', array_filter( $cb_classes ) ) ),
							Escape::option_name_attribute( $field_name ),
							Escape::option_name_attribute( $field_id ),
							\checked( $value, true, false ),
							( $args['disabled'] ? ' disabled' : '' ),
							HTML::make_data_attributes( $args['data'] ),
							$args['label'],
						],
					),
				],
			),
			$args['description']
				? \sprintf( '<p class="description better-seo-option-spacer">%s</p>', $args['description'] )
				: '',
		);
	}

	/**
	 * Returns conditional CSS classes based on default and warned option states.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed ...$key The option key(s) to check.
	 * @return array<int, string> Array of conditional CSS class strings.
	 */
	public static function get_conditional_checked_classes( mixed ...$key ): array {
		return [
			Data\Plugin\Setup::get_default_option( ...$key ) ? 'better-seo-default-selected' : '',
			Data\Plugin\Setup::get_warned_option( ...$key )  ? 'better-seo-warning-selected' : '',
		];
	}

	/**
	 * Outputs JavaScript title reference and data span elements for a given input field.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $id   The input field ID.
	 * @param array<string, mixed> $data The title data attributes.
	 * @return void
	 */
	public static function output_js_title_data( string $id, array $data ): void {
		vprintf(
			implode(
				'',
				[
					'<span id="better-seo-title-reference_%1$s" class="better-seo-title-reference hidden wp-exclude-emoji" data-for="%1$s"></span>',
					'<span id="better-seo-title-noadditions-reference_%1$s" class="better-seo-title-noadditions-reference hidden wp-exclude-emoji" data-for="%1$s"></span>',
					'<span class=better-seo-title-offset-wrap><span id="better-seo-title-offset_%1$s" class="better-seo-title-offset wp-exclude-emoji hide-if-no-better-seo-js" data-for="%1$s"></span></span>',
					'<span id="better-seo-title-placeholder-additions_%1$s" class="better-seo-title-placeholder-additions wp-exclude-emoji hide-if-no-better-seo-js" data-for="%1$s"></span>',
					'<span id="better-seo-title-placeholder-prefix_%1$s" class="better-seo-title-placeholder-prefix wp-exclude-emoji hide-if-no-better-seo-js" data-for="%1$s"></span>',
					'<span id="better-seo-title-data_%1$s" class="hidden wp-exclude-emoji" data-for="%1$s" %2$s></span>',
				],
			),
			[
				\esc_attr( $id ),
				HTML::make_data_attributes( $data ),
			],
		);
	}

	/**
	 * Outputs a JavaScript social data span element for a given group.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $group    The social data group identifier.
	 * @param array<string, mixed> $settings The social settings data.
	 * @return void
	 */
	public static function output_js_social_data( string $group, array $settings ): void {
		vprintf(
			'<span id="better-seo-social-data_%1$s" class="hidden wp-exclude-emoji" data-group="%1$s" %2$s></span>',
			[
				\esc_attr( $group ),
				// phpcs:ignore WordPress.Security.EscapeOutput -- make_data_attributes escapes.
				HTML::make_data_attributes( [ 'settings' => $settings ] ),
			],
		);
	}

	/**
	 * Outputs JavaScript description reference and data span elements for a given input field.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $id   The input field ID.
	 * @param array<string, mixed> $data The description data attributes.
	 * @return void
	 */
	public static function output_js_description_data( string $id, array $data ): void {
		vprintf(
			implode(
				'',
				[
					'<span id="better-seo-description-reference_%1$s" class="hidden wp-exclude-emoji" data-for="%1$s"></span>',
					'<span id="better-seo-description-data_%1$s" class="hidden wp-exclude-emoji" data-for="%1$s" %2$s></span>',
				],
			),
			[
				\esc_attr( $id ),
				// phpcs:ignore WordPress.Security.EscapeOutput -- make_data_attributes escapes.
				HTML::make_data_attributes( $data ),
			],
		);
	}

	/**
	 * Outputs a JavaScript canonical data span element for a given input field.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $id   The input field ID.
	 * @param array<string, mixed> $data The canonical data attributes.
	 * @return void
	 */
	public static function output_js_canonical_data( string $id, array $data ): void {
		vprintf(
			'<span id="better-seo-canonical-data_%1$s" class="hidden wp-exclude-emoji" data-for="%1$s" %2$s></span>',
			[
				\esc_attr( $id ),
				// phpcs:ignore WordPress.Security.EscapeOutput -- make_data_attributes escapes.
				HTML::make_data_attributes( $data ),
			],
		);
	}
}
