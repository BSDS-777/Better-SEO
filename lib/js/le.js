/**
 * Better SEO — List Edit (LE) Module
 *
 * Integrates Better SEO SEO fields into the WordPress post and term list table
 * quick edit and bulk edit panels. Hijacks the WordPress inline edit functions
 * to inject title, description, canonical, visibility, and primary term inputs
 * when a quick edit row is opened.
 *
 * Exposed as: window.BetterSeoLe
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - Data element IDs (set by class-listedit.php):
 *       better-seo-le-data[{id}]             — field values and defaults
 *       better-seo-le-post-data[{id}]        — post-specific data (isFront, primaryTerms)
 *       better-seo-le-title-data[{id}]       — title state (refTitleLocked, defaultTitle, etc.)
 *       better-seo-le-description-data[{id}] — description state (refDescriptionLocked, etc.)
 *       better-seo-le-canonical-data[{id}]   — canonical state (urlStructure, preferredScheme, etc.)
 *   - Input IDs (set by quick-post.php / quick-term.php):
 *       better-seo-quick[doctitle]            — meta title input
 *       better-seo-quick[description]         — meta description textarea
 *       better-seo-quick[noindex]             — indexing select
 *       better-seo-quick[canonical]           — canonical URL input
 *   - Custom events consumed:
 *       'better-seo-onload'                   — fired by better-seo.js when UI is ready
 *       'betterSeoLeDispatchUpdate'           — fired by class-table.php after AJAX column update
 *       'better-seo-updated-primary-term'     — fired by pt-le.js when primary term changes
 *   - Custom events dispatched:
 *       'better-seo-le-updated'               — fired after list edit fields are refreshed
 */

'use strict';

/**
 * List edit integration module.
 *
 * @namespace BetterSeoLe
 */
