/**
 * Better SEO — Gutenberg Block Editor Compatibility Module
 *
 * Subscribes to the WordPress block editor (Gutenberg) data store to detect
 * post data changes, save events, and sidebar state changes, then dispatches
 * corresponding Better SEO custom events for other modules to consume.
 *
 * Exposed as: window.BetterSeoGBC
 *
 * Usage:
 *   BetterSeoGBC.load();           // Initialise (called automatically)
 *   BetterSeoGBC.triggerUpdate( 'title' );
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - No L10n object — GBC module uses no PHP-side localisation
 *   - No AJAX actions — all processing is client-side via wp.data
 *   - Custom events dispatched:
 *       'better-seo-updated-block-editor'           — any post data change (detail: {type, value, postData})
 *       'better-seo-updated-block-editor-{type}'    — specific field change (detail: {type, value})
 *       'better-seo-gutenberg-onpreview'            — post preview triggered
 *       'better-seo-gutenberg-onautosave'           — post autosaved
 *       'better-seo-gutenberg-onsave'               — post saved (manual)
 *       'better-seo-gutenberg-onsave-completed'     — save fully completed
 *       'better-seo-gutenberg-saved-document'       — any save type completed (detail: {savedType})
 *       'better-seo-gutenberg-sidebar-opened'       — editor sidebar opened
 *       'better-seo-gutenberg-sidebar-closed'       — editor sidebar closed
 *       'better-seo-subscribed-to-gutenberg'        — subscription initialised
 *   - Custom events consumed:
 *       'better-seo-onload'                         — fired by better-seo.js when UI is ready
 *   - Deprecated jQuery events (for backward compat):
 *       'better-seo-updated-gutenberg-{type}'       — use 'better-seo-updated-block-editor-{type}' instead
 */

'use strict';

/**
 * Gutenberg block editor compatibility module.
 *
 * @namespace BetterSeoGBC
 * @param {jQuery} $ jQuery instance passed as IIFE argument.
 */
