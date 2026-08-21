<?php
/**
 * Better SEO - Pool (Facade)
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Classes\Facade
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

\defined( 'BETTER_SEO_PRESENT' ) or exit;

use Better_SEO\Traits\Internal\Static_Deprecator;

/**
 * Class Better_SEO\Pool
 *
 * Holds a pool of proxied composite objects to support the Better SEO facade.
 *
 * Objects are decorated with Static_Deprecator, allowing methods and properties
 * to be deprecated quickly without breaking external API calls. Each pool entry
 * is lazily instantiated on first access and cached for subsequent calls.
 *
 * @NOTE: Pool methods MUST BE CALLED in a NON-STATIC manner.
 *        Do NOT use   better_seo()::admin()::layout()::make_single_select_form();
 *        Instead, use better_seo()->admin()->layout()->make_single_select_form();
 *        Failing to do so will break deprecation handling.
 *
 * @NOTE: All static:: calls within this class are intentional, to allow
 *        overrides in deprecator subclasses.
 *
 * @since 1.0.0
 * @see   Better_SEO\Load
 * @see   Better_SEO\Legacy_API
 * @link  https://en.wikipedia.org/wiki/Object_pool_pattern
 *
 * Access these methods via better_seo() or Better_SEO().
 */
class Pool extends Legacy_API {

	/**
	 * The class store (object pool).
	 *
	 * Used in favor of memo() because a chain would become expensive.
	 *
	 * @since 1.0.0
	 * @var   array<string, object>
	 */
	private static array $pool = [];

	// phpcs:disable Squiz.Commenting.VariableComment.Missing -- see trait Static_Deprecator.

	// =========================================================================
	// ADMIN
	// =========================================================================

	/**
	 * Returns a pool of Admin classes as an anonymous object with deprecation capabilities.
	 *
	 * @since 1.0.0
	 * @api   Not used internally.
	 *
	 * @return object Anonymous class instance with admin subpools.
	 */
	public static function admin(): object {
		return static::$pool['admin'] ??= new class {
			use Static_Deprecator;

			private string $colloquial_handle     = 'better_seo()->admin()';
			private array  $deprecated_methods    = [];
			private array  $deprecated_properties = [];

			/**
			 * Returns the Admin Layout subpool.
			 *
			 * @since 1.0.0
			 * @return object Anonymous class instance with layout subpools.
			 */
			public static function layout(): object {
				return static::$subpool['layout'] ??= new class {
					use Static_Deprecator;

					private string $colloquial_handle     = 'better_seo()->admin()->layout()';
					private array  $deprecated_methods    = [];
					private array  $deprecated_properties = [];

					/**
					 * Returns the Admin Settings Layout Form instance.
					 *
					 * @since 1.0.0
					 * @return Admin\Settings\Layout\Form
					 */
					public static function form(): Admin\Settings\Layout\Form {
						return static::$subpool['form'] ??= new class extends Admin\Settings\Layout\Form {
							use Static_Deprecator;

							private string $colloquial_handle     = 'better_seo()->admin()->layout()->form()';
							private array  $deprecated_methods    = [];
							private array  $deprecated_properties = [];
						};
					}

					/**
					 * Returns the Admin Settings Layout HTML instance.
					 *
					 * @since 1.0.0
					 * @return Admin\Settings\Layout\HTML
					 */
					public static function html(): Admin\Settings\Layout\HTML {
						return static::$subpool['html'] ??= new class extends Admin\Settings\Layout\HTML {
							use Static_Deprecator;

							private string $colloquial_handle     = 'better_seo()->admin()->layout()->html()';
							private array  $deprecated_methods    = [];
							private array  $deprecated_properties = [];
						};
					}
				};
			}

			/**
			 * Returns the Admin Menu instance.
			 *
			 * @since 1.0.0
			 * @return Admin\Menu
			 */
			public static function menu(): Admin\Menu {
				return static::$subpool['menu'] ??= new class extends Admin\Menu {
					use Static_Deprecator;

					private string $colloquial_handle     = 'better_seo()->admin()->menu()';
					private array  $deprecated_methods    = [];
					private array  $deprecated_properties = [];
				};
			}

			/**
			 * Returns the Admin Notice instance with its own subpools.
			 *
			 * @since 1.0.0
			 * @return Admin\Notice
			 */
			public static function notice(): Admin\Notice {
				return static::$subpool['notice'] ??= new class extends Admin\Notice {
					use Static_Deprecator;

					private string $colloquial_handle     = 'better_seo()->admin()->notice()';
					private array  $deprecated_methods    = [];
					private array  $deprecated_properties = [];

					/**
					 * Returns the Admin Notice Persistent instance.
					 *
					 * @since 1.0.0
					 * @return Admin\Notice\Persistent
					 */
					public static function persistent(): Admin\Notice\Persistent {
						return static::$subpool['persistent'] ??= new class extends Admin\Notice\Persistent {
							use Static_Deprecator;

							private string $colloquial_handle     = 'better_seo()->admin()->notice()->persistent()';
							private array  $deprecated_methods    = [];
							private array  $deprecated_properties = [];
						};
					}
				};
			}

			/**
			 * Returns the Admin Utils instance.
			 *
			 * @since 1.0.0
			 * @return Admin\Utils
			 */
			public static function utils(): Admin\Utils {
				return static::$subpool['utils'] ??= new class extends Admin\Utils {
					use Static_Deprecator;

					private string $colloquial_handle     = 'better_seo()->admin()->utils()';
					private array  $deprecated_methods    = [];
					private array  $deprecated_properties = [];
				};
			}
		};
	}

