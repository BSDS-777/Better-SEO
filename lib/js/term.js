/**
 * Better SEO — Term Edit Screen Module
 *
 * Orchestrates all Better SEO UI behavior on the WordPress term edit screen
 * (add-tag / edit-tags). Initializes title, description, canonical URL,
 * social meta, and visibility (robots/noindex) listeners for the current term.
 *
 * Equivalent to post.js but for taxonomy term edit screens.
 *
 * Exposed as: window.BetterSeoTerm
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - L10n object:    BetterSeoTermL10n (registered in class-loader.php get_term_edit_scripts())
 *   - L10n keys:
 *       BetterSeoTermL10n.params.taxonomy          — current taxonomy slug
 *       BetterSeoTermL10n.params.termPrefix        — archive title prefix string
 *       BetterSeoTermL10n.params.id                — current term ID (fallback slug)
 *       BetterSeoTermL10n.params.parentTermSlugs   — pre-fetched parent slug chain
 *       BetterSeoTermL10n.params.additionsForcedDisabled — whether title additions are locked off
 *   - Meta field element IDs (set by inc/views/term/settings.php):
 *       better-seo-meta[doctitle]    — title input
 *       better-seo-meta[description] — description textarea
 *       better-seo-meta[canonical]   — canonical URL input
 *       better-seo-meta[noindex]     — robots noindex select
 *       better-seo-meta[title_no_blog_name] — title additions toggle checkbox
 *   - Data element IDs (set by class-input.php):
 *       better-seo-title-data_{titleId}       — title state data
 *       better-seo-description-data_{descId}  — description state data
 *       better-seo-canonical-data_{canonicalId} — canonical state data
 *       better-seo-social-data_{socialGroup}  — social meta state data
 *   - Social group key: 'better_seo_social_tt'
 *   - WordPress term edit form element IDs:
 *       #slug    — term slug input
 *       #name    — term name input
 *       #parent  — parent term select
 *   - Custom events consumed:
 *       'better-seo-onload' — fired by the Better SEO UI bootstrap
 *   - Dependencies:
 *       BetterSeoTermL10n           — localization data
 *       BetterSeo.escapeString()    — HTML entity escaping
 *       BetterSeo.stripTags()       — tag stripping for title
 *       BetterSeoTitle.*            — title module
 *       BetterSeoDescription.*      — description module
 *       BetterSeoCanonical.*        — canonical URL module
 *       BetterSeoSocial.*           — social meta module
 *       BetterSeoTermSlugs.*        — term parent slug cache/fetch service
 *       BetterSeoUtils.debounce()   — debounce helper
 */

'use strict';

/**
 * Term edit screen UI module.
 *
 * @namespace BetterSeoTerm
 */
