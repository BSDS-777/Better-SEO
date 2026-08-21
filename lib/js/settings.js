/**
 * Better SEO — Settings Page Module
 *
 * Manages all interactive behaviour on the Better SEO global settings page,
 * including title settings, homepage SEO fields, post type archive (PTA) settings,
 * social meta, schema, robots, webmaster verification, sitemap, and color picker.
 *
 * Exposed as: window.BetterSeoSettings
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - L10n object:    BetterSeoSettingsL10n (registered in class-loader.php)
 *   - L10n keys:      BetterSeoSettingsL10n.{states, params}
 *   - Settings field ID prefix: better-seo-site-settings[{name}]
 *                               (matches BETTER_SEO_SITE_OPTIONS constant in PHP)
 *   - Form ID:        better-seo-settings (set by settings/wrap.php)
 *   - Data element IDs (set by class-input.php):
 *       better-seo-title-data_{id}        — title state data
 *       better-seo-description-data_{id}  — description state data
 *       better-seo-canonical-data_{id}    — canonical state data
 *       better-seo-social-data_{id}       — social meta state data
 *   - Template IDs (set by templates/settings/warnings.php):
 *       tmpl-better-seo-disabled-title-additions-help
 *       tmpl-better-seo-disabled-title-additions-help-social
 *       tmpl-better-seo-disabled-post-type-help
 *       tmpl-better-seo-disabled-taxonomy-help
 *       tmpl-better-seo-disabled-taxonomy-from-pt-help
 *       tmpl-better-seo-robots-pt-help
 *   - Custom events dispatched:
 *       'better-seo-canonical-scheme-changed'     — canonical scheme select changed
 *       'better-seo-post-type-support-changed'    — post type excluded/included
 *       'better-seo-taxonomy-support-changed'     — taxonomy excluded/included
 *       'better-seo-title-sep-updated'            — title separator changed
 *       'better-seo-update-title-rem-additions'   — title additions toggle changed
 *       'better-seo-update-twitter-card-type'     — Twitter card type changed
 *       'better-seo-post-type-archive-switched'   — PTA selector changed
 *       'better-seo-post-type-robots-changed'     — post type robots changed
 *       'better-seo-taxonomy-robots-changed'      — taxonomy robots changed
 *       'better-seo-site-robots-changed'          — site-wide robots changed
 *   - Custom events consumed:
 *       'better-seo-onload'                       — fired when UI is ready
 *       'better-seo-tab-toggled'                  — fired by tabs.js on tab switch
 *       'better-seo-post-type-support-changed'    — consumed by validateTaxonomyState
 *       'better-seo-post-type-robots-changed'     — consumed by validatePostTypes
 *       'better-seo-taxonomy-robots-changed'      — consumed by validateTaxonomies
 *       'better-seo-taxonomy-support-changed'     — consumed by toggleWarnings
 *       'better-seo-site-robots-changed'          — consumed by checkSiteNoindex
 *       'better-seo-canonical-scheme-changed'     — consumed by canonical inputs
 *   - jQuery dependency: used for wpColorPicker and postbox-toggled event
 */

'use strict';

/**
 * Settings page module.
 *
 * @namespace BetterSeoSettings
 * @param {jQuery} $ jQuery instance passed as IIFE argument.
 */
