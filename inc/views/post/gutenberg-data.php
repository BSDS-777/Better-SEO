<?php
/**
 * Better SEO - View: Post Gutenberg Data
 *
 * Provides the hidden Gutenberg data holder used by Better SEO.
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\Post
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 * @link       https://www.gnu.org/licenses/gpl-2.0.html
 */

declare( strict_types=1 );

namespace Better_SEO;

if ( ! \defined( 'BETTER_SEO_PRESENT' ) || ! Helper\Template::verify_secret( $secret ) ) {
      exit; // Access denied — direct file access is not permitted.
}

use Better_SEO\Helper\Query;

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- view file, not global scope.

printf(
      '<div id="%s" data-post-id="%d" class="hidden"></div>',
      'better-seo-gutenberg-data-holder',
      Query::get_the_real_id(), // phpcs:ignore WordPress.Security.EscapeOutput -- integer value, no escaping needed.
);
