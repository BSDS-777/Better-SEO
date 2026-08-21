/**
 * Better SEO — Post Edit Module
 *
 * Manages all Better SEO SEO fields on the WordPress post edit screen,
 * including the classic editor and Gutenberg block editor. Handles title,
 * description, canonical URL, social meta, visibility/robots, tab navigation,
 * flex resize, and Gutenberg save event integration.
 *
 * Exposed as: window.BetterSeoPost
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - L10n object:    BetterSeoPostL10n (registered in class-loader.php:497)
 *   - L10n keys:      BetterSeoPostL10n.{params, states, nonces}
 *   - AJAX action:    better_seo_update_post_data (wp_ajax_better_seo_update_post_data in class-ajax.php:222)
 *   - Input IDs (set by post/settings.php):
 *       better-seo-title                  — meta title input
 *       better-seo-description            — meta description textarea
 *       better-seo-canonical              — canonical URL input
 *       better-seo-noindex                — indexing select
 *       better-seo-title-no-blogname      — remove site title checkbox
 *       better-seo-socialimage-url        — social image URL input
 *   - Social group ID: better-seo-social-singular
 *   - Data element IDs (set by class-input.php):
 *       better-seo-title-data_{id}        — title state data
 *       better-seo-description-data_{id}  — description state data
 *       better-seo-canonical-data_{id}    — canonical state data
 *       better-seo-social-data_{id}       — social meta state data
 *   - DOM IDs:
 *       better-seo-flex-inpost-tabs-wrapper — tab navigation wrapper
 *       better-seo-flex-inpost-tab-general  — general tab content panel
 *       better-seo-doing-it-right-wrap      — SEO bar metabox wrap
 *   - CSS classes:
 *       .better-seo-seo-bar               — SEO bar element
 *       .better-seo-ajax                  — AJAX loader element
 *       .better-seo-flex                  — flex layout elements
 *       .better-seo-flex-nav-name         — tab label text elements
 *   - Custom events consumed:
 *       'better-seo-onload'                        — fired when UI is ready (load phase)
 *       'better-seo-ready'                         — fired when UI is ready (ready phase)
 *       'better-seo-gutenberg-onsave'              — fired by gbc.js on post save
 *       'better-seo-updated-block-editor'          — fired by gbc.js on any post data change
 *       'better-seo-updated-block-editor-title'    — fired by gbc.js on title change
 *       'better-seo-updated-block-editor-visibility' — fired by gbc.js on visibility change
 *       'better-seo-flex-tab-toggled'              — fired by tabs.js on tab switch
 *       'better-seo-flex-resize'                   — fired on flex container resize
 *       'better-seo-updated-primary-term'          — fired by pt-le.js on primary term change
 */

'use strict';

/**
 * Post edit SEO fields module.
 *
 * @namespace BetterSeoPost
 */
