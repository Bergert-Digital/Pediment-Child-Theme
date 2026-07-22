( function () {
	var cfg = window.pedimentChildUpdateTest;
	if ( ! cfg ) {
		return;
	}
	var btn = document.getElementById( 'pediment-child-test-connection' );
	var out = document.getElementById( 'pediment-child-test-result' );
	if ( ! btn || ! out ) {
		return;
	}
	btn.addEventListener( 'click', function () {
		btn.disabled = true;
		btn.textContent = cfg.testing;
		out.textContent = '';
		var body = new URLSearchParams();
		body.set( 'action', 'pediment_child_test_update_token' );
		body.set( '_ajax_nonce', cfg.nonce );
		fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				var msg = res && res.data && res.data.message ? res.data.message : 'Unexpected response.';
				out.textContent = ( res && res.success ? '✓ ' : '✕ ' ) + msg;
			} )
			.catch( function ( e ) { out.textContent = '✕ ' + e.message; } )
			.finally( function () {
				btn.disabled = false;
				btn.textContent = cfg.label;
			} );
	} );
}() );
