/**
 * Better SEO — Character & Pixel Counter Module
 *
 * Provides real-time character counting and pixel-width measurement for
 * Better SEO meta title and description fields, with visual status indicators
 * (bad/okay/good/unknown) based on configured guidelines.
 *
 * Exposed as: window.BetterSeoC
 *
 * Counter types (cycled on click):
 *   0 — Hidden (no counter displayed)
 *   1 — Character count only (default)
 *   2 — Status label only
 *   3 — Character count + status label
 *
 * Usage:
 *   BetterSeoC.updateCharacterCounter( test );
 *   BetterSeoC.updatePixelCounter( test );
 *   BetterSeoC.triggerCounterUpdate();
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - L10n object:    BetterSeoCL10n (must be registered in get_counter_scripts() in class-loader.php)
 *   - L10n keys:      BetterSeoCL10n.counterType, .guidelines, .i18n.guidelines, .i18n.pixelsUsed
 *   - AJAX action:    better_seo_update_counter (wp_ajax_better_seo_update_counter in class-ajax.php:94)
 *   - Nonce:          BetterSeoL10n.nonces.edit_posts
 *   - POST data:      { nonce, val: counterType }
 *   - Custom events:
 *       'better-seo-onload'           — fired by better-seo.js when admin UI is ready
 *       'better-seo-counter-updated'  — dispatched by this module after counter type changes
 *
 * @note  Missing PHP: get_counter_scripts() in class-loader.php must register BetterSeoCL10n
 *        with counterType, guidelines, and i18n data. See class-guidelines.php for data source.
 */

'use strict';

/**
 * Character and pixel counter module.
 *
 * @namespace BetterSeoC
 */
