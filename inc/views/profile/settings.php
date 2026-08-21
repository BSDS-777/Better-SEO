<?php
/**
 * Better SEO - View: Profile Settings
 *
 * Renders the Better SEO author SEO fields on the WordPress user profile page.
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\Profile
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

use const Better_SEO\ROBOTS_IGNORE_SETTINGS;

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- view file, not global scope.

[ $user ] = $view_args;

$fields = [
	'better-seo-user-meta[facebook_page]' => [
		'name'        => \__( 'Facebook Profile URL', 'better-seo' ),
		'type'        => 'url',
		'placeholder' => \_x( 'https://www.facebook.com/myProfile', 'Example Facebook profile URL', 'better-seo' ),
		'value'       => Data\Plugin\User::get_meta_item( 'facebook_page', $user->ID ),
		'class'       => '',
	],
	'better-seo-user-meta[twitter_page]'  => [
		'name'        => \__( 'X (Twitter) Profile Handle', 'better-seo' ),
		'type'        => 'text',
		'placeholder' => \_x( '@your-username', 'X (Twitter) @username example', 'better-seo' ),
		'value'       => Data\Plugin\User::get_meta_item( 'twitter_page', $user->ID ),
		'class'       => 'ltr',
	],
];

?>
<h2><?php \esc_html_e( 'Author SEO Information', 'better-seo' ); ?></h2>
<table class="form-table">
<?php
foreach ( $fields as $field => $labels ) {
	?>
	<tr class="user-<?php echo \esc_attr( $field ); ?>-wrap">
		<th>
			<label for="<?php echo \esc_attr( $field ); ?>">
				<?php echo \esc_html( $labels['name'] ); ?>
			</label>
		</th>
		<td>
			<input
				type="<?php echo \esc_attr( $labels['type'] ); ?>"
				name="<?php echo \esc_attr( $field ); ?>"
				id="<?php echo \esc_attr( $field ); ?>"
				value="<?php echo \esc_attr( $labels['value'] ); ?>"
				placeholder="<?php echo \esc_attr( $labels['placeholder'] ); ?>"
				class="regular-text <?php echo \esc_attr( $labels['class'] ); ?>" />
			<p class="description"><?php \esc_html_e( 'This information may be displayed publicly on your author profile.', 'better-seo' ); ?></p>
		</td>
	</tr>
	<?php
}
?>
</table>