window.BetterSeoSettings = ( function ( $ ) {

	/**
	 * Localisation data passed from PHP via wp_localize_script().
	 *
	 * @type {{ states: Object, params: Object }}
	 */
	const l10n = BetterSeoSettingsL10n;

	/**
	 * Shortcut to BetterSeo.dispatchAtInteractive for deferred event dispatch.
	 *
	 * @type {Function}
	 */
	const _dispatchAtInteractive = BetterSeo.dispatchAtInteractive;

	// ─── FIELD ID HELPER ───────────────────────────────────────────────────────

	/**
	 * Returns the full settings field element ID for the given option name.
	 *
	 * Builds the ID as: better-seo-site-settings[{name}]
	 * This matches the BETTER_SEO_SITE_OPTIONS constant used in PHP.
	 *
	 * @param {string} name The option name.
	 * @return {string} The full field element ID.
	 */
	function _getSettingsId( name ) {
		return `better-seo-site-settings[${name}]`;
	}

	// ─── FORM SUBMIT ───────────────────────────────────────────────────────────

	/**
	 * Disables submit buttons on form submission to prevent double-submits.
	 * Re-enables them after 3 seconds as a safety fallback.
	 *
	 * @return {void}
	 */
	function _initSubmit() {

		const form = document.getElementById( 'better-seo-settings' );

		if ( ! form ) {
			return;
		}

		const submitButtons = form.querySelectorAll( '[name=submit]' );

		form.addEventListener(
			'submit',
			() => {
				for ( const el of submitButtons ) {
					el.disabled = true;
				}
				setTimeout( () => {
					for ( const el of submitButtons ) {
						el.disabled = false;
					}
				}, 3000 );
			},
		);
	}

	// ─── GENERAL SETTINGS ──────────────────────────────────────────────────────

	/**
	 * Initialises general settings listeners: character/pixel counter toggles,
	 * canonical scheme selector, and post type / taxonomy exclusion checkboxes.
	 *
	 * @return {void}
	 */
	function _initGeneralSettings() {

		const toggleCharCounterDisplay = event => {
			for ( const el of document.querySelectorAll( '.better-seo-counter-wrap' ) ) {
				el.style.display = event.target.checked ? '' : 'none';
			}
			if ( event.target.checked ) {
				BetterSeoC?.triggerCounterUpdate();
			}
		};
		document.getElementById( _getSettingsId( 'display_character_counter' ) )
			?.addEventListener( 'click', toggleCharCounterDisplay );

		const togglePixelCounterDisplay = event => {
			for ( const el of document.querySelectorAll( '.better-seo-pixel-counter-wrap' ) ) {
				el.style.display = event.target.checked ? '' : 'none';
			}
			if ( event.target.checked ) {
				BetterSeoC?.triggerCounterUpdate();
			}
		};
		document.getElementById( _getSettingsId( 'display_pixel_counter' ) )
			?.addEventListener( 'click', togglePixelCounterDisplay );

		const dispatchCanonicalSchemeUpdate = event => {
			const selected = event.target.value;
			const values   = JSON.parse( event.target.dataset?.values || '0' ) || {};

			document.body.dispatchEvent( new CustomEvent(
				'better-seo-canonical-scheme-changed',
				{
					detail: {
						scheme: values[ selected ] ?? selected,
					},
				},
			) );
		};
		document.getElementById( _getSettingsId( 'canonical_scheme' ) )
			?.addEventListener( 'change', dispatchCanonicalSchemeUpdate );

		const excludedPostTypes     = new Set();
		const excludedTaxonomies    = new Set();
		const excludedPtTaxonomies  = new Set();
		const excludedTaxonomiesAll = new Set();

		const refreshTaxonomies = () => {
			excludedTaxonomiesAll.clear();
			for ( const taxonomy of excludedTaxonomies ) {
				excludedTaxonomiesAll.add( taxonomy );
			}
			for ( const taxonomy of excludedPtTaxonomies ) {
				excludedTaxonomiesAll.add( taxonomy );
			}
		};

		const dispatchTaxonomySupportChangedEvent = taxonomy => {
			document.body.dispatchEvent( new CustomEvent(
				'better-seo-taxonomy-support-changed',
				{
					detail: {
						taxonomy,
						set:    excludedTaxonomies,
						setPt:  excludedPtTaxonomies,
						setAll: excludedTaxonomiesAll,
					},
				},
			) );
		};

		const dispatchPosttypeSupportChangedEvent = postType => {
			document.body.dispatchEvent( new CustomEvent(
				'better-seo-post-type-support-changed',
				{
					detail: {
						postType,
						set: excludedPostTypes,
					},
				},
			) );
		};

		const validateTaxonomyState = () => {
			let triggerchange = false;

			for ( const element of document.querySelectorAll( '.better-seo-excluded-taxonomies' ) ) {
				const taxonomy     = element.name.split( /(?:.+\[)(.+?)(?:])/ ).join( '' );
				const taxPostTypes = JSON.parse( element.dataset?.postTypes || '0' ) || [];
				const isDisabled   = taxPostTypes && taxPostTypes.every( postType => excludedPostTypes.has( postType ) );

				if ( isDisabled ) {
					if ( ! excludedPtTaxonomies.has( taxonomy ) ) {
						triggerchange = true;
					}
					excludedPtTaxonomies.add( taxonomy );
				} else {
					if ( excludedPtTaxonomies.has( taxonomy ) ) {
						excludedPtTaxonomies.delete( taxonomy );
						triggerchange = true;
					}
				}

				refreshTaxonomies();

				if ( triggerchange ) {
					dispatchTaxonomySupportChangedEvent( taxonomy );
				}
			}
		};
		document.body.addEventListener( 'better-seo-post-type-support-changed', validateTaxonomyState );

		// Prevents notice-removal checks before listeners are added.
		let init = false;

		const checkDisabledPT = event => {
			if ( ! event.target.name ) {
				return;
			}

			const postType = event.target.name.split( /(?:.+\[)(.+?)(?:])/ ).join( '' );

			if ( event.target.checked ) {
				excludedPostTypes.add( postType );
				dispatchPosttypeSupportChangedEvent( postType );
			} else {
				if ( init ) {
					excludedPostTypes.delete( postType );
					dispatchPosttypeSupportChangedEvent( postType );
				}
			}
		};

		const checkDisabledTaxonomy = event => {
			if ( ! event.target.name ) {
				return;
			}

			const taxonomy = event.target.name.split( /(?:.+\[)(.+?)(?:])/ ).join( '' );

			if ( event.target.checked ) {
				excludedTaxonomies.add( taxonomy );
				refreshTaxonomies();
				dispatchTaxonomySupportChangedEvent( taxonomy );
			} else {
				if ( init ) {
					excludedTaxonomies.delete( taxonomy );
					refreshTaxonomies();
					dispatchTaxonomySupportChangedEvent( taxonomy );
				}
			}
		};

		for ( const el of document.querySelectorAll( '.better-seo-excluded-post-types' ) ) {
			el.addEventListener( 'change', checkDisabledPT );
			_dispatchAtInteractive( el, 'change' );
		}

		for ( const el of document.querySelectorAll( '.better-seo-excluded-taxonomies' ) ) {
			el.addEventListener( 'change', checkDisabledTaxonomy );
			_dispatchAtInteractive( el, 'change' );
		}

		init = true;
	}

	// ─── COLOR PICKER ──────────────────────────────────────────────────────────

	/**
	 * Initialises the WordPress color picker (wpColorPicker) for all
	 * .better-seo-color-picker elements on the settings page.
	 *
	 * @return {void}
	 */
	function _initColorPicker() {

		for ( const element of document.querySelectorAll( '.better-seo-color-picker' ) ) {
			const $input       = $( element );
			let currentColor   = '';
			const defaultColor = $input.data( 'better-seo-default-color' );

			$input.wpColorPicker( {
				defaultColor: defaultColor,
				width:        238,
				change: () => {
					currentColor = $input.wpColorPicker( 'color' );

					if ( '' === currentColor ) {
						currentColor = defaultColor;
					}

					element.value = defaultColor;

					BetterSeoAys.registerChange();
				},
				clear: () => {
					// Fill in the default color for the user since the clear callback is not reliable.
					if ( defaultColor.length ) {
						element.value = defaultColor;
						$input.closest( '.wp-picker-container' ).find( '.wp-color-result' ).css( 'backgroundColor', defaultColor );
					}
					BetterSeoAys.registerChange();
				},
				palettes: false,
			} );
		}
	}

	// ─── TITLE SETTINGS ────────────────────────────────────────────────────────

	/**
	 * Initialises the global title settings: additions toggle, location example,
	 * prefix example, separator picker, and site title example output.
	 *
	 * @return {void}
	 */
	function _initTitleSettings() {

		const additionsToggle            = document.getElementById( _getSettingsId( 'title_rem_additions' ) );
		const socialAdditionsToggle      = document.getElementById( _getSettingsId( 'social_title_rem_additions' ) );
		const titleAdditionsHelpTemplate = wp.template( 'better-seo-disabled-title-additions-help-social' )();

		const toggleAdditionsDisplayExample = event => {
			if ( event.target.checked ) {
				for ( const el of document.querySelectorAll( '.better-seo-title-additions-js' ) ) {
					el.style.display = 'none';
				}
				if ( socialAdditionsToggle ) {
					socialAdditionsToggle.dataset.disabledWarning = 1;
					socialAdditionsToggle.closest( 'label' ).insertAdjacentHTML( 'beforeend', titleAdditionsHelpTemplate );
					BetterSeoTT.triggerReset();
				}
			} else {
				for ( const el of document.querySelectorAll( '.better-seo-title-additions-js' ) ) {
					el.style.display = 'inline';
				}
				if ( socialAdditionsToggle?.dataset.disabledWarning ) {
					socialAdditionsToggle.closest( 'label' ).querySelector( '.better-seo-title-additions-warning-social' )?.remove();
				}
			}

			document.body.dispatchEvent( new CustomEvent(
				'better-seo-update-title-rem-additions',
				{
					detail: {
						removeAdditions: !! event.target.checked,
					},
				},
			) );
		};

		if ( additionsToggle ) {
			additionsToggle.addEventListener( 'change', toggleAdditionsDisplayExample );
			_dispatchAtInteractive( additionsToggle, 'change' );
		}

		const toggleAdditionsLocationExample = event => {
			let value;

			for ( const el of document.getElementsByName( event.target.name ) ) {
				if ( el.checked ) {
					value = el.value;
				}
			}

			const showLeft      = 'left' === value;
			const locationClass = 'better-seo-title-additions-location-hidden';

			for ( const el of document.querySelectorAll( '.better-seo-title-additions-example-left' ) ) {
				el.classList.toggle( locationClass, ! showLeft );
				el.classList.remove( 'hidden' );
			}
			for ( const el of document.querySelectorAll( '.better-seo-title-additions-example-right' ) ) {
				el.classList.toggle( locationClass, showLeft );
				el.classList.remove( 'hidden' );
			}

			BetterSeoTitle.updateStateAll(
				'additionPlacement',
				showLeft ? 'before' : 'after',
				_getSettingsId( 'homepage_title' ),
			);
		};

		for ( const el of document.querySelectorAll( '#better-seo-title-location input' ) ) {
			el.addEventListener( 'change', toggleAdditionsLocationExample );
			if ( el.checked ) {
				_dispatchAtInteractive( el, 'change' );
			}
		}

		const adjustPrefixExample = event => {
			const showPrefix  = ! event.target.checked;
			const prefixClass = 'better-seo-title-tax-prefix-hidden';

			for ( const el of document.querySelectorAll( '.better-seo-title-tax-prefix' ) ) {
				el.classList.toggle( prefixClass, ! showPrefix );
				el.classList.remove( 'hidden' );
			}
			for ( const el of document.querySelectorAll( '.better-seo-title-tax-noprefix' ) ) {
				el.classList.toggle( prefixClass, showPrefix );
				el.classList.remove( 'hidden' );
			}

			BetterSeoTitle.updateStateAll( 'showPrefix', showPrefix, _getSettingsId( 'homepage_title' ) );
		};

		const titleRemPrefixes = document.getElementById( _getSettingsId( 'title_rem_prefixes' ) );
		if ( titleRemPrefixes ) {
			titleRemPrefixes.addEventListener( 'change', adjustPrefixExample );
			_dispatchAtInteractive( titleRemPrefixes, 'change' );
		}

		const updateSeparator = event => {
			const separator   = BetterSeo.decodeEntities( event.target.dataset.entity );
			const activeClass = 'better-seo-title-separator-active';

			for ( const el of document.querySelectorAll( '.better-seo-sep-js' ) ) {
				el.textContent = ` ${separator} `;
			}

			window.dispatchEvent( new CustomEvent(
				'better-seo-title-sep-updated',
				{ detail: { separator } },
			) );

			const oldActiveLabel = document.querySelector( `.${activeClass}` );
			if ( oldActiveLabel ) {
				oldActiveLabel.classList.remove( activeClass, 'better-seo-no-focus-ring' );
			}

			const activeLabel = document.querySelector( `label[for="${event.target.id}"]` );
			if ( activeLabel ) {
				activeLabel.classList.add( activeClass );
			}
		};

		for ( const el of document.querySelectorAll( '#better-seo-title-separator input' ) ) {
			el.addEventListener( 'click', updateSeparator );
		}

		const addNoFocusClass = event => {
			event.target.classList.add( 'better-seo-no-focus-ring' );
		};

		for ( const el of document.querySelectorAll( '#better-seo-title-separator label' ) ) {
			el.addEventListener( 'click', addNoFocusClass );
		}

		const homeTitleId    = _getSettingsId( 'homepage_title' );
		const siteTitleInput = document.getElementById( _getSettingsId( 'site_title' ) );

		const adjustSiteTitleExampleOutput = event => {
			const examples = document.querySelectorAll( '.better-seo-site-title-js' );
			let newVal     = BetterSeo.decodeEntities( BetterSeo.sDoubleSpace( event.target.value.trim() ) );

			newVal ||= BetterSeo.decodeEntities( event.target.placeholder );

			// Only update the default title if the homepage title is not locked.
			if ( ! BetterSeoTitle.getStateOf( homeTitleId, '_defaultTitleLocked' ) ) {
				BetterSeoTitle.updateStateOf( homeTitleId, 'defaultTitle', newVal );
			}

			BetterSeoTitle.updateStateAll( 'additionValue', newVal, homeTitleId );

			const htmlVal = BetterSeo.escapeString( newVal );
			for ( const el of examples ) {
				el.innerHTML = htmlVal;
			}
		};

		if ( siteTitleInput ) {
			siteTitleInput.addEventListener( 'input', adjustSiteTitleExampleOutput );
			_dispatchAtInteractive( siteTitleInput, 'input' );
		}
	}

	// ─── HOMEPAGE GENERAL LISTENERS ────────────────────────────────────────────

	/**
	 * Initialises general input trigger listeners for the homepage settings tab.
	 *
	 * @return {void}
	 */
	function _initHomeGeneralListeners() {

		const enqueueGeneralInputListeners = () => {
			BetterSeoTitle.enqueueUnregisteredInputTrigger( _getSettingsId( 'homepage_title' ) );
			BetterSeoDescription.enqueueUnregisteredInputTrigger( _getSettingsId( 'homepage_description' ) );
		};

		const triggerPostboxSynchronousUnregisteredInput = ( event, elem ) => {
			if ( 'better-seo-homepage-settings' === elem.id ) {
				const inside = elem.querySelector( '.inside' );
				if ( inside.offsetHeight > 0 && inside.offsetWidth > 0 ) {
					enqueueGeneralInputListeners();
				}
			}
		};

		// jQuery: WordPress postbox-toggled action.
		$( document ).on( 'postbox-toggled', triggerPostboxSynchronousUnregisteredInput );

		document.getElementById( 'better-seo-homepage-tab-general' )
			?.addEventListener( 'better-seo-tab-toggled', enqueueGeneralInputListeners );
	}

	// ─── HOMEPAGE TITLE SETTINGS ───────────────────────────────────────────────

	/**
	 * Initialises the homepage meta title input and its state listeners.
	 *
	 * @return {void}
	 */
	function _initHomeTitleSettings() {

		const _titleId = _getSettingsId( 'homepage_title' );

		const titleInput    = document.getElementById( _titleId );
		const taglineInput  = document.getElementById( _getSettingsId( 'homepage_title_tagline' ) );
		const taglineToggle = document.getElementById( _getSettingsId( 'homepage_tagline' ) );

		if ( ! titleInput ) {
			return;
		}

		BetterSeoTitle.setInputElement( titleInput );

		const state = JSON.parse(
			document.getElementById( `better-seo-title-data_${_titleId}` )?.dataset.state || '0',
		);

		BetterSeoTitle.updateStateOf( _titleId, 'allowReferenceChange', ! state.refTitleLocked );
		BetterSeoTitle.updateStateOf( _titleId, 'defaultTitle',         state.defaultTitle );
		BetterSeoTitle.updateStateOf( _titleId, 'addAdditions',         state.addAdditions );
		BetterSeoTitle.updateStateOf( _titleId, 'useSocialTagline',     !! ( state.useSocialTagline ?? false ) );
		BetterSeoTitle.updateStateOf( _titleId, 'additionValue',        state.additionValue );
		BetterSeoTitle.updateStateOf( _titleId, 'additionPlacement',    state.additionPlacement );
		BetterSeoTitle.updateStateOf( _titleId, '_defaultTitleLocked',  !! ( state._defaultTitleLocked ?? false ) );

		BetterSeoTitle.enqueueUnregisteredInputTrigger( _titleId );

		const toggleHoverAdditionsPlacement = event => {
			BetterSeoTitle.updateStateOf(
				_titleId,
				'additionPlacement',
				'left' === event.target.value ? 'before' : 'after',
			);
		};

		for ( const el of document.querySelectorAll( '#better-seo-home-title-location input' ) ) {
			el.addEventListener( 'change', toggleHoverAdditionsPlacement );
			if ( el.checked ) {
				_dispatchAtInteractive( el, 'change' );
			}
		}

		const setTitleVisibilityPrefix = visibility => {
			const oldPrefixValue = BetterSeoTitle.getStateOf( _titleId, 'prefixValue' );
			let prefixValue      = '';

			switch ( visibility ) {
				case 'password':
					prefixValue = BetterSeoTitle.protectedPrefix;
					break;
				case 'private':
					prefixValue = BetterSeoTitle.privatePrefix;
					break;
				default:
				case 'public':
					prefixValue = '';
					break;
			}

			if ( prefixValue !== oldPrefixValue ) {
				BetterSeoTitle.updateStateOf( _titleId, 'prefixValue', prefixValue );
			}
		};

		if ( l10n.states.isFrontPrivate ) {
			setTitleVisibilityPrefix( 'private' );
		} else if ( l10n.states.isFrontProtected ) {
			setTitleVisibilityPrefix( 'password' );
		}

		const adjustHomepageExampleOutput = event => {
			const examples = document.querySelectorAll( '.better-seo-custom-title-js' );
			let val        = BetterSeo.decodeEntities( BetterSeo.sDoubleSpace( event.target.value.trim() ) );

			if ( val.length ) {
				val = BetterSeo.escapeString( val );
				for ( const el of examples ) {
					el.innerHTML = val;
				}
			} else {
				val = BetterSeo.escapeString( BetterSeo.decodeEntities( BetterSeoTitle.getStateOf( _titleId, 'defaultTitle' ) ) );
				for ( const el of examples ) {
					el.innerHTML = val;
				}
			}
		};

		titleInput.addEventListener( 'input', adjustHomepageExampleOutput );
		_dispatchAtInteractive( titleInput, 'input' );

		let updateHomePageTaglineExampleOutputBuffer;

		const updateHomePageTaglineExampleOutput = () => {
			clearTimeout( updateHomePageTaglineExampleOutputBuffer );
			updateHomePageTaglineExampleOutputBuffer = setTimeout(
				() => {
					let value = BetterSeoTitle.getStateOf( _titleId, 'additionValue' );

					value = BetterSeo.decodeEntities( BetterSeo.sDoubleSpace( value.trim() ) );

					if ( value.length && BetterSeoTitle.getStateOf( _titleId, 'addAdditions' ) ) {
						for ( const el of document.querySelectorAll( '.better-seo-custom-tagline-js' ) ) {
							el.innerHTML = BetterSeo.escapeString( value );
						}
						for ( const el of document.querySelectorAll( '.better-seo-custom-blogname-js' ) ) {
							el.style.display = null;
						}
					} else {
						for ( const el of document.querySelectorAll( '.better-seo-custom-blogname-js' ) ) {
							el.style.display = 'none';
						}
					}
				},
				1000 / 60, // ~60fps
			);
		};

		const updateHoverAdditionsValue = () => {
			let value = taglineInput.value.trim();

			if ( ! value.length ) {
				value = taglineInput.placeholder ?? '';
			}

			value = BetterSeo.escapeString( BetterSeo.decodeEntities( value.trim() ) );

			BetterSeoTitle.updateStateOf( _titleId, 'additionValue', value );
			updateHomePageTaglineExampleOutput();
		};

		taglineInput.addEventListener( 'input', updateHoverAdditionsValue );
		_dispatchAtInteractive( taglineInput, 'input' );

		const toggleHomePageTaglineExampleDisplay = event => {
			const addAdditions = event.target.checked;

			taglineInput.readOnly = ! addAdditions;

			BetterSeoTitle.updateStateOf( _titleId, 'addAdditions', addAdditions );
			updateHomePageTaglineExampleOutput();
		};

		taglineToggle.addEventListener( 'change', toggleHomePageTaglineExampleDisplay );
		_dispatchAtInteractive( taglineToggle, 'change' );

		/**
		 * Updates the title separator state when the separator picker changes.
		 *
		 * @param {CustomEvent} event
		 * @return {void}
		 */
		const updateSeparator = event => {
			BetterSeoTitle.updateStateAll( 'separator', event.detail.separator );
		};
		window.addEventListener( 'better-seo-title-sep-updated', updateSeparator );
	}

	// ─── HOMEPAGE DESCRIPTION SETTINGS ────────────────────────────────────────

	/**
	 * Initialises the homepage meta description input and its state.
	 *
	 * @return {void}
	 */
	function _initHomeDescriptionSettings() {

		const descId = _getSettingsId( 'homepage_description' );

		BetterSeoDescription.setInputElement( document.getElementById( descId ) );

		const state = JSON.parse(
			document.getElementById( `better-seo-description-data_${descId}` )?.dataset.state || '0',
		);

		if ( state ) {
			BetterSeoDescription.updateStateOf( descId, 'defaultDescription', state.defaultDescription.trim() );
		}

		BetterSeoDescription.enqueueUnregisteredInputTrigger( descId );
	}

	// ─── HOMEPAGE SOCIAL SETTINGS ──────────────────────────────────────────────

	/**
	 * Initialises the homepage social meta inputs and their state.
	 *
	 * @return {void}
	 */
	function _initHomeSocialSettings() {

		const _socialGroup = 'homepage_social_settings';

		BetterSeoSocial.setInputInstance(
			_socialGroup,
			_getSettingsId( 'homepage_title' ),
			_getSettingsId( 'homepage_description' ),
		);

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
		BetterSeoSocial.updateStateOf(
			_socialGroup,
			'placeholderLocks',
			{
				ogTitle: groupData.og.state?.titlePhLock ?? false,
				twTitle: groupData.tw.state?.titlePhLock ?? false,
				ogDesc:  groupData.og.state?.descPhLock  ?? false,
				twDesc:  groupData.tw.state?.descPhLock  ?? false,
			},
		);

		const twitterCardType = document.getElementById( _getSettingsId( 'homepage_twitter_card_type' ) );

		const updateTitleRemoveAdditions = event => {
			const { cardType }        = event.detail;
			const defaultIndexOption  = twitterCardType.querySelector( '[value=""]' );
			const _data               = twitterCardType.dataset ?? {};

			const newHTML = _data.defaultI18n?.replace(
				'%s',
				_data.defaultLocked ? _data.defaultValue : cardType,
			);

			if ( defaultIndexOption ) {
				defaultIndexOption.innerHTML = newHTML;
			}
			twitterCardType.dispatchEvent( new Event( 'change' ) );
		};

		if ( twitterCardType ) {
			document.body.addEventListener( 'better-seo-update-twitter-card-type', updateTitleRemoveAdditions );
		}
	}

	// ─── HOMEPAGE VISIBILITY SETTINGS ─────────────────────────────────────────

	/**
	 * Initialises the homepage canonical URL and robots noindex listeners.
	 *
	 * @return {void}
	 */
	function _initHomeVisibilitySettings() {

		const _canonicalId = _getSettingsId( 'homepage_canonical' );
		const _noindexId   = _getSettingsId( 'homepage_noindex' );

		const canonicalInput = document.getElementById( _canonicalId );
		const noindexInput   = document.getElementById( _noindexId );

		if ( ! canonicalInput ) {
			return;
		}

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
		}

		BetterSeoCanonical.enqueueTriggerUnregisteredInput( _canonicalId );

		document.body.addEventListener( 'better-seo-canonical-scheme-changed', event => {
			BetterSeoCanonical.updateStateOf( _canonicalId, 'preferredScheme', event.detail.scheme );
		} );

		const updateCanonicalPlaceholder = () => {
			BetterSeoCanonical.updateStateOf(
				_canonicalId,
				'showUrlPlaceholder',
				! ( canonicalPhState & BNOINDEX ),
			);
		};
		updateCanonicalPlaceholder();

		let pageNoindex = false;
		let siteNoindex = false;

		const updateNoindexState = () => {
			let type = 'index';

			switch ( state.noindexQubit ) {
				case 0:
					type = ( noindexInput?.checked || siteNoindex || pageNoindex || state.isProtected )
						? 'noindex'
						: 'index';
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

		noindexInput?.addEventListener( 'change', updateNoindexState );

		if ( state.isPage ) {
			const checkPTNoindex = event => {
				const { robotsType, set } = event.detail;

				if ( 'noindex' !== robotsType ) {
					return;
				}

				pageNoindex = set.has( 'page' );
				updateNoindexState();
			};
			document.body.addEventListener( 'better-seo-post-type-robots-changed', checkPTNoindex );
		}

		const checkSiteNoindex = event => {
			const { checked, robotsType } = event.detail;

			if ( 'noindex' !== robotsType ) {
				return;
			}

			siteNoindex = !! checked;
			updateNoindexState();
		};
		document.body.addEventListener( 'better-seo-site-robots-changed', checkSiteNoindex );
	}

	// ─── POST TYPE ARCHIVE (PTA) HELPERS ───────────────────────────────────────

	/**
	 * Returns the full field element ID for a PTA option.
	 *
	 * @param {string} postType The post type slug.
	 * @param {string} id       The option key.
	 * @return {string} The full field element ID.
	 */
	function _getPtaInputId( postType, id ) {
		return `${_getSettingsId( 'pta' )}[${postType}][${id}]`;
	}

	/** @type {Object|undefined} */
	let _cachedPtaData;

	/**
	 * Returns the post type archive data from the DOM, memoized.
	 *
	 * @return {Object.<string, Object>} Map of post type slug → PTA data.
	 */
	function _getPtaData() {
		return _cachedPtaData ??= JSON.parse(
			document.getElementById( 'better-seo-post-type-archive-data' )?.dataset.postTypes || '0',
		) || {};
	}

	// ─── PTA SETTINGS ──────────────────────────────────────────────────────────

	/**
	 * Initialises the post type archive settings section.
	 *
	 * @return {void}
	 */
	function _initPtaSettings() {

		const postTypeData = _getPtaData();
		const itemLength   = Object.keys( postTypeData ).length;

		switch ( true ) {
			case itemLength > 1:
				_initPtaSelector();
				// fall through
			case itemLength > 0:
				_initPtaListeners();
				break;
		}

		for ( const postType in postTypeData ) {
			_initPtaTitleSettings( postType );
			_initPtaDescriptionSettings( postType );
			_initPtaSocialSettings( postType );
			_initPtaVisibilitySettings( postType );
			_initPtaMainListeners( postType );
		}
	}

	/**
	 * Initialises the post type archive selector dropdown.
	 *
	 * @return {void}
	 */
	function _initPtaSelector() {

		const postTypeData = _getPtaData();
		const select       = document.getElementById( 'better-seo-post-type-archive-selector' );
		const optionOption = document.createElement( 'option' );
		const headerWrap   = document.getElementById( 'better-seo-post-type-archive-header-wrap' );

		if ( headerWrap ) {
			headerWrap.style.display = null;
		}

		const populateSelect = () => {
			for ( const postType in postTypeData ) {
				const option     = optionOption.cloneNode();
				option.value     = BetterSeo.escapeString( postType );
				option.innerHTML = BetterSeo.escapeString( postTypeData[ postType ].label );
				select?.appendChild( option );
			}
		};
		populateSelect();

		for ( const el of document.querySelectorAll( '.better-seo-post-type-header' ) ) {
			el.classList.add( 'hidden' );
		}

		let _debounceSwitch;
		let _detailsEl;

		const switchPostTypeSettingsView = event => {
			clearTimeout( _debounceSwitch );
			_debounceSwitch = setTimeout(
				() => {
					if ( _detailsEl ) {
						headerWrap?.removeChild( _detailsEl );
					}

					for ( const el of document.querySelectorAll( '.better-seo-post-type-archive-wrap' ) ) {
						if ( event.target.value === el.dataset.postType ) {
							el.style.display = null;
							_detailsEl = el.querySelector( '.better-seo-post-type-archive-details' )?.cloneNode( true );
						} else {
							el.style.display = 'none';
						}
						el.classList.remove( 'hide-if-better-seo-js' );
					}

					if ( _detailsEl ) {
						headerWrap?.appendChild( _detailsEl );
					}

					document.body.dispatchEvent(
						new CustomEvent( 'better-seo-post-type-archive-switched', {
							detail: { postType: event.target.value },
						} ),
					);
				},
				1000 / 60,
			);
		};

		if ( select ) {
			select.addEventListener( 'change', switchPostTypeSettingsView );
			_dispatchAtInteractive( select, 'change' );
		}
	}

	/**
	 * Initialises the post type archive support change listener.
	 *
	 * @return {void}
	 */
	function _initPtaListeners() {

		const augmentSwitcher = event => {
			const { postType, set } = event.detail;
			const wrap              = document.querySelector( `.better-seo-post-type-archive-wrap[data-post-type="${postType}"]` );
			const excluded          = set.has( postType );

			wrap?.querySelector( '.better-seo-post-type-archive-if-excluded' )?.classList.toggle( 'hidden', ! excluded );
			wrap?.querySelector( '.better-seo-post-type-archive-if-not-excluded' )?.classList.toggle( 'hidden', excluded );

			document.body.dispatchEvent(
				new CustomEvent( 'better-seo-post-type-archive-switched', {
					detail: { postType },
				} ),
			);
		};

		document.body.addEventListener( 'better-seo-post-type-support-changed', augmentSwitcher );
	}

	/**
	 * Initialises the title input for a specific post type archive.
	 *
	 * @param {string} postType The post type slug.
	 * @return {void}
	 */
	function _initPtaTitleSettings( postType ) {

		const _titleId   = _getPtaInputId( postType, 'doctitle' );
		const titleInput = document.getElementById( _titleId );

		if ( ! titleInput ) {
			return;
		}

		BetterSeoTitle.setInputElement( titleInput );

		const state = JSON.parse(
			document.getElementById( `better-seo-title-data_${_titleId}` )?.dataset.state || '0',
		);

		if ( state ) {
			BetterSeoTitle.updateStateOf( _titleId, 'defaultTitle',      state.defaultTitle );
			BetterSeoTitle.updateStateOf( _titleId, 'addAdditions',      state.addAdditions );
			BetterSeoTitle.updateStateOf( _titleId, 'useSocialTagline',  !! ( state.useSocialTagline ?? false ) );
			BetterSeoTitle.updateStateOf( _titleId, 'additionValue',     state.additionValue );
			BetterSeoTitle.updateStateOf( _titleId, 'additionPlacement', state.additionPlacement );
			BetterSeoTitle.updateStateOf( _titleId, 'prefixValue',       state.prefixValue );
			BetterSeoTitle.updateStateOf( _titleId, 'showPrefix',        state.showPrefix );
		}

		const updateTitlePrefix = event => {
			let showPrefix = ! event.target.value.trim().length;

			if ( document.getElementById( _getSettingsId( 'title_rem_prefixes' ) )?.checked ) {
				showPrefix = false;
			}

			BetterSeoTitle.updateStateOf( _titleId, 'showPrefix', showPrefix );
		};
		titleInput.addEventListener( 'input', updateTitlePrefix );

		const updateTitleAdditions = event => {
			let addAdditions = ! event.target.checked;

			if ( document.getElementById( _getSettingsId( 'title_rem_additions' ) )?.checked ) {
				addAdditions = false;
			}

			BetterSeoTitle.updateStateOf( _titleId, 'addAdditions', addAdditions );
		};

		const disabledTitleAdditionsHelp = wp.template( 'better-seo-disabled-title-additions-help' )();
		const blogNameTrigger            = document.getElementById( _getPtaInputId( postType, 'title_no_blog_name' ) );

		const updateTitleRemoveAdditions = event => {
			const { removeAdditions } = event.detail;

			blogNameTrigger.disabled = removeAdditions;

			if ( removeAdditions ) {
				blogNameTrigger.closest( 'label' ).insertAdjacentHTML( 'beforeend', disabledTitleAdditionsHelp );
				BetterSeoTT.triggerReset();
			} else {
				blogNameTrigger.closest( 'label' ).querySelector( '.better-seo-title-additions-warning' )?.remove();
			}

			blogNameTrigger.dispatchEvent( new Event( 'change' ) );
		};

		if ( blogNameTrigger ) {
			document.body.addEventListener( 'better-seo-update-title-rem-additions', updateTitleRemoveAdditions );
			blogNameTrigger.addEventListener( 'change', updateTitleAdditions );
			_dispatchAtInteractive( blogNameTrigger, 'change' );
		}

		BetterSeoTitle.enqueueUnregisteredInputTrigger( _titleId );
	}

	/**
	 * Initialises the description input for a specific post type archive.
	 *
	 * @param {string} postType The post type slug.
	 * @return {void}
	 */
	function _initPtaDescriptionSettings( postType ) {

		const _descId   = _getPtaInputId( postType, 'description' );
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

	/**
	 * Initialises the social meta inputs for a specific post type archive.
	 *
	 * @param {string} postType The post type slug.
	 * @return {void}
	 */
	function _initPtaSocialSettings( postType ) {

		const _socialGroup = `pta_social_settings_${postType}`;

		const groupData = JSON.parse(
			document.getElementById( `better-seo-social-data_${_socialGroup}` )?.dataset.settings || '0',
		);

		BetterSeoSocial.setInputInstance(
			_socialGroup,
			_getPtaInputId( postType, 'doctitle' ),
			_getPtaInputId( postType, 'description' ),
		);
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

		const twitterCardType = document.getElementById( _getPtaInputId( postType, 'tw_card_type' ) );

		const updateTitleRemoveAdditions = event => {
			const { cardType }       = event.detail;
			const defaultIndexOption = twitterCardType.querySelector( '[value=""]' );
			const _data              = twitterCardType.dataset ?? {};

			const newHTML = _data.defaultI18n?.replace( '%s', cardType );

			if ( defaultIndexOption ) {
				defaultIndexOption.innerHTML = newHTML;
			}
			twitterCardType.dispatchEvent( new Event( 'change' ) );
		};

		if ( twitterCardType ) {
			document.body.addEventListener( 'better-seo-update-twitter-card-type', updateTitleRemoveAdditions );
		}
	}

	/**
	 * Initialises the visibility (canonical + robots) inputs for a specific post type archive.
	 *
	 * @param {string} postType The post type slug.
	 * @return {void}
	 */
	function _initPtaVisibilitySettings( postType ) {

		const canonicalId  = _getPtaInputId( postType, 'canonical' );
		const noindexId    = _getPtaInputId( postType, 'noindex' );

		const canonicalInput = document.getElementById( canonicalId );
		const indexSelect    = document.getElementById( noindexId );

		const BNOINDEX = 0b10;

		let canonicalPhState = 0b00;

		const updateCanonicalPlaceholder = () => {
			BetterSeoCanonical.updateStateOf(
				canonicalId,
				'showUrlPlaceholder',
				! ( canonicalPhState & BNOINDEX ),
			);
		};

		if ( canonicalInput ) {
			BetterSeoCanonical.setInputElement( canonicalInput );

			const state = JSON.parse(
				document.getElementById( `better-seo-canonical-data_${canonicalId}` )?.dataset.state || '0',
			);

			if ( state ) {
				BetterSeoCanonical.updateStateOf( canonicalId, 'allowReferenceChange', ! state.refCanonicalLocked );
				BetterSeoCanonical.updateStateOf( canonicalId, 'defaultCanonical',     state.defaultCanonical.trim() );
				BetterSeoCanonical.updateStateOf( canonicalId, 'preferredScheme',      state.preferredScheme.trim() );
				BetterSeoCanonical.updateStateOf( canonicalId, 'urlStructure',         state.urlStructure );
			}

			BetterSeoCanonical.enqueueTriggerUnregisteredInput( canonicalId );

			document.body.addEventListener( 'better-seo-canonical-scheme-changed', event => {
				BetterSeoCanonical.updateStateOf( canonicalId, 'preferredScheme', event.detail.scheme );
			} );
		}

		const robotsData = {
			site: new Map(),
			pt:   new Map(),
		};

		const isNo_Default = robotsType => {
			let off = false;

			if ( 'noindex' === robotsType ) {
				off = ! _getPtaData()[ postType ].hasPosts;
			}

			return off || robotsData.site.get( robotsType ) || robotsData.pt.get( robotsType );
		};

		const updateRobots = robotsType => {
			const robotsSelect = document.getElementById( _getPtaInputId( postType, robotsType ) );

			if ( ! robotsSelect ) {
				return;
			}

			const defaultIndexOption = [ ...robotsSelect.options ].find( o => '0' === o.value );
			const _data              = robotsSelect.dataset ?? {};

			const newHTML = _data.defaultI18n?.replace(
				'%s',
				BetterSeo.decodeEntities(
					isNo_Default( robotsType ) ? _data.defaultOff : _data.defaultOn,
				),
			);

			if ( newHTML !== defaultIndexOption?.innerHTML ) {
				if ( defaultIndexOption ) {
					defaultIndexOption.innerHTML = newHTML;
				}
				robotsSelect.dispatchEvent( new Event( 'change' ) );
			}
		};

		const _registerPTDefaultRobotsValue = event => {
			const { postType: pt, robotsType, set } = event.detail;
			if ( postType !== pt ) {
				return;
			}
			robotsData.pt.set( robotsType, set.has( postType ) );
			updateRobots( robotsType );
		};

		const _registerSiteDefaultRobotsValue = event => {
			const { checked, robotsType } = event.detail;
			robotsData.site.set( robotsType, !! checked );
			updateRobots( robotsType );
		};

		document.body.addEventListener( 'better-seo-post-type-robots-changed', _registerPTDefaultRobotsValue );
		document.body.addEventListener( 'better-seo-site-robots-changed',      _registerSiteDefaultRobotsValue );

		for ( const type of [ 'noindex', 'nofollow', 'noarchive' ] ) {
			updateRobots( type );
		}

		const setRobotsIndexingState = value => {
			let type = '';

			switch ( +value ) {
				case 0:
					type = isNo_Default( 'noindex' ) ? 'noindex' : 'index';
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

		indexSelect.addEventListener( 'change', event => setRobotsIndexingState( event.target.value ) );
		setRobotsIndexingState( indexSelect.value );
	}

	/**
	 * Initialises the general input trigger listeners for a specific post type archive.
	 *
	 * @param {string} postType The post type slug.
	 * @return {void}
	 */
	function _initPtaMainListeners( postType ) {

		const enqueueGeneralInputListeners = () => {
			BetterSeoTitle.enqueueUnregisteredInputTrigger( _getPtaInputId( postType, 'doctitle' ) );
			BetterSeoDescription.enqueueUnregisteredInputTrigger( _getPtaInputId( postType, 'description' ) );
		};

		const triggerPostboxSynchronousUnregisteredInput = ( event, elem ) => {
			if ( 'better-seo-post-type-archive-settings' === elem.id ) {
				const inside = elem.querySelector( '.inside' );
				if ( inside.offsetHeight > 0 && inside.offsetWidth > 0 ) {
					enqueueGeneralInputListeners();
				}
			}
		};

		// jQuery: WordPress postbox-toggled action.
		$( document ).on( 'postbox-toggled', triggerPostboxSynchronousUnregisteredInput );

		const triggerPtaSynchronousUnregisteredInput = event => {
			if ( event.detail?.postType === postType ) {
				enqueueGeneralInputListeners();
			}
		};
		document.body.addEventListener( 'better-seo-post-type-archive-switched', triggerPtaSynchronousUnregisteredInput );

		document.getElementById( `better-seo-post_type_archive_${postType}-tab-general` )
			?.addEventListener( 'better-seo-tab-toggled', enqueueGeneralInputListeners );
	}

	// ─── SOCIAL SETTINGS ───────────────────────────────────────────────────────

	/**
	 * Initialises the social meta settings: additions toggle, OG fields toggle,
	 * tag toggles for social tabs, and Twitter card type dispatcher.
	 *
	 * @return {void}
	 */
	function _initSocialSettings() {

		const socialTitleRemoveAdditions = document.getElementById( _getSettingsId( 'social_title_rem_additions' ) );

		const updateSocialAdditions = event => {
			BetterSeoSocial.updateStateAll( 'addAdditions', ! event.target.checked );
		};

		if ( socialTitleRemoveAdditions ) {
			socialTitleRemoveAdditions.addEventListener( 'change', updateSocialAdditions );
			_dispatchAtInteractive( socialTitleRemoveAdditions, 'change' );
		}

		const ogTagsToggle = document.getElementById( _getSettingsId( 'og_tags' ) );

		const displayOgFields = event => {
			document.getElementById( 'multi_og_image_wrapper' )
				?.classList
				.toggle( 'hidden', ! event.target.checked );
		};

		if ( ogTagsToggle ) {
			ogTagsToggle.addEventListener( 'change', displayOgFields );
			_dispatchAtInteractive( ogTagsToggle, 'change' );
		}

		const registerTagToggle = toggleData => {
			if ( ! toggleData.id ) {
				return;
			}

			const toggle = document.getElementById( _getSettingsId( toggleData.id ) );

			const hideDisableTab = event => {
				BetterSeoTabs.toggleTab( 'betterSeoSettings', `better-seo-social-tab-${toggleData.tab}`, event.target.checked );
			};

			if ( toggle ) {
				toggle.addEventListener( 'change', hideDisableTab );
				_dispatchAtInteractive( toggle, 'change' );
			}
		};

		for ( const toggleData of [
			{ id: 'og_tags',        tab: 'postdates' },
			{ id: 'facebook_tags',  tab: 'facebook' },
			{ id: 'twitter_tags',   tab: 'twitter' },
			{ id: 'oembed_scripts', tab: 'oembed' },
		] ) {
			registerTagToggle( toggleData );
		}

		const toggleCheckRegistry = new Set();

		const checkAllDisabled = event => {
			if ( event.target.checked ) {
				toggleCheckRegistry.add( event.target.name );
			} else {
				toggleCheckRegistry.delete( event.target.name );
			}

			document.getElementById( 'better-seo-social-settings-wrapper' )
				?.classList
				.toggle( 'hidden', ! toggleCheckRegistry.size );
		};

		for ( const id of [ 'og_tags', 'facebook_tags', 'twitter_tags', 'oembed_scripts' ] ) {
			const toggle = document.getElementById( _getSettingsId( id ) );
			toggle.addEventListener( 'change', checkAllDisabled );
			_dispatchAtInteractive( toggle, 'change' );
		}

		const dispatchCardToggleEvent = event => {
			document.body.dispatchEvent( new CustomEvent(
				'better-seo-update-twitter-card-type',
				{
					detail: { cardType: event.target.value },
				},
			) );
		};

		for ( const el of document.querySelectorAll( '#better-seo-twitter-cards input' ) ) {
			el.addEventListener( 'change', dispatchCardToggleEvent );
			if ( el.checked ) {
				_dispatchAtInteractive( el, 'change' );
			}
		}
	}

	// ─── SCHEMA SETTINGS ───────────────────────────────────────────────────────

	/**
	 * Initialises the schema/structured data settings: LD+JSON toggle,
	 * presence tab visibility, knowledge type selector, and logo toggle.
	 *
	 * @return {void}
	 */
	function _initSchemaSettings() {

		const sdToggle = document.getElementById( _getSettingsId( 'ld_json_enabled' ) );

		const toggleSettingsDisplay = event => {
			document.getElementById( 'better-seo-advanced-structured-data-settings-wrapper' )
				?.classList
				.toggle( 'hidden', ! event.target.checked );

			togglePresenceTab();
		};

		if ( sdToggle ) {
			sdToggle.addEventListener( 'change', toggleSettingsDisplay );
			_dispatchAtInteractive( sdToggle, 'change' );
		}

		const presenceTab = {
			id:  'knowledge_output',
			tab: 'presence',
		};

		const presenceToggle   = document.getElementById( _getSettingsId( presenceTab.id ) );
		const presenceTabRadio = document.getElementById( `better-seo-social-tab-${presenceTab.tab}` );
		const presenceTabLabel = document.getElementById( 'schema-tabs-wrapper' )
			?.querySelector( `[for=better-seo-schema-tab-${presenceTab.tab}]` );

		const togglePresenceTab = () => {
			const show = sdToggle?.checked && presenceToggle?.checked;

			presenceTabLabel?.classList.toggle( 'hidden', ! show );

			if ( show ) {
				presenceTabRadio?.removeAttribute( 'disabled' );
			} else {
				presenceTabRadio?.setAttribute( 'disabled', '' );
			}
		};

		if ( presenceToggle ) {
			presenceToggle.addEventListener( 'change', togglePresenceTab );
			togglePresenceTab();
		}

		const knowledgeTypeSelect = document.getElementById( _getSettingsId( 'knowledge_type' ) );

		const toggleKnowledgeType = event => {
			document.getElementById( 'better-seo-logo-structured-data-settings-wrapper' )
				?.classList
				.toggle( 'hidden', event.target.value === 'person' );
		};

		if ( knowledgeTypeSelect ) {
			knowledgeTypeSelect.addEventListener( 'change', toggleKnowledgeType );
			_dispatchAtInteractive( knowledgeTypeSelect, 'change' );
		}

		const logoToggle = document.getElementById( _getSettingsId( 'knowledge_logo' ) );

		const toggleDisplayLogo = event => {
			document.getElementById( 'better-seo-logo-upload-structured-data-settings-wrapper' )
				?.classList
				.toggle( 'hidden', ! event.target.checked );
		};

		if ( logoToggle ) {
			logoToggle.addEventListener( 'change', toggleDisplayLogo );
			_dispatchAtInteractive( logoToggle, 'change' );
		}
	}

	// ─── ROBOTS INPUTS ─────────────────────────────────────────────────────────

	/**
	 * Initialises the robots meta settings: copyright directives toggle,
	 * post type robots checkboxes, taxonomy robots checkboxes, and site-wide
	 * robots checkboxes. Dispatches change events for cross-field synchronisation.
	 *
	 * @return {void}
	 */
	function _initRobotsInputs() {

		const copyrightToggle = document.getElementById( _getSettingsId( 'set_copyright_directives' ) );

		if ( copyrightToggle ) {
			const controlNodes = [
				'max_snippet_length',
				'max_image_preview',
				'max_video_preview',
			].map( name => document.getElementById( _getSettingsId( name ) ) );

			const surrogateClass = 'better-seo-toggle-directives-surrogate';

			const toggleCopyrightControl = event => {
				if ( event.target.checked ) {
					for ( const el of controlNodes ) {
						el.disabled = false;
					}
					for ( const el of document.querySelectorAll( `.${surrogateClass}` ) ) {
						el.remove();
					}
				} else {
					for ( const el of controlNodes ) {
						el.disabled = true;
						const surrogate   = document.createElement( 'input' );
						surrogate.type    = 'hidden';
						surrogate.name    = el.name ?? '';
						surrogate.value   = el.value || 0;
						surrogate.classList.add( surrogateClass );
						el.insertAdjacentElement( 'afterend', surrogate );
					}
				}
			};

			copyrightToggle.addEventListener( 'change', toggleCopyrightControl );
			_dispatchAtInteractive( copyrightToggle, 'change' );
		}

		const robotsPostTypes    = { noindex: new Set(), nofollow: new Set(), noarchive: new Set() };
		const robotsPtTaxonomies = { noindex: new Set(), nofollow: new Set(), noarchive: new Set() };

		const dispatchPosttypeRobotsChangedEvent = ( postType, robotsType ) => {
			document.body.dispatchEvent( new CustomEvent(
				'better-seo-post-type-robots-changed',
				{
					detail: { postType, robotsType, set: robotsPostTypes[ robotsType ] },
				},
			) );
		};

		const dispatchTaxonomyRobotsChangedEvent = ( taxonomy, robotsType ) => {
			document.body.dispatchEvent( new CustomEvent(
				'better-seo-taxonomy-robots-changed',
				{
					detail: { taxonomy, robotsType, set: robotsPtTaxonomies[ robotsType ] },
				},
			) );
		};

		const dispatchSiteRobotsChangedEvent = ( checked, robotsType ) => {
			document.body.dispatchEvent( new CustomEvent(
				'better-seo-site-robots-changed',
				{
					detail: { checked, robotsType },
				},
			) );
		};

		const postTypeRobotsHelp = wp.template( 'better-seo-robots-pt-help' )();

		const addTaxRobotsByPtWarning = ( taxonomy, robotsType, disable ) => {
			const taxEl = document.getElementById( `${ _getSettingsId( `${robotsType}_taxonomies` ) }[${taxonomy}]` );
			if ( disable ) {
				taxEl.closest( 'label' ).insertAdjacentHTML( 'beforeend', postTypeRobotsHelp );
				BetterSeoTT.triggerReset();
			} else {
				taxEl.closest( 'label' ).querySelector( '.better-seo-taxonomy-from-pt-robots-warning' )?.remove();
			}

			toggleWarnings( taxonomy );
		};

		const validateTaxonomyState = robotsType => {
			const taxEntries = document.querySelectorAll( `.better-seo-robots-taxonomies[data-robots="${robotsType}"]` );
			let triggerchange = false;

			for ( const element of taxEntries ) {
				const taxonomy     = element.name.split( /(?:.+\[)(.+?)(?:])/ ).join( '' );
				const taxPostTypes = JSON.parse( element.dataset.postTypes || '0' );
				const hasRobots    = taxPostTypes && taxPostTypes.every( postType => robotsPostTypes[ robotsType ].has( postType ) );

				if ( hasRobots ) {
					if ( ! robotsPtTaxonomies[ robotsType ].has( taxonomy ) ) {
						triggerchange = true;
					}
					robotsPtTaxonomies[ robotsType ].add( taxonomy );
				} else {
					if ( robotsPtTaxonomies[ robotsType ].has( taxonomy ) ) {
						robotsPtTaxonomies[ robotsType ].delete( taxonomy );
						triggerchange = true;
					}
				}

				if ( triggerchange ) {
					dispatchTaxonomyRobotsChangedEvent( taxonomy, robotsType );
				}
			}
		};

		const validateTaxonomiesCache = {
			noindex:   new Map(),
			nofollow:  new Map(),
			noarchive: new Map(),
		};

		const getValidateTaxonomiesCache = ( key, robotsType ) =>
			validateTaxonomiesCache[ robotsType ].get( key ) || ( new Set() );

		const validateTaxonomies = event => {
			const { taxonomy, robotsType } = event.detail;

			if ( getValidateTaxonomiesCache( 'robotsPtTaxonomies', robotsType ).size
				!== robotsPtTaxonomies[ robotsType ].size
			) {
				addTaxRobotsByPtWarning( taxonomy, robotsType, robotsPtTaxonomies[ robotsType ].has( taxonomy ) );
			}

			validateTaxonomiesCache[ robotsType ].set( 'robotsPtTaxonomies', new Set( robotsPtTaxonomies[ robotsType ] ) );
		};
		document.body.addEventListener( 'better-seo-taxonomy-robots-changed', validateTaxonomies );

		const validatePostTypes = event => {
			validateTaxonomyState( event.detail.robotsType );
		};
		document.body.addEventListener( 'better-seo-post-type-robots-changed', validatePostTypes );

		const toggleWarnings = taxonomy => {
			for ( const robotsType in robotsPtTaxonomies ) {
				if ( robotsPtTaxonomies[ robotsType ].has( taxonomy ) ) {
					const taxEl  = document.getElementById( `${ _getSettingsId( `${robotsType}_taxonomies` ) }[${taxonomy}]` );
					const warning = taxEl.closest( 'label' ).querySelector( '.better-seo-taxonomy-from-pt-robots-warning' );

					if ( warning ) {
						warning.style.display = taxEl.dataset.disabledWarning ? 'none' : '';
					}
				}
			}
		};
		document.body.addEventListener( 'better-seo-taxonomy-support-changed', event => toggleWarnings( event.detail.taxonomy ) );

		// Prevents notice-removal checks before listeners are added.
		let init = false;

		const checkRobotsPT = event => {
			const postType   = event.target?.name.split( /(?:.+\[)(.+?)(?:])/ ).join( '' );
			const robotsType = event.target?.dataset.robots;

			if ( event.target.checked ) {
				robotsPostTypes[ robotsType ].add( postType );
				dispatchPosttypeRobotsChangedEvent( postType, robotsType );
			} else {
				if ( init ) {
					robotsPostTypes[ robotsType ].delete( postType );
					dispatchPosttypeRobotsChangedEvent( postType, robotsType );
				}
			}
		};

		for ( const el of document.querySelectorAll( '.better-seo-robots-post-types' ) ) {
			el.addEventListener( 'change', checkRobotsPT );
			_dispatchAtInteractive( el, 'change' );
		}

		const checkRobotsSite = event => {
			const robotsType = event.target?.dataset.robots;
			const checked    = event.target.checked;

			if ( checked || init ) {
				dispatchSiteRobotsChangedEvent( checked, robotsType );
			}
		};

		for ( const el of document.querySelectorAll( '.better-seo-robots-site' ) ) {
			el.addEventListener( 'change', checkRobotsSite );
			_dispatchAtInteractive( el, 'change' );
		}

		init = true;
	}

	// ─── ROBOTS SUPPORT ────────────────────────────────────────────────────────

	/**
	 * Initialises the robots support listeners that disable/enable post type and
	 * taxonomy robots checkboxes when post types or taxonomies are excluded.
	 *
	 * @return {void}
	 */
	function _initRobotsSupport() {

		const getCloneClassPT          = postType => BetterSeo.escapeString( `better-seo-disabled-post-type-input-clone-${postType}` );
		const postTypeHelpTemplate     = wp.template( 'better-seo-disabled-post-type-help' )();

		const getPostTypeRobotsSettings = postType => [
			document.getElementById( `${ _getSettingsId( 'noindex_post_types' ) }[${postType}]` ),
			document.getElementById( `${ _getSettingsId( 'nofollow_post_types' ) }[${postType}]` ),
			document.getElementById( `${ _getSettingsId( 'noarchive_post_types' ) }[${postType}]` ),
		].filter( el => el );

		const augmentPTRobots = event => {
			const { postType, set } = event.detail;

			if ( set.has( postType ) ) {
				for ( const element of getPostTypeRobotsSettings( postType ) ) {
					if ( ! element ) {
						continue;
					}

					const clone   = element.cloneNode( true );
					clone.type    = 'hidden';
					clone.value   = element.checked ? element.value : '';
					clone.id     += '-cloned';
					clone.classList.add( getCloneClassPT( postType ) );

					element.disabled               = true;
					element.dataset.disabledWarning = 1;

					const label = element.closest( 'label' );
					label.insertAdjacentHTML( 'beforeend', postTypeHelpTemplate );
					label.append( clone );
				}

				BetterSeoTT.triggerReset();
			} else {
				for ( const element of getPostTypeRobotsSettings( postType ) ) {
					if ( ! element || ! element.dataset.disabledWarning ) {
						continue;
					}

					element.closest( 'label' ).querySelector( '.better-seo-post-type-warning' ).remove();

					for ( const clone of document.querySelectorAll( `.${getCloneClassPT( postType )}` ) ) {
						clone.remove();
					}

					element.disabled               = false;
					element.dataset.disabledWarning = '';
				}
			}
		};
		document.body.addEventListener( 'better-seo-post-type-support-changed', augmentPTRobots );

		const taxonomyHelpTemplate   = wp.template( 'better-seo-disabled-taxonomy-help' )();
		const taxonomyPtHelpTemplate = wp.template( 'better-seo-disabled-taxonomy-from-pt-help' )();

		const getCloneClassTaxonomy     = taxonomy => BetterSeo.escapeString( `better-seo-disabled-taxonomy-input-clone-${taxonomy}` );

		const getTaxonomyRobotsSettings = taxonomy => [
			document.getElementById( `${ _getSettingsId( 'noindex_taxonomies' ) }[${taxonomy}]` ),
			document.getElementById( `${ _getSettingsId( 'nofollow_taxonomies' ) }[${taxonomy}]` ),
			document.getElementById( `${ _getSettingsId( 'noarchive_taxonomies' ) }[${taxonomy}]` ),
		].filter( el => el );

		const augmentTaxonomyRobots = event => {
			const { taxonomy, set, setPt, setAll } = event.detail;

			if ( setAll.has( taxonomy ) ) {
				for ( const element of getTaxonomyRobotsSettings( taxonomy ) ) {
					if ( ! element ) {
						continue;
					}

					const clone   = element.cloneNode( true );
					clone.type    = 'hidden';
					clone.value   = element.checked ? element.value : '';
					clone.id     += '-cloned';
					clone.classList.add( getCloneClassTaxonomy( taxonomy ) );

					element.disabled               = true;
					element.dataset.disabledWarning = 1;

					const label = element.closest( 'label' );

					if ( ! label.querySelector( '.better-seo-taxonomy-warning' ) ) {
						label.insertAdjacentHTML( 'beforeend', taxonomyHelpTemplate );
					}

					if ( ! label.querySelector( getCloneClassTaxonomy( taxonomy ) ) ) {
						label.append( clone );
					}
				}

				BetterSeoTT.triggerReset();
			} else {
				for ( const element of getTaxonomyRobotsSettings( taxonomy ) ) {
					if ( ! element || ! element.dataset.disabledWarning ) {
						continue;
					}

					element.closest( 'label' ).querySelector( '.better-seo-taxonomy-warning' )?.remove();

					for ( const clone of document.querySelectorAll( `.${getCloneClassTaxonomy( taxonomy )}` ) ) {
						clone.remove();
					}

					element.disabled               = false;
					element.dataset.disabledWarning = '';
				}
			}

			const taxEl = document.getElementById( `${ _getSettingsId( 'disabled_taxonomies' ) }[${taxonomy}]` );

			if ( setPt.has( taxonomy ) ) {
				if ( ! taxEl.closest( 'label' ).querySelector( '.better-seo-taxonomy-from-pt-warning' ) ) {
					taxEl.closest( 'label' ).insertAdjacentHTML( 'beforeend', taxonomyPtHelpTemplate );
					BetterSeoTT.triggerReset();
				}
			} else {
				taxEl.closest( 'label' ).querySelector( '.better-seo-taxonomy-from-pt-warning' )?.remove();
			}
		};
		document.body.addEventListener( 'better-seo-taxonomy-support-changed', augmentTaxonomyRobots );
	}

	// ─── WEBMASTERS ────────────────────────────────────────────────────────────

	/**
	 * Initialises the webmaster verification code inputs.
	 *
	 * Intercepts paste events to extract the content attribute value from
	 * pasted meta tag HTML, so users can paste the full meta tag directly.
	 *
	 * @return {void}
	 */
	function _initWebmastersInputs() {

		const webmasterNodes = [
			'google_verification',
			'bing_verification',
			'yandex_verification',
			'baidu_verification',
			'pint_verification',
		].map( name => document.getElementById( _getSettingsId( name ) ) );

		const trimScript = event => {
			const val = event.clipboardData && event.clipboardData.getData( 'text' ) || '';

			if ( val ) {
				// Extract the content attribute value if a full meta tag is pasted.
				const match = /<meta\b[^>]+?\bcontent=(['"])?([^'">\s]+)\1?[^>]*?>/i.exec( val );
				if ( match?.[2]?.length ) {
					event.stopPropagation();
					event.preventDefault();
					event.target.value = match[2];
					BetterSeoAys.registerChange();
				}
			}
		};

		for ( const el of webmasterNodes ) {
			el.addEventListener( 'paste', trimScript );
		}
	}

	// ─── SITEMAP INPUTS ────────────────────────────────────────────────────────

	/**
	 * Initialises the sitemap settings: optimized sitemap toggle and
	 * prerendering settings visibility based on cache + sitemap toggles.
	 *
	 * @return {void}
	 */
	function _initSitemapInputs() {

		const optimizedSitemapsToggle = document.getElementById( _getSettingsId( 'sitemaps_output' ) );
		const cacheSitemapsToggle     = document.getElementById( _getSettingsId( 'cache_sitemap' ) );

		const updateSitemapDisplay = event => {
			const sitemapsEnabled = !! event.target.checked;

			BetterSeoTabs.toggleTab( 'betterSeoSettings', 'better-seo-sitemaps-tab-style', sitemapsEnabled );

			document.getElementById( 'better-seo-sitemap-transient-cache-settings' )
				?.classList.toggle( 'hidden', ! sitemapsEnabled );
		};

		if ( optimizedSitemapsToggle ) {
			optimizedSitemapsToggle.addEventListener( 'change', updateSitemapDisplay );
			_dispatchAtInteractive( optimizedSitemapsToggle, 'change' );
		}

		const toggleCheckRegistry = new Map();

		const checkAllEnabled = event => {
			const prerenderingSettings = document.getElementById( 'better-seo-sitemap-prerendering-settings' );

			toggleCheckRegistry.set( event.target.name, !! event.target.checked );

			for ( const val of toggleCheckRegistry.values() ) {
				if ( ! val ) {
					prerenderingSettings?.classList.add( 'hidden' );
					return;
				}
			}

			prerenderingSettings?.classList.remove( 'hidden' );
		};

		for ( const toggle of [ optimizedSitemapsToggle, cacheSitemapsToggle ] ) {
			if ( toggle ) {
				toggle.addEventListener( 'change', checkAllEnabled );
				_dispatchAtInteractive( toggle, 'change' );
			}
		}
	}

	// ─── TAB NAVIGATION ────────────────────────────────────────────────────────

	/**
	 * Initialises the Better SEO settings page tab navigation stack.
	 *
	 * @return {void}
	 */
	function _initTabs() {
		BetterSeoTabs.initStack(
			'betterSeoSettings',
			{
				tabToggledEvent: new CustomEvent( 'better-seo-tab-toggled' ),
				HTMLClasses:     {
					wrapper:          'better-seo-nav-tab-wrapper',
					tabRadio:         'better-seo-nav-tab-radio',
					tabLabel:         'better-seo-nav-tab-label',
					activeTab:        'better-seo-nav-tab-active',
					activeTabContent: 'better-seo-nav-tab-content-active',
				},
				fixHistory: true,
			},
		);
	}

	// ─── INITIALISATION ────────────────────────────────────────────────────────

	/**
	 * Loads all Better SEO settings page listeners.
	 *
	 * Each initialiser is called in a try/catch so a single failure does not
	 * prevent the remaining sections from initialising.
	 *
	 * @return {void}
	 */
	function _loadSettings() {
		for ( const fn of [
			_initSubmit,
			_initGeneralSettings,
			_initTitleSettings,
			_initHomeGeneralListeners,
			_initHomeTitleSettings,
			_initHomeDescriptionSettings,
			_initHomeSocialSettings,
			_initHomeVisibilitySettings,
			_initPtaSettings,
			_initSocialSettings,
			_initSchemaSettings,
			_initRobotsInputs,
			_initRobotsSupport,
			_initWebmastersInputs,
			_initSitemapInputs,
			_initColorPicker,
		] ) {
			try {
				fn();
			} catch ( error ) {
				console.error( `BetterSeoSettings: Error in ${fn.name}:`, error );
			}
		}
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		/**
		 * Attaches the settings module to the 'better-seo-onload' event and
		 * initialises tabs immediately (before onload) to prevent layout shift.
		 * Called automatically on script load.
		 *
		 * @return {void}
		 */
		load: () => {
			// Move admin notices to after the header end element immediately to prevent layout shift.
			const headerEnd = document.querySelector( '.wp-header-end' );
			for ( const el of document.querySelectorAll(
				'div.updated, div.error, div.notice, .notice-error, .notice-warning, .notice-info',
			) ) {
				headerEnd.insertAdjacentElement( 'afterend', el );
			}

			document.body.addEventListener( 'better-seo-onload', _loadSettings );

			// Initialise tabs early — relies on a fallback event that better-seo-onload uses.
			_initTabs();
		},
		l10n,
	};

}( jQuery ) );

// Auto-initialise — registers the 'better-seo-onload' listener and initialises tabs immediately.
window.BetterSeoSettings.load();