window.BetterSeoC = ( function () {

	/**
	 * Localisation data passed from PHP via wp_localize_script().
	 * Must include: counterType, guidelines, i18n.guidelines, i18n.pixelsUsed
	 *
	 * @type {Object}
	 */
	const l10n = BetterSeoCL10n;

	/**
	 * Current counter display type (0–3).
	 * Persisted per-user via AJAX to better_seo_update_counter.
	 *
	 * @type {number}
	 */
	let counterType = +( l10n.counterType || 0 );

	/**
	 * CSS class names for each counter display type.
	 * Applied to the counter element to control its visual style.
	 *
	 * @type {Object.<number, string>}
	 */
	const counterClasses = {
		0: 'better-seo-counter-zero',
		1: 'better-seo-counter-one',
		2: 'better-seo-counter-two',
		3: 'better-seo-counter-three',
	};

	// ─── PUBLIC STATE API ──────────────────────────────────────────────────────

	/**
	 * Returns the current counter display type (0–3).
	 *
	 * @return {number}
	 */
	function getCounterType() {
		return counterType;
	}

	// ─── CHARACTER COUNTER ─────────────────────────────────────────────────────

	/**
	 * Updates the character counter element for a given field test.
	 *
	 * Evaluates the text length against the configured guidelines and applies
	 * the appropriate status class (bad/okay/good/unknown) and label.
	 *
	 * @param {{ e: Element, text: string, field: string, type: string }} test
	 * @return {void}
	 */
	function updateCharacterCounter( test ) {

		const el         = test.e;
		const text       = BetterSeo.decodeEntities( test.text );
		const guidelines = l10n.guidelines[ test.field ][ test.type ].chars;

		const testLength = BetterSeo.getStringLength( text );
		let newClass     = '';
		let exclaimer    = '';

		const classes = {
			bad:     'better-seo-count-bad',
			okay:    'better-seo-count-okay',
			good:    'better-seo-count-good',
			unknown: 'better-seo-count-unknown',
		};

		if ( ! testLength ) {
			newClass  = classes.unknown;
			exclaimer = l10n.i18n.guidelines.short.empty;
		} else if ( testLength < guidelines.lower ) {
			newClass  = classes.bad;
			exclaimer = l10n.i18n.guidelines.short.farTooShort;
		} else if ( testLength < guidelines.goodLower ) {
			newClass  = classes.okay;
			exclaimer = l10n.i18n.guidelines.short.tooShort;
		} else if ( testLength > guidelines.upper ) {
			newClass  = classes.bad;
			exclaimer = l10n.i18n.guidelines.short.farTooLong;
		} else if ( testLength > guidelines.goodUpper ) {
			newClass  = classes.okay;
			exclaimer = l10n.i18n.guidelines.short.tooLong;
		} else {
			// Within the good range (between goodLower and goodUpper).
			newClass  = classes.good;
			exclaimer = l10n.i18n.guidelines.short.good;
		}

		// Format the exclaimer based on the current counter display type.
		switch ( counterType ) {
			case 3:
				// Type 3: character count + status label
				exclaimer = `${testLength} &ndash; ${exclaimer}`;
				break;
			case 2:
				// Type 2: status label only — exclaimer already set above
				break;
			case 1:
			default:
				// Type 1 (default): character count only
				exclaimer = testLength;
				break;
		}

		el.innerHTML = exclaimer;

		el.classList.remove( ...Object.values( classes ), ...Object.values( counterClasses ) );
		el.classList.add( newClass, counterClasses[ counterType ] );
	}

	// ─── PIXEL COUNTER ─────────────────────────────────────────────────────────

	/**
	 * Updates the pixel-width counter bar for a given field test.
	 *
	 * Measures the rendered pixel width of the text using a hidden shadow element,
	 * then updates the progress bar width and status class accordingly.
	 *
	 * @param {{ e: Element, text: string, field: string, type: string }} test
	 * @return {void}
	 */
	function updatePixelCounter( test ) {

		const wrap = test.e.parentElement;

		if ( ! wrap ) {
			return;
		}

		const bar    = wrap.querySelector( '.better-seo-pixel-counter-bar' );
		const shadow = wrap.querySelector( '.better-seo-pixel-counter-shadow' );

		if ( ! bar || ! shadow ) {
			return;
		}

		shadow.innerHTML = BetterSeo.escapeString( BetterSeo.decodeEntities( test.text ) );

		const testWidth  = shadow.offsetWidth;
		const guidelines = l10n.guidelines[ test.field ][ test.type ].pixels;

		let newClass        = '';
		let newWidth        = '';
		let guidelineHelper = '';

		const classes = {
			bad:     'better-seo-pixel-counter-bad',
			okay:    'better-seo-pixel-counter-okay',
			good:    'better-seo-pixel-counter-good',
			unknown: 'better-seo-pixel-counter-unknown',
		};

		newWidth = ( testWidth / guidelines.goodUpper * 100 ) + '%';

		if ( ! testWidth ) {
			// No text — show full bar as "unknown" (100% unknown, not 0%)
			newClass        = classes.unknown;
			newWidth        = '100%';
			guidelineHelper = l10n.i18n.guidelines.long.empty;
		} else if ( testWidth < guidelines.lower ) {
			newClass        = classes.bad;
			guidelineHelper = l10n.i18n.guidelines.long.farTooShort;
		} else if ( testWidth < guidelines.goodLower ) {
			newClass        = classes.okay;
			guidelineHelper = l10n.i18n.guidelines.long.tooShort;
		} else if ( testWidth > guidelines.upper ) {
			// Beyond the hard upper limit — compress the bar to show overflow visually
			newWidth        = ( guidelines.upper / ( testWidth + ( ( testWidth - guidelines.upper ) * 2 / 3 ) ) * 100 ) + '%';
			newClass        = classes.bad;
			guidelineHelper = l10n.i18n.guidelines.long.farTooLong;
		} else if ( testWidth > guidelines.goodUpper ) {
			// Within upper limit but past the good upper — cap bar at 100%
			newClass        = classes.okay;
			guidelineHelper = l10n.i18n.guidelines.long.tooLong;
			newWidth        = '100%';
		} else {
			// Within the good range
			newClass        = classes.good;
			guidelineHelper = l10n.i18n.guidelines.long.good;
		}

		let label = l10n.i18n.pixelsUsed
			.replace( /%1\$d/g, testWidth )
			.replace( /%2\$d/g, guidelines.goodUpper );

		label += `<br>${guidelineHelper}`;

		bar.classList.remove( ...Object.values( classes ) );
		bar.classList.add( newClass );
		bar.querySelector( '.better-seo-pixel-counter-fluid' ).style.width = newWidth;

		bar.dataset.desc = label;
		bar.setAttribute( 'aria-label', BetterSeo.escapeString( label.replace( /(<([^>]+)?>?)/ig, ' ' ) ) );

		BetterSeoTT.triggerUpdate( bar );
	}

	// ─── COUNTER TYPE MANAGEMENT ───────────────────────────────────────────────

	/**
	 * Dispatches the 'better-seo-counter-updated' event on the window.
	 * Consumed by title.js, description.js, and other counter-aware modules
	 * to re-render their counter displays with the new counter type.
	 *
	 * @return {void}
	 */
	function triggerCounterUpdate() {
		window.dispatchEvent( new CustomEvent( 'better-seo-counter-updated' ) );
	}

	/**
	 * Increments the counter type (0→1→2→3→0) and triggers a counter update.
	 *
	 * @param {boolean} countUp Whether to increment the counter type.
	 * @return {void}
	 */
	function updateCounterClasses( countUp ) {

		if ( countUp ) {
			++counterType;
		}

		if ( counterType > 3 ) {
			counterType = 0;
		}

		triggerCounterUpdate();
	}

	// ─── AJAX COUNTER PERSISTENCE ──────────────────────────────────────────────

	/**
	 * Handles a counter click — increments the counter type, updates the UI,
	 * and persists the new type to the server via AJAX.
	 *
	 * AJAX action: better_seo_update_counter (wp_ajax_better_seo_update_counter)
	 * Nonce:       BetterSeoL10n.nonces.edit_posts
	 * POST data:   { nonce, val: counterType }
	 *
	 * @return {void}
	 */
	function _counterUpdate() {

		updateCounterClasses( true );

		const target = '.better-seo-counter-wrap .better-seo-ajax';

		BetterSeo.resetAjaxLoader( target );
		BetterSeo.setAjaxLoader( target );

		wp.ajax.post(
			'better_seo_update_counter',
			{
				nonce: BetterSeoL10n.nonces.edit_posts,
				val:   counterType,
			},
		).done( () => {
			BetterSeo.unsetAjaxLoader( target, true );
		} ).fail( () => {
			BetterSeo.unsetAjaxLoader( target, false );
		} );
	}

	// ─── LISTENER MANAGEMENT ──────────────────────────────────────────────────

	/**
	 * Attaches click listeners to all counter elements.
	 * Called on init and after dynamic content changes.
	 *
	 * @return {void}
	 */
	function resetCounterListener() {
		document.querySelectorAll( '.better-seo-counter' ).forEach(
			el => el.addEventListener( 'click', _counterUpdate ),
		);
	}

	// ─── INITIALISATION ────────────────────────────────────────────────────────

	/**
	 * Initialises the counter module on any edit screen.
	 *
	 * @return {void}
	 */
	function _initCounters() {
		resetCounterListener();
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return Object.assign(
		{
			/**
			 * Attaches the counter module to the 'better-seo-onload' event.
			 * Called automatically on script load.
			 *
			 * @return {void}
			 */
			load: () => {
				document.body.addEventListener( 'better-seo-onload', _initCounters );
			},
		},
		{
			updatePixelCounter,
			updateCharacterCounter,
			triggerCounterUpdate,
			resetCounterListener,
			getCounterType,
		},
		{
			counterClasses,
			l10n,
		},
	);

}() );

// Auto-initialise — registers the 'better-seo-onload' listener immediately.
window.BetterSeoC.load();