window.BetterSeoPost = ( function () {

	/**
	 * Localisation data passed from PHP via wp_localize_script().
	 *
	 * @type {{ params: Object, states: Object, nonces: Object }}
	 */
	const l10n = BetterSeoPostL10n;

	/**
	 * The meta title input element ID.
	 *
	 * @type {string}
	 */
	const _titleId = 'better-seo-title';

	/**
	 * The meta description textarea element ID.
	 *
	 * @type {string}
	 */
	const _descId = 'better-seo-description';

	/**
	 * The canonical URL input element ID.
	 *
	 * @type {string}
	 */
	const _canonicalId = 'better-seo-canonical';

	/**
	 * The social meta input group ID.
	 *
	 * @type {string}
	 */
	const _socialGroup = 'better-seo-social-singular';

	// ─── FLEX RESIZE ───────────────────────────────────────────────────────────

	/**
	 * Initialises the ResizeObserver for the flex tab navigation wrapper.
	 *
	 * Hides tab name labels when the wrapper overflows its container,
	 * and restores them when space is available again. Uses requestAnimationFrame
	 * to batch DOM reads and prevent layout thrashing.
	 *
	 * @return {void}
	 */
	function _doFlexResizeListener() {

		if ( ! document.querySelector( '.better-seo-flex' ) ) {
			return;
		}

		const wrapper = document.getElementById( 'better-seo-flex-inpost-tabs-wrapper' );

		const overflowAnimationFrame = new Map();

		const calculateTextOverflow = target => {

			const innerWrap = target.querySelector( '.better-seo-flex-nav-tab-inner' );
			const navNames  = target.querySelectorAll( '.better-seo-flex-nav-name' );

			if ( innerWrap.clientWidth <= target.clientWidth ) {
				// Names fit — show them if not already shown.
				if ( +( target.dataset.displayedNames || 1 ) ) {
					return;
				}
				target.dataset.displayedNames = 1;
				for ( const element of navNames ) {
					element.style.display = null;
					BetterSeoUI.fadeIn( element );
				}
			} else {
				// Names overflow — hide them immediately (no animation to prevent layout shift).
				if ( ! +( target.dataset.displayedNames || 1 ) ) {
					return;
				}
				target.dataset.displayedNames = 0;
				for ( const element of navNames ) {
					element.style.display = 'none';
				}
			}

			if ( +target.dataset.displayedNames ) {
				if ( innerWrap.clientWidth > target.clientWidth ) {
					for ( const element of navNames ) {
						element.style.display = 'none';
					}
					target.dataset.displayedNames = 0;
				} else {
					// Loop once to confirm — browser may be too slow to notice the offset change.
					setTimeout(
						() => {
							cancelAnimationFrame( overflowAnimationFrame.get( target.id ) );
							overflowAnimationFrame.set( target.id, requestAnimationFrame( () => calculateTextOverflow( target ) ) );
						},
						1000 / 144, // ~144hz
					);
				}
			}
		};

		const prepareCalculateTextOverflow = event => {
			const target = event.detail.target || wrapper;
			if ( target ) {
				overflowAnimationFrame.set( target.id, requestAnimationFrame( () => calculateTextOverflow( target ) ) );
			}
		};

		window.addEventListener( 'better-seo-flex-resize', prepareCalculateTextOverflow );

		const triggerResize = target => {
			window.dispatchEvent( new CustomEvent(
				'better-seo-flex-resize',
				{
					bubbles:    false,
					cancelable: false,
					detail:     { target },
				},
			) );
		};

		let resizeAnimationFrame = {};

		const resizeObserver = new ResizeObserver( entries => {
			for ( const entry of entries ) {
				const target = entry.target;
				cancelAnimationFrame( resizeAnimationFrame[ target.id ] );
				resizeAnimationFrame[ target.id ] = requestAnimationFrame( () => {
					target.dataset.lastWidth ||= 0;

					if ( +target.clientWidth !== +target.dataset.lastWidth ) {
						target.dataset.lastWidth = target.clientWidth;
						triggerResize( target );
					}
				} );
			}
		} );

		if ( wrapper ) {
			resizeObserver.observe( wrapper );
		}

		triggerResize();
	}

	// ─── TAB NAVIGATION ────────────────────────────────────────────────────────

	/**
	 * Initialises the Better SEO flex tab navigation stack for the post edit screen.
	 *
	 * @return {void}
	 */
	function _initTabs() {
		BetterSeoTabs.initStack(
			'betterSeoSettings',
			{
				tabToggledEvent: new CustomEvent( 'better-seo-flex-tab-toggled' ),
				HTMLClasses:     {
					wrapper:          'better-seo-flex-nav-tab-wrapper',
					tabRadio:         'better-seo-flex-nav-tab-radio',
					tabLabel:         'better-seo-flex-nav-tab-label',
					activeTab:        'better-seo-flex-tab-active',
					activeTabContent: 'better-seo-flex-tab-content-active',
				},
				fixHistory: true,
			},
		);
	}

	// ─── POST VISIBILITY ───────────────────────────────────────────────────────

	/**
	 * Returns the current post visibility from the Classic Editor visibility fields.
	 *
	 * Handles the WordPress bug where a falsy password value still shows 'password' type.
	 *
	 * @return {'public'|'private'|'password'} The current post visibility.
	 */
	function _getClassicVisibility() {

		let visibility = [ ...document.getElementsByName( 'visibility' ) ].filter( e => e.checked )?.[0]?.value;

		// WordPress bug: password type with falsy value should be treated as public.
		if ( 'password' === visibility ) {
			const val = document.getElementById( 'post_password' )?.value;
			if ( val?.length && '0' === val ) {
				visibility = 'public';
			}
		}

		return visibility;
	}

	/**
	 * Registers a callback to be called whenever the post visibility changes.
	 *
	 * Listens to both the Gutenberg block editor visibility event and the
	 * Classic Editor visibility save button click. The Classic Editor callback
	 * is debounced at 20ms to prevent duplicate events from password field toggling.
	 *
	 * @param {Function} callback The function to call with the new visibility value.
	 * @return {void}
	 */
	function _registerPostPrivacyListener( callback ) {

		// Block Editor — visibility change event from gbc.js.
		document.addEventListener( 'better-seo-updated-block-editor-visibility', event => callback( event.detail.value ) );

		// Classic Editor — debounced to prevent duplicate events from password field toggling.
		const debouncedCallback = BetterSeoUtils.debounce( callback, 20 );

		document.querySelector( '#visibility .save-post-visibility' )
			?.addEventListener( 'click', () => debouncedCallback( _getClassicVisibility() ) );
	}

	// ─── VISIBILITY / CANONICAL LISTENERS ─────────────────────────────────────

	/**
	 * Initialises the canonical URL placeholder and robots indexing state listeners.
	 *
	 * Handles dynamic canonical URL generation from the permalink structure,
	 * post/term slug inputs, parent slug resolution, author slug resolution,
	 * date field listeners, and robots indexing state synchronisation.
	 *
	 * @return {void}
	 */
	function _initVisibilityListeners() {

		const noindexSelect  = document.getElementById( 'better-seo-noindex' );
		const canonicalInput = document.getElementById( 'better-seo-canonical' );

		const urlDataParts = new Map();

		/**
		 * Bitmask flags for canonical placeholder visibility.
		 * BPROTECTED: post is password-protected or private.
		 * BNOINDEX:   post is set to noindex.
		 */
		const BPROTECTED = 0b01;
		const BNOINDEX   = 0b10;

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

		const updateCanonicalPlaceholder = () => {
			BetterSeoCanonical.updateStateOf(
				_canonicalId,
				'showUrlPlaceholder',
				! ( ( canonicalPhState & BPROTECTED ) || ( canonicalPhState & BNOINDEX ) ),
			);
			BetterSeoCanonical.updateStateOf(
				_canonicalId,
				'urlDataParts',
				Object.fromEntries( urlDataParts.entries() ),
			);
		};

		if ( BetterSeoCanonical.usingPermalinks && canonicalInput ) {
			// %pagename% is rewritten to %postname% in Meta\URI\Utils::get_url_permastruct().
			const writePostname = BetterSeoCanonical.structIncludes( _canonicalId, '%postname%' );
			const writeDate     = BetterSeoCanonical.structIncludes( _canonicalId, [ '%year%', '%monthnum%', '%day%', '%hour%', '%minute%', '%second%' ] );
			const writeTerm     = {};
			const writeAuthor   = BetterSeoCanonical.structIncludes( _canonicalId, '%author%' );

			let postSlug    = '';
			let postTitle   = '';
			let authorSlug  = '';
			let dateString  = '';
			let parentSlugs = [];
			let termSlugs   = [];

			if ( writePostname ) {
				BetterSeoPostSlugs.store( state.parentPostSlugs );
				parentSlugs = state.parentPostSlugs.map( post => post.slug );
			}

			for ( const taxonomy of state.supportedTaxonomies ) {
				writeTerm[ taxonomy ] = BetterSeoCanonical.structIncludes( _canonicalId, `%${taxonomy}%` );
			}

			for ( const [ taxonomy, terms ] of Object.entries( state.parentTermSlugs ) ) {
				BetterSeoTermSlugs.store( terms, taxonomy );
				termSlugs[ taxonomy ] = terms.map( term => term.slug );
			}

			if ( writeAuthor ) {
				BetterSeoAuthorSlugs.store( state.authorSlugs );
				authorSlug = state.authorSlugs?.[0]?.slug ?? '';
			}

			const updateCanonical = () => {
				if ( writePostname ) {
					let activeSlug = '';

					if ( postSlug.length ) {
						// WordPress trims post names to the first 200 characters.
						activeSlug = BetterSeoCanonical.sanitizeSlug( postSlug.substring( 0, 200 ) );
						// WordPress ignores '0' as a slug value.
						if ( '0' === activeSlug ) {
							activeSlug = '';
						}
					}

					// Slug falls back to the title if not set.
					if ( ! activeSlug.length && postTitle.length ) {
						activeSlug = BetterSeoCanonical.sanitizeSlug( postTitle.substring( 0, 200 ) );
					}

					// Fall back to the post ID if no slug can be determined.
					if ( ! activeSlug.length ) {
						activeSlug = l10n.params.id;
					}

					urlDataParts.set( '%postname%', [ ...parentSlugs, activeSlug ].join( '/' ) );
				}

				urlDataParts
					.set( '%post_id%', l10n.params.id )
					.set( '%author%',  authorSlug );

				if ( writeDate ) {
					const date    = new Date( dateString );
					const padDate = v => String( v ).padStart( 2, '0' );

					urlDataParts
						.set( '%year%',     date.getFullYear() )
						.set( '%monthnum%', padDate( date.getMonth() + 1 ) )
						.set( '%day%',      padDate( date.getDate() ) )
						.set( '%hour%',     padDate( date.getHours() ) )
						.set( '%minute%',   padDate( date.getMinutes() ) )
						.set( '%second%',   padDate( date.getSeconds() ) );
				}

				for ( const taxonomy in writeTerm ) {
					if ( writeTerm[ taxonomy ] ) {
						urlDataParts.set(
							`%${taxonomy}%`,
							Object.values( termSlugs[ taxonomy ] ?? {} ).join( '/' ),
						);
					}
				}

				updateCanonicalPlaceholder();
			};

			const queueUpdateCanonical = BetterSeoUtils.debounce( updateCanonical, 1000 / 60 );

			// Block Editor — listen for post data changes from gbc.js.
			document.addEventListener(
				'better-seo-updated-block-editor',
				async event => {
					switch ( event.detail.type ) {
						case 'title':
							if ( writePostname ) {
								postTitle = event.detail.postData.get( 'title' );
								queueUpdateCanonical();
							}
							break;
						case 'slug':
							if ( writePostname ) {
								postSlug = event.detail.postData.get( 'slug' );
								queueUpdateCanonical();
							}
							break;
						case 'parent':
							if ( writePostname ) {
								parentSlugs = await BetterSeoPostSlugs.get( event.detail.postData.get( 'parent' ) );
								queueUpdateCanonical();
							}
							break;
						case 'author':
							if ( writeAuthor ) {
								authorSlug = await BetterSeoAuthorSlugs.get( event.detail.postData.get( 'author' ) );
								queueUpdateCanonical();
							}
							break;
						case 'date':
							if ( writeDate ) {
								dateString = new Date( event.detail.postData.get( 'date' ) ).toISOString();
								queueUpdateCanonical();
							}
							break;
					}
				},
			);

			if ( Object.values( writeTerm ).includes( true ) ) {
				const updateParentTermSlugsViaPrimary = BetterSeoUtils.debounce(
					async event => {
						const taxonomy = event.detail.taxonomy;

						if ( writeTerm[ taxonomy ] ) {
							termSlugs[ taxonomy ] = await BetterSeoTermSlugs.get( event.detail.id, taxonomy );
							queueUpdateCanonical();
						}
					},
					100,
				);
				document.addEventListener( 'better-seo-updated-primary-term', updateParentTermSlugsViaPrimary );
			}

			// Classic Editor only.
			if ( ! l10n.params.isBlockEditor ) {
				if ( writePostname ) {
					const editSlugBox   = document.getElementById( 'edit-slug-box' );
					const postNameInput = document.getElementById( 'post_name' );
					const titleInput    = document.getElementById( 'title' );
					const parentIdInput = document.getElementById( 'parent_id' );

					const updatePostName = () => {
						postTitle = titleInput?.value    ?? '';
						postSlug  = postNameInput?.value ?? document.getElementById( 'editable-post-name-full' )?.innerText ?? '';
						queueUpdateCanonical();
					};

					titleInput?.addEventListener( 'input', updatePostName );
					postNameInput?.addEventListener( 'input', updatePostName );
					updatePostName();

					if ( editSlugBox ) {
						// Observe the slug box for DOM changes — more reliable than jQuery callbacks.
						new MutationObserver( mutationList => {
							for ( const mutation of mutationList ) {
								if ( mutation.addedNodes.entries().some(
									( [ , node ] ) => 'editable-post-name-full' === node.id,
								) ) {
									updatePostName();
									break;
								}
							}
						} ).observe( editSlugBox, { childList: true, subtree: true } );
					}

					if ( parentIdInput ) {
						const updateParentSlug = BetterSeoUtils.debounce(
							async () => {
								parentSlugs = await BetterSeoPostSlugs.get( parentIdInput.value );
								queueUpdateCanonical();
							},
							100,
						);
						parentIdInput.addEventListener( 'input', updateParentSlug );
						updateParentSlug();
					}
				}

				if ( writeAuthor ) {
					const authorIdInput = document.getElementById( 'post_author_override' )
						?? document.getElementById( 'post_author' );

					if ( authorIdInput ) {
						const updateAuthor = BetterSeoUtils.debounce(
							async () => {
								authorSlug = await BetterSeoAuthorSlugs.get( authorIdInput.value );
								queueUpdateCanonical();
							},
							100,
						);
						authorIdInput.addEventListener( 'input', updateAuthor );
						updateAuthor();
					}
				}

				if ( writeDate ) {
					const dateFields = [
						document.getElementById( 'aa' ), // year
						document.getElementById( 'mm' ), // month
						document.getElementById( 'jj' ), // day
						document.getElementById( 'hh' ), // hour
						document.getElementById( 'mn' ), // minute
						document.getElementById( 'ss' ), // second
					];
					const useDateFields = ! dateFields.some( v => v === null );

					const getActiveDateValues = () => {
						const values = dateFields.map( field => field.value );
						// Revert WordPress's 0-index month compensation.
						if ( values[1] ) {
							--values[1];
						}
						return values.map( v => v ?? '00' );
					};

					const updateDateString = () => {
						dateString = useDateFields
							? new Date( ...getActiveDateValues() ).toISOString()
							: state.publishDate;
						queueUpdateCanonical();
					};

					for ( const field of dateFields ) {
						field?.addEventListener( 'change', updateDateString );
					}

					updateDateString();
				}

				queueUpdateCanonical();
			}
		}

		if ( noindexSelect ) {
			const setRobotsDefaultIndexingState = visibility => {
				const defaultIndexOption = noindexSelect.querySelector( '[value="0"]' );
				let indexDefaultValue    = '';

				switch ( visibility ) {
					case 'password':
					case 'private':
						indexDefaultValue  = 'noindex';
						canonicalPhState  |= BPROTECTED;
						break;
					default:
					case 'public':
						indexDefaultValue  = noindexSelect.dataset.defaultUnprotected;
						canonicalPhState  &= ~BPROTECTED;
						break;
				}

				if ( defaultIndexOption ) {
					defaultIndexOption.innerHTML = noindexSelect.dataset.defaultI18n.replace(
						'%s',
						BetterSeo.escapeString( BetterSeo.decodeEntities( indexDefaultValue ) ),
					);
				}

				updateCanonicalPlaceholder();
			};

			_registerPostPrivacyListener( setRobotsDefaultIndexingState );

			if ( l10n.states.isPrivate ) {
				setRobotsDefaultIndexingState( 'private' );
			} else if ( l10n.states.isProtected ) {
				setRobotsDefaultIndexingState( 'password' );
			} else {
				setRobotsDefaultIndexingState( 'public' );
			}

			const setRobotsIndexingState = value => {
				let type = '';

				switch ( +value ) {
					case 0:
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

			noindexSelect.addEventListener( 'change', event => setRobotsIndexingState( event.target.value ) );
			setRobotsIndexingState( noindexSelect.value );
		}
	}

	// ─── TITLE LISTENERS ───────────────────────────────────────────────────────

	/**
	 * Initialises the Better SEO title input and its state listeners.
	 *
	 * Loads stored title state, registers the "remove site title" checkbox listener,
	 * sets up visibility prefix listeners, and attaches default title update listeners
	 * for both the Classic Editor title input and the Gutenberg block editor.
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
			BetterSeoTitle.updateStateOf( _titleId, 'defaultTitle',         state.defaultTitle );
			BetterSeoTitle.updateStateOf( _titleId, 'addAdditions',         state.addAdditions );
			BetterSeoTitle.updateStateOf( _titleId, 'additionValue',        state.additionValue );
			BetterSeoTitle.updateStateOf( _titleId, 'additionPlacement',    state.additionPlacement );
		}

		const updateTitleAdditions = event => {
			let addAdditions = ! event.target.checked;

			if ( l10n.params.additionsForcedDisabled ) {
				addAdditions = false;
			} else if ( l10n.params.additionsForcedEnabled ) {
				addAdditions = true;
			}

			BetterSeoTitle.updateStateOf( _titleId, 'addAdditions', addAdditions );
		};

		const blogNameTrigger = document.getElementById( 'better-seo-title-no-blogname' );
		if ( blogNameTrigger ) {
			blogNameTrigger.addEventListener( 'change', updateTitleAdditions );
			blogNameTrigger.dispatchEvent( new Event( 'change' ) );
		}

		const setTitleVisibilityPrefix = visibility => {
			let prefixValue = '';

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

			BetterSeoTitle.updateStateOf( _titleId, 'prefixValue', prefixValue );
		};

		_registerPostPrivacyListener( setTitleVisibilityPrefix );

		if ( l10n.states.isPrivate ) {
			setTitleVisibilityPrefix( 'private' );
		} else if ( l10n.states.isProtected ) {
			setTitleVisibilityPrefix( 'password' );
		}

		const updateDefaultTitle = val => {
			val = val?.trim() ?? '';

			BetterSeoTitle.updateStateOf(
				_titleId,
				'defaultTitle',
				( BetterSeoTitle.stripTitleTags ? BetterSeo.stripTags( val ) : val ) || BetterSeoTitle.untitledTitle,
			);
		};

		// The homepage uses a static preset value — only update for non-front pages.
		if ( ! l10n.params.isFront ) {
			// Classic Editor title input (extra-specific selector to avoid targeting other inputs).
			document.querySelector( '#titlewrap #title' )
				?.addEventListener( 'input', event => updateDefaultTitle( event.target.value ) );

			// Block Editor title change event from gbc.js.
			document.addEventListener(
				'better-seo-updated-block-editor-title',
				event => updateDefaultTitle( event.detail.value ),
			);
		}

		BetterSeoTitle.enqueueUnregisteredInputTrigger( _titleId );
	}

	// ─── DESCRIPTION LISTENERS ─────────────────────────────────────────────────

	/**
	 * Initialises the Better SEO description input and its state listeners.
	 *
	 * Loads stored description state and registers visibility listeners to
	 * disable the default description for private/password-protected posts.
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
			BetterSeoDescription.updateStateOf( _descId, 'allowReferenceChange', ! state.refDescriptionLocked );
			BetterSeoDescription.updateStateOf( _descId, 'defaultDescription',   state.defaultDescription.trim() );
		}

		BetterSeoDescription.enqueueUnregisteredInputTrigger( _descId );

		const setDescriptionVisibility = visibility => {
			const oldUseDefaultDescription = BetterSeoDescription.getStateOf( _descId, 'useDefaultDescription' );
			let useDefaultDescription      = true;

			switch ( visibility ) {
				case 'password':
				case 'private':
					useDefaultDescription = false;
					break;
				default:
				case 'public':
					useDefaultDescription = true;
					break;
			}

			if ( useDefaultDescription !== oldUseDefaultDescription ) {
				BetterSeoDescription.updateStateOf( _descId, 'useDefaultDescription', useDefaultDescription );
			}
		};

		_registerPostPrivacyListener( setDescriptionVisibility );

		if ( l10n.states.isPrivate ) {
			setDescriptionVisibility( 'private' );
		} else if ( l10n.states.isProtected ) {
			setDescriptionVisibility( 'password' );
		}
	}

	// ─── SOCIAL LISTENERS ──────────────────────────────────────────────────────

	/**
	 * Initialises the Better SEO social meta inputs and their state.
	 *
	 * Loads stored social state data and registers the social input instance
	 * with the title and description inputs for cross-field synchronisation.
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
		BetterSeoSocial.updateStateOf(
			_socialGroup,
			'inputLocks',
			{
				ogTitle: groupData.og.state?.titleLock ?? false,
				twTitle: groupData.tw.state?.titleLock ?? false,
				ogDesc:  groupData.og.state?.descLock  ?? false,
				twDesc:  groupData.tw.state?.descLock  ?? false,
			},
		);
	}

	// ─── GENERAL LISTENERS ─────────────────────────────────────────────────────

	/**
	 * Registers general input trigger listeners for tab switches and flex resize events.
	 *
	 * Ensures title and description counters/placeholders are re-rendered when
	 * the general tab becomes visible or the container is resized.
	 *
	 * @return {void}
	 */
	function _initGeneralListeners() {

		const enqueueGeneralInputListeners = () => {
			BetterSeoTitle.enqueueUnregisteredInputTrigger( _titleId );
			BetterSeoDescription.enqueueUnregisteredInputTrigger( _descId );
		};

		document.getElementById( 'better-seo-flex-inpost-tab-general' )
			?.addEventListener( 'better-seo-flex-tab-toggled', enqueueGeneralInputListeners );

		window.addEventListener( 'better-seo-flex-resize', enqueueGeneralInputListeners );
	}

	// ─── GUTENBERG META BOX UPDATE ─────────────────────────────────────────────

	/**
	 * Registers the Gutenberg save event listener to update the SEO bar and
	 * meta field defaults after a post is saved in the block editor.
	 *
	 * Sends an AJAX request to better_seo_update_post_data to fetch fresh
	 * SEO data and updates the description, social defaults, image placeholder,
	 * and SEO bar with the response.
	 *
	 * @return {void}
	 */
	function _initUpdateMetaBox() {

		if ( ! l10n.params.isBlockEditor ) {
			return;
		}

		const seobar          = document.querySelector( '.better-seo-seo-bar' );
		const seobarAjaxLoader = document.querySelector( '#better-seo-doing-it-right-wrap .better-seo-ajax' );
		const imageUrl        = document.getElementById( 'better-seo-socialimage-url' );

		const _ogDescription = BetterSeoSocial.getInputInstance( _socialGroup )?.inputs?.ogDesc;
		const _twDescription = BetterSeoSocial.getInputInstance( _socialGroup )?.inputs?.twDesc;

		const getData = {
			seobar:          !! seobar,
			metadescription: !! document.getElementById( _descId ),
			ogdescription:   !! _ogDescription,
			twdescription:   !! _twDescription,
			imageurl:        !! imageUrl,
		};

		const onSuccess = response => {

			response = BetterSeo.convertJSONResponse( response );

			// Sync the fade timing with the SEO bar fade.
			const fadeTime = 75;

			setTimeout(
				() => {
					BetterSeoDescription.updateStateOf( _descId, 'defaultDescription', response.data.metadescription.trim() );

					const socialDefaults  = BetterSeoSocial.getStateOf( _socialGroup, 'defaults' );
					socialDefaults.ogDesc = response.data.ogdescription.trim();
					socialDefaults.twDesc = response.data.twdescription.trim();
					BetterSeoSocial.updateStateOf( _socialGroup, 'defaults', socialDefaults );

					if ( imageUrl ) {
						imageUrl.placeholder = BetterSeo.decodeEntities( response.data.imageurl );
						imageUrl.dispatchEvent( new Event( 'change' ) );
						BetterSeoTT.triggerReset();
					}

					BetterSeoAys.reset();
				},
				fadeTime,
			);

			if ( seobar ) {
				BetterSeoUI.fadeOut(
					seobar,
					fadeTime,
					() => {
						if ( seobarAjaxLoader ) {
							BetterSeo.unsetAjaxLoader( seobarAjaxLoader, true );
						}
						seobar.innerHTML = response.data.seobar;

						BetterSeoUI.fadeIn(
							seobar,
							fadeTime,
							() => BetterSeoTT.triggerReset(),
						);
					},
				);
			}
		};

		const onFailure = () => {
			if ( seobarAjaxLoader ) {
				BetterSeo.unsetAjaxLoader( seobarAjaxLoader, false );
			}
		};

		document.addEventListener(
			'better-seo-gutenberg-onsave',
			() => {
				if ( seobarAjaxLoader ) {
					BetterSeo.resetAjaxLoader( seobarAjaxLoader );
					BetterSeo.setAjaxLoader( seobarAjaxLoader );
				}

				wp.ajax.send(
					'better_seo_update_post_data',
					{
						data: {
							nonce:   l10n.nonces.edit_post[ l10n.params.id ],
							post_id: l10n.params.id,
							get:     getData,
						},
						timeout: 7000,
					},
				).done( onSuccess ).fail( onFailure );
			},
		);
	}

	// ─── INITIALISATION ────────────────────────────────────────────────────────

	/**
	 * Loads all Better SEO post edit field listeners.
	 *
	 * Each initialiser is called in a try/catch so a single failure does not
	 * prevent the remaining fields from initialising.
	 *
	 * @return {void}
	 */
	function _loadSettings() {
		for ( const fn of [
			_initVisibilityListeners,
			_initTitleListeners,
			_initDescriptionListeners,
			_initSocialListeners,
			_initGeneralListeners,
		] ) {
			try {
				fn();
			} catch ( error ) {
				console.error( `BetterSeoPost: Error in ${fn.name}:`, error );
			}
		}
	}

	/**
	 * Runs post-load setup: flex resize, tab navigation, and Gutenberg meta box update.
	 *
	 * @return {void}
	 */
	function _readySettings() {
		_doFlexResizeListener();
		_initTabs();
		_initUpdateMetaBox();
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		/**
		 * Attaches the post edit module to the 'better-seo-onload' and 'better-seo-ready' events.
		 * Called automatically on script load.
		 *
		 * @return {void}
		 */
		load: () => {
			document.body.addEventListener( 'better-seo-onload', _loadSettings );
			document.body.addEventListener( 'better-seo-ready',  _readySettings );
		},
		l10n,
	};

}() );

// Auto-initialise — registers the 'better-seo-onload' and 'better-seo-ready' listeners immediately.
window.BetterSeoPost.load();