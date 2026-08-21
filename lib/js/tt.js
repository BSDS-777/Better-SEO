/**
 * Better SEO — Tooltip Module
 *
 * Provides accessible, pointer-aware tooltip overlays for Better SEO admin UI
 * elements. Supports mouse, touch, and keyboard (focus) activation, animated
 * arrow positioning, boundary-aware horizontal placement, and programmatic
 * tooltip creation, removal, and update.
 *
 * Exposed as: window.BetterSeoTT
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - No direct AJAX calls — purely a UI tooltip manager.
 *   - CSS class names are defined as constants in this module and must match
 *     the classes output by PHP view templates and class-input.php:
 *       better-seo-tooltip           — tooltip root element
 *       better-seo-tooltip-item      — element that triggers a tooltip
 *       better-seo-tooltip-wrap      — wrapper that receives pointer events
 *       better-seo-tooltip-super-wrap — outer wrapper for multi-item groups
 *       better-seo-tooltip-text      — inner text span
 *       better-seo-tooltip-text-wrap — text container span
 *       better-seo-tooltip-boundary  — explicit boundary element
 *       better-seo-tooltip-arrow     — arrow indicator element
 *       better-seo-tooltip-down      — modifier class for downward-facing tooltips
 *   - data attributes used:
 *       data-desc          — tooltip description text (set by PHP, updated by JS)
 *       data-has-tooltip   — presence flag set while tooltip is active
 *       data-prevented-click — click lock flag set during tooltip activation
 *       data-adjust        — horizontal adjustment value stored on tooltip element
 *       data-last-pagex    — last known pointer X position stored on tooltip element
 *   - Custom events dispatched:
 *       'better-seo-tooltip-reset'   — triggers re-initialisation of tooltip wraps
 *       'better-seo-tooltip-update'  — triggers description refresh on tooltip items
 *   - Custom events consumed:
 *       'better-seo-ready'           — fires _initToolTips on page ready
 *       'better-seo-tooltip-reset'   — re-runs init() to attach listeners to new wraps
 *       'better-seo-tooltip-update'  — updates tooltip description text
 *   - Dependencies: none (this module does not depend on BetterSeo.*)
 */

'use strict';

/**
 * Tooltip manager.
 *
 * @namespace BetterSeoTT
 */