window.BetterSeoGBC = ( function ( $ ) {

	/**
	 * WordPress block editor data selectors.
	 */
	const editor   = wp.data.select( 'core/editor' );
	const editPost = wp.data.select( 'core/edit-post' );

	/**
	 * Current post data snapshot, updated on each wp.data subscription tick.
	 *
	 * @type {Map<string, *>}
	 */
	const postData = new Map();

	// ─── POST DATA TRACKING ────────────────────────────────────────────────────

	/**
	 * Returns the current value of a post attribute from the block editor.
	 *
	 * @param {string} attribute The post attribute name.
	 * @return {*}
	 */
	function getPostAttribute( attribute ) {
		return editor.getEditedPostAttribute( attribute );
	}

	/**
	 * Updates the postData Map with the latest values from the block editor.
	 *
	 * @return {void}
	 */
	function updateData() {
		postData
			.set( 'title',      getPostAttribute( 'title' ) )
			.set( 'link',       editor.getPermalink() )
			.set( 'slug',       getPostAttribute( 'slug' ) )
			.set( 'parent',     getPostAttribute( 'parent' ) )
			.set( 'date',       getPostAttribute( 'date' ) )
			.set( 'author',     getPostAttribute( 'author' ) )
			.set( 'content',    getPostAttribute( 'content' ) )
			.set( 'excerpt',    getPostAttribute( 'excerpt' ) )
			.set( 'visibility', editor.getEditedPostVisibility() );
	}

	/**
	 * Compares the current post data against the previous snapshot and dispatches
	 * update events for any changed fields.
	 *
	 * @return {void}
	 */
	function assessData() {

		const oldData = new Map( postData );

		updateData();

		for ( const [ key, val ] of postData ) {
			if ( val !== oldData.get( key ) ) {
				triggerUpdate( key );
			}
		}
	}

	// ─── SAVE STATE TRACKING ───────────────────────────────────────────────────

	/** @type {boolean} */
	let saved = false;

	/** @type {string} */
	let savedType = '';

	/**
	 * Tracks the save lifecycle and dispatches save events at the right moment.
	 * Subscribed to wp.data — called on every store update.
	 *
	 * @return {void}
	 */
	function saveDispatcher() {
		if ( ! saved ) {
			if ( editor.isSavingPost() ) {
				saved = true;
				if ( editor.isPreviewingPost() ) {
					savedType = 'preview';
				} else if ( editor.isAutosavingPost() ) {
					savedType = 'autosave';
				} else {
					savedType = 'save';
				}
			}
		} else {
			if ( editor.didPostSaveRequestSucceed() ) {
				dispatchSaveEventDebouncer();
				revertSaveStateDebouncer.cancel();
				revertSaveState();
			} else {
				revertSaveStateDebouncer();
			}
		}
	}

	/**
	 * Debounced fallback to revert save state if the save request times out.
	 * Timeout of 7s covers typical HTTP resolution time.
	 */
	const revertSaveStateDebouncer = BetterSeoUtils.debounce( revertSaveState, 7000 );

	/**
	 * Resets the save tracking state.
	 *
	 * @return {void}
	 */
	function revertSaveState() {
		saved = false;
	}

	/**
	 * Debounced dispatcher for save completion events.
	 * 500ms delay allows the editor to settle after save.
	 */
	const dispatchSaveEventDebouncer = BetterSeoUtils.debounce( dispatchSavedEvent, 500 );

	/** @type {number} Retry counter for locked save state. */
	let retryDispatch = 0;

	/**
	 * Dispatches save completion events once the post is no longer save-locked.
	 * Retries up to 3 times if the post is still locked.
	 *
	 * @return {void}
	 */
	function dispatchSavedEvent() {

		if ( editor.isPostSavingLocked() ) {
			if ( ++retryDispatch < 3 ) {
				dispatchSaveEventDebouncer();
			} else {
				dispatchSaveEventDebouncer.cancel();
				retryDispatch = 0;
			}
			return;
		}

		retryDispatch = 0;

		// Determine whether to fire the onsave event based on save type and content state.
		const triggerOnSaveEvent = savedType === 'save' || ! editor.hasChangedContent();

		switch ( savedType ) {
			case 'preview':
				document.dispatchEvent( new CustomEvent( 'better-seo-gutenberg-onpreview' ) );
				break;
			case 'autosave':
				document.dispatchEvent( new CustomEvent( 'better-seo-gutenberg-onautosave' ) );
				break;
		}

		if ( triggerOnSaveEvent ) {
			document.dispatchEvent( new CustomEvent( 'better-seo-gutenberg-onsave' ) );
			document.dispatchEvent( new CustomEvent( 'better-seo-gutenberg-onsave-completed' ) );
		}

		document.dispatchEvent(
			new CustomEvent(
				'better-seo-gutenberg-saved-document',
				{ detail: { savedType } },
			),
		);

		savedType = '';
	}

	// ─── SIDEBAR STATE TRACKING ────────────────────────────────────────────────

	/**
	 * Tracks the last known sidebar open/closed state to avoid duplicate events.
	 *
	 * @type {{ opened: boolean }}
	 */
	const lastSidebarState = { opened: false };

	/**
	 * Detects sidebar open/close transitions and dispatches corresponding events.
	 * Subscribed to wp.data — called on every store update.
	 *
	 * @return {void}
	 */
	function sidebarDispatcher() {
		if ( editPost.isEditorSidebarOpened() ) {
			if ( ! lastSidebarState.opened ) {
				lastSidebarState.opened = true;
				document.dispatchEvent( new CustomEvent( 'better-seo-gutenberg-sidebar-opened' ) );
			}
		} else {
			if ( lastSidebarState.opened ) {
				lastSidebarState.opened = false;
				document.dispatchEvent( new CustomEvent( 'better-seo-gutenberg-sidebar-closed' ) );
			}
		}
	}

	// ─── UPDATE DISPATCH ───────────────────────────────────────────────────────

	/**
	 * Dispatches block editor update events for the given post data field.
	 *
	 * Fires both a generic 'better-seo-updated-block-editor' event and a
	 * field-specific 'better-seo-updated-block-editor-{type}' event.
	 * Also fires the deprecated jQuery 'better-seo-updated-gutenberg-{type}' event
	 * for backward compatibility with older integrations.
	 *
	 * @param {string} type The post data field name (e.g. 'title', 'slug').
	 * @return {void}
	 */
	function triggerUpdate( type ) {

		const value = postData.get( type );

		document.dispatchEvent( new CustomEvent(
			'better-seo-updated-block-editor',
			{ detail: { type, value, postData } },
		) );

		document.dispatchEvent( new CustomEvent(
			`better-seo-updated-block-editor-${type}`,
			{ detail: { type, value } },
		) );

		// Deprecated jQuery event — fire only if listeners are registered.
		if ( $._data( document, 'events' )?.[ `better-seo-updated-gutenberg-${type}` ] ) {
			BetterSeo.deprecatedFunc(
				'jQuery event "better-seo-updated-gutenberg"',
				'1.0.0',
				`JS Event "better-seo-updated-block-editor" or "better-seo-updated-block-editor-${type}"`,
			);
		}

		$( document ).trigger(
			`better-seo-updated-gutenberg-${type}`,
			[ value ],
		);
	}

	// ─── INITIALISATION ────────────────────────────────────────────────────────

	/**
	 * Subscribes to the WordPress block editor data store.
	 * Called once when the 'better-seo-onload' event fires.
	 *
	 * @return {void}
	 */
	function _initCompat() {

		const { subscribe } = wp.data;

		subscribe( BetterSeoUtils.debounce( sidebarDispatcher, 500 ) );
		subscribe( BetterSeoUtils.debounce( assessData, 300 ) );
		subscribe( saveDispatcher );

		document.dispatchEvent( new CustomEvent( 'better-seo-subscribed-to-gutenberg' ) );
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		/**
		 * Attaches the GBC module to the 'better-seo-onload' event.
		 * Called automatically on script load.
		 *
		 * @return {void}
		 */
		load: () => {
			document.body.addEventListener( 'better-seo-onload', _initCompat );
		},
		triggerUpdate,
	};

}( jQuery ) );

// Auto-initialise — registers the 'better-seo-onload' listener immediately.
window.BetterSeoGBC.load();