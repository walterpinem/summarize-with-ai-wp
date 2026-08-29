/**
 * Summarize with AI - settings page tabs.
 *
 * Presentation only. Every panel stays inside the one form, so a save always
 * writes every section no matter which tab is open, and with JavaScript off the
 * panels simply stack down the page.
 *
 * @package SUMMARIZEAI
 */

(function () {
	'use strict';

	var STORAGE_KEY = 'swiActiveTab';

	var wrap = document.querySelector( '.summarizewithai-settings' );

	if ( ! wrap ) {
		return;
	}

	var tabs = Array.prototype.slice.call( wrap.querySelectorAll( '.swi-tabs .nav-tab' ) );
	var panels = Array.prototype.slice.call( wrap.querySelectorAll( '.swi-tab-panel' ) );
	var submit = wrap.querySelector( '.submit' );

	if ( ! tabs.length || ! panels.length ) {
		return;
	}

	/**
	 * Read a remembered tab, tolerating browsers that block session storage.
	 *
	 * @return {string} Stored tab id, or an empty string.
	 */
	function readStored() {
		try {
			return window.sessionStorage.getItem( STORAGE_KEY ) || '';
		} catch ( error ) {
			return '';
		}
	}

	/**
	 * Remember the active tab so it survives the redirect after saving.
	 *
	 * @param {string} id Tab id.
	 * @return {void}
	 */
	function store( id ) {
		try {
			window.sessionStorage.setItem( STORAGE_KEY, id );
		} catch ( error ) {
			// Private browsing and locked-down profiles: the tab just resets.
		}
	}

	/**
	 * Show one panel and mark its tab active.
	 *
	 * @param {string}  id          Tab id.
	 * @param {boolean} updateHash  Whether to reflect the choice in the URL.
	 * @return {void}
	 */
	function activate( id, updateHash ) {
		var known = tabs.some( function ( tab ) {
			return tab.getAttribute( 'data-swi-tab' ) === id;
		} );

		if ( ! known ) {
			id = tabs[0].getAttribute( 'data-swi-tab' );
		}

		tabs.forEach( function ( tab ) {
			var isActive = tab.getAttribute( 'data-swi-tab' ) === id;

			tab.classList.toggle( 'nav-tab-active', isActive );
			tab.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
		} );

		panels.forEach( function ( panel ) {
			panel.classList.toggle( 'is-active', panel.id === 'swi-panel-' + id );
		} );

		// The Usage tab is reference material with no fields to save.
		if ( submit ) {
			var active = wrap.querySelector( '#swi-panel-' + id );

			submit.hidden = !! ( active && active.getAttribute( 'data-swi-readonly' ) );
		}

		store( id );

		if ( updateHash && window.history && window.history.replaceState ) {
			window.history.replaceState( null, '', '#swi-panel-' + id );
		}
	}

	tabs.forEach( function ( tab ) {
		tab.setAttribute( 'role', 'tab' );

		tab.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			activate( tab.getAttribute( 'data-swi-tab' ), true );
		} );
	} );

	// Only hide panels once the script is running, so no-JS keeps every section.
	wrap.classList.add( 'swi-tabs-enabled' );

	/*
	 * A validation failure focuses a field in a hidden panel and the browser
	 * cannot scroll to it, so open that field's tab instead.
	 */
	wrap.addEventListener(
		'invalid',
		function ( event ) {
			var panel = event.target.closest ? event.target.closest( '.swi-tab-panel' ) : null;

			if ( panel ) {
				activate( panel.id.replace( 'swi-panel-', '' ), true );
			}
		},
		true
	);

	activate( ( window.location.hash || '' ).replace( '#swi-panel-', '' ) || readStored(), false );

	// Follow the hash so the browser's Back and Forward move between tabs.
	window.addEventListener( 'hashchange', function () {
		var id = ( window.location.hash || '' ).replace( '#swi-panel-', '' );

		if ( id ) {
			activate( id, false );
		}
	} );

	/**
	 * Briefly swap a button's label to confirm what happened.
	 *
	 * @param {HTMLElement} button Copy button.
	 * @param {string}      label  Confirmation text.
	 * @return {void}
	 */
	function flashLabel( button, label ) {
		if ( button.hasAttribute( 'data-swi-original' ) || ! label ) {
			return;
		}

		button.setAttribute( 'data-swi-original', button.textContent );
		button.textContent = label;
		button.classList.add( 'is-copied' );

		window.setTimeout( function () {
			button.textContent = button.getAttribute( 'data-swi-original' );
			button.removeAttribute( 'data-swi-original' );
			button.classList.remove( 'is-copied' );
		}, 1800 );
	}

	/**
	 * Select a snippet so it can be copied by hand.
	 *
	 * The clipboard is not always writable: navigator.clipboard needs a secure
	 * context, and execCommand can be refused outright. Selecting the text means
	 * the button always does something useful rather than failing in silence.
	 *
	 * @param {HTMLElement} button Copy button.
	 * @return {void}
	 */
	function selectSnippet( button ) {
		var snippet = button.parentNode
			? button.parentNode.querySelector( '.swi-snippet-code' )
			: null;

		if ( ! snippet || ! window.getSelection || ! document.createRange ) {
			return;
		}

		var range = document.createRange();
		var selection = window.getSelection();

		range.selectNodeContents( snippet );
		selection.removeAllRanges();
		selection.addRange( range );

		flashLabel( button, button.getAttribute( 'data-swi-select' ) );
	}

	/**
	 * Copy a usage snippet to the clipboard.
	 *
	 * @param {HTMLElement} button Copy button.
	 * @return {void}
	 */
	function copySnippet( button ) {
		var text = button.getAttribute( 'data-swi-copy' ) || '';

		function done() {
			flashLabel( button, button.getAttribute( 'data-swi-copied' ) );
		}

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done, function () {
				selectSnippet( button );
			} );

			return;
		}

		// ponytail: execCommand is deprecated but is the only clipboard write
		// available on the http:// admin URLs plenty of local installs still use.
		var field = document.createElement( 'textarea' );
		var copied = false;

		field.value = text;
		field.setAttribute( 'readonly', '' );
		field.style.position = 'fixed';
		field.style.left = '-9999px';
		document.body.appendChild( field );
		field.select();

		try {
			copied = document.execCommand( 'copy' );
		} catch ( error ) {
			copied = false;
		}

		document.body.removeChild( field );

		if ( copied ) {
			done();
		} else {
			selectSnippet( button );
		}
	}

	/*
	 * Live preview.
	 *
	 * The stage holds real front-end markup with every service rendered, so the
	 * appearance settings can be reflected by toggling the same classes the
	 * renderer would emit. Nothing here touches the saved options; it only shows
	 * what saving would produce.
	 */
	var stage = document.getElementById( 'swi-preview-stage' );

	if ( stage ) {
		var row = stage.querySelector( '.share-with-ai' );
		var field = function ( name ) {
			return wrap.querySelector( '[name="summarizewithai_options[' + name + ']"]' );
		};

		var styleField = field( 'button_style' );
		var layoutField = field( 'layout' );
		var textField = field( 'show_button_text' );
		var labelField = field( 'summarize_label' );
		var copyField = field( 'enable_copy' );
		var darkToggle = document.getElementById( 'swi-preview-dark' );

		var refresh = function () {
			if ( ! row ) {
				return;
			}

			var style = styleField ? styleField.value : 'filled';
			var layout = layoutField ? layoutField.value : 'inline';

			[ 'filled', 'outline', 'minimal' ].forEach( function ( name ) {
				row.classList.toggle( 'swi-style-' + name, name === style );
			} );

			[ 'inline', 'stacked' ].forEach( function ( name ) {
				row.classList.toggle( 'swi-layout-' + name, name === layout );
			} );

			var showText = ! textField || textField.checked;

			row.classList.toggle( 'swi-icons-only', ! showText );

			// The renderer swaps these two classes; mirror that so the preview
			// hides the names rather than merely shrinking the buttons.
			Array.prototype.forEach.call(
				row.querySelectorAll( '.swi-button-text, .screen-reader-text' ),
				function ( span ) {
					if ( span.closest( '.swi-google-source-inline' ) ) {
						return;
					}

					span.className = showText ? 'swi-button-text' : 'screen-reader-text';
				}
			);

			var labelWrap = row.querySelector( '.share-ai-text' );
			var labelText = labelField ? labelField.value.trim() : '';

			if ( labelWrap ) {
				labelWrap.hidden = '' === labelText;
				var labelSpan = labelWrap.querySelector( 'span' );

				if ( labelSpan && '' !== labelText ) {
					labelSpan.textContent = labelText;
				}
			}

			var copyWrap = row.querySelector( '.swi-copy' );

			if ( copyWrap ) {
				copyWrap.hidden = !! ( copyField && ! copyField.checked );
			}

			// Enabled services.
			Array.prototype.forEach.call(
				wrap.querySelectorAll( '[name="summarizewithai_options[enabled_services][]"]' ),
				function ( box ) {
					var cell = row.querySelector( '.share-ai.' + box.value );

					if ( cell ) {
						cell.hidden = ! box.checked;
					}
				}
			);
		};

		wrap.addEventListener( 'change', refresh );
		wrap.addEventListener( 'input', refresh );

		if ( darkToggle ) {
			darkToggle.addEventListener( 'change', function () {
				stage.classList.toggle( 'is-dark', darkToggle.checked );
			} );
		}

		refresh();
	}

	wrap.addEventListener( 'click', function ( event ) {
		var button = event.target.closest ? event.target.closest( '.swi-snippet-copy' ) : null;

		if ( button ) {
			event.preventDefault();
			copySnippet( button );
		}
	} );
})();
