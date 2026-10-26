/* HomyStay Core : « Mon espace ». Affiche les favoris enregistrés dans ce navigateur (cœur des cartes de biens). */
( function () {
	var boite = document.querySelector( '[data-hs-favoris]' );
	if ( ! boite || ! window.hsEspace || ! window.fetch ) {
		return;
	}
	var ids;
	try {
		ids = JSON.parse( window.localStorage.getItem( 'hs_favoris' ) || '[]' );
	} catch ( e ) {
		ids = [];
	}
	ids = ids.filter( function ( n ) { return parseInt( n, 10 ) > 0; } ).slice( 0, 12 );
	if ( ! ids.length ) {
		return; // le message « vide » est déjà affiché
	}

	function fcfa( n ) {
		return Number( n ).toLocaleString( 'fr-FR' ).replace( / | /g, ' ' ) + ' FCFA';
	}

	fetch( window.hsEspace.rest + '?_embed=wp:featuredmedia&per_page=12&include=' + ids.join( ',' ), { credentials: 'same-origin' } )
		.then( function ( r ) { return r.ok ? r.json() : []; } )
		.then( function ( biens ) {
			if ( ! biens.length ) {
				return;
			}
			boite.textContent = '';
			biens.forEach( function ( b ) {
				var a = document.createElement( 'a' );
				a.className = 'es-favori';
				a.href = b.link;
				var media = b._embedded && b._embedded[ 'wp:featuredmedia' ] && b._embedded[ 'wp:featuredmedia' ][ 0 ];
				var src = media && media.media_details && media.media_details.sizes && ( media.media_details.sizes.medium || media.media_details.sizes.thumbnail );
				if ( src ) {
					var img = document.createElement( 'img' );
					img.src = src.source_url;
					img.alt = '';
					img.loading = 'lazy';
					a.appendChild( img );
				} else {
					var vide = document.createElement( 'span' );
					vide.className = 'es-favori__vide';
					a.appendChild( vide );
				}
				var txt = document.createElement( 'span' );
				var titre = document.createElement( 'strong' );
				var tmp = document.createElement( 'div' );
				tmp.innerHTML = b.title && b.title.rendered ? b.title.rendered : '';
				titre.textContent = tmp.textContent;
				txt.appendChild( titre );
				var m = b.meta || {};
				var detail = [ m.hs_ville, m.hs_offre_courte && m.hs_prix_courte ? fcfa( m.hs_prix_courte ) + ' / nuit' : ( m.hs_offre_longue && m.hs_prix_longue ? fcfa( m.hs_prix_longue ) + ' / mois' : '' ) ].filter( Boolean ).join( ', ' );
				var d = document.createElement( 'span' );
				d.className = 'es-doux';
				d.textContent = detail;
				txt.appendChild( d );
				a.appendChild( txt );
				boite.appendChild( a );
			} );
		} )
		.catch( function () {} );
} )();
