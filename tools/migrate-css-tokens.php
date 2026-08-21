#!/usr/bin/env php
<?php
/**
 * Better SEO — CSS Token Migration Tool
 *
 * Replaces hardcoded hex color values in Better SEO CSS files with their
 * corresponding CSS custom property var() references from tokens.css.
 *
 * Usage:
 *   php tools/migrate-css-tokens.php [--dry-run] [--file=filename.css] [--verbose]
 *
 * Options:
 *   --dry-run   Preview all changes without writing any files (default: false)
 *   --file=     Process only a specific CSS file (default: all files)
 *   --verbose   Show every replacement, not just file summaries (default: false)
 *
 * Examples:
 *   php tools/migrate-css-tokens.php --dry-run
 *   php tools/migrate-css-tokens.php --dry-run --verbose
 *   php tools/migrate-css-tokens.php --file=tt.css --dry-run
 *   php tools/migrate-css-tokens.php
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 */

declare( strict_types=1 );

// ─── CONFIGURATION ────────────────────────────────────────────────────────────

/** Absolute path to the lib/css/ directory (relative to this script's location). */
const CSS_DIR = __DIR__ . '/../lib/css/';

/**
 * Files to process.
 * tokens.css and le.css are excluded — tokens.css IS the source of truth,
 * and le.css :root will be removed manually after migration.
 */
const CSS_FILES = [
	'better-seo.css',
	'better-seo-c.css',
	'media.css',
	'post.css',
	'pt.css',
	'settings.css',
	'term.css',
	'tt.css',
	'ui.css',
];

/**
 * Token replacement map.
 *
 * Format: 'hex_value' => 'var(--better-seo-token)'
 *
 * Rules:
 * - More specific (longer) patterns are listed first to prevent partial matches.
 * - Hex values with inline comments are matched with their comment stripped.
 * - WP admin colors (#2271b1, #646970, etc.) are intentionally NOT in this map.
 * - Only Better SEO brand and status colors are replaced.
 *
 * Priority order: semantic aliases > status tokens > brand palette tokens.
 */
const TOKEN_MAP = [

	// ── STATUS COLORS — via semantic aliases (preferred) ──────────────────────

	// Bad / Error — red
	'#c0392b' => 'var(--better-seo-color-error)',

	// Good / Success — green
	'#27ae60' => 'var(--better-seo-color-success)',

	// Info — mid blue (only when used as informational, not as brand accent)
	// Note: #0f3460 is also --better-seo-mid-blue; context determines alias.
	// We use --better-seo-color-info for notice icons, --better-seo-mid-blue for brand.
	// The script uses --better-seo-mid-blue as the safer generic replacement.

	// ── STATUS COLORS — low contrast variants ────────────────────────────────

	'#e88080' => 'var(--better-seo-status-bad-lt)',
	'#6fcf97' => 'var(--better-seo-status-good-lt)',
	'#5b7fa6' => 'var(--better-seo-status-unknown-lt)',
	'#aaaaaa' => 'var(--better-seo-status-undef-lt)',
	'#aaa'    => 'var(--better-seo-status-undef-lt)',

	// ── BRAND PALETTE ─────────────────────────────────────────────────────────

	// Navy — tooltip bg, tab text, focus ring, headers
	'#1a1a2e' => 'var(--better-seo-navy)',

	// Deep Blue
	'#16213e' => 'var(--better-seo-deep-blue)',

	// Mid Blue — unknown SEO state, info icons
	'#0f3460' => 'var(--better-seo-mid-blue)',

	// Gold — warnings, loading, focus glow, okay state
	'#c9a84c' => 'var(--better-seo-gold)',

	// Light Gold — low contrast okay
	'#e8c97a' => 'var(--better-seo-gold-lt)',

	// Cream — tooltip text
	'#faf8f4' => 'var(--better-seo-cream)',

	// Grey Mid — unknown/disabled/muted
	'#888888' => 'var(--better-seo-grey-mid)',
	'#888'    => 'var(--better-seo-grey-mid)',

	// Text Body
	'#3d3d3d' => 'var(--better-seo-text-body)',
];

/**
 * Patterns that should NOT be replaced even if they contain a matching hex.
 * These are WP admin colors or intentional hardcoded values.
 */
