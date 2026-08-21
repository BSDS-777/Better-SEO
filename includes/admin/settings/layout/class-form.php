<?php
/**
 * Better SEO - Admin Settings Layout Form
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
	Data\Filter\Escape,
	Helper\Format\Arrays,
	Helper\Query,
};

/**
 * Class Better_SEO\Admin\Settings\Layout\Form
 *
 * Provides form element generation utilities for the Better SEO admin settings UI,
 * including select dropdowns, character/pixel counters, and image uploader forms.
 *
 * @since 1.0.0
 */
class Form {

	/**
	 * Generates a single select form element with optional label and info tooltip.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args {
	 *     Form arguments.
	 *     @type string $id          The field ID.
	 *     @type string $class       Wrapper CSS class.
	 *     @type string $name        The field name attribute.
	 *     @type mixed  $selected    The currently selected value.
	 *     @type array  $options     Key-value pairs of option values and labels.
	 *     @type string $label       Optional label text.
	 *     @type bool   $labelstrong Whether to wrap the label in <strong>.
	 *     @type bool   $required    Whether the field is required.
	 *     @type array  $data        Data attributes for the select element.
	 *     @type array  $info        Info tooltip [description, link].
	 * }
	 * @return string The generated select form HTML.
	 */
	public static function make_single_select_form( array $args ): string {

		$args += [
			'id'          => '',
			'class'       => '',
			'name'        => '',
			'selected'    => $args['default'] ?? '',
			'options'     => [],
			'label'       => '',
			'labelstrong' => false,
			'required'    => false,
			'data'        => [],
			'info'        => [],
		];

		$html_options = $args['options'];

		array_walk(
			$html_options,
			static function ( string &$name, mixed $value, mixed $selected ): void {
				$name = \sprintf(
					'<option value="%s"%s>%s</option>',
					\esc_attr( $value ),
					(string) $value === (string) $selected ? ' selected' : '',
					\esc_html( $name ),
				);
			},
			$args['selected'],
		);

		return vsprintf(
			\sprintf(
				'<div class="%s">%s</div>',
				\esc_attr( $args['class'] ),
				\is_rtl() ? '%2$s%1$s%3$s' : '%1$s%2$s%3$s',
			),
			[
				$args['label'] ? \sprintf(
					'<label for="%s">%s</label> ',
					Escape::option_name_attribute( $args['id'] ),
					\sprintf(
						$args['labelstrong'] ? '<strong>%s</strong>' : '%s',
						\esc_html( $args['label'] ),
					)
				) : '',
				$args['info'] ? HTML::make_info(
					$args['info'][0],
					$args['info'][1] ?? '',
					false
				) . ' ' : '',
				vsprintf(
					'<select id="%s" name="%s"%s %s>%s</select>',
					[
						Escape::option_name_attribute( $args['id'] ),
						\esc_attr( $args['name'] ),
						$args['required'] ? ' required' : '',
						HTML::make_data_attributes( $args['data'] ),
						implode( '', $html_options ),
					],
				),
			],
		);
	}

	/**
	 * Outputs the character counter wrap for a given input field.
	 *
	 * @since 1.0.0
	 *
	 * @param string $input_id The input field ID to associate the counter with.
	 * @param bool   $display  Whether to display the counter. Default true.
	 * @return void
	 */
	public static function output_character_counter_wrap( string $input_id, bool $display = true ): void {
		vprintf(
			'<div class="better-seo-counter-wrap hide-if-no-better-seo-js" %s><span class=better-seo-counter title="%s">%s</span><span class=better-seo-ajax></span></div>',
			[
				( $display ? '' : 'style=display:none;' ),
				\esc_attr__( 'Click to change the counter type', 'better-seo' ),
				\sprintf(
					/* translators: %s = number */
					\esc_html__( 'Characters: %s', 'better-seo' ),
					\sprintf(
						'<span id="%s">0</span>',
						\esc_attr( "{$input_id}_chars" ),
					),
				),
			],
		);
	}

	/**
	 * Outputs the pixel counter wrap for a given input field.
	 *
	 * @since 1.0.0
	 *
	 * @param string $input_id The input field ID to associate the counter with.
	 * @param string $type     The counter type (e.g. 'title', 'description').
	 * @param bool   $display  Whether to display the counter. Default true.
	 * @return void
	 */
	public static function output_pixel_counter_wrap( string $input_id, string $type, bool $display = true ): void {
		vprintf(
			'<div class="better-seo-pixel-counter-wrap hide-if-no-better-seo-js" %s>%s%s</div>',
			[
				( $display ? '' : 'style="display:none;"' ),
				\sprintf(
					'<div id="%s" class=better-seo-tooltip-wrap>%s</div>',
					\esc_attr( "{$input_id}_pixels" ),
					'<span class="better-seo-pixel-counter-bar better-seo-tooltip-item" aria-label data-desc tabindex="0"><span class=better-seo-pixel-counter-fluid></span></span>',
				),
				\sprintf(
					'<div class=better-seo-pixel-shadow-wrap><span class="%s"></span></div>',
					\esc_attr( "better-seo-{$type}-pixel-counter-shadow" ),
				),
			],
		);
	}

	/**
	 * Generates an image uploader form button and preview elements.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args {
	 *     Image uploader arguments.
	 *     @type string $id           The field ID. Required.
	 *     @type int    $post_id      The post ID to bind the upload to.
	 *     @type array  $data         Data attributes for the button.
	 *     @type array  $i18n         Internationalization strings (button_title, button_text).
	 *     @type array  $button_class CSS classes for set/remove buttons.
	 * }
	 * @return string The generated image uploader HTML, or empty string if no ID provided.
	 */
	public static function get_image_uploader_form( array $args ): string {

		if ( empty( $args['id'] ) ) {
			return '';
		}

		$args = Arrays::array_merge_recursive_distinct(
			[
				'id'           => '',
				'post_id'      => Query::get_the_real_id(),
				'data'         => [
					'inputType' => 'social',
					'width'     => 1200,
					'height'    => 630,
					'minWidth'  => 200,
					'minHeight' => 200,
					'flex'      => true,
				],
				'i18n'         => [
					'button_title' => '',
					'button_text'  => \__( 'Select Image', 'better-seo' ),
				],
				'button_class' => [
					'set'    => [
						'button',
						'button-primary',
						'button-small',
					],
					'remove' => [
						'button',
						'button-small',
					],
				],
			],
			$args,
		);

		$s_id = \esc_attr( $args['id'] );

		$content = vsprintf(
			'<button type=button data-href="%s" class="better-seo-set-image-button %s" title="%s" id="%s-select" %s>%s</button>',
			[
				\esc_url( \get_upload_iframe_src( 'image', $args['post_id'] ) ),
				\esc_attr( implode( ' ', (array) $args['button_class']['set'] ) ),
				\esc_attr( $args['i18n']['button_title'] ),
				$s_id,
				HTML::make_data_attributes(
					[ 'inputId' => $args['id'] ]
					+ $args['data']
					+ [ 'buttonClass' => $args['button_class'] ],
				),
				\esc_html( $args['i18n']['button_text'] ),
			],
		);

		$content .= <<<HTML
			<span class=better-seo-image-notifications data-for="{$s_id}"><span class=better-seo-tooltip-wrap><span id="{$s_id}-preview" class="better-seo-image-preview better-seo-tooltip-item hidden" tabindex="0"></span></span><span class=better-seo-tooltip-wrap><span id="{$s_id}-image-warning" class="better-seo-image-warning better-seo-tooltip-item hidden" tabindex="0"></span></span></span>
		HTML;

		return $content;
	}
}