	// =========================================================================
	// BREADCRUMBS
	// =========================================================================

	/**
	 * Returns the Breadcrumbs API instance with deprecation capabilities.
	 *
	 * @since 1.0.0
	 * @api   Not used internally.
	 *
	 * @return Meta\Breadcrumbs
	 */
	public static function breadcrumbs(): Meta\Breadcrumbs {
		return static::$pool['breadcrumbs'] ??= new class extends Meta\Breadcrumbs {
			use Static_Deprecator;

			private string $colloquial_handle     = 'better_seo()->breadcrumbs()';
			private array  $deprecated_methods    = [];
			private array  $deprecated_properties = [];
		};
	}

	// =========================================================================
	// DATA
	// =========================================================================

	/**
	 * Returns a pool of Data classes as an anonymous object with deprecation capabilities.
	 *
	 * @since 1.0.0
	 * @api   Not used internally.
	 *
	 * @return object Anonymous class instance with data subpools.
	 */
	public static function data(): object {
		return static::$pool['data'] ??= new class {
			use Static_Deprecator;

			private string $colloquial_handle     = 'better_seo()->data()';
			private array  $deprecated_methods    = [];
			private array  $deprecated_properties = [];

			/**
			 * Returns the Data Blog instance.
			 *
			 * @since 1.0.0
			 * @return Data\Blog
			 */
			public static function blog(): Data\Blog {
				return static::$subpool['blog'] ??= new class extends Data\Blog {
					use Static_Deprecator;

					private string $colloquial_handle     = 'better_seo()->data()->blog()';
					private array  $deprecated_methods    = [];
					private array  $deprecated_properties = [];
				};
			}

			/**
			 * Returns the Data Plugin instance with its own subpools.
			 *
			 * @since 1.0.0
			 * @return Data\Plugin
			 */
			public static function plugin(): Data\Plugin {
				return static::$subpool['plugin'] ??= new class extends Data\Plugin {
					use Static_Deprecator;

					private string $colloquial_handle     = 'better_seo()->data()->plugin()';
					private array  $deprecated_methods    = [];
					private array  $deprecated_properties = [];

					/**
					 * Returns the Data Plugin Filter instance.
					 *
					 * @since 1.0.0
					 * @return Data\Plugin\Filter
					 */
					public static function filter(): Data\Plugin\Filter {
						return static::$subpool['filter'] ??= new class extends Data\Plugin\Filter {
							use Static_Deprecator;

							private string $colloquial_handle     = 'better_seo()->data()->plugin()->filter()';
							private array  $deprecated_methods    = [];
							private array  $deprecated_properties = [];
						};
					}

					/**
					 * Returns the Data Plugin Helper instance.
					 *
					 * @since 1.0.0
					 * @return Data\Plugin\Helper
					 */
					public static function helper(): Data\Plugin\Helper {
						return static::$subpool['helper'] ??= new class extends Data\Plugin\Helper {
							use Static_Deprecator;

							private string $colloquial_handle     = 'better_seo()->data()->plugin()->helper()';
							private array  $deprecated_methods    = [];
							private array  $deprecated_properties = [];
						};
					}

					/**
					 * Returns the Data Plugin Post instance.
					 *
					 * @since 1.0.0
					 * @return Data\Plugin\Post
					 */
					public static function post(): Data\Plugin\Post {
						return static::$subpool['post'] ??= new class extends Data\Plugin\Post {
							use Static_Deprecator;

							private string $colloquial_handle     = 'better_seo()->data()->plugin()->post()';
							private array  $deprecated_methods    = [];
							private array  $deprecated_properties = [];
						};
					}

					/**
					 * Returns the Data Plugin PTA (Post Type Archive) instance.
					 *
					 * @since 1.0.0
					 * @return Data\Plugin\PTA
					 */
					public static function pta(): Data\Plugin\PTA {
						return static::$subpool['pta'] ??= new class extends Data\Plugin\PTA {
							use Static_Deprecator;

							private string $colloquial_handle     = 'better_seo()->data()->plugin()->pta()';
							private array  $deprecated_methods    = [];
							private array  $deprecated_properties = [];
						};
					}

					/**
					 * Returns the Data Plugin Setup instance.
					 *
					 * @since 1.0.0
					 * @return Data\Plugin\Setup
					 */
					public static function setup(): Data\Plugin\Setup {
						return static::$subpool['setup'] ??= new class extends Data\Plugin\Setup {
							use Static_Deprecator;

							private string $colloquial_handle     = 'better_seo()->data()->plugin()->setup()';
							private array  $deprecated_methods    = [];
							private array  $deprecated_properties = [];
						};
					}

					/**
					 * Returns the Data Plugin Term instance.
					 *
					 * @since 1.0.0
					 * @return Data\Plugin\Term
					 */
					public static function term(): Data\Plugin\Term {
						return static::$subpool['term'] ??= new class extends Data\Plugin\Term {
							use Static_Deprecator;

							private string $colloquial_handle     = 'better_seo()->data()->plugin()->term()';
							private array  $deprecated_methods    = [];
							private array  $deprecated_properties = [];
						};
					}

					/**
					 * Returns the Data Plugin User instance.
					 *
					 * @since 1.0.0
					 * @return Data\Plugin\User
					 */
					public static function user(): Data\Plugin\User {
						return static::$subpool['user'] ??= new class extends Data\Plugin\User {
							use Static_Deprecator;

							private string $colloquial_handle     = 'better_seo()->data()->plugin()->user()';
							private array  $deprecated_methods    = [];
							private array  $deprecated_properties = [];
						};
					}
				};
			}

			/**
			 * Returns the Data Post instance.
			 *
			 * @since 1.0.0
			 * @return Data\Post
			 */
			public static function post(): Data\Post {
				return static::$subpool['post'] ??= new class extends Data\Post {
					use Static_Deprecator;

					private string $colloquial_handle     = 'better_seo()->data()->post()';
					private array  $deprecated_methods    = [];
					private array  $deprecated_properties = [];
				};
			}

			/**
			 * Returns the Data Term instance.
			 *
			 * @since 1.0.0
			 * @return Data\Term
			 */
			public static function term(): Data\Term {
				return static::$subpool['term'] ??= new class extends Data\Term {
					use Static_Deprecator;

					private string $colloquial_handle     = 'better_seo()->data()->term()';
					private array  $deprecated_methods    = [];
					private array  $deprecated_properties = [];
				};
			}

			/**
			 * Returns the Data User instance.
			 *
			 * @since 1.0.0
			 * @return Data\User
			 */
			public static function user(): Data\User {
				return static::$subpool['user'] ??= new class extends Data\User {
					use Static_Deprecator;

					private string $colloquial_handle     = 'better_seo()->data()->user()';
					private array  $deprecated_methods    = [];
					private array  $deprecated_properties = [];
				};
			}
		};
	}

