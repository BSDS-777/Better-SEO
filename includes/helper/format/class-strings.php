<?php
/**
 * Better SEO - Helper Format Strings
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

/**
 * Class Better_SEO\Helper\Format\Strings
 *
 * Provides string manipulation utilities for Better SEO, including
 * ellipsis truncation, duplicate word detection, and sentence clamping.
 *
 * @since 1.0.0
 */
class Strings {

	/**
	 * Appends an HTML ellipsis entity if the string exceeds the given length.
	 *
	 * @since 1.0.0
	 *
	 * @param string $string The string to check.
	 * @param int    $over   The maximum character length. 0 disables truncation.
	 * @return string The original string, or truncated string with &hellip; appended.
	 */
	public static function hellip_if_over( string $string, int $over = 0 ): string {

		if ( $over > 0 && \mb_strlen( $string ) > $over ) {
			return mb_substr( $string, 0, abs( $over - 2 ) ) . '&hellip;';
		}

		return $string;
	}

	/**
	 * Returns a list of words that appear too frequently in the given string.
	 *
	 * Uses Unicode-aware word splitting and configurable frequency thresholds.
	 * Returns an empty array if no over-represented words are found.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $string The string to analyze.
	 * @param array<string, mixed> $args   Optional arguments:
	 *                                     - filter_under (int): Min count for long words. Default 3.
	 *                                     - filter_short_under (int): Min count for short words. Default 5.
	 *                                     - short_word_length (int): Max length for "short" words. Default 3.
	 * @return array<int, array<string, int>> List of [word => count] pairs for over-represented words.
	 */
	public static function get_word_count( string $string, array $args = [] ): array {

		$string = \wp_check_invalid_utf8( html_entity_decode( $string, \ENT_QUOTES, 'UTF-8' ) );

		if ( empty( $string ) ) {
			return [];
		}

		$args += [
			'filter_under'       => 3,
			'filter_short_under' => 5,
			'short_word_length'  => 3,
		];

		$use_mb = \extension_loaded( 'mbstring' );

		$word_list = preg_split(
			'/[^\p{Cc}\p{L}\p{N}\p{Pc}\p{Pd}\p{Pf}\'"]+/mu',
			$use_mb ? mb_strtolower( $string ) : strtolower( $string ),
			-1,
			\PREG_SPLIT_OFFSET_CAPTURE | \PREG_SPLIT_NO_EMPTY,
		);

		if ( empty( $word_list ) ) {
			return [];
		}

		$words        = array_column( $word_list, 0, 1 );
		$word_offsets = array_flip( array_reverse( $words, true ) );

		$min_count      = min( $args['filter_under'], $args['filter_short_under'] );
		$words_too_many = [];

		foreach ( array_count_values( $words ) as $word => $count ) {
			if ( $count < $min_count ) {
				continue;
			}

			if ( ( $use_mb ? mb_strlen( $word ) : \strlen( $word ) ) <= $args['short_word_length'] ) {
				if ( $count < $args['filter_short_under'] ) {
					continue;
				}
			} else {
				if ( $count < $args['filter_under'] ) {
					continue;
				}
			}

			$first_encountered_word = substr( $string, $word_offsets[ $word ], \strlen( $word ) );

			$words_too_many[] = [ $first_encountered_word => $count ];
		}

		return $words_too_many;
	}

	/**
	 * Clamps a sentence to a maximum character length at a natural sentence boundary.
	 *
	 * Uses Unicode-aware regex to find a clean break point. Applies wptexturize
	 * for proper typographic punctuation. Returns empty string if the result
	 * is shorter than the minimum length.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sentence        The sentence to clamp.
	 * @param int    $min_char_length The minimum acceptable character length. Default 1.
	 * @param int    $max_char_length The maximum character length. Default 4096.
	 * @return string The clamped sentence, or empty string if too short.
	 */
	public static function clamp_sentence( string $sentence, int $min_char_length = 1, int $max_char_length = 4096 ): string {

		$min_char_length = max( 1, $min_char_length );

		if ( \strlen( $sentence ) < $min_char_length ) {
			return '';
		}

		$sentence = trim( html_entity_decode( $sentence, \ENT_QUOTES, 'UTF-8' ) );

		preg_match(
			\sprintf(
				'/^.{0,%d}(?:[^\P{Po}\'":]|[\p{Pc}\p{Pd}\p{Pf}\p{Z}]|\x20|$)/su',
				$max_char_length,
			),
			$sentence,
			$matches,
		);

		$sentence = trim( $matches[0] ?? '' );

		if ( \strlen( $sentence ) < $min_char_length ) {
			return '';
		}

		$sentence = html_entity_decode(
			\wptexturize( htmlentities(
				$sentence,
				\ENT_QUOTES,
				'UTF-8',
			) ),
			\ENT_QUOTES,
			'UTF-8',
		);

		preg_match(
			'/(?:\A[\p{P}\p{Z}]*?)?((?:(?:\p{Z}*?\w\.)+[\P{Po}\p{M}\xBF\xA1:\'\p{Z}\.]*|[\P{Po}\p{M}\xBF\xA1:\'\p{Z}]+)[\p{Z}\w])(?:([^\P{Po}\p{M}\xBF\xA1:]\Z(*ACCEPT))|((?(?=.+(?:\w+[\p{Pc}\p{Pd}\p{Pf}\p{Z}]*){1,3}|[\p{Po}]\Z)(?:[^\p{Pe}\p{Pf}]*+.*[\p{Pe}\p{Pf}]+\Z(*ACCEPT)|.*[^\P{Po}\p{M}\xBF\xA1:][^\P{Nd}\p{Z}]*)|.*\Z(*ACCEPT)))(?>(.+?\p{Z}*(?:\w+[\p{Pc}\p{Pd}\p{Pf}\p{Z}]*){1,3})|[^\p{Pc}\p{Pd}\p{M}\xBF\xA1:])?)(.+)?/su',
			$sentence,
			$matches,
		);

		if ( isset( $matches[5] ) ) {
			$sentence = "{$matches[1]}{$matches[3]}{$matches[4]}{$matches[5]}";
			// Skip match[4] — it's useless content without match[5].
		} elseif ( isset( $matches[3] ) ) {
			$sentence = "{$matches[1]}{$matches[3]}";
		} elseif ( isset( $matches[2] ) ) {
			$sentence = "{$matches[1]}{$matches[2]}";
		} elseif ( isset( $matches[1] ) ) {
			$sentence = $matches[1];
		} else {
			// The sentence consists of control characters — discard it.
			return '';
		}

		if ( \strlen( $sentence ) < $min_char_length ) {
			return '';
		}

		preg_match(
			'/(.+[^\p{Pc}\p{Pd}\p{M}\xBF\xA1:;,\p{Z}\p{Po}])+?(\p{Z}*?[^\p{Pc}\p{Pd}\p{M}\xBF\xA1:;,\p{Z}]+)?([\p{Pc}\p{Pd}\p{M}\xBF\xA1:;,\p{Z}]+)?/su',
			$sentence,
			$matches,
		);

		if ( isset( $matches[2] ) && \strlen( $matches[2] ) ) {
			$sentence = "{$matches[1]}{$matches[2]}";
		} elseif ( isset( $matches[1] ) && \strlen( $matches[1] ) ) {
			// Append ellipsis — will be texturized to &hellip; later.
			$sentence = "{$matches[1]}...";
		} else {
			$sentence = '';
		}

		if ( \strlen( $sentence ) < $min_char_length ) {
			return '';
		}

		return trim( htmlentities( $sentence, \ENT_QUOTES, 'UTF-8' ) );
	}
}