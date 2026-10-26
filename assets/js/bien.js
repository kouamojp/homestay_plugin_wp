/* HomyStay Core : lightbox de la page d'un bien.
 * Une photo à la fois, flèches, clavier (← → Échap, Début/Fin), glissement tactile, vignettes, compteur.
 * Repose sur <dialog> : focus piégé dans la lightbox et rendu au bouton d'origine à la fermeture. */
( function () {
	var box = document.querySelector( '[data-hs-lightbox]' );
	if ( ! box || typeof box.showModal !== 'function' ) {
		return; // Navigateur trop ancien : les photos restent visibles dans la mosaïque.
	}

	var photos;
	try {
		photos = JSON.parse( box.querySelector( '[data-hs-photos]' ).textContent );
	} catch ( e ) {
		return;
	}
	if ( ! photos.length ) {
		return;
	}

	var img       = box.querySelector( '[data-hs-image]' );
	var legende   = box.querySelector( '[data-hs-legende]' );
	var compteur  = box.querySelector( '[data-hs-compteur]' );
	var vignettes = box.querySelector( '[data-hs-vignettes]' );
	var courant   = 0;
	var origine   = null;
	var precharge = {};

	// Vignettes générées une fois.
	var miniatures = [];
	if ( vignettes ) {
		photos.forEach( function ( p, i ) {
			var b = document.createElement( 'button' );
			b.type = 'button';
			b.className = 'hs-lightbox__vignette';
			b.setAttribute( 'aria-label', 'Photo ' + ( i + 1 ) );
			var t = document.createElement( 'img' );
			t.src = p.thumb;
			t.alt = '';
			t.loading = 'lazy';
			b.appendChild( t );
			b.addEventListener( 'click', function () { afficher( i ); } );
			vignettes.appendChild( b );
			miniatures.push( b );
		} );
	}

	function prechargerAutour( i ) {
		[ i + 1, i - 1 ].forEach( function ( j ) {
			j = ( j + photos.length ) % photos.length;
			if ( ! precharge[ j ] ) {
				var p = new Image();
				if ( photos[ j ].srcset ) {
					p.srcset = photos[ j ].srcset;
					p.sizes = '100vw';
				}
				p.src = photos[ j ].src;
				precharge[ j ] = true;
			}
		} );
	}

	function afficher( i ) {
		courant = ( i + photos.length ) % photos.length;
		var p = photos[ courant ];
		img.classList.add( 'est-chargement' );
		img.onload = function () { img.classList.remove( 'est-chargement' ); };
		img.removeAttribute( 'srcset' );
		if ( p.srcset ) {
			img.srcset = p.srcset;
			img.sizes = '(min-width: 1200px) 1100px, 100vw';
		}
		img.src = p.src;
		img.alt = p.alt;
		img.width = p.w;
		img.height = p.h;
		legende.textContent = p.legende || '';
		legende.hidden = ! p.legende;
		compteur.textContent = String( courant + 1 );
		miniatures.forEach( function ( m, k ) {
			var actif = k === courant;
			m.classList.toggle( 'est-actif', actif );
			if ( actif ) {
				m.setAttribute( 'aria-current', 'true' );
				m.scrollIntoView( { block: 'nearest', inline: 'center', behavior: reduit() ? 'auto' : 'smooth' } );
			} else {
				m.removeAttribute( 'aria-current' );
			}
		} );
		prechargerAutour( courant );
	}

	function reduit() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function ouvrir( i, bouton ) {
		origine = bouton || null;
		afficher( i );
		box.showModal();
		document.documentElement.classList.add( 'hs-sans-defilement' );
	}

	box.addEventListener( 'close', function () {
		document.documentElement.classList.remove( 'hs-sans-defilement' );
		if ( origine ) {
			origine.focus();
		}
	} );

	document.querySelectorAll( '[data-hs-ouvrir]' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			ouvrir( parseInt( b.getAttribute( 'data-hs-ouvrir' ), 10 ) || 0, b );
		} );
	} );

	box.querySelector( '[data-hs-fermer]' ).addEventListener( 'click', function () { box.close(); } );
	var prec = box.querySelector( '[data-hs-prec]' );
	var suiv = box.querySelector( '[data-hs-suiv]' );
	if ( prec ) { prec.addEventListener( 'click', function () { afficher( courant - 1 ); } ); }
	if ( suiv ) { suiv.addEventListener( 'click', function () { afficher( courant + 1 ); } ); }

	// Clic sur le fond (hors image et commandes) : fermer.
	box.addEventListener( 'click', function ( e ) {
		if ( e.target === box || e.target.classList.contains( 'hs-lightbox__scene' ) ) {
			box.close();
		}
	} );

	box.addEventListener( 'keydown', function ( e ) {
		if ( photos.length < 2 ) { return; }
		if ( e.key === 'ArrowRight' ) { afficher( courant + 1 ); e.preventDefault(); }
		else if ( e.key === 'ArrowLeft' ) { afficher( courant - 1 ); e.preventDefault(); }
		else if ( e.key === 'Home' ) { afficher( 0 ); e.preventDefault(); }
		else if ( e.key === 'End' ) { afficher( photos.length - 1 ); e.preventDefault(); }
	} );

	// Glissement tactile horizontal.
	var x0 = null, y0 = null;
	img.addEventListener( 'touchstart', function ( e ) {
		x0 = e.touches[ 0 ].clientX;
		y0 = e.touches[ 0 ].clientY;
	}, { passive: true } );
	img.addEventListener( 'touchend', function ( e ) {
		if ( x0 === null || photos.length < 2 ) { return; }
		var dx = e.changedTouches[ 0 ].clientX - x0;
		var dy = e.changedTouches[ 0 ].clientY - y0;
		if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) ) {
			afficher( courant + ( dx < 0 ? 1 : -1 ) );
		}
		x0 = y0 = null;
	}, { passive: true } );
} )();