	// =========================================================================
	// DESCRIPTION
	// =========================================================================

	/**
	 * Returns the Description API instance with deprecation capabilities.
	 *
	 * @since 1.0.0
	 * @api   Not used internally.
	 *
	 * @return Meta\Description
	 */
	public static function description(): Meta\Description {
		return static::$pool['description'] ??= new class extends Meta\Description {
			use Static_Deprecator;

			private string $colloquial_handle     = 'better_seo()->description()';
			private array  $deprecated_methods    = [];
			private array  $deprecated_properties = [];
		};
	}

	// =========================================================================
	// META
	// =========================================================================

	/**
	 * Returns the Image API instance with deprecation capabilities.
	 *
	 * @since 1.0.0
	 * @api   Not used internally.
	 *
	 * @return Meta\Image
	 */
	public static function image(): Meta\Image {
		return static::$pool['image'] ??= new class extends Meta\Image {
			use Static_Deprecator;

			private string $colloquial_handle     = 'better_seo()->image()';
			private array  $deprecated_methods    = [];
			private array  $deprecated_properties = [];
		};
	}

	/**
	 * Returns the Title API instance with deprecation capabilities.
	 *
	 * @since 1.0.0
	 * @api   Not used internally.
	 *
	 * @return Meta\Title
	 */
	public static function title(): Meta\Title {
		return static::$pool['title'] ??= new class extends Meta\Title {
			use Static_Deprecator;

			private string $colloquial_handle     = 'better_seo()->title()';
			private array  $deprecated_methods    = [];
			private array  $deprecated_properties = [];
		};
	}

