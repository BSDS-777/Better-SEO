<?php
/**
 * Better SEO - Admin Script AJAX
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\Script
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

namespace Better_SEO\Admin\Script;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Admin,
	Data,
	Data\Filter\Sanitize,
	Helper,
	Helper\Query,
	Meta,
};

/**
 * Class Better_SEO\Admin\Script\AJAX
 *
 * Holds AJAX action callbacks for Better SEO admin functionality,
 * including notice dismissal, counter updates, image cropping,
 * and post/term data retrieval for the block editor.
 *
 * @since 1.0.0
 */
final class AJAX {

	/**
	 * Clears a persistent notice on user request via AJAX (Dismiss icon clicked).
	 *
	 * @since 1.0.0
	 * @hook  wp_ajax_better_seo_dismiss_notice 10
	 *
	 * @return void
	 */
	public static function dismiss_notice(): void {

		Helper\Headers::clean_response_header();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- POST key is used only to look up locally stored nonce for verification below.
		$key = \sanitize_key( $_POST['better_seo_dismiss_key'] ?? '' );

		if ( ! $key ) {
			\wp_send_json_error( null, 400 );
		}

		$notices = Data\Plugin::get_site_cache( 'persistent_notices' ) ?? [];

		if ( empty( $notices[ $key ]['conditions']['capability'] ) ) {
			// Notice was already cleared elsewhere or key was invalid — self-resolving.
			\wp_send_json_error( null, 409 );
		}

		if (
			! \current_user_can( $notices[ $key ]['conditions']['capability'] )
			|| ! \check_ajax_referer( Admin\Notice\Persistent::_get_dismiss_nonce_action( $key ), 'better_seo_dismiss_nonce', false )
		) {
			\wp_die( -1, '', [ 'response' => 403 ] );
		}

		Admin\Notice\Persistent::clear_notice( $key );
		\wp_send_json_success( null, 200 );
	}