window.BetterSeoTerm = ( function () {

	/**
	 * Localisation data passed from PHP via wp_localize_script().
	 *
	 * @type {{ params: Object }}
	 */
	const l10n = BetterSeoTermL10n;

	/**
	 * The current taxonomy slug, HTML-escaped for safe DOM use.
	 *
	 * @type {string}
	 */
	const taxonomy = BetterSeo.escapeString( l10n.params.taxonomy );

	// ─── FIELD ID CONSTANTS ────────────────────────────────────────────────────

	/** @type {string} Element ID of the meta title input. */
	const _titleId = 'better-seo-meta[doctitle]';

	/** @type {string} Element ID of the meta description textarea. */
	const _descId = 'better-seo-meta[description]';

	/** @type {string} Element ID of the canonical URL input. */
	const _canonicalId = 'better-seo-meta[canonical]';

	/** @type {string} Social meta group key for this term. */
	const _socialGroup = 'better_seo_social_tt';

	// ─── VISIBILITY LISTENERS ──────────────────────────────────────────────────

	/**
	 * Initialises canonical URL and robots noindex listeners for the term edit screen.
	 *
	 * Sets up the canonical URL state from PHP-provided data, registers slug/name/parent
	 * change listeners to update the canonical placeholder in real time, and wires the
	 * noindex select to toggle the canonical placeholder visibility.
	 *
	 * @return {void}
	 */
	function _initVisibilityListeners() {

		const noindexSelect  = document.getElementById( 'better-seo-meta[noindex]' );
		const canonicalInput = document.getElementById( 'better-seo-meta[canonical]' );

		/** Map of URL structure placeholder parts → resolved values. */
		const urlDataParts = new Map();

		// Prefixed with B to avoid potential future reserved word conflicts.
		const BNOINDEX = 0b10;

		let canonicalPhState = 0b00;

		BetterSeoCanonical.setInputElement( canonicalInput );

		const state = JSON.parse(
			document.getElementById( `better-seo-canonical-data_${_canonicalId}` )?.dataset.state || '0',
		);

		if ( state ) {
			BetterSeoCanonical.updateStateOf( _canonicalId, 'allowReferenceChange', ! state.refCanonicalLocked );
			BetterSeoCanonical.updateStateOf( _canonicalId, 'defaultCanonical',     state.defaultCanonical.trim() );
			BetterSeoCanonical.updateStateOf( _canonicalId, 'preferredScheme',      state.preferredScheme.trim() );
			BetterSeoCanonical.updateStateOf( _canonicalId, 'urlStructure',         state.urlStructure );
			// parentTermSlugs and isHierarchical are handled locally — BetterSeoCanonical does not use them.
		}

		BetterSeoCanonical.enqueueTriggerUnregisteredInput( _canonicalId );

		/**
		 * Pushes the current urlDataParts map and noindex state into the canonical module.
		 *
		 * @return {void}
		 */
		const updateCanonicalPlaceholder = () => {
			BetterSeoCanonical.updateStateOf(
				_canonicalId,
				'showUrlPlaceholder',
				! ( canonicalPhState & BNOINDEX ),
			);
			BetterSeoCanonical.updateStateOf(
				_canonicalId,
				'urlDataParts',
				Object.fromEntries( urlDataParts.entries() ),
			);
		};

		if ( BetterSeoCanonical.usingPermalinks && canonicalInput ) {
			const writeTaxonomy = BetterSeoCanonical.structIncludes( _canonicalId, `%${taxonomy}%` );

			let termSlug    = '';
			let termName    = '';
			let parentSlugs = [];

			if ( writeTaxonomy ) {
				BetterSeoTermSlugs.store( state.parentTermSlugs, taxonomy );
				// Pre-populate parent slugs from PHP data; the parent select may not be available yet.
				parentSlugs = state.parentTermSlugs.map( term => term.slug );
			}

			/**
			 * Rebuilds the taxonomy URL part from the current slug, name, and parent chain,
			 * then triggers a canonical placeholder update.
			 *
			 * @return {void}
			 */
			const updateCanonical = () => {
				if ( writeTaxonomy ) {
					let activeSlug = '';

					if ( termSlug.length ) {
						// WordPress trims slugs to the first 200 characters.
						activeSlug = BetterSeoCanonical.sanitizeSlug( termSlug.substring( 0, 200 ) );

						// WordPress ignores '0' as a slug value.
						if ( '0' === activeSlug ) {
							activeSlug = '';
						}
					}

					// Fall back to the term name if the slug field is empty.
					if ( ! activeSlug.length && termName.length ) {
						activeSlug = BetterSeoCanonical.sanitizeSlug( termName.substring( 0, 200 ) );
					}

					// Final fallback: use the term ID (WordPress behaviour for untitled terms).
					if ( ! activeSlug.length ) {
						activeSlug = l10n.params.id;
					}

					urlDataParts.set( `%${taxonomy}%`, [ ...parentSlugs, activeSlug ].join( '/' ) );
				}

				updateCanonicalPlaceholder();
			};

			/** @type {Function} Debounced canonical update at ~60 fps. */
			const queueUpdateCanonical = BetterSeoUtils.debounce( updateCanonical, 1000 / 60 );

			if ( writeTaxonomy ) {
				const termSlugInput = document.getElementById( 'slug' );
				const termNameInput = document.getElementById( 'name' );
				const parentIdInput = document.getElementById( 'parent' );

				/**
				 * Reads the current slug and name inputs and queues a canonical update.
				 *
				 * @return {void}
				 */
				const updateTermName = () => {
					termName = termNameInput?.value ?? '';
					termSlug = termSlugInput?.value ?? '';
					queueUpdateCanonical();
				};

				termSlugInput?.addEventListener( 'input', updateTermName );
				termNameInput?.addEventListener( 'input', updateTermName );
				updateTermName();

				if ( parentIdInput ) {
					/**
					 * Fetches the new parent slug chain when the parent select changes,
					 * then queues a canonical update.
					 *
					 * Debounced at 100 ms — high enough to prevent self-DoS on rapid changes,
					 * low enough to remain responsive.
					 *
					 * @type {Function}
					 */
					const updateParentSlug = BetterSeoUtils.debounce(
						async () => {
							parentSlugs = await BetterSeoTermSlugs.get( parentIdInput.value, taxonomy );
							queueUpdateCanonical();
						},
						100,
					);

					parentIdInput.addEventListener( 'change', updateParentSlug );
					updateParentSlug();
				}
			}

			queueUpdateCanonical();
		}

		/**
		 * Updates the canonical placeholder visibility based on the noindex select value.
		 *
		 * @param {string|number} value The current noindex select value (0 = default, -1 = index, 1 = noindex).
		 * @return {void}
		 */
		const setRobotsIndexingState = value => {
			let type = '';

			switch ( +value ) {
				case 0:
					// Default — use the PHP-provided default state for this term.
					type = noindexSelect.dataset.defaultUnprotected;
					break;
				case -1:
					type = 'index';
					break;
				case 1:
					type = 'noindex';
					break;
			}

			if ( 'noindex' === type ) {
				canonicalPhState |= BNOINDEX;
			} else {
				canonicalPhState &= ~BNOINDEX;
			}

			updateCanonicalPlaceholder();
		};

		if ( noindexSelect ) {
			noindexSelect.addEventListener( 'change', event => setRobotsIndexingState( event.target.value ) );
			setRobotsIndexingState( noindexSelect.value );
		}
	}

	// ─── TITLE LISTENERS ───────────────────────────────────────────────────────

	/**
	 * Initialises the meta title input and its state for the term edit screen.
	 *
	 * Registers the title input with BetterSeoTitle, applies PHP-provided state,
	 * wires the title additions toggle, and updates the default title when the
	 * term name input changes.
	 *
	 * @return {void}
	 */
	function _initTitleListeners() {

		const titleInput = document.getElementById( _titleId );

		if ( ! titleInput ) {
			return;
		}

		BetterSeoTitle.setInputElement( titleInput );

		const state = JSON.parse(
			document.getElementById( `better-seo-title-data_${_titleId}` )?.dataset.state || '0',
		);

		if ( state ) {
			BetterSeoTitle.updateStateOf( _titleId, 'allowReferenceChange', ! state.refTitleLocked );
			BetterSeoTitle.updateStateOf( _titleId, 'defaultTitle',         state.defaultTitle.trim() );
			BetterSeoTitle.updateStateOf( _titleId, 'addAdditions',         state.addAdditions );
			BetterSeoTitle.updateStateOf( _titleId, 'useSocialTagline',     !! ( state.useSocialTagline ?? false ) );
			BetterSeoTitle.updateStateOf( _titleId, 'additionValue',        state.additionValue.trim() );
			BetterSeoTitle.updateStateOf( _titleId, 'additionPlacement',    state.additionPlacement );
		}

		// The term prefix is prepended to the default title but is not exposed to BetterSeoTitle directly.
		const termPrefix = BetterSeo.escapeString( l10n.params.termPrefix );

		/**
		 * Updates the title additions state when the "remove blog name" checkbox changes.
		 *
		 * @param {Event} event The checkbox change event.
		 * @return {void}
		 */
		const updateTitleAdditions = event => {
			let addAdditions = ! event.target.checked;

			if ( l10n.params.additionsForcedDisabled ) {
				addAdditions = false;
			}

			BetterSeoTitle.updateStateOf( _titleId, 'addAdditions', addAdditions );
		};

		const blogNameTrigger = document.getElementById( 'better-seo-meta[title_no_blog_name]' );

		if ( blogNameTrigger ) {
			blogNameTrigger.addEventListener( 'change', updateTitleAdditions );
			blogNameTrigger.dispatchEvent( new Event( 'change' ) );
		}

		/**
		 * Rebuilds the default title from the term name input value and the taxonomy prefix.
		 *
		 * Respects RTL layout by reversing the prefix/title order.
		 *
		 * @param {string|undefined} val The raw term name value.
		 * @return {void}
		 */
		const updateDefaultTitle = val => {
			val = val?.trim();

			let title = BetterSeoTitle.stripTitleTags
				? BetterSeo.stripTags( val )
				: val;

			title ||= BetterSeoTitle.untitledTitle;

			const defaultTitle = window.isRtl
				? `${title} ${termPrefix}`
				: `${termPrefix} ${title}`;

			BetterSeoTitle.updateStateOf( _titleId, 'defaultTitle', defaultTitle );
		};

		document.querySelector( '#edittag #name' )
			?.addEventListener( 'input', event => updateDefaultTitle( event.target.value ) );

		BetterSeoTitle.enqueueUnregisteredInputTrigger( _titleId );
	}

	// ─── DESCRIPTION LISTENERS ─────────────────────────────────────────────────

	/**
	 * Initialises the meta description textarea and its state for the term edit screen.
	 *
	 * @return {void}
	 */
	function _initDescriptionListeners() {

		const descInput = document.getElementById( _descId );

		if ( ! descInput ) {
			return;
		}

		BetterSeoDescription.setInputElement( descInput );

		const state = JSON.parse(
			document.getElementById( `better-seo-description-data_${_descId}` )?.dataset.state || '0',
		);

		if ( state ) {
			BetterSeoDescription.updateStateOf( _descId, 'defaultDescription', state.defaultDescription.trim() );
		}

		BetterSeoDescription.enqueueUnregisteredInputTrigger( _descId );
	}

	// ─── SOCIAL LISTENERS ──────────────────────────────────────────────────────

	/**
	 * Initialises the social meta (Open Graph / Twitter Card) inputs for the term edit screen.
	 *
	 * @return {void}
	 */
	function _initSocialListeners() {

		BetterSeoSocial.setInputInstance( _socialGroup, _titleId, _descId );

		const groupData = JSON.parse(
			document.getElementById( `better-seo-social-data_${_socialGroup}` )?.dataset.settings || '0',
		);

		if ( ! groupData ) {
			return;
		}

		BetterSeoSocial.updateStateOf( _socialGroup, 'addAdditions', groupData.og.state.addAdditions );

		BetterSeoSocial.updateStateOf(
			_socialGroup,
			'defaults',
			{
				ogTitle: groupData.og.state.defaultTitle,
				twTitle: groupData.tw.state.defaultTitle,
				ogDesc:  groupData.og.state.defaultDesc,
				twDesc:  groupData.tw.state.defaultDesc,
			},
		);
	}

	// ─── INITIALISATION ────────────────────────────────────────────────────────

	/**
	 * Runs all term edit screen initialisers.
	 *
	 * Each initialiser is wrapped in a try/catch so a single failure does not
	 * prevent the remaining sections from initialising.
	 *
	 * @return {void}
	 */
	function _loadSettings() {
		for ( const fn of [
			_initVisibilityListeners,
			_initTitleListeners,
			_initDescriptionListeners,
			_initSocialListeners,
		] ) {
			try {
				fn();
			} catch ( error ) {
				console.error( `BetterSeoTerm: Error in ${fn.name}:`, error );
			}
		}
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		/**
		 * Registers the 'better-seo-onload' listener that triggers all initialisers.
		 * Called automatically on script load.
		 *
		 * @return {void}
		 */
		load: () => {
			document.body.addEventListener( 'better-seo-onload', _loadSettings );
		},
		l10n,
		taxonomy,
	};

}() );

// Auto-initialise — registers the 'better-seo-onload' listener.
window.BetterSeoTerm.load();