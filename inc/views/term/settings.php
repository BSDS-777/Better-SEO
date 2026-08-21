<?php
/**
 * Better SEO - View: Term Settings
 *
 * Renders the Better SEO SEO meta fields on the WordPress term edit page,
 * including title, description, social, canonical, robots, and redirect settings.
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\Term
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

use Better_SEO\Admin\Settings\Layout\{
	Form,
	HTML,
	Input,
};
use Better_SEO\Data\Filter\Sanitize;

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- view file, not global scope.

[ $term, $taxonomy ] = $view_args;

$term_id = $term->term_id;
$meta    = Data\Plugin\Term::get_meta( $term_id );

$generator_args = [
	'id'  => $term_id,
	'tax' => $taxonomy,
];

$show_og = (bool) Data\Plugin::get_option( 'og_tags' );
$show_tw = (bool) Data\Plugin::get_option( 'twitter_tags' );

$tw_supported_cards = Meta\Twitter::get_supported_cards();

$image_placeholder = Meta\Image::get_first_generated_image_url( $generator_args, 'social' );

$default_canonical = Meta\URI::get_generated_url( $generator_args );
$robots_defaults   = Meta\Robots::get_generated_meta(
	$generator_args,
	[ 'noindex', 'nofollow', 'noarchive' ],
	ROBOTS_IGNORE_SETTINGS,
);

/* translators: %s = the currently active default robots value (e.g. "index" or "noindex") */
$_default_i18n = \__( 'Default (%s)', 'better-seo' );

$robots_settings = [
	'noindex'   => [
		'id'        => 'better-seo-meta[noindex]',
		'name'      => 'better-seo-meta[noindex]',
		'force_on'  => 'index',
		'force_off' => 'noindex',
		'label'     => \__( 'Indexing', 'better-seo' ),
		'_default'  => empty( $robots_defaults['noindex'] ) ? 'index' : 'noindex',
		'_value'    => $meta['noindex'],
		'_info'     => [
			\__( 'Instructs search engines not to include this term archive in search results.', 'better-seo' ),
			'https://developers.google.com/search/docs/advanced/crawling/block-indexing',
		],
	],
	'nofollow'  => [
		'id'        => 'better-seo-meta[nofollow]',
		'name'      => 'better-seo-meta[nofollow]',
		'force_on'  => 'follow',
		'force_off' => 'nofollow',
		'label'     => \__( 'Link Following', 'better-seo' ),
		'_default'  => empty( $robots_defaults['nofollow'] ) ? 'follow' : 'nofollow',
		'_value'    => $meta['nofollow'],
		'_info'     => [
			\__( 'Instructs search engines not to follow links on this term archive page.', 'better-seo' ),
			'https://developers.google.com/search/docs/advanced/guidelines/qualify-outbound-links',
		],
	],
	'noarchive' => [
		'id'        => 'better-seo-meta[noarchive]',
		'name'      => 'better-seo-meta[noarchive]',
		'force_on'  => 'archive',
		'force_off' => 'noarchive',
		'label'     => \__( 'Archiving', 'better-seo' ),
		'_default'  => empty( $robots_defaults['noarchive'] ) ? 'archive' : 'noarchive',
		'_value'    => $meta['noarchive'],
		'_info'     => [
			\__( 'Instructs search engines not to store a cached copy of this term archive page.', 'better-seo' ),
			'https://developers.google.com/search/docs/advanced/robots/robots_meta_tag#directives',
		],
	],
];

?>
<div class="better-seo-section-header">
	<h2><?php \esc_html_e( 'General SEO Settings', 'better-seo' ); ?></h2>
</div>