window.BetterSeoTT = ( function () {

	// ─── CSS CLASS CONSTANTS ───────────────────────────────────────────────────

	/** @type {string} Base tooltip CSS class prefix. */
	const _ttBase = 'better-seo-tooltip';

	/**
	 * Map of semantic name → CSS class string.
	 *
	 * @type {Object.<string, string>}
	 */
	const ttNames = {
		base:      _ttBase,
		item:      `${_ttBase}-item`,
		wrap:      `${_ttBase}-wrap`,
		superWrap: `${_ttBase}-super-wrap`,
		text:      `${_ttBase}-text`,
		textWrap:  `${_ttBase}-text-wrap`,
		boundary:  `${_ttBase}-boundary`,
		arrow:     `${_ttBase}-arrow`,
	};

	/**
	 * Map of semantic name → CSS selector string (dot-prefixed).
	 *
	 * @type {Object.<string, string>}
	 */
	const ttSelectors = Object.fromEntries(
		Object.entries( ttNames ).map( ( [ i, v ] ) => [ i, `.${v}` ] ),
	);

	// ─── ACTIVE TOOLTIP HANDLES ────────────────────────────────────────────────

	/**
	 * Event handler callbacks for active tooltip elements.
	 *
	 * @type {Object.<string, Function>}
	 */
	const _activeToolTipHandles = {

		/**
		 * Updates the tooltip description text when a 'better-seo-tooltip-update' event fires.
		 *
		 * @param {CustomEvent} event
		 * @return {void}
		 */
		updateDesc: event => {
			if ( ! event.target.classList.contains( ttNames.item ) ) {
				return;
			}

			const tooltipText = event.target.querySelector( ttSelectors.text );
			if ( tooltipText instanceof Element ) {
				tooltipText.innerHTML = event.target.dataset.desc;
				event.target.dispatchEvent( new Event( 'mousemove' ) );
			}
		},

		/**
		 * Handles pointer enter: reads the description, stores it on the element,
		 * clears the native title attribute, and renders the tooltip.
		 *
		 * @async
		 * @param {PointerEvent|MouseEvent|FocusEvent} event
		 * @return {Promise<boolean>} Resolves to true if the tooltip was rendered, false otherwise.
		 */
		pointerEnter: async event => {
			const desc = event.target.dataset.desc || event.target.title || '';

			// Skip if bubbled from a child that already has a tooltip.
			if ( desc && ! event.target.getElementsByClassName( ttNames.base ).length ) {
				event.target.dataset.desc = desc;
				event.target.removeAttribute( 'title' );
				return await doTooltip( event, event.target, desc );
			}

			return false;
		},

		/**
		 * Tracks the current pointer X position for arrow animation.
		 *
		 * @param {MouseEvent} event
		 * @return {void}
		 */
		pointerMove: event => {
			_pointer.currPos.x     = event.pageX || NaN;
			_pointer.lastMoveEvent = event;
		},

		/**
		 * Removes the tooltip and cleans up event listeners when the pointer leaves.
		 *
		 * @param {MouseEvent|FocusEvent} event
		 * @return {void}
		 */
		pointerLeave: event => {
			removeTooltip( event.target );
			_events( event.target ).unset();

			// Continue the arrow animation if moving to another tooltip item.
			if ( ! event.relatedTarget?.classList?.contains( ttNames.item ) ) {
				_cancelArrowAnimation();
			}
		},
	};

	// ─── EVENT HELPERS ─────────────────────────────────────────────────────────

	/**
	 * Returns set/unset helpers for attaching and removing active tooltip event listeners.
	 *
	 * @param {HTMLElement} target The tooltip item element.
	 * @return {{ set: Function, unset: Function }}
	 */
	function _events( target ) {
		const commonEvents = {
			mousemove:  _activeToolTipHandles.pointerMove,
			mouseleave: _activeToolTipHandles.pointerLeave,
			mouseout:   _activeToolTipHandles.pointerLeave,
			blur:       _activeToolTipHandles.pointerLeave,
		};

		return {
			set: () => {
				for ( const [ event, callBack ] of Object.entries( commonEvents ) ) {
					target.addEventListener( event, callBack );
				}
				target.addEventListener( 'better-seo-tooltip-update', _activeToolTipHandles.updateDesc );
			},
			unset: () => {
				for ( const [ event, callBack ] of Object.entries( commonEvents ) ) {
					target.removeEventListener( event, callBack );
				}
			},
		};
	}

	// ─── ACTIVE TOOLTIP STATE ──────────────────────────────────────────────────

	/**
	 * References to the currently active tooltip DOM elements.
	 *
	 * @type {{ tooltip: HTMLElement|undefined, arrow: HTMLElement|undefined, wrap: HTMLElement|undefined, reset: Function }}
	 */
	const _activeTooltipElements = {
		tooltip: undefined,
		arrow:   undefined,
		wrap:    undefined,
		reset:   () => {
			_activeTooltipElements.tooltip =
			_activeTooltipElements.arrow   =
			_activeTooltipElements.wrap    = undefined;
		},
	};

	/**
	 * Pointer position tracking state.
	 *
	 * @type {{ lastPos: Object, currPos: Object, lastMoveEvent: Event|undefined, reset: Function }}
	 */
	const _pointer = {
		lastPos:       { x: undefined },
		currPos:       { x: undefined },
		lastMoveEvent: undefined,
		reset:         () => {
			_pointer.lastMoveEvent = undefined;
			// Assign separate objects to avoid shared reference.
			_pointer.currPos = { x: undefined };
			_pointer.lastPos = { x: undefined };
		},
	};

	// ─── ARROW ANIMATION ───────────────────────────────────────────────────────

	/**
	 * Arrow animation controller — encapsulates requestAnimationFrame management
	 * and the animate loop.
	 *
	 * @type {{ _requestArrowAnimation: Function, _cancelArrowAnimation: Function, _requestArrowAnimationOnce: Function }}
	 */
	const {
		_requestArrowAnimation,
		_cancelArrowAnimation,
		_requestArrowAnimationOnce,
	} = ( () => {

		/** @type {number|undefined} Current animation frame ID. */
		let _pointerAnimationId;

		/** Requests the next animation frame for arrow positioning. */
		const _requestArrowAnimation = () => {
			_pointerAnimationId = requestAnimationFrame( animate );
		};

		/** Cancels the animation frame and resets all pointer/tooltip state. */
		const _cancelArrowAnimation = () => {
			cancelAnimationFrame( _pointerAnimationId );
			_pointer.lastMoveEvent = undefined;
			_activeTooltipElements.reset();
			_pointer.reset();
		};

		/** Runs a single animation frame then cancels (used for touch events). */
		const _requestArrowAnimationOnce = () => {
			animate();
			_cancelArrowAnimation();
		};

		/**
		 * Animation loop: positions the tooltip arrow based on the current pointer X.
		 *
		 * Skips the frame if the pointer has not moved. Falls back to the element
		 * centre on focus events, and to the last known position on manual updates.
		 *
		 * @return {void}
		 */
		const animate = () => {
			const isMouseEvent = ! [ _pointer.currPos.x ].includes( NaN );

			if ( isMouseEvent ) {
				if ( _pointer.currPos.x === _pointer.lastPos.x ) {
					_requestArrowAnimation();
					return;
				}
			}

			_pointer.lastPos.x = _pointer.currPos.x;

			const event   = _pointer.lastMoveEvent;
			const element = event.target;

			let tooltip = _activeTooltipElements.tooltip ?? element.querySelector( ttSelectors.base );

			// Browser lagged — tooltip not yet in DOM. Retry next frame.
			if ( ! tooltip ) {
				_requestArrowAnimation();
				return;
			}

			_activeTooltipElements.tooltip ??= tooltip;
			_activeTooltipElements.arrow   ??= tooltip.querySelector( ttSelectors.arrow );
			_activeTooltipElements.wrap    ??= element.closest( ttSelectors.wrap ) ?? element.parentNode;

			let pagex = _pointer.currPos.x;

			if ( 'focus' === event.type ) {
				// Centre the arrow over the item on keyboard focus.
				pagex = element.getBoundingClientRect().left + ( element.offsetWidth / 2 );
			} else if ( isNaN( pagex ) ) {
				// Use the last known position on manual tooltip description updates.
				pagex = +( _activeTooltipElements.tooltip.dataset.lastPagex )
					|| element.getBoundingClientRect().left;
			}

			// Store pagex so updateDesc() can retrieve it via the isNaN branch above.
			_activeTooltipElements.tooltip.dataset.lastPagex = pagex;

			const textWrap      = _activeTooltipElements.tooltip.querySelector( ttSelectors.textWrap );
			const arrowBoundary = 7;
			const arrowWidth    = 16;

			let mousex = pagex
				- _activeTooltipElements.wrap.getBoundingClientRect().left
				- ( arrowWidth / 2 );

			let adjust        = parseInt( _activeTooltipElements.tooltip.dataset.adjust, 10 );
			let boundaryRight = textWrap.offsetWidth - arrowWidth - arrowBoundary;

			adjust = isNaN( adjust ) ? 0 : Math.round( adjust );

			if ( adjust ) {
				mousex -= adjust;

				// Use text width for right boundary if adjustment exceeds wrap width.
				if ( boundaryRight + adjust > _activeTooltipElements.wrap.offsetWidth ) {
					const innerText   = textWrap.querySelector( ttSelectors.text );
					const textWidth   = innerText.offsetWidth;
					boundaryRight     = textWidth - arrowWidth - arrowBoundary;
				}
			}

			if ( mousex <= arrowBoundary ) {
				// Overflown left.
				_activeTooltipElements.arrow.style.left = `${arrowBoundary}px`;
			} else if ( mousex >= boundaryRight ) {
				// Overflown right.
				_activeTooltipElements.arrow.style.left = `${boundaryRight}px`;
			} else {
				// Somewhere in the middle.
				_activeTooltipElements.arrow.style.left = `${mousex}px`;
			}

			if ( isMouseEvent ) {
				_requestArrowAnimation();
			} else if ( _pointerAnimationId ) {
				_cancelArrowAnimation();
			}
		};

		return {
			_requestArrowAnimation,
			_cancelArrowAnimation,
			_requestArrowAnimationOnce,
		};
	} )();

	// ─── CLICK LOCKER ──────────────────────────────────────────────────────────

	/**
	 * Returns lock/release/isLocked helpers for preventing accidental clicks
	 * during tooltip activation on the given element.
	 *
	 * Forwards the lock to associated label/input elements as needed.
	 *
	 * @param {HTMLElement} element The element to lock.
	 * @return {{ lock: Function, release: Function, isLocked: Function }}
	 */
	function _clickLocker( element ) {
		return {
			lock: () => {
				element.dataset.preventedClick = '1';

				if ( element instanceof HTMLLabelElement && element.htmlFor ) {
					const input = document.getElementById( element.htmlFor );
					if ( input ) {
						input.dataset.preventedClick = '1';
					}
				}
				if ( element instanceof HTMLInputElement && element.id ) {
					for ( const label of document.querySelectorAll( `label[for="${element.id}"]` ) ) {
						label.dataset.preventedClick = '1';
					}
				}
			},
			release: () => {
				if ( ! ( element instanceof Element ) ) {
					return;
				}

				delete element.dataset.preventedClick;

				if ( element instanceof HTMLLabelElement && element.htmlFor ) {
					const input = document.getElementById( element.htmlFor );
					if ( input ) {
						delete input.dataset.preventedClick;
					}
				}
				if ( element instanceof HTMLInputElement && element.id ) {
					for ( const label of document.querySelectorAll( `label[for="${element.id}"]` ) ) {
						delete label.dataset.preventedClick;
					}
				}
			},
			isLocked: () => element instanceof Element && !! +element.dataset.preventedClick,
		};
	}

	// ─── INITIALISATION ────────────────────────────────────────────────────────

	/**
	 * Initialises tooltip event listeners on all tooltip wrap elements.
	 * Detects passive and capture event listener support via feature detection.
	 * Registers the 'better-seo-tooltip-reset' listener to re-run on DOM changes.
	 *
	 * @return {void}
	 */
	function _initToolTips() {

		let passiveSupported = false;
		let captureSupported = false;

		// Feature-detect passive and capture addEventListener options.
		try {
			( () => {
				const options = {
					get passive() { passiveSupported = true; return false; },
					get capture() { captureSupported = true; return false; },
				};
				window.addEventListener( 'better-seo-tt-test-passive', null, options );
				window.removeEventListener( 'better-seo-tt-test-passive', null, options );
			} )();
		} catch {
			passiveSupported = false;
			captureSupported = false;
		}

		/**
		 * Loads a tooltip for the event target if not already active.
		 *
		 * @async
		 * @param {Event} event
		 * @return {Promise<void>}
		 */
		const loadToolTip = async event => {

			if ( event.target.dataset.hasTooltip ) {
				return;
			}

			let isTouch = false;

			switch ( event.type ) {
				case 'mouseenter':
					// Most common case — placed first.
					break;
				case 'pointerdown':
				case 'touchstart':
					isTouch = true;
					break;
				case 'focus':
				default:
					break;
			}

			if ( ! isTouch ) {
				_clickLocker( event.target ).lock();
			}

			_cancelArrowAnimation();

			if ( ! ( await _activeToolTipHandles.pointerEnter( event ) ) ) {
				return;
			}

			// Initiate arrow placement immediately.
			_activeToolTipHandles.pointerMove( event );

			if ( isTouch ) {
				_requestArrowAnimationOnce();
			} else {
				_requestArrowAnimation();
			}

			_events( event.target ).set();
		};

		/**
		 * Prevents accidental clicks on tooltip wrap elements during activation.
		 * Handles the iOS 12 double-click bug by deferring the lock asynchronously.
		 *
		 * @param {MouseEvent} event
		 * @return {void}
		 */
		const preventTooltipHandleClick = event => {
			if ( _clickLocker( event.target ).isLocked() ) {
				return;
			}
			event.preventDefault();
			// iOS 12 fires two clicks simultaneously — defer the lock to avoid race condition.
			setTimeout( () => _clickLocker( event.target ).lock() );
		};

		let instigatingTooltip = false;

		/**
		 * Dispatches tooltip loading for the event target, guarded against re-entrancy.
		 *
		 * @param {Event} event
		 * @return {void}
		 */
		const handleToolTip = event => {
			if ( instigatingTooltip ) {
				return;
			}

			instigatingTooltip = true;

			if ( event.target.classList.contains( ttNames.item ) ) {
				loadToolTip( event );
			}

			event.stopPropagation();
			instigatingTooltip = false;
		};

		const listenerOptions = ( passiveSupported && captureSupported )
			? { capture: true, passive: true }
			: true;

		/**
		 * Attaches tooltip event listeners to all current tooltip wrap elements.
		 *
		 * @return {void}
		 */
		const init = () => {
			const actions = [ 'mouseenter', 'pointerdown', 'touchstart', 'focus' ];

			for ( const wrap of document.querySelectorAll( ttSelectors.wrap ) ) {
				for ( const action of actions ) {
					wrap.addEventListener( action, handleToolTip, listenerOptions );
				}
				// If the wrap is a label with a for-attribute, the click is forwarded to the input.
				// This is mitigated inside loadToolTip via _clickLocker.
				wrap.addEventListener(
					'click',
					preventTooltipHandleClick,
					captureSupported ? { capture: false } : false,
				);
			}
		};

		window.addEventListener( 'better-seo-tooltip-reset', init );
		triggerReset();
	}

	// ─── TOOLTIP RENDERING ─────────────────────────────────────────────────────

	/**
	 * Creates and positions a tooltip element on the given target element.
	 *
	 * Handles boundary detection, horizontal overflow correction, and vertical
	 * flip (downward tooltip) when the tooltip would overflow the top boundary.
	 *
	 * @param {Event|null}  event   The triggering event (used for pointer position). May be null.
	 * @param {HTMLElement} element The tooltip item element to attach the tooltip to.
	 * @param {string}      desc    The HTML description string to display.
	 * @return {boolean} True if the tooltip was rendered successfully.
	 */
	function _renderTooltip( event, element, desc ) {

		element.dataset.hasTooltip = '1';

		const tooltip = document.createElement( 'div' );
		tooltip.classList.add( ttNames.base );
		tooltip.insertAdjacentHTML(
			'afterbegin',
			`<span class=${ttNames.textWrap}><span class=${ttNames.text}>${desc}</span></span><div class=${ttNames.arrow} style=will-change:left></div>`,
		);
		element.prepend( tooltip );

		const boundary =
			   element.closest( ttSelectors.boundary )
			|| element.closest( '#tabs-0-edit-post\\/document-view' ) // Gutenberg sidebar WP 6.9+
			|| element.closest( '#tabs-1-edit-post\\/document-view' ) // Gutenberg sidebar WP 6.6+
			|| document.getElementById( 'wpcontent' )
			|| document.body;

		const boundaryRect  = boundary.getBoundingClientRect();
		const boundaryTop   = boundaryRect.top - ( boundary.scrollTop || 0 );
		const boundaryWidth = boundaryRect.width;
		const maxWidth      = 250; // Gutenberg sidebar is 262px; tooltip has 24px padding (12×2).

		const hoverItemSuperWrap = element.closest( ttSelectors.superWrap );
		const hoverItemWrap      = element.closest( ttSelectors.wrap ) ?? element.parentElement;
		const textWrap           = tooltip.querySelector( ttSelectors.textWrap );

		const superWrapRect     = hoverItemSuperWrap?.getBoundingClientRect();
		const hoverItemWrapRect = hoverItemWrap.getBoundingClientRect();

		let textWrapRect;
		const resetTextRects = () => {
			textWrapRect = textWrap.getBoundingClientRect();
		};
		resetTextRects();

		// appeal = computed paddingRight of textWrap (12px).
		let appeal    = 12;
		let horIndent = 0;

		if ( textWrapRect.width > ( boundaryWidth - ( appeal / 2 ) ) ) {
			// Tooltip overflows the boundary — squeeze it.
			textWrap.style.flexBasis = `${Math.min( maxWidth, boundaryWidth - appeal )}px`;
			resetTextRects();
			appeal /= 2;
		} else if ( textWrapRect.width > maxWidth ) {
			textWrap.style.flexBasis = `${maxWidth}px`;
			textWrap.style.maxWidth  = `${maxWidth}px`;
			resetTextRects();
		} else {
			appeal /= 2;
		}

		const boundaryLeft  = boundaryRect.left - ( boundary.scrollLeft || 0 );
		const boundaryRight = boundaryLeft + boundaryWidth;

		const textWrapWidth   = textWrapRect.width;
		const textBorderLeft  = textWrapRect.left;
		const textBorderRight = textBorderLeft + textWrapWidth;
		const wrapperWidth    = superWrapRect?.width ?? hoverItemWrapRect.width;

		if ( textBorderLeft < boundaryLeft ) {
			// Overflown past left boundary — indent relative to boundary.
			horIndent = boundaryLeft - textBorderLeft + appeal;
		} else if ( textBorderRight > boundaryRight ) {
			// Overflown past right boundary — indent relative to boundary minus text width.
			horIndent = boundaryRight - textBorderLeft - textWrapWidth - appeal;
		} else if ( wrapperWidth < 42 ) {
			// Small tooltip container — indent relative to item for visual appeal.
			horIndent = ( -wrapperWidth / 2 ) - appeal;
		} else if ( wrapperWidth > textWrapWidth ) {
			// Wrap is larger than tooltip — align to pointer or centre.
			const pagex = event?.pageX || NaN;

			if ( 'focus' === event?.type ) {
				horIndent = ( wrapperWidth / 2 ) - ( textWrapWidth / 2 );
			} else if ( isNaN( pagex ) ) {
				horIndent = -appeal;
			} else {
				horIndent = pagex - hoverItemWrapRect.left - ( textWrapWidth / 2 ) + appeal;
			}

			const appealLeft  = -appeal;
			const appealRight = wrapperWidth - textWrapWidth + appeal;

			if ( horIndent < appealLeft ) {
				horIndent = appealLeft;
			}
			if ( horIndent > appealRight ) {
				horIndent = appealRight;
			}
		} else {
			// Default: shift slightly based on text direction.
			horIndent = window.isRtl ? appeal : -appeal;
		}

		if ( ( horIndent + textBorderLeft ) < ( boundaryLeft + appeal ) ) {
			horIndent += appeal / 2;
		}
		if ( ( horIndent + textBorderRight ) > ( boundaryRight + appeal ) ) {
			// Overflows right boundary appeal — shift back slightly.
			horIndent -= appeal / 2;
		}
		if ( ( horIndent + textBorderLeft ) < boundaryLeft ) {
			// Still overflowing after alignment — reset to 0.
			horIndent = 0;
		}

		if ( ! event ) {
			const basis = parseInt( textWrap.style.flexBasis, 10 );
			if ( horIndent < -basis ) {
				horIndent = -basis;
			}
		}

		let offsetTop     = 0;
		let offsetTopFlip = 0;
		let offsetLeft    = 0;

		if ( superWrapRect ) {
			// Make positioning relative to the superWrap's top-left corner.
			offsetTop  = hoverItemWrapRect.top  - superWrapRect.top;
			offsetLeft = hoverItemWrapRect.left - superWrapRect.left;

			// If the text is narrower than the superWrap, centre it over the hover item.
			if ( textWrapWidth < superWrapRect.width ) {
				horIndent += offsetLeft;
			}

			offsetTopFlip = offsetTop;
			// For regular (upward) tooltips, subtract the relative bottom for accurate positioning.
			offsetTop -= superWrapRect.height - hoverItemWrapRect.height;
		}

		tooltip.style.left     = `${horIndent}px`;
		tooltip.dataset.adjust = String( horIndent - offsetLeft );

		// Determine vertical flip: show tooltip below if it would overflow the top boundary.
		const tooltipHeight = element.offsetHeight + 8; // +8 for arrow height.

		if ( boundaryTop > ( tooltip.getBoundingClientRect().top - tooltipHeight ) ) {
			tooltip.classList.add( 'better-seo-tooltip-down' );
			tooltip.style.top = `${tooltipHeight + offsetTopFlip}px`;
		} else {
			tooltip.style.bottom = `${tooltipHeight - offsetTop}px`;
		}

		return true;
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	/**
	 * Creates a tooltip on the given element with the given description.
	 *
	 * Removes any existing tooltips before rendering. Accepts jQuery elements
	 * for backward compatibility.
	 *
	 * @param {Event|null}        event   The triggering event, or null for programmatic use.
	 * @param {HTMLElement|jQuery} element The element to attach the tooltip to.
	 * @param {string}            desc    The HTML description string.
	 * @return {boolean} True if the tooltip was rendered, false if desc is empty.
	 */
	function doTooltip( event, element, desc ) {

		// Backward compatibility: unwrap jQuery element.
		if ( element?.[0] ) {
			element = element[0];
		}

		for ( const el of document.querySelectorAll( ttSelectors.base ) ) {
			removeTooltip( el );
			_events( el ).unset();
		}

		if ( ! desc.length ) {
			return false;
		}

		return _renderTooltip( event, element, desc );
	}

	/**
	 * Adds the boundary CSS class to the given element, making it an explicit
	 * tooltip overflow boundary.
	 *
	 * @param {HTMLElement} element The element to mark as a boundary.
	 * @return {void}
	 */
	function addBoundary( element ) {
		if ( element instanceof Element ) {
			element.classList.add( ttNames.boundary );
		}
	}

	/**
	 * Removes the tooltip from the given element and releases the click lock.
	 *
	 * Accepts jQuery elements for backward compatibility.
	 *
	 * @param {HTMLElement|jQuery} element The element whose tooltip should be removed.
	 * @return {void}
	 */
	function removeTooltip( element ) {

		// Backward compatibility: unwrap jQuery element.
		if ( element?.[0] ) {
			element = element[0];
		}

		if ( element instanceof HTMLElement ) {
			delete element.dataset.hasTooltip;
			_clickLocker( element ).release();
		}

		const toolTip = getTooltip( element );
		toolTip?.parentNode.removeChild( toolTip );
	}

	/**
	 * Returns the tooltip element for the given element, or the element itself
	 * if it is a tooltip.
	 *
	 * Accepts jQuery elements for backward compatibility.
	 *
	 * @param {HTMLElement|jQuery} element The element to search.
	 * @return {HTMLElement|null} The tooltip element, or null if not found.
	 */
	function getTooltip( element ) {

		// Backward compatibility: unwrap jQuery element.
		if ( element?.[0] ) {
			element = element[0];
		}

		return element?.classList.contains( ttNames.base )
			? element
			: element?.querySelector( ttSelectors.base ) ?? null;
	}

	/** @type {number|undefined} Pending setTimeout ID for triggerReset debounce. */
	let _debounceTriggerReset;

	/**
	 * Debounced dispatcher for the 'better-seo-tooltip-reset' event.
	 *
	 * Debounced at 100 ms — low enough not to cause annoyances,
	 * high enough not to cause lag.
	 *
	 * @return {void}
	 */
	function triggerReset() {
		clearTimeout( _debounceTriggerReset );
		_debounceTriggerReset = setTimeout(
			() => window.dispatchEvent( new CustomEvent( 'better-seo-tooltip-reset' ) ),
			100,
		);
	}

	/**
	 * Dispatches a 'better-seo-tooltip-update' event on the given element or,
	 * if no element is provided, on all tooltip item elements.
	 *
	 * @param {HTMLElement|NodeList|null} element The element(s) to update, or null for all.
	 * @return {void}
	 */
	function triggerUpdate( element ) {

		if ( ! element || ! ( element instanceof Element ) ) {
			element = document.querySelectorAll( ttSelectors.item );
		}

		if ( ! element ) {
			return;
		}

		const updateEvent = new CustomEvent( 'better-seo-tooltip-update' );

		if ( element instanceof Element ) {
			element.dispatchEvent( updateEvent );
		} else if ( element instanceof NodeList ) {
			for ( const el of element ) {
				el.dispatchEvent( updateEvent );
			}
		}
	}

	return {
		/**
		 * Registers the 'better-seo-ready' listener that triggers tooltip initialisation.
		 * Called automatically on script load.
		 *
		 * @return {void}
		 */
		load: () => {
			document.body.addEventListener( 'better-seo-ready', _initToolTips );
		},

		doTooltip,
		removeTooltip,
		getTooltip,
		addBoundary,
		triggerReset,
		triggerUpdate,
	};

}() );

// Auto-initialise — registers the 'better-seo-ready' listener.
window.BetterSeoTT.load();