	/**
	 * Handles counter option update via AJAX for users that can edit posts.
	 *
	 * Cycles through counter types 0–3, resetting to 0 after 3.
	 *
	 * @since 1.0.0
	 * @hook  wp_ajax_better_seo_update_counter 10
	 *
	 * @return void
	 */
	public static function update_counter_type(): void {

		Helper\Headers::clean_response_header();

		// phpcs:disable WordPress.Security.NonceVerification -- check_ajax_capability_referer() handles nonce verification.
		Utils::check_ajax_capability_referer( 'edit_posts' );

		// Count up, reset to 0 after 3. $_POST['val'] may contain the updated number directly.
		$value = isset( $_POST['val'] )
			? (int) $_POST['val']
			: Data\Plugin\User::get_meta_item( 'counter_type' ) + 1;

		$value = \absint( $value );

		if ( $value > 3 ) {
			$value = 0;
		}

		Data\Plugin\User::update_single_meta_item( Query::get_current_user_id(), 'counter_type', $value );

		\wp_send_json_success();
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Handles image cropping via AJAX for users that can upload files.
	 *
	 * Overrides WordPress Core wp_ajax_crop_image() to require 'upload_files'
	 * capability instead of 'edit_post', since cropping creates a new attachment
	 * rather than editing the original. Only accepts 'better-seo-image' context
	 * and Better SEO AJAX nonces.
	 *
	 * @since 1.0.0
	 * @hook  wp_ajax_better_seo_crop_image 10
	 *
	 * @return void
	 */
	public static function crop_image(): void {

		Helper\Headers::clean_response_header();

		// phpcs:disable WordPress.Security.NonceVerification -- check_ajax_capability_referer() handles nonce verification.
		Utils::check_ajax_capability_referer( 'upload_files' );

		if ( ! isset( $_POST['id'], $_POST['context'], $_POST['cropDetails'] ) ) {
			\wp_send_json_error( [ 'message' => \esc_js( \__( 'Invalid request.', 'better-seo' ) ) ] );
		}

		$attachment_id = \absint( $_POST['id'] );

		if ( ! $attachment_id || 'attachment' !== \get_post_type( $attachment_id ) || ! \wp_attachment_is_image( $attachment_id ) ) {
			\wp_send_json_error( [ 'message' => \esc_js( \__( 'Image could not be processed.', 'better-seo' ) ) ] );
		}

		$context = str_replace( '_', '-', \sanitize_key( $_POST['context'] ) );
		$data    = array_map( 'absint', $_POST['cropDetails'] );
		$cropped = \wp_crop_image( $attachment_id, $data['x1'], $data['y1'], $data['width'], $data['height'], $data['dst_width'], $data['dst_height'] );

		if ( ! $cropped || \is_wp_error( $cropped ) ) {
			\wp_send_json_error( [ 'message' => \esc_js( \__( 'Image could not be processed.', 'better-seo' ) ) ] );
		}

		if ( 'better-seo-image' !== $context ) {
			\wp_send_json_error( [ 'message' => \esc_js( \__( 'Image could not be processed.', 'better-seo' ) ) ] );
		}

		\do_action( 'wp_ajax_crop_image_pre_save', $context, $attachment_id, $cropped );

		/** This filter is documented in wp-admin/includes/class-custom-image-header.php */
		$cropped = \apply_filters( 'wp_create_file_in_uploads', $cropped, $attachment_id );

		$parent_url       = \wp_get_attachment_url( $attachment_id );
		$parent_basename  = \wp_basename( $parent_url );
		$cropped_basename = \wp_basename( $cropped );
		$url              = str_replace( $parent_basename, $cropped_basename, $parent_url );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors -- See https://core.trac.wordpress.org/ticket/42480
		$size       = \function_exists( 'wp_getimagesize' ) ? \wp_getimagesize( $cropped ) : @getimagesize( $cropped );
		$image_type = $size ? $size['mime'] : 'image/jpeg';

		$original_attachment  = \get_post( $attachment_id );
		$sanitized_post_title = \sanitize_file_name( $original_attachment->post_title );
		$use_original_title   = \strlen( trim( $original_attachment->post_title ) )
			&& ( $parent_basename !== $sanitized_post_title )
			&& ( pathinfo( $parent_basename, \PATHINFO_FILENAME ) !== $sanitized_post_title );

		$use_original_description = \strlen( trim( $original_attachment->post_content ) );

		$attachment = [
			'post_title'     => $use_original_title ? $original_attachment->post_title : $cropped_basename,
			'post_content'   => $use_original_description ? $original_attachment->post_content : $url,
			'post_mime_type' => $image_type,
			'guid'           => $url,
			'context'        => $context,
		];

		if ( \strlen( trim( $original_attachment->post_excerpt ) ) ) {
			$attachment['post_excerpt'] = $original_attachment->post_excerpt;
		}

		if ( \strlen( trim( $original_attachment->_wp_attachment_image_alt ) ) ) {
			$attachment['meta_input'] = [
				'_wp_attachment_image_alt' => \wp_slash( $original_attachment->_wp_attachment_image_alt ),
			];
		}

		$attachment_id = \wp_insert_attachment( $attachment, $cropped );
		$metadata      = \wp_generate_attachment_metadata( $attachment_id, $cropped );
		$metadata      = \apply_filters( 'wp_ajax_cropped_attachment_metadata', $metadata );

		\wp_update_attachment_metadata( $attachment_id, $metadata );

		$attachment_id = \apply_filters( 'wp_ajax_cropped_attachment_id', $attachment_id, $context );

		\wp_send_json_success( \wp_prepare_attachment_for_js( $attachment_id ) );
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Returns various post data for the block editor on save via AJAX.
	 *
	 * Supports fetching: seobar, metadescription, ogdescription, twdescription, imageurl.
	 *
	 * @since 1.0.0
	 * @hook  wp_ajax_better_seo_update_post_data 10
	 *
	 * @return void
	 */
	public static function get_post_data(): void {

		Helper\Headers::clean_response_header();

		// phpcs:disable WordPress.Security.NonceVerification -- check_ajax_capability_referer() handles nonce verification.
		$post_id = \absint( $_POST['post_id'] ?? 0 );

		Utils::check_ajax_capability_referer( 'edit_post', $post_id );

		$_get_defaults = [
			'seobar'          => false,
			'metadescription' => false,
			'ogdescription'   => false,
			'twdescription'   => false,
			'imageurl'        => false,
		];

		// Only process keys that exist in defaults and are set to true.
		$get = array_keys(
			array_filter(
				array_intersect_key(
					array_merge(
						$_get_defaults,
						(array) ( $_POST['get'] ?? [] ),
					),
					$_get_defaults,
				)
			)
		);

		$generator_args = [ 'id' => $post_id ];
		$data           = [];

		foreach ( $get as $g ) {
			switch ( $g ) {
				case 'seobar':
					$data[ $g ] = Admin\SEOBar\Builder::generate_bar( $generator_args );
					break;

				case 'metadescription':
					$data[ $g ] = Query::is_static_front_page( $post_id )
						? ( Sanitize::metadata_content( Data\Plugin::get_option( 'homepage_description' ) ) ?: Meta\Description::get_generated_description( $generator_args ) )
						: Meta\Description::get_generated_description( $generator_args );
					$data[ $g ] = \esc_html( $data[ $g ] );
					break;

				case 'ogdescription':
					$data[ $g ] = Query::is_static_front_page( $post_id )
						? ( Sanitize::metadata_content( Data\Plugin::get_option( 'homepage_description' ) ) ?: Meta\Open_Graph::get_generated_description( $generator_args ) )
						: Meta\Open_Graph::get_generated_description( $generator_args );
					$data[ $g ] = \esc_html( $data[ $g ] );
					break;

				case 'twdescription':
					$data[ $g ] = Query::is_static_front_page( $post_id )
						? ( Sanitize::metadata_content( Data\Plugin::get_option( 'homepage_description' ) ) ?: Meta\Twitter::get_generated_description( $generator_args ) )
						: Meta\Twitter::get_generated_description( $generator_args );
					$data[ $g ] = \esc_html( $data[ $g ] );
					break;

				case 'imageurl':
					$data[ $g ] = Query::is_static_front_page( $post_id )
						? ( \sanitize_url( Data\Plugin::get_option( 'homepage_social_image_url' ), [ 'https', 'http' ] ) ?: Meta\Image::get_first_generated_image_url( $generator_args, 'social' ) )
						: Meta\Image::get_first_generated_image_url( $generator_args, 'social' );
					break;
			}
		}

		\wp_send_json_success( [
			'data'      => $data,
			'processed' => $get,
		] );
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Returns parent term slugs for a given term via AJAX.
	 *
	 * Used by the block editor to build hierarchical slug paths for terms.
	 *
	 * @since 1.0.0
	 * @hook  wp_ajax_better_seo_get_term_parent_slugs 10
	 *
	 * @return void
	 */
	public static function get_term_parent_slugs(): void {

		Helper\Headers::clean_response_header();

		// phpcs:disable WordPress.Security.NonceVerification -- check_ajax_capability_referer() handles nonce verification.
		Utils::check_ajax_capability_referer( 'edit_posts' );

		if ( ! isset( $_POST['term_id'], $_POST['taxonomy'] ) ) {
			\wp_send_json_error( 'invalid_request' );
		}

		$term_id = \absint( $_POST['term_id'] );

		if ( ! $term_id ) {
			\wp_send_json_error( 'invalid_object_id' );
		}

		$taxonomy          = \sanitize_key( \wp_unslash( $_POST['taxonomy'] ) );
		$parent_term_slugs = [];

		foreach ( Data\Term::get_term_parents( $term_id, $taxonomy, true ) as $parent_term ) {
			// Written as [ id, slug ] pairs instead of [ id => slug ] to preserve order via JSON.parse.
			$parent_term_slugs[] = [
				'id'   => $parent_term->term_id,
				'slug' => $parent_term->slug,
			];
		}

		\wp_send_json_success( $parent_term_slugs );
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Returns parent post slugs for a given post via AJAX.
	 *
	 * Used by the block editor to build hierarchical slug paths for posts.
	 * Restricted to post types that support page-like hierarchical structures
	 * and that the current user has permission to read.
	 *
	 * @since 1.0.0
	 * @hook  wp_ajax_better_seo_get_post_parent_slugs 10
	 *
	 * @return void
	 */
	public static function get_post_parent_slugs(): void {

		Helper\Headers::clean_response_header();

		// phpcs:disable WordPress.Security.NonceVerification -- check_ajax_capability_referer() handles nonce verification.
		Utils::check_ajax_capability_referer( 'edit_posts' );

		if ( ! isset( $_POST['post_id'] ) ) {
			\wp_send_json_error( 'invalid_request' );
		}

		$post_id = \absint( $_POST['post_id'] );

		if ( ! $post_id ) {
			\wp_send_json_error( 'invalid_object_id' );
		}

		$post_type_object = \get_post_type_object( \get_post( $post_id )->post_type ?? '' );

		// Prevent unauthorized access to restricted post types.
		if (
			! $post_type_object
			|| ! \current_user_can( $post_type_object->cap->read_post, $post_id )
		) {
			\wp_send_json_error( 'insufficient_capability' );
		}

		$parent_post_slugs = [];

		foreach ( Data\Post::get_post_parents( $post_id, true ) as $parent_post ) {
			// Written as [ id, slug ] pairs instead of [ id => slug ] to preserve order via JSON.parse.
			$parent_post_slugs[] = [
				'id'   => $parent_post->ID,
				'slug' => $parent_post->post_name,
			];
		}

		\wp_send_json_success( $parent_post_slugs );
		// phpcs:enable WordPress.Security.NonceVerification
	}
}