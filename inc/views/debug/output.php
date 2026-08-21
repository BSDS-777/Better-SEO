<?php
/**
 * Better SEO - View: Debug Output
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\Debug
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

namespace Better_SEO;

if ( ! \defined( 'BETTER_SEO_PRESENT' ) || ! Helper\Template::verify_secret( $secret ) ) {
	die;
}

use Better_SEO\Internal\Debug;

use Better_SEO\Helper\{
	Post_Type,
	Query,
	Taxonomy,
};

$id        = Query::get_the_real_id();
$mdash     = ' &mdash; ';
$taxonomy  = Query::get_current_taxonomy();
$post_type = Query::get_current_post_type();

if ( Query::is_real_front_page() ) {
	$type = 'Front Page';
} elseif ( $taxonomy ) {
	$type = Taxonomy::get_label( $taxonomy );
} elseif ( $post_type ) {
	$type = Post_Type::get_label( $post_type );
} else {
	$type = 'Unknown';
}

?>
<style>
	:root {
		--navy:      #1a1a2e;
		--deep-blue: #16213e;
		--mid-blue:  #0f3460;
		--gold:      #c9a84c;
		--gold-lt:   #e8c97a;
		--cream:     #faf8f4;
		--white:     #ffffff;
		--grey-lt:   #f4f4f4;
		--grey-mid:  #888;
		--text-dark: #1a1a2e;
		--text-body: #3d3d3d;
	}
	.wp-ui-notification {
		color: var(--cream);
		background-color: var(--gold);
	}
	.code.highlight {
		font-size: 14px;
	}
	.better-seo-debug h3 {
		font-size: 18px;
		margin: 18px 0;
	}
</style>
<?php if ( \is_admin() ) : ?>
	<?php
	$bstyle = \is_rtl()
		? 'direction:ltr;color:var(--text-body);font-family:inherit;font-size:14px;clear:both;float:left;position:relative;width:calc(100% - 200px);min-height:700px;padding: 0;margin: 0;overflow:hidden;border:1px solid var(--grey-mid);border-radius:3px;line-height:18px;font-feature-settings:normal;font-variant:normal'
		: 'direction:ltr;color:var(--text-body);font-family:inherit;font-size:14px;clear:both;float:left;position:relative;width:calc(100% - 200px);min-height:700px;padding: 0;margin: 0;overflow:hidden;border:1px solid var(--grey-mid);border-radius:3px;line-height:18px;font-feature-settings:normal;font-variant:normal';
	?>
	<div style="<?php echo \esc_attr( $bstyle ); ?>">
		<h3 style="font-family:inherit;font-size:14px;padding: 0;margin: 0;line-height:39px;border-bottom:2px solid var(--gold);position:absolute;z-index:9002;width:100%;right:0;left:0;top:0;background:var(--navy);color:var(--cream);border-radius:3px 3px 0 0;height:39px;">
			SEO Debug Information
			<?php if ( Query::is_post_edit() || Query::is_term_edit() ) : ?>
				<?php
				echo ' :: ';
				echo \esc_html( "Type: {$type}" );
				echo \esc_html( $mdash . 'ID: ' . $id );
				echo \esc_html( $mdash . 'Plugin version: ' . BETTER_SEO_VERSION );
				echo \esc_html( $mdash . 'Plugin DB version: c' . \get_option( 'better_seo_upgraded_db_version' ) . ' | e' . BETTER_SEO_DB_VERSION );
				?>
			<?php endif; ?>
		</h3>
		<div style="position:absolute;bottom:0;right:0;left:0;top:39px;margin: 0;padding: 0;background:var(--cream);border-radius:3px;overflow-x:hidden;z-index:9001">
			<?php
			Debug::_output_debug_header();
			Debug::_output_debug_query();
			?>
		</div>
	</div>
<?php else : ?>
	<div class="better-seo-debug" style="direction:ltr;color:var(--text-body);font-family:inherit;font-size:14px;clear:both;float:left;position:relative;width:calc(100% - 80px);min-height:700px;padding: 0;margin: 0;overflow:hidden;border:1px solid var(--grey-mid);border-radius:3px;line-height:18px;font-feature-settings:normal;font-variant:normal">
		<h3 style="font-family:inherit;font-size:14px;padding: 0;margin: 0;line-height:39px;border-bottom:2px solid var(--gold);position:absolute;z-index:9002;width:100%;right:0;left:0;top:0;background:var(--navy);color:var(--cream);border-radius:3px 3px 0 0;height:39px">
			SEO Debug Information
			<?php
			echo ' :: ';
			echo 'Type: ' . \esc_html( $type );
			echo \esc_html( $mdash . 'ID: ' . $id );
			echo \esc_html( $mdash . 'Plugin version: ' . BETTER_SEO_VERSION );
			echo \esc_html( $mdash . 'Plugin DB version: c' . \get_option( 'better_seo_upgraded_db_version' ) . ' | e' . BETTER_SEO_DB_VERSION );
			?>
		</h3>
		<div style="position:absolute;bottom:0;right:0;left:0;top:39px;margin: 0;padding: 0;background:var(--cream);border-radius:3px;overflow-x:hidden;z-index:9001">
			<?php Debug::_output_debug_header(); ?>
			<div style="width:50%;float:left;">
				<?php Debug::_output_debug_query_from_cache(); ?>
			</div>
			<div style="width:50%;float:right;">
				<?php Debug::_output_debug_query(); ?>
			</div>
		</div>
	</div>
<?php endif; ?>