const SKIP_PATTERNS = [
	'#2271b1',  // WP Admin Blue — focus rings
	'#d63638',  // WP Admin Red — warning-selected
	'#646970',  // WP Admin Muted — description text
	'#1d2327',  // WP Admin Dark — hover
	'#e2e4e7',  // WP Admin Separator
	'#dadada',  // WP Admin Border
	'#f5f5f5',  // WP Admin Light BG
	'#f9f9f9',  // WP Admin Label BG
	'#ccc',     // WP Admin Border (generic)
	'#ddd',     // WP Admin Border (generic)
	'#aaa',     // Intentional — only replace when standalone (handled in TOKEN_MAP)
	'#fff',     // Pure white — WP admin context
	'#333',     // Dark grey — WP admin context
	'#777',     // Medium grey — title placeholder
	'rgba(',    // RGBA values — skip entirely
	'transparent', // Keyword — skip
];

// ─── CLI ARGUMENT PARSING ──────────────────────────────────────────────────────

$args     = array_slice( $argv ?? [], 1 );
$dry_run  = in_array( '--dry-run', $args, true );
$verbose  = in_array( '--verbose', $args, true );
$file_arg = null;

foreach ( $args as $arg ) {
	if ( str_starts_with( $arg, '--file=' ) ) {
		$file_arg = substr( $arg, 7 );
	}
}

// ─── HELPERS ──────────────────────────────────────────────────────────────────

/**
 * Outputs a coloured terminal message.
 */
function output( string $message, string $colour = 'default' ): void {
	$colours = [
		'red'     => "\033[31m",
		'green'   => "\033[32m",
		'yellow'  => "\033[33m",
		'blue'    => "\033[34m",
		'cyan'    => "\033[36m",
		'bold'    => "\033[1m",
		'default' => "\033[0m",
	];
	$reset = "\033[0m";
	$code  = $colours[ $colour ] ?? $colours['default'];
	echo $code . $message . $reset . "\n";
}

/**
 * Strips inline CSS comments from a value for matching purposes.
 * e.g. "#1a1a2e; /* --better-seo-navy *\/" → "#1a1a2e;"
 */
function strip_inline_comment( string $value ): string {
	return trim( preg_replace( '/\/\*.*?\*\//s', '', $value ) );
}

/**
 * Returns whether a line contains a skip pattern that should not be replaced.
 */
function line_contains_skip_pattern( string $line ): bool {
	foreach ( SKIP_PATTERNS as $pattern ) {
		if ( str_contains( $line, $pattern ) ) {
			// Only skip if the skip pattern is the ONLY hex on this line
			// (i.e. don't skip a line that has both a skip pattern AND a replaceable hex)
			return true;
		}
	}
	return false;
}

/**
 * Processes a single CSS file and returns an array of replacements made.
 *
 * @return array{
 *   replacements: array<int, array{line: int, before: string, after: string, token: string}>,
 *   new_content: string,
 * }
 */
function process_file( string $filepath ): array {
	$content      = file_get_contents( $filepath );
	$lines        = explode( "\n", $content );
	$replacements = [];
	$new_lines    = [];

	foreach ( $lines as $line_num => $line ) {
		$new_line    = $line;
		$line_number = $line_num + 1;

		// Skip lines that are pure comments.
		$trimmed = trim( $line );
		if ( str_starts_with( $trimmed, '//' ) || str_starts_with( $trimmed, '*' ) || str_starts_with( $trimmed, '/*' ) ) {
			$new_lines[] = $line;
			continue;
		}

		// Skip lines that only contain WP admin or intentional hardcoded colors.
		// We check each token individually to allow mixed lines.
		foreach ( TOKEN_MAP as $hex => $var ) {
			// Case-insensitive hex match.
			if ( ! stripos( $new_line, $hex ) && stripos( $new_line, $hex ) !== 0 ) {
				continue;
			}

			// Check if this hex appears on the line.
			if ( ! preg_match( '/' . preg_quote( $hex, '/' ) . '/i', $new_line ) ) {
				continue;
			}

			// Skip if the line already uses a var() for this token.
			if ( str_contains( $new_line, $var ) ) {
				continue;
			}

			// Skip if the line contains a WP admin color that would conflict.
			$has_skip = false;
			foreach ( SKIP_PATTERNS as $skip ) {
				if ( $skip !== $hex && str_contains( $new_line, $skip ) ) {
					$has_skip = true;
					break;
				}
			}
			if ( $has_skip ) {
				continue;
			}

			// Build replacement: replace hex + optional inline comment with var().
			// Pattern: #hex followed by optional whitespace and /* comment */
			$pattern     = '/' . preg_quote( $hex, '/' ) . '(\s*\/\*[^*]*\*+(?:[^\/*][^*]*\*+)*\/)?/i';
			$replacement = $var;
			$replaced    = preg_replace( $pattern, $replacement, $new_line, 1, $count );

			if ( $count > 0 && $replaced !== $new_line ) {
				$replacements[] = [
					'line'   => $line_number,
					'before' => trim( $line ),
					'after'  => trim( $replaced ),
					'token'  => $var,
					'hex'    => $hex,
				];
				$new_line = $replaced;
			}
		}

		$new_lines[] = $new_line;
	}

	return [
		'replacements' => $replacements,
		'new_content'  => implode( "\n", $new_lines ),
	];
}