	/**
	 * Returns the URI API instance with deprecation capabilities.
	 *
	 * @since 1.0.0
	 * @api   Not used internally.
	 *
	 * @return Meta\URI
	 */
	public static function uri(): Meta\URI {
		return static::$pool['uri'] ??= new class extends Meta\URI {
			use Static_Deprecator;

			private string $colloquial_handle     = 'better_seo()->uri()';
			private array  $deprecated_methods    = [];
			private array  $deprecated_properties = [];
		};
	}

	/**
	 * Returns the Robots API instance with deprecation capabilities.
	 *
	 * @since 1.0.0
	 * @api   Not used internally.
	 *
	 * @return Meta\Robots
	 */
	public static function robots(): Meta\Robots {
		return static::$pool['robots'] ??= new class extends Meta\Robots {
			use Static_Deprecator;

			private string $colloquial_handle     = 'better_seo()->robots()';
			private array  $deprecated_methods    = [];
			private array  $deprecated_properties = [];
		};
	}

	/**
	 * Returns the Schema API instance with deprecation capabilities.
	 *
	 * @since 1.0.0
	 * @api   Not used internally.
	 *
	 * @return Meta\Schema
	 */
	public static function schema(): Meta\Schema {
		return static::$pool['schema'] ??= new class extends Meta\Schema {
			use Static_Deprecator;

			private string $colloquial_handle     = 'better_seo()->schema()';
			private array  $deprecated_methods    = [];
			private array  $deprecated_properties = [];
		};
	}

	// =========================================================================
	// SITEMAP
	// =========================================================================

	/**
	 * Returns the Sitemap Registry instance with deprecation capabilities.
	 *
	 * @since 1.0.0
	 * @api   Not used internally.
	 *
	 * @return Sitemap\Registry
	 */
	public static function sitemap(): Sitemap\Registry {
		return static::$pool['sitemap'] ??= new class extends Sitemap\Registry {
			use Static_Deprecator;

			private string $colloquial_handle     = 'better_seo()->sitemap()';
			private array  $deprecated_methods    = [];
			private array  $deprecated_properties = [];
		};
	}
}