<table class="form-table better-seo-term-meta">
	<tbody>
		<?php
		if ( Data\Plugin::get_option( 'display_seo_bar_metabox' ) ) {
			?>
			<tr class="form-field">
				<th scope="row"><strong><?php \esc_html_e( 'SEO Status', 'better-seo' ); ?></strong></th>
				<td>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput -- generate_bar() escapes.
					echo Admin\SEOBar\Builder::generate_bar( $generator_args );
					?>
				</td>
			</tr>
			<?php
		}
		?>

		<tr class="form-field">
			<th scope="row">
				<label for="better-seo-meta[doctitle]">
					<strong><?php \esc_html_e( 'Meta Title', 'better-seo' ); ?></strong>
					<?php
					echo ' ';
					HTML::make_info(
						\__( 'The meta title controls how this term archive appears in search engine results. A well-crafted title improves click-through rates.', 'better-seo' ),
						'https://developers.google.com/search/docs/advanced/appearance/title-link',
					);
					?>
				</label>
				<?php
				if ( Data\Plugin::get_option( 'display_character_counter' ) ) {
					Form::output_character_counter_wrap( 'better-seo-meta[doctitle]' );
				}
				if ( Data\Plugin::get_option( 'display_pixel_counter' ) ) {
					Form::output_pixel_counter_wrap( 'better-seo-meta[doctitle]', 'title' );
				}
				?>
			</th>
			<td>
				<div class="better-seo-title-wrap">
					<input type="text" name="better-seo-meta[doctitle]" id="better-seo-meta[doctitle]" value="<?php echo \esc_html( Sanitize::metadata_content( $meta['doctitle'] ) ); ?>" class="large-text" autocomplete="off" data-form-type="other">
					<?php
					Input::output_js_title_data(
						'better-seo-meta[doctitle]',
						[
							'state' => [
								'refTitleLocked'    => false,
								'defaultTitle'      => \esc_html( Meta\Title::get_bare_generated_title( $generator_args ) ),
								'addAdditions'      => Meta\Title\Conditions::use_branding( $generator_args ),
								'useSocialTagline'  => Meta\Title\Conditions::use_branding( $generator_args, true ),
								'additionValue'     => \esc_html( Meta\Title::get_addition() ),
								'additionPlacement' => 'left' === Meta\Title::get_addition_location() ? 'before' : 'after',
							],
						],
					);
					?>
				</div>
				<label for="better-seo-meta[title_no_blog_name]" class="better-seo-term-checkbox-wrap">
					<input type="checkbox" name="better-seo-meta[title_no_blog_name]" id="better-seo-meta[title_no_blog_name]" value="1" <?php \checked( Data\Plugin\Term::get_meta_item( 'title_no_blog_name' ) ); ?>>
					<?php
					\esc_html_e( 'Remove the site title?', 'better-seo' );
					echo ' ';
					HTML::make_info( \__( 'Enable this option when you want to control the title format manually, without the site name appended or prepended.', 'better-seo' ) );
					?>
				</label>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row">
				<label for="better-seo-meta[description]">
					<strong><?php \esc_html_e( 'Meta Description', 'better-seo' ); ?></strong>
					<?php
					echo ' ';
					HTML::make_info(
						\__( 'The meta description appears beneath your title in search results. A compelling description encourages users to click through to this term archive.', 'better-seo' ),
						'https://developers.google.com/search/docs/advanced/appearance/snippet',
					);
					?>
				</label>
				<?php
				if ( Data\Plugin::get_option( 'display_character_counter' ) ) {
					Form::output_character_counter_wrap( 'better-seo-meta[description]' );
				}
				if ( Data\Plugin::get_option( 'display_pixel_counter' ) ) {
					Form::output_pixel_counter_wrap( 'better-seo-meta[description]', 'description' );
				}
				?>
			</th>
			<td>
				<textarea name="better-seo-meta[description]" id="better-seo-meta[description]" rows="4" class="large-text" autocomplete="off"><?php echo \esc_html( Sanitize::metadata_content( $meta['description'] ) ); ?></textarea>
				<?php
				Input::output_js_description_data(
					'better-seo-meta[description]',
					[
						'state' => [
							'defaultDescription' => \esc_html(
								Meta\Description::get_generated_description( $generator_args ),
							),
						],
					],
				);
				?>
			</td>
		</tr>
	</tbody>
</table>

