<?php
/**
 * Better SEO - Sitemap Optimized Main
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Sitemap\Optimized
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

namespace Better_SEO\Sitemap\Optimized;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Data,
	Sitemap,
};

/**
 * Class Better_SEO\Sitemap\Optimized\Main
 *
 * Abstract base class for Better SEO optimized sitemap generators.
 * Provides shared generation lifecycle methods (prepare, shutdown),
 * URL counting, and XML entry building utilities.
 *
 * @since 1.0.0
 */
abstract class Main {

	/**
	 * The number of URLs added to the sitemap during generation.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public int $url_count = 0;

	/**
	 * Prepares the environment for sitemap generation.
	 *
	 * Raises the PHP memory limit to the 'sitemap' context limit.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	final public function prepare_generation(): void {
		\wp_raise_memory_limit( 'sitemap' );
	}

	/**
	 * Performs any cleanup after sitemap generation completes.
	 *
	 * Currently a no-op; reserved for future use.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	final public function shutdown_generation(): void {}

	/**
	 * Generates the sitemap by running the full prepare → build → shutdown lifecycle.
	 *
	 * @since 1.0.0
	 *
	 * @return string The generated sitemap XML content.
	 */
	public function generate_sitemap(): string {

		$this->prepare_generation();

		$sitemap = $this->build_sitemap();

		$this->shutdown_generation();

		return $sitemap;
	}

	/**
	 * Builds and returns the sitemap XML content string.
	 *
	 * @since 1.0.0
	 *
	 * @return string The sitemap XML content.
	 */
	abstract public function build_sitemap(): string;

	/**
	 * Recursively builds an XML entry string from a nested data array.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data  The data array to convert to XML.
	 * @param int                  $level The current indentation level. Default 1.
	 * @return string The XML entry string.
	 */
	protected static function create_xml_entry( array $data, int $level = 1 ): string {

		$out = '';

		foreach ( $data as $key => $value ) {
			$tabs = str_repeat( "\t", $level );

			if ( \is_array( $value ) ) {
				$value = "\n" . self::create_xml_entry( $value, $level + 1 ) . $tabs;
			}

			$out .= "{$tabs}<{$key}>{$value}</{$key}>\n";
		}

		return $out;
	}
}