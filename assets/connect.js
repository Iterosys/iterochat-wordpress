( function () {
	var cfg = window.iterochatConnect;
	if ( ! cfg || ! cfg.verificationUrl ) {
		return;
	}

	var statusEl = document.getElementById( 'iterochat-status' );
	function setStatus( text ) {
		if ( statusEl ) {
			statusEl.textContent = text;
		}
	}

	// Open the approval page once.
	window.open( cfg.verificationUrl, '_blank', 'noopener' );

	var intervalMs = ( parseInt( cfg.interval, 10 ) || 5 ) * 1000;
	var maxPolls = Math.ceil( ( 10 * 60 * 1000 ) / intervalMs ); // ~10 minute cap
	var polls = 0;
	var timer = null;

	function stop() {
		if ( timer ) {
			clearInterval( timer );
			timer = null;
		}
	}

	function poll() {
		polls++;
		if ( polls > maxPolls ) {
			stop();
			setStatus( 'This connection request expired. Reload the page to try again.' );
			return;
		}
		var body = new FormData();
		body.append( 'action', 'iterochat_poll' );
		body.append( '_ajax_nonce', cfg.nonce );
		fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) {
				if ( ! data || ! data.status ) {
					return;
				}
				if ( data.status === 'connected' ) {
					stop();
					setStatus( 'Connected. Reloading...' );
					window.location.reload();
				} else if ( data.status === 'error' || data.status === 'expired' ) {
					stop();
					setStatus( data.message || 'The connection could not be completed. Reload to try again.' );
				} else {
					setStatus( 'Waiting for approval...' );
				}
			} )
			.catch( function () {
				// transient network error; keep polling
			} );
	}

	setStatus( 'Waiting for approval...' );
	timer = setInterval( poll, intervalMs );
	poll(); // fire immediately, do not wait one interval
} )();
