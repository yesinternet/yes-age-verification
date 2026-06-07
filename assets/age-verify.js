/* YES Age Verification — popup controller */
( function () {
	var config   = window.yesAgeVerificationConfig || {};
	var COOKIE   = config.cookie   || 'age_verification';
	var DAYS     = parseInt( config.days, 10 ) || 30;
	var REDIRECT = config.redirect || 'https://www.google.com';
	var BOTS     = config.bots     || [];

	var overlay = document.getElementById( 'yes-age-verification__overlay' );
	if ( ! overlay ) return;

	function getCookie( name ) {
		var match = document.cookie.match( '(?:^|; )' + name + '=([^;]*)' );
		return match ? match[ 1 ] : null;
	}

	function setCookie( name, value, days ) {
		var expiry = new Date();
		expiry.setDate( expiry.getDate() + days );
		document.cookie = name + '=' + value
			+ ';expires=' + expiry.toUTCString()
			+ ';path=/'
			+ ';SameSite=Lax';
	}

	// Skip known search-engine / AI crawlers — checked here (not server-side)
	// so the page itself stays byte-identical for everyone and remains safe
	// to serve from a full-page cache.
	function isKnownBot() {
		var ua = ( navigator.userAgent || '' ).toLowerCase();
		for ( var i = 0; i < BOTS.length; i++ ) {
			if ( ua.indexOf( String( BOTS[ i ] ).toLowerCase() ) !== -1 ) {
				return true;
			}
		}
		return false;
	}

	if ( getCookie( COOKIE ) || isKnownBot() ) return;

	// Hide background content from assistive technology.
	var backgroundElements  = [];
	var previousAriaHidden  = [];
	Array.prototype.forEach.call( document.body.children, function ( el ) {
		if ( el === overlay ) return;
		backgroundElements.push( el );
		previousAriaHidden.push( el.getAttribute( 'aria-hidden' ) );
		el.setAttribute( 'aria-hidden', 'true' );
	} );

	function restoreBackground() {
		backgroundElements.forEach( function ( el, i ) {
			if ( previousAriaHidden[ i ] === null ) {
				el.removeAttribute( 'aria-hidden' );
			} else {
				el.setAttribute( 'aria-hidden', previousAriaHidden[ i ] );
			}
		} );
	}

	overlay.style.display = 'flex';
	document.body.classList.add( 'yes-age-verification--locked' );

	var firstButton = overlay.querySelector( 'button' );
	if ( firstButton ) firstButton.focus();

	var focusableElements     = overlay.querySelectorAll( 'button:not([disabled]),[href],[tabindex]:not([tabindex="-1"])' );
	var firstFocusableElement = focusableElements[ 0 ];
	var lastFocusableElement  = focusableElements[ focusableElements.length - 1 ];

	function onKeyDown( e ) {
		if ( e.key === 'Escape' ) {
			window.location.href = REDIRECT;
			return;
		}
		if ( e.key !== 'Tab' ) return;

		if ( e.shiftKey ) {
			if ( document.activeElement === firstFocusableElement ) {
				e.preventDefault();
				lastFocusableElement.focus();
			}
		} else {
			if ( document.activeElement === lastFocusableElement ) {
				e.preventDefault();
				firstFocusableElement.focus();
			}
		}
	}

	document.addEventListener( 'keydown', onKeyDown );

	var buttonYes = document.getElementById( 'yes-age-verification__button--yes' );
	var buttonNo  = document.getElementById( 'yes-age-verification__button--no' );

	if ( buttonYes ) {
		buttonYes.addEventListener( 'click', function () {
			setCookie( COOKIE, '1', DAYS );
			document.removeEventListener( 'keydown', onKeyDown );
			overlay.style.display = 'none';
			document.body.classList.remove( 'yes-age-verification--locked' );
			restoreBackground();
		} );
	}

	if ( buttonNo ) {
		buttonNo.addEventListener( 'click', function () {
			window.location.href = REDIRECT;
		} );
	}
}() );