window.BetterSeoLe = ( function () {

	/**
	 * Field values and defaults for the currently open quick edit row.
	 * Populated from the better-seo-le-data[{id}] element on each edit open.
	 *
	 * @type {Object.<string, {value: *, default: *, isSelect: boolean, placeholder: string}>|undefined}
	 */
	let fieldsData;

	/**
	 * Post-specific data for the currently open quick edit row.
	 * Populated from the better-seo-le-post-data[{id}] element on each edit open.
	 *
	 * @type {{ isFront: boolean, primaryTerms: Object }|undefined}
	 */
	let postData;

	/**
	 * The current edit context: 'post' for post quick edit, 'tax' for term quick edit.
	 *
	 * @type {string}
	 */
	let _editType = '';

	/**
	 * Debounced dispatcher for the 'better-seo-le-updated' event.
	 * 50ms delay balances visual smoothness and performance.
	 *
	 * @type {Function}
	 */
	const _dispatchUpdate = BetterSeoUtils.debounce(
		() => document.dispatchEvent( new CustomEvent( 'better-seo-le-updated' ) ),
		50,
	);

	/**
	 * Resets all Better SEO tooltips after list edit fields are refreshed.
	 *
	 * @return {void}
	 */
	function _updated() {
		BetterSeoTT.triggerReset();
	}

	// ─── FIELD VALUE POPULATION ────────────────────────────────────────────────

	/**
	 * Populates all Better SEO quick edit fields with the current post's stored values.
	 *
	 * For select elements, updates the selected option and replaces the default
	 * option label's %s placeholder with the actual default value.
	 * For text inputs, sets the value and placeholder directly.
	 *
	 * @return {void}
	 */
	function _setInlinePostValues() {
		for ( const option in fieldsData ) {
			const params  = fieldsData[ option ];
			const element = document.getElementById( `better-seo-quick[${option}]` );

			if ( ! element ) {
				continue;
			}

			if ( params.isSelect ) {
				BetterSeo.selectByValue( element, params.value );

				const defaultOption = element.querySelector( '[value="0"]' );
				if ( defaultOption ) {
					defaultOption.innerHTML = defaultOption.innerHTML.replace(
						'%s',
						BetterSeo.escapeString( BetterSeo.decodeEntities( params.default ) ),
					);
				}
			} else {
				element.value = BetterSeo.decodeEntities( params.value );

				if ( params.placeholder?.length ) {
					element.placeholder = BetterSeo.decodeEntities( params.placeholder );
				}
			}
		}
	}

	/**
	 * Populates all Better SEO quick edit fields for a term edit row.
	 * Delegates to _setInlinePostValues() as the field structure is identical.
	 *
	 * @return {void}
	 */
	function _setInlineTermValues() {
		return _setInlinePostValues();
	}

	// ─── POST VISIBILITY ───────────────────────────────────────────────────────

	/**
	 * Returns the current visibility state of the post in the given quick edit row.
	 *
	 * Reads the keep_private checkbox and post_password field from the inline
	 * edit wrap to determine whether the post is public, private, or password-protected.
	 *
	 * @param {number|string} id The post ID.
	 * @return {'public'|'private'|'password'} The current post visibility.
	 */
	function _getPostVisibility( id ) {

		const inlineEditWrap = document.getElementById( `edit-${id}` );

		let visibility = 'public';

		if ( inlineEditWrap?.querySelector( '[name=keep_private]' )?.checked ) {
			visibility = 'private';
		} else {
			const pass = inlineEditWrap?.querySelector( '[name=post_password]' )?.value;
			// WordPress bug workaround: if password type is set but value is falsy, treat as public.
			if ( pass?.length && '0' !== pass ) {
				visibility = 'password';
			}
		}

		return visibility;
	}

	/**
	 * Registers debounced listeners on the post password and keep-private fields
	 * to detect visibility changes in the quick edit row.
	 *
	 * The 20ms debounce prevents duplicate events that fire within a few milliseconds
	 * of each other due to WordPress's inline edit implementation.
	 *
	 * @param {number|string} id       The post ID.
	 * @param {Function}      callback The function to call on visibility change.
	 * @return {void}
	 */
	function _registerPostPrivacyListener( id, callback ) {

		const inlineEditWrap = document.getElementById( `edit-${id}` );

		callback = BetterSeoUtils.debounce( callback, 20 );

		inlineEditWrap?.querySelector( '[name=post_password]' )?.addEventListener( 'input', callback );
		inlineEditWrap?.querySelector( '[name=keep_private]' )?.addEventListener( 'click', callback );
	}

	// ─── TITLE INPUT ───────────────────────────────────────────────────────────

	/**
	 * Initialises the Better SEO title input for the given quick edit row.
	 *
	 * Registers the title input with BetterSeoTitle, loads stored state data,
	 * sets up visibility prefix listeners (for post edit), and attaches a
	 * default title listener that updates as the post/term name changes.
	 *
	 * @param {number|string} id The post or term ID.
	 * @return {void}
	 */
	function _prepareTitleInput( id ) {

		const titleId    = 'better-seo-quick[doctitle]';
		const titleInput = document.getElementById( titleId );

		if ( ! titleInput ) {
			return;
		}

		BetterSeoTitle.setInputElement( titleInput );

		const data = JSON.parse(
			document.getElementById( `better-seo-le-title-data[${id}]` )?.dataset.leTitle || '0',
		);

		if ( data ) {
			BetterSeoTitle.updateStateOf( titleId, 'allowReferenceChange', ! data.refTitleLocked );
			BetterSeoTitle.updateStateOf( titleId, 'defaultTitle',         data.defaultTitle.trim() );
			BetterSeoTitle.updateStateOf( titleId, 'addAdditions',         data.addAdditions );
			BetterSeoTitle.updateStateOf( titleId, 'additionValue',        data.additionValue.trim() );
			BetterSeoTitle.updateStateOf( titleId, 'additionPlacement',    data.additionPlacement );
		}

		if ( 'post' === _editType ) {
			const setTitleVisibilityPrefix = () => {
				const prefixValue = match ( _getPostVisibility( id ) ) {
					'password' => BetterSeoTitle.protectedPrefix,
					'private'  => BetterSeoTitle.privatePrefix,
					default    => '',
				};
				BetterSeoTitle.updateStateOf( titleId, 'prefixValue', prefixValue );
			};

			// Use a standard switch since match expressions aren't valid in all contexts
			const setTitleVisibilityPrefixFn = () => {
				let prefixValue = '';
				switch ( _getPostVisibility( id ) ) {
					case 'password':
						prefixValue = BetterSeoTitle.protectedPrefix;
						break;
					case 'private':
						prefixValue = BetterSeoTitle.privatePrefix;
						break;
					default:
						prefixValue = '';
				}
				BetterSeoTitle.updateStateOf( titleId, 'prefixValue', prefixValue );
			};

			_registerPostPrivacyListener( id, setTitleVisibilityPrefixFn );
			setTitleVisibilityPrefixFn();
		}

		const setDefaultTitle = event => {
			const target     = ( event.originalEvent || event ).target;
			const inputTitle = target.value?.trim() ?? '';

			let defaultTitle = (
				BetterSeoTitle.stripTitleTags
					? BetterSeo.stripTags( inputTitle )
					: inputTitle
			) || BetterSeoTitle.untitledTitle;

			if ( 'tax' === _editType ) {
				const termPrefix = data?.termPrefix?.trim() ?? '';

				if ( termPrefix.length ) {
					defaultTitle = window.isRtl
						? `${defaultTitle} ${termPrefix}`
						: `${termPrefix} ${defaultTitle}`;
				}
			}

			defaultTitle = BetterSeo.escapeString( BetterSeo.decodeEntities( defaultTitle.trim() ) );

			BetterSeoTitle.updateStateOf( titleId, 'defaultTitle', defaultTitle );
		};

		const inlineEditWrap = document.getElementById( `edit-${id}` );

		switch ( _editType ) {
			case 'post': {
				const postTitleInput = inlineEditWrap?.querySelector( '[name=post_title]' );

				if ( postTitleInput && ! postData.isFront ) {
					postTitleInput.addEventListener( 'input', setDefaultTitle );
					postTitleInput.dispatchEvent( new Event( 'input' ) );
				}
				break;
			}
			case 'tax': {
				const termNameInput = inlineEditWrap?.querySelector( '[name=name]' );

				if ( termNameInput ) {
					termNameInput.addEventListener( 'input', setDefaultTitle );
					termNameInput.dispatchEvent( new Event( 'input' ) );
				}
				break;
			}
		}

		BetterSeoTT.triggerReset();
	}

	// ─── DESCRIPTION INPUT ─────────────────────────────────────────────────────

	/**
	 * Initialises the Better SEO description input for the given quick edit row.
	 *
	 * @param {number|string} id The post or term ID.
	 * @return {void}
	 */
	function _prepareDescriptionInput( id ) {

		const descId    = 'better-seo-quick[description]';
		const descInput = document.getElementById( descId );

		if ( ! descInput ) {
			return;
		}

		BetterSeoDescription.setInputElement( descInput );

		const state = JSON.parse(
			document.getElementById( `better-seo-le-description-data[${id}]` )?.dataset.leDescription || '0',
		);

		if ( state ) {
			BetterSeoDescription.updateStateOf( descId, 'allowReferenceChange', ! state.refDescriptionLocked );
			BetterSeoDescription.updateStateOf( descId, 'defaultDescription',   state.defaultDescription.trim() );
		}

		BetterSeoTT.triggerReset();
	}

	// ─── VISIBILITY / CANONICAL INPUT ──────────────────────────────────────────

	/**
	 * Initialises the Better SEO canonical URL and robots indexing inputs for the
	 * given quick edit row.
	 *
	 * Handles dynamic canonical URL placeholder generation from the permalink
	 * structure, post/term slug inputs, parent slug resolution, author slug
	 * resolution, date field listeners, and robots indexing state synchronisation
	 * with the canonical placeholder visibility.
	 *
	 * @param {number|string} id The post or term ID.
	 * @return {void}
	 */
	function _prepareVisibilityInput( id ) {

		const indexId     = 'better-seo-quick[noindex]';
		const canonicalId = 'better-seo-quick[canonical]';

		const indexSelect    = document.getElementById( indexId );
		const canonicalInput = document.getElementById( canonicalId );

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
			document.getElementById( `better-seo-le-canonical-data[${id}]` )?.dataset.leCanonical || '0',
		);

		if ( state ) {
			BetterSeoCanonical.updateStateOf( canonicalId, 'allowReferenceChange', ! state.refCanonicalLocked );
			BetterSeoCanonical.updateStateOf( canonicalId, 'defaultCanonical',     state.defaultCanonical.trim() );
			BetterSeoCanonical.updateStateOf( canonicalId, 'preferredScheme',      state.preferredScheme.trim() );
			BetterSeoCanonical.updateStateOf( canonicalId, 'urlStructure',         state.urlStructure );
		}

		BetterSeoCanonical.enqueueTriggerUnregisteredInput( canonicalId );

		/**
		 * Updates the canonical placeholder visibility based on the current
		 * protection and noindex bitmask state.
		 *
		 * @return {void}
		 */
		const updateCanonicalPlaceholder = () => {
			BetterSeoCanonical.updateStateOf(
				canonicalId,
				'showUrlPlaceholder',
				! ( ( canonicalPhState & BPROTECTED ) || ( canonicalPhState & BNOINDEX ) ),
			);
			BetterSeoCanonical.updateStateOf(
				canonicalId,
				'urlDataParts',
				Object.fromEntries( urlDataParts.entries() ),
			);
		};

		const inlineEditWrap = document.getElementById( `edit-${id}` );

		if ( BetterSeoCanonical.usingPermalinks && canonicalInput && inlineEditWrap ) {
			switch ( _editType ) {
				case 'post': {
					// %pagename% is rewritten to %postname% in Meta\URI\Utils::get_url_permastruct().
					const writePostname = BetterSeoCanonical.structIncludes( canonicalId, '%postname%' );
					const writeDate     = BetterSeoCanonical.structIncludes( canonicalId, [ '%year%', '%monthnum%', '%day%', '%hour%', '%minute%', '%second%' ] );
					const writeTerm     = {};
					const writeAuthor   = BetterSeoCanonical.structIncludes( canonicalId, '%author%' );

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
						writeTerm[ taxonomy ] = BetterSeoCanonical.structIncludes( canonicalId, `%${taxonomy}%` );
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
								activeSlug = BetterSeoCanonical.sanitizeSlug( postSlug.substring( 0, 200 ) );
								// WordPress ignores '0' as a slug value.
								if ( '0' === activeSlug ) {
									activeSlug = '';
								}
							}

							if ( ! activeSlug.length && postTitle.length ) {
								activeSlug = BetterSeoCanonical.sanitizeSlug( postTitle.substring( 0, 200 ) );
							}

							// Fall back to the post ID if no slug can be determined.
							if ( ! activeSlug.length ) {
								activeSlug = id;
							}

							urlDataParts.set( '%postname%', [ ...parentSlugs, activeSlug ].join( '/' ) );
						}

						urlDataParts
							.set( '%post_id%', id )
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

					if ( writePostname ) {
						const postNameInput = inlineEditWrap.querySelector( '[name=post_name]' );
						const titleInput    = inlineEditWrap.querySelector( '[name=post_title]' );
						const parentIdInput = inlineEditWrap.querySelector( '[name=post_parent]' );

						const updatePostName = () => {
							postTitle = titleInput?.value    ?? '';
							postSlug  = postNameInput?.value ?? '';
							queueUpdateCanonical();
						};

						postNameInput?.addEventListener( 'input', updatePostName );
						titleInput?.addEventListener( 'input', updatePostName );
						updatePostName();

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
						const authorIdInput = inlineEditWrap.querySelector( '[name=post_author]' );

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
							inlineEditWrap.querySelector( '[name=aa]' ), // year
							inlineEditWrap.querySelector( '[name=mm]' ), // month
							inlineEditWrap.querySelector( '[name=jj]' ), // day
							inlineEditWrap.querySelector( '[name=hh]' ), // hour
							inlineEditWrap.querySelector( '[name=mn]' ), // minute
							inlineEditWrap.querySelector( '[name=ss]' ), // second
						];
						const useDateFields = ! dateFields.some( v => v === null );

						const getActiveDateValues = () => {
							const values = dateFields.map( field => field.value );
							// Month is 0-indexed in the Date constructor.
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
					break;
				}

				case 'tax': {
					const taxonomy     = inlineEditWrap.querySelector( 'input[name=taxonomy]' )?.value ?? '';
					const writeTaxonomy = BetterSeoCanonical.structIncludes( canonicalId, `%${taxonomy}%` );

					let termSlug    = '';
					let termName    = '';
					let parentSlugs = [];

					if ( writeTaxonomy ) {
						BetterSeoTermSlugs.store( state.parentTermSlugs, taxonomy );
						parentSlugs = state.parentTermSlugs.map( term => term.slug );
					}

					const updateCanonical = () => {
						if ( writeTaxonomy ) {
							let activeSlug = '';

							if ( termSlug.length ) {
								activeSlug = BetterSeoCanonical.sanitizeSlug( termSlug.substring( 0, 200 ) );
								// WordPress ignores '0' as a slug value.
								if ( '0' === activeSlug ) {
									activeSlug = '';
								}
							}

							if ( ! activeSlug.length && termName.length ) {
								activeSlug = BetterSeoCanonical.sanitizeSlug( termName.substring( 0, 200 ) );
							}

							// Fall back to the term ID if no slug can be determined.
							if ( ! activeSlug.length ) {
								activeSlug = id;
							}

							urlDataParts.set( `%${taxonomy}%`, [ ...parentSlugs, activeSlug ].join( '/' ) );
						}

						updateCanonicalPlaceholder();
					};

					const queueUpdateCanonical = BetterSeoUtils.debounce( updateCanonical, 1000 / 60 );

					if ( writeTaxonomy ) {
						const termNameInput = inlineEditWrap.querySelector( '[name=name]' );
						const termSlugInput = inlineEditWrap.querySelector( '[name=slug]' );

						const updateTermName = () => {
							termName = termNameInput?.value ?? '';
							termSlug = termSlugInput?.value ?? '';
							queueUpdateCanonical();
						};

						termSlugInput?.addEventListener( 'input', updateTermName );
						termNameInput?.addEventListener( 'input', updateTermName );
						updateTermName();
					}

					queueUpdateCanonical();
					break;
				}
			}
		}

		if ( indexSelect ) {
			if ( 'post' === _editType ) {
				const setRobotsDefaultIndexingState = BetterSeoUtils.debounce(
					() => {
						const defaultIndexOption = indexSelect.querySelector( '[value="0"]' );
						let indexDefaultValue    = '';

						switch ( _getPostVisibility( id ) ) {
							case 'password':
							case 'private':
								indexDefaultValue    = 'noindex';
								canonicalPhState    |= BPROTECTED;
								break;
							default:
							case 'public':
								indexDefaultValue    = fieldsData.noindex.default;
								canonicalPhState    &= ~BPROTECTED;
								break;
						}

						if ( defaultIndexOption ) {
							defaultIndexOption.innerHTML = indexSelect.dataset.defaultI18n.replace(
								'%s',
								BetterSeo.escapeString( BetterSeo.decodeEntities( indexDefaultValue ) ),
							);
						}

						updateCanonicalPlaceholder();
					},
					1000 / 60,
				);

				inlineEditWrap?.querySelector( '[name=post_password]' )
					?.addEventListener( 'input', () => setRobotsDefaultIndexingState() );
				inlineEditWrap?.querySelector( '[name=keep_private]' )
					?.addEventListener( 'change', () => setRobotsDefaultIndexingState() );

				setRobotsDefaultIndexingState();
			}

			const setRobotsIndexingState = value => {
				let type = '';

				switch ( +value ) {
					case 0:
						type = fieldsData.noindex.default;
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
	}

	// ─── PRIMARY TERM INPUT ────────────────────────────────────────────────────

	/**
	 * Initialises the primary term selector for the given quick edit row.
	 *
	 * @param {number|string} id The post ID.
	 * @return {void}
	 */
	function _prepareTermInput( id ) {
		window.BetterSeoPTLE._prepareQuickEditTerms( id, postData?.primaryTerms );
	}

	/**
	 * Initialises the primary term selector for the bulk edit panel.
	 *
	 * @return {void}
	 */
	function _prepareTermInputBulk() {
		window.BetterSeoPTLE._prepareBulkEditTerms();
	}

	// ─── EVENT LISTENERS ───────────────────────────────────────────────────────

	/**
	 * Registers the list edit event listeners.
	 * Called once when the 'better-seo-onload' event fires.
	 *
	 * @return {void}
	 */
	function _setListeners() {
		document.addEventListener( 'betterSeoLeDispatchUpdate', _dispatchUpdate );
		document.addEventListener( 'better-seo-le-updated',    _updated );
	}

	// ─── WORDPRESS INLINE EDIT HIJACKING ───────────────────────────────────────

	/**
	 * Wraps the WordPress inline edit functions to inject Better SEO fields
	 * when a quick edit or bulk edit row is opened.
	 *
	 * Wraps:
	 *   - window.inlineEditPost.edit   — post quick edit
	 *   - window.inlineEditTax.edit    — term quick edit
	 *   - window.inlineEditPost.setBulk — post bulk edit
	 *
	 * Each preparation function is called in a try/catch to prevent a single
	 * failure from blocking the remaining field initialisations.
	 *
	 * @return {void}
	 */
	function _hijackListeners() {

		const _oldInlineEditPost = window.inlineEditPost?.edit;

		if ( _oldInlineEditPost ) {
			window.inlineEditPost.edit = function ( id ) {

				const ret = _oldInlineEditPost.apply( this, arguments );

				if ( 'object' === typeof id ) {
					id = window.inlineEditPost?.getId( id );
				}

				if ( ! id ) {
					return ret;
				}

				_editType  = 'post';
				fieldsData = JSON.parse( document.getElementById( `better-seo-le-data[${id}]` )?.dataset.le || '0' ) || {};
				postData   = JSON.parse( document.getElementById( `better-seo-le-post-data[${id}]` )?.dataset.lePostData || '0' ) || {};

				for ( const fn of [ _setInlinePostValues, _prepareVisibilityInput, _prepareTitleInput, _prepareDescriptionInput, _prepareTermInput ] ) {
					try {
						fn( id );
					} catch ( error ) {
						console.error( `BetterSeoLe: Error in ${fn.name}:`, error );
					}
				}

				window.BetterSeoC?.resetCounterListener();

				return ret;
			};
		}

		const _oldInlineEditTax = window.inlineEditTax?.edit;

		if ( _oldInlineEditTax ) {
			window.inlineEditTax.edit = function ( id ) {

				const ret = _oldInlineEditTax.apply( this, arguments );

				if ( 'object' === typeof id ) {
					id = window.inlineEditTax?.getId( id );
				}

				if ( ! id ) {
					return ret;
				}

				_editType  = 'tax';
				fieldsData = JSON.parse( document.getElementById( `better-seo-le-data[${id}]` )?.dataset.le || '0' ) || {};

				for ( const fn of [ _setInlineTermValues, _prepareVisibilityInput, _prepareTitleInput, _prepareDescriptionInput ] ) {
					try {
						fn( id );
					} catch ( error ) {
						console.error( `BetterSeoLe: Error in ${fn.name}:`, error );
					}
				}

				window.BetterSeoC?.resetCounterListener();

				return ret;
			};
		}

		const _oldBulkEdit = window.inlineEditPost?.setBulk;

		if ( _oldBulkEdit ) {
			window.inlineEditPost.setBulk = function () {

				const ret = _oldBulkEdit.apply( this, arguments );

				for ( const fn of [ _prepareTermInputBulk ] ) {
					try {
						fn();
					} catch ( error ) {
						console.error( `BetterSeoLe: Error in ${fn.name}:`, error );
					}
				}

				return ret;
			};
		}
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		/**
		 * Attaches the list edit module to the 'better-seo-onload' event.
		 * Called automatically on script load.
		 *
		 * @return {void}
		 */
		load: () => {
			document.body.addEventListener( 'better-seo-onload', _setListeners );
			document.body.addEventListener( 'better-seo-onload', _hijackListeners );
		},
	};

}() );

// Auto-initialise — registers the 'better-seo-onload' listener immediately.
window.BetterSeoLe.load();