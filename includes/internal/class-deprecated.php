<?php
/**
 * Better SEO - Internal Deprecated
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Internal
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

namespace Better_SEO\Internal;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\{
	is_headless,
	normalize_generation_args,
	get_query_type_from_args,
	memo,
	umemo,
};

use Better_SEO\{
	Data,
	Helper,
	Helper\Query,
	Meta,
};

/**
 * Class Better_SEO\Internal\Deprecated
 *
 * Holds deprecated Better SEO methods and properties.
 * This class is intentionally empty for v1.0.0 — deprecated items
 * will be added here as the plugin evolves and methods are superseded.
 *
 * @since 1.0.0
 */
final class Deprecated {}