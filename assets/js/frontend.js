/**
 * Summarize with AI - front-end behaviour.
 *
 * Handles the copy-prompt button and, when enabled, forwards click events to
 * whichever analytics library the site already loads.
 *
 * @package SUMMARIZEAI
 */

(function () {
	'use strict';

	/**
	 * Read the tracking context stamped on a button by the renderer.
	 *
	 * @param {HTMLElement} el Button or link element.
	 * @return {Object} Event payload.
	 */
	function trackingPayload( el ) {
		return {
			event_category: el.getAttribute( 'data-track-category' ) || 'ai_tool',
			event_label: el.getAttribute( 'data-track-label' ) || '',
			placement: el.getAttribute( 'data-track-placement' ) || 'summarize-with-ai',
			post_id: el.getAttribute( 'data-track-post-id' ) || '',
			post_title: el.getAttribute( 'data-track-post-title' ) || '',
			post_url: el.getAttribute( 'data-track-post-url' ) || '',
			post_type: el.getAttribute( 'data-track-post-type' ) || ''
		};
	}

	/**
	 * Send a click event to gtag() and/or dataLayer when either is present.
	 *
	 * @param {HTMLElement} el Button or link element.
	 * @return {void}
	 */
	function track( el ) {
		var action = el.getAttribute( 'data-track-action' ) || 'summarize_click';
		var payload = trackingPayload( el );

		if ( typeof window.gtag === 'function' ) {
			window.gtag( 'event', action, payload );
		}

		if ( Array.isArray( window.dataLayer ) ) {
			payload.event = action;
			window.dataLayer.push( payload );
		}
	}

	/**
	 * Copy text to the clipboard, falling back to a hidden textarea where the
	 * async Clipboard API is unavailable (older browsers, or non-secure origins).
	 *
	 * @param {string} text Text to copy.
	 * @return {Promise} Resolves when the text has been copied.
	 */
	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}

		return new Promise( function ( resolve, reject ) {
			var field = document.createElement( 'textarea' );

			field.value = text;
			field.setAttribute( 'readonly', '' );
			field.style.position = 'fixed';
			field.style.left = '-9999px';
			document.body.appendChild( field );
			field.select();

			try {
				// ponytail: execCommand is deprecated but is the only fallback
				// for http:// origins, where navigator.clipboard is undefined.
				if ( document.execCommand( 'copy' ) ) {
					resolve();
				} else {
					reject();
				}
			} catch ( error ) {
				reject( error );
			} finally {
				document.body.removeChild( field );
			}
		} );
	}

	/**
	 * Show a short confirmation inside the copy button, then restore its label.
	 *
	 * @param {HTMLElement} button Copy button.
	 * @return {void}
	 */
	function confirmCopy( button ) {
		var label = button.querySelector( '.swi-button-text, .screen-reader-text' );
		var done = button.getAttribute( 'data-swi-copied' );

		button.classList.add( 'is-copied' );

		if ( ! label || ! done || button.hasAttribute( 'data-swi-original' ) ) {
			return;
		}

		button.setAttribute( 'data-swi-original', label.textContent );
		label.textContent = done;

		window.setTimeout( function () {
			label.textContent = button.getAttribute( 'data-swi-original' );
			button.removeAttribute( 'data-swi-original' );
			button.classList.remove( 'is-copied' );
		}, 2000 );
	}

	document.addEventListener( 'click', function ( event ) {
		var copyButton = event.target.closest ? event.target.closest( '.swi-copy-button' ) : null;

		if ( copyButton ) {
			event.preventDefault();

			copyText( copyButton.getAttribute( 'data-swi-prompt' ) || '' ).then(
				function () {
					confirmCopy( copyButton );
				},
				function () {
					// Nothing to recover from: the visitor can still select the text manually.
				}
			);
		}

		var trackable = copyButton || ( event.target.closest ? event.target.closest( '.summarize-with-ai-icon' ) : null );

		if ( ! trackable ) {
			return;
		}

		var wrapper = trackable.closest( '[data-swi-analytics="1"]' );

		if ( wrapper ) {
			track( trackable );
		}
	} );
})();
