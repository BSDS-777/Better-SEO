<?php
/**
 * Better SEO — Uninstall
 *
 * Runs when the plugin is deleted via the WordPress admin.
 * Removes all plugin data: options, transients, caches, and user meta.
 *
 * @package    Better_SEO
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
 *
 * @see https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 *
 * SECURITY NOTE:
 * WordPress sets WP_UNINSTALL_PLUGIN before calling this file.
 * We verify this constant is defined before executing any cleanup.
 * This prevents direct execution of this file outside of WordPress.
 */

declare( strict_types=1 );

// Prevent direct file access — WordPress must define WP_UNINSTALL_PLUGIN.
if ( ! \defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Access denied — direct file access is not permitted.
}

// ─── SITE OPTIONS ──────────────────────────────────────────────────────────────

/**
 * Removes all Better SEO site-wide options from the wp_options table.
 *
 * @since 1.0.0
 */
$options_to_delete = [
	'better-seo-site-settings',   // BETTER_SEO_SITE_OPTIONS — plugin settings array
	'better-seo-site-cache',      // BETTER_SEO_SITE_CACHE — plugin cache array
	'better_seo_upgraded_db_version', // DB version tracking
	'better_seo_activated_version',   // Activation version tracking
];

foreach ( $options_to_delete as $option ) {
	\delete_option( $option );
}

// ─── TRANSIENTS ────────────────────────────────────────────────────────────────

/**
 * Removes all Better SEO sitemap transients from the database.
 *
 * Sitemap transients use a dynamic prefix (better_seo_sitemap_*).
 * We query the options table directly to catch all variants.
 *
 * @since 1.0.0
 */
global $wpdb;

// Delete all sitemap transients matching the Better SEO prefix.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_better_seo_sitemap_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_better_seo_sitemap_' ) . '%',
	),
);

// Delete any other Better SEO transients.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_better-seo_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_better-seo_' ) . '%',
	),
);

// ─── USER META ─────────────────────────────────────────────────────────────────

/**
 * Removes all Better SEO user meta from the wp_usermeta table.
 *
 * @since 1.0.0
 */
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( 'better_seo_' ) . '%',
	),
);

// ─── POST META ─────────────────────────────────────────────────────────────────

/**
 * Removes all Better SEO post meta from the wp_postmeta table.
 *
 * Better SEO stores per-post SEO settings under the '_better_seo_meta' key.
 *
 * @since 1.0.0
 */
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_better_seo_' ) . '%',
	),
);

// ─── TERM META ─────────────────────────────────────────────────────────────────

/**
 * Removes all Better SEO term meta from the wp_termmeta table.
 *
 * @since 1.0.0
 */
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( 'better_seo_' ) . '%',
	),
);

// ─── SCHEDULED EVENTS ──────────────────────────────────────────────────────────

/**
 * Removes all Better SEO scheduled cron events.
 *
 * @since 1.0.0
 */
$cron_hooks = [
	'better_seo_sitemap_cron',
	'better_seo_sitemap_prerender',
];

foreach ( $cron_hooks as $hook ) {
	$timestamp = \wp_next_scheduled( $hook );
	if ( $timestamp ) {
		\wp_unschedule_event( $timestamp, $hook );
	}
	\wp_clear_scheduled_hook( $hook );
}

// ─── MULTISITE CLEANUP ─────────────────────────────────────────────────────────

/**
 * On multisite installations, removes Better SEO data from all subsites.
 *
 * Iterates over all sites and deletes site-specific options, transients,
 * and user meta for each subsite.
 *
 * @since 1.0.0
 */
if ( \is_multisite() ) {

	$sites = \get_sites( [
		'fields'     => 'ids',
		'number'     => 0, // All sites.
		'spam'       => 0,
		'deleted'    => 0,
		'archived'   => 0,
	] );

	foreach ( $sites as $site_id ) {
		\switch_to_blog( $site_id );

		// Delete site-specific options.
		foreach ( $options_to_delete as $option ) {
			\delete_option( $option );
		}

		// Delete site-specific sitemap transients.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_better_seo_sitemap_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_better_seo_sitemap_' ) . '%',
			),
		);

		// Clear scheduled events for this site.
		foreach ( $cron_hooks as $hook ) {
			\wp_clear_scheduled_hook( $hook );
		}

		\restore_current_blog();
	}

	// Delete network-wide options (if any were stored).
	\delete_site_option( 'better-seo-site-settings' );
	\delete_site_option( 'better-seo-site-cache' );
	\delete_site_option( 'better_seo_upgraded_db_version' );
}

// ─── REWRITE RULES ─────────────────────────────────────────────────────────────

/**
 * Flushes rewrite rules to remove Better SEO sitemap endpoints.
 *
 * @since 1.0.0
 */
\flush_rewrite_rules();