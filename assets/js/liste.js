/* HomyStay Core : barre de filtres de [hs_biens] et favoris. */
( function () {
	// Filtres : envoi automatique au changement, retour à la page 1.
	document.querySelectorAll( '.hs-barre [data-hs-auto]' ).forEach( function ( select ) {
		select.addEventListener( 'change', function () {
			var form = select.form;
			// Changer de ville réinitialise le quartier (il appartient à l'ancienne ville).
			if ( select.name === 'hs_ville' && form.elements.hs_quartier ) {
				form.elements.hs_quartier.value = '';
			}
			// Ne pas envoyer les filtres vides : URL plus courte.
			Array.prototype.forEach.call( form.elements, function ( el ) {
				if ( el.name && el.value === '' ) {
					el.disabled = true;
				}
			} );
			form.submit();
		} );
	} );

	// Favoris : mémorisés dans ce navigateur uniquement (en attendant les comptes voyageurs).
	var CLE = 'hs_favoris';
	function lire() {
		try {
			return JSON.parse( window.localStorage.getItem( CLE ) || '[]' );
		} catch ( e ) {
			return [];
		}
	}
	function ecrire( liste ) {
		try {
			window.localStorage.setItem( CLE, JSON.stringify( liste ) );
		} catch ( e ) {}
	}
	function maj( btn, actif ) {
		btn.classList.toggle( 'est-favori', actif );
		btn.setAttribute( 'aria-pressed', actif ? 'true' : 'false' );
		btn.setAttribute( 'aria-label', actif ? 'Retirer des favoris' : 'Ajouter aux favoris' );
	}

	var favoris = lire();
	document.querySelectorAll( '[data-hs-favori]' ).forEach( function ( btn ) {
		var id = parseInt( btn.getAttribute( 'data-hs-favori' ), 10 );
		maj( btn, favoris.indexOf( id ) !== -1 );
		btn.addEventListener( 'click', function () {
			favoris = lire();
			var i = favoris.indexOf( id );
			if ( i === -1 ) {
				favoris.push( id );
			} else {
				favoris.splice( i, 1 );
			}
			ecrire( favoris );
			maj( btn, i === -1 );
		} );
	} );
} )();
