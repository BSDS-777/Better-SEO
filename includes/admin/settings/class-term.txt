<?php
/**
 * Better SEO - Admin Settings Term
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\Settings
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

namespace Better_SEO\Admin\Settings;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Data,
	Helper\Query,
	Helper\Taxonomy,
	Helper\Template,
};

/**
 * Class Better_SEO\Admin\Settings\Term
 *
 * Handles registration and output of the Better SEO settings fields
 * on taxonomy term edit screens.
 *
 * @since 1.0.0
 */
final class Term {

	/**
	 * Registers the Better SEO term settings fields for the current taxonomy if supported.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function prepare_setting_fields(): void {

		if ( ! Query::is_term_edit() ) {
			return;
		}

		$taxonomy = Query::get_current_taxonomy();

		if ( ! Taxonomy::is_supported( $taxonomy ) ) {
			return;
		}

		\add_action(
			"{$taxonomy}_edit_form",
			[ self::class, 'output_setting_fields' ],
			/**
			 * Filters the Better SEO term meta box priority.
			 *
			 * @since 1.0.0
			 * @param int $priority The action priority. Default 0.
			 */
			(int) \apply_filters( 'better_seo_term_metabox_priority', 0 ),
			2,
		);
	}

	/**
	 * Outputs the Better SEO term settings fields.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term $term     The current term object.
	 * @param string   $taxonomy The current taxonomy slug.
	 * @return void
	 */
	public static function output_setting_fields( \WP_Term $term, string $taxonomy ): void {

		\wp_nonce_field(
			Data\Admin\Term::SAVE_NONCES['term-edit']['action'],
			Data\Admin\Term::SAVE_NONCES['term-edit']['name'],
		);

		/**
		 * Fires before the Better SEO term settings fields are rendered.
		 *
		 * @since 1.0.0
		 */
		\do_action( 'better_seo_pre_tt_inpost_box' );

		Template::output_view( 'term/settings', $term, $taxonomy );

		/**
		 * Fires after the Better SEO term settings fields are rendered.
		 *
		 * @since 1.0.0
		 */
		\do_action( 'better_seo_pro_tt_inpost_box' );
	}
}