<div class="better-seo-section-header">
	<h2><?php \esc_html_e( 'Social SEO Settings', 'better-seo' ); ?></h2>
</div>
<?php

Input::output_js_social_data(
	'better-seo-social-tt',
	[
		'og' => [
			'state' => [
				'defaultTitle' => \esc_html( Meta\Open_Graph::get_generated_title( $generator_args ) ),
				'addAdditions' => Meta\Title\Conditions::use_branding( $generator_args, 'og' ),
				'defaultDesc'  => \esc_html( Meta\Open_Graph::get_generated_description( $generator_args ) ),
			],
		],
		'tw' => [
			'state' => [
				'defaultTitle' => \esc_html( Meta\Twitter::get_generated_title( $generator_args ) ),
				'addAdditions' => Meta\Title\Conditions::use_branding( $generator_args, 'twitter' ),
				'defaultDesc'  => \esc_html( Meta\Twitter::get_generated_description( $generator_args ) ),
			],
		],
	],
);
?>

<table class="form-table better-seo-term-meta">
	<tbody>
		<tr class="form-field" <?php echo $show_og ? '' : 'style="display:none"'; ?>>
			<th scope="row">
				<label for="better-seo-meta[og_title]">
					<strong><?php \esc_html_e( 'Open Graph Title', 'better-seo' ); ?></strong>
				</label>
				<?php
				if ( Data\Plugin::get_option( 'display_character_counter' ) ) {
					Form::output_character_counter_wrap( 'better-seo-meta[og_title]' );
				}
				?>
			</th>
			<td>
				<div id="better-seo-og-title-wrap">
					<input name="better-seo-meta[og_title]" id="better-seo-meta[og_title]" type="text" value="<?php echo \esc_html( Sanitize::metadata_content( $meta['og_title'] ) ); ?>" class="large-text" autocomplete="off" data-form-type="other" data-better-seo-social-group="better-seo-social-tt" data-better-seo-social-type="ogTitle">
				</div>
			</td>
		</tr>

		<tr class="form-field" <?php echo $show_og ? '' : 'style="display:none"'; ?>>
			<th scope="row">
				<label for="better-seo-meta[og_description]">
					<strong><?php \esc_html_e( 'Open Graph Description', 'better-seo' ); ?></strong>
				</label>
				<?php
				if ( Data\Plugin::get_option( 'display_character_counter' ) ) {
					Form::output_character_counter_wrap( 'better-seo-meta[og_description]' );
				}
				?>
			</th>
			<td>
				<textarea name="better-seo-meta[og_description]" id="better-seo-meta[og_description]" rows="4" class="large-text" autocomplete="off" data-better-seo-social-group="better-seo-social-tt" data-better-seo-social-type="ogDesc"><?php echo \esc_html( Sanitize::metadata_content( $meta['og_description'] ) ); ?></textarea>
			</td>
		</tr>

		<tr class="form-field" <?php echo $show_tw ? '' : 'style="display:none"'; ?>>
			<th scope="row">
				<label for="better-seo-meta[tw_title]">
					<strong><?php \esc_html_e( 'X (Twitter) Title', 'better-seo' ); ?></strong>
				</label>
				<?php
				if ( Data\Plugin::get_option( 'display_character_counter' ) ) {
					Form::output_character_counter_wrap( 'better-seo-meta[tw_title]' );
				}
				?>
			</th>
			<td>
				<div id="better-seo-tw-title-wrap">
					<input name="better-seo-meta[tw_title]" id="better-seo-meta[tw_title]" type="text" value="<?php echo \esc_html( Sanitize::metadata_content( $meta['tw_title'] ) ); ?>" class="large-text" autocomplete="off" data-form-type="other" data-better-seo-social-group="better-seo-social-tt" data-better-seo-social-type="twTitle">
				</div>
			</td>
		</tr>

		<tr class="form-field" <?php echo $show_tw ? '' : 'style="display:none"'; ?>>
			<th scope="row">
				<label for="better-seo-meta[tw_description]">
					<strong><?php \esc_html_e( 'X (Twitter) Description', 'better-seo' ); ?></strong>
				</label>
				<?php
				if ( Data\Plugin::get_option( 'display_character_counter' ) ) {
					Form::output_character_counter_wrap( 'better-seo-meta[tw_description]' );
				}
				?>
			</th>
			<td>
				<textarea name="better-seo-meta[tw_description]" id="better-seo-meta[tw_description]" rows="4" class="large-text" autocomplete="off" data-better-seo-social-group="better-seo-social-tt" data-better-seo-social-type="twDesc"><?php echo \esc_html( Sanitize::metadata_content( $meta['tw_description'] ) ); ?></textarea>
			</td>
		</tr>

		<tr class="form-field" <?php echo $show_tw ? '' : 'style="display:none"'; ?>>
			<th scope="row">
				<label for="better-seo-meta[tw_card_type]">
					<strong><?php \esc_html_e( 'X (Twitter) Card Type', 'better-seo' ); ?></strong>
					<?php
					echo ' ';
					HTML::make_info(
						\__( 'The card type controls how your content appears when shared on X (Twitter) and other platforms like Discord. Use "Summary with Large Image" for maximum visual impact, or "Summary" for a compact card with a small thumbnail.', 'better-seo' ),
						'https://developer.twitter.com/en/docs/twitter-for-websites/cards/overview/abouts-cards',
					);
					?>
				</label>
			</th>
			<td>
				<?php
				// phpcs:disable WordPress.Security.EscapeOutput -- make_single_select_form() escapes.
				echo Form::make_single_select_form( [
					'id'       => 'better-seo-meta[tw_card_type]',
					'class'    => 'better-seo-term-select-wrap',
					'name'     => 'better-seo-meta[tw_card_type]',
					'options'  => array_merge(
						[ '' => \sprintf( $_default_i18n, Meta\Twitter::get_generated_card_type( $generator_args ) ) ],
						array_combine( $tw_supported_cards, $tw_supported_cards ),
					),
					'selected' => $meta['tw_card_type'],
				] );
				// phpcs:enable WordPress.Security.EscapeOutput
				?>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row">
				<label for="better-seo-meta-socialimage-url">
					<strong><?php \esc_html_e( 'Social Sharing Image', 'better-seo' ); ?></strong>
					<?php
					echo ' ';
					HTML::make_info(
						\__( 'This image is displayed when your term archive is shared on social networks and in search results. For best results, use an image with a 1.91:1 aspect ratio that is at least 1200px wide.', 'better-seo' ),
						'https://developers.facebook.com/docs/sharing/best-practices#images',
					);
					?>
				</label>
			</th>
			<td>
				<input type="url" name="better-seo-meta[social_image_url]" id="better-seo-meta-socialimage-url" placeholder="<?php echo \esc_attr( $image_placeholder ); ?>" value="<?php echo \esc_attr( $meta['social_image_url'] ); ?>" class="large-text" autocomplete="off">
				<input type="hidden" name="better-seo-meta[social_image_id]" id="better-seo-meta-socialimage-id" value="<?php echo (int) $meta['social_image_id']; ?>" disabled class="better-seo-enable-media-if-js">
				<div class="hide-if-no-better-seo-js better-seo-term-button-wrap">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput -- get_image_uploader_form() escapes.
					echo Form::get_image_uploader_form( [ 'id' => 'better-seo-meta-socialimage' ] );
					?>
				</div>
			</td>
		</tr>
	</tbody>
</table>

<div class="better-seo-section-header">
	<h2><?php \esc_html_e( 'Visibility SEO Settings', 'better-seo' ); ?></h2>
</div>

<table class="form-table better-seo-term-meta">
	<tbody>
		<tr class="form-field">
			<th scope="row">
				<label for="better-seo-meta[canonical]">
					<strong><?php \esc_html_e( 'Canonical URL', 'better-seo' ); ?></strong>
					<?php
					echo ' ';
					HTML::make_info(
						\__( 'The canonical URL tells search engines which version of this term archive is the authoritative source, helping to prevent duplicate content issues.', 'better-seo' ),
						'https://developers.google.com/search/docs/advanced/crawling/consolidate-duplicate-urls',
					);
					?>
				</label>
			</th>
			<td>
				<input type="url" name="better-seo-meta[canonical]" id="better-seo-meta[canonical]" placeholder="<?php echo \esc_url( $default_canonical ); ?>" value="<?php echo \esc_attr( $meta['canonical'] ); ?>" class="large-text" autocomplete="off">
				<?php
				$tax_object  = \get_taxonomy( $taxonomy );
				$permastruct = Meta\URI\Utils::get_url_permastruct( $generator_args );

				$parent_term_slugs        = [];
				$is_taxonomy_hierarchical = $tax_object->hierarchical && $tax_object->rewrite['hierarchical'];

				// Build parent term slug chain for hierarchical taxonomies using the taxonomy in the permalink structure.
				if ( $is_taxonomy_hierarchical && str_contains( $permastruct, "%{$taxonomy}%" ) ) {
					foreach ( Data\Term::get_term_parents( $term_id, $taxonomy ) as $parent_term ) {
						$parent_term_slugs[] = [
							'id'   => $parent_term->term_id,
							'slug' => $parent_term->slug,
						];
					}
				}

				Input::output_js_canonical_data(
					'better-seo-meta[canonical]',
					[
						'state' => [
							'refCanonicalLocked' => false,
							'defaultCanonical'   => \esc_url( $default_canonical ),
							'preferredScheme'    => Meta\URI\Utils::get_preferred_url_scheme(),
							'urlStructure'       => Meta\URI\Utils::get_url_permastruct( $generator_args ),
							'parentTermSlugs'    => $parent_term_slugs,
							'isHierarchical'     => $is_taxonomy_hierarchical,
						],
					],
				);
				?>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row">
				<strong><?php \esc_html_e( 'Robots Meta Settings', 'better-seo' ); ?></strong>
				<?php
				echo ' ';
				HTML::make_info(
					\__( 'These directives instruct search engine robots whether to index this term archive, follow its links, or store a cached copy. Changes here override the global defaults for this term only.', 'better-seo' ),
					'https://developers.google.com/search/docs/advanced/robots/robots_meta_tag#directives',
				);
				?>
			</th>
			<td>
				<?php
				foreach ( $robots_settings as $_s ) {
					// phpcs:disable WordPress.Security.EscapeOutput -- make_single_select_form() escapes.
					echo Form::make_single_select_form( [
						'id'       => $_s['id'],
						'class'    => 'better-seo-term-select-wrap',
						'name'     => $_s['name'],
						'label'    => $_s['label'],
						'options'  => [
							0  => \sprintf( $_default_i18n, $_s['_default'] ),
							-1 => $_s['force_on'],
							1  => $_s['force_off'],
						],
						'selected' => $_s['_value'],
						'info'     => $_s['_info'],
						'data'     => [
							'defaultUnprotected' => $_s['_default'],
							'defaultI18n'        => $_default_i18n,
						],
					] );
					// phpcs:enable WordPress.Security.EscapeOutput
				}
				?>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row">
				<label for="better-seo-meta[redirect]">
					<strong><?php \esc_html_e( '301 Redirect URL', 'better-seo' ); ?></strong>
					<?php
					echo ' ';
					HTML::make_info(
						\__( 'Permanently redirect all visitors and search engines from this term archive to another URL. Use this when content has moved and you want to preserve SEO value.', 'better-seo' ),
						'https://developers.google.com/search/docs/crawling-indexing/301-redirects',
					);
					?>
				</label>
			</th>
			<td>
				<input type="url" name="better-seo-meta[redirect]" id="better-seo-meta[redirect]" value="<?php echo \esc_attr( $meta['redirect'] ); ?>" class="large-text" autocomplete="off">
			</td>
		</tr>
	</tbody>
</table>