// ─── MAIN EXECUTION ────────────────────────────────────────────────────────────

output( '', 'default' );
output( '╔══════════════════════════════════════════════════════════╗', 'bold' );
output( '║     Better SEO — CSS Token Migration Tool                ║', 'bold' );
output( '╚══════════════════════════════════════════════════════════╝', 'bold' );
output( '', 'default' );

if ( $dry_run ) {
	output( '  MODE: DRY RUN — no files will be written', 'yellow' );
} else {
	output( '  MODE: LIVE — files will be updated', 'green' );
}

if ( $file_arg ) {
	output( "  FILE: {$file_arg} only", 'cyan' );
}

output( '', 'default' );
output( '──────────────────────────────────────────────────────────', 'default' );

// Determine which files to process.
$files_to_process = $file_arg ? [ $file_arg ] : CSS_FILES;

$total_files       = 0;
$total_replacements = 0;
$errors            = [];

foreach ( $files_to_process as $filename ) {
	$filepath = CSS_DIR . $filename;

	if ( ! file_exists( $filepath ) ) {
		output( "  ✗ SKIP  {$filename} — file not found", 'red' );
		$errors[] = $filename;
		continue;
	}

	$result       = process_file( $filepath );
	$replacements = $result['replacements'];
	$count        = count( $replacements );

	if ( $count === 0 ) {
		output( "  ✓ CLEAN {$filename} — no replacements needed", 'green' );
		continue;
	}

	$total_files++;
	$total_replacements += $count;

	output( "  ✎ FOUND {$filename} — {$count} replacement(s)", 'yellow' );

	if ( $verbose ) {
		foreach ( $replacements as $r ) {
			output( "      Line {$r['line']}: {$r['hex']} → {$r['token']}", 'cyan' );
			output( "        Before: {$r['before']}", 'default' );
			output( "        After:  {$r['after']}", 'green' );
		}
	} else {
		foreach ( $replacements as $r ) {
			output( "      Line {$r['line']}: {$r['hex']} → {$r['token']}", 'cyan' );
		}
	}

	if ( ! $dry_run ) {
		$bytes_written = file_put_contents( $filepath, $result['new_content'] );
		if ( $bytes_written === false ) {
			output( "      ✗ ERROR — could not write {$filename}", 'red' );
			$errors[] = $filename;
		} else {
			output( "      ✓ WRITTEN — {$filename} updated ({$bytes_written} bytes)", 'green' );
		}
	}
}

// ─── SUMMARY ──────────────────────────────────────────────────────────────────

output( '', 'default' );
output( '──────────────────────────────────────────────────────────', 'default' );
output( '  SUMMARY', 'bold' );
output( "  Files with changes:  {$total_files}", 'default' );
output( "  Total replacements:  {$total_replacements}", 'default' );

if ( ! empty( $errors ) ) {
	output( '  Errors:              ' . count( $errors ) . ' (' . implode( ', ', $errors ) . ')', 'red' );
}

if ( $dry_run ) {
	output( '', 'default' );
	output( '  ⚠  DRY RUN complete — no files were modified.', 'yellow' );
	output( '     Run without --dry-run to apply changes.', 'yellow' );
} elseif ( $total_replacements > 0 ) {
	output( '', 'default' );
	output( '  ✓  Migration complete — all files updated.', 'green' );
	output( '     Remember to:', 'default' );
	output( '       1. Remove :root block from le.css', 'default' );
	output( '       2. Enqueue tokens.css before all other CSS files', 'default' );
	output( '       3. Test the plugin in the WordPress admin', 'default' );
} else {
	output( '', 'default' );
	output( '  ✓  All files are already using CSS custom properties.', 'green' );
}

output( '', 'default' );

exit( empty( $errors ) ? 0 : 1 );