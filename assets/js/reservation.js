/* HomyStay Core : réservation en ligne.
 * 1. Page d'un bien : calendrier des disponibilités (nuits prises barrées), prix calculé, formulaire en deux temps.
 *    Sans JavaScript, le formulaire reste utilisable avec les champs de date natifs ; le serveur revérifie tout.
 * 2. Page de suivi : boutons « Copier » (numéro, montant) et choix de l'opérateur. */
( function () {
	'use strict';

	/* ---------- Outils de dates (chaînes Y-m-d, sans fuseau) ---------- */
	function versDate( s ) {
		var p = s.split( '-' );
		return new Date( Date.UTC( +p[ 0 ], +p[ 1 ] - 1, +p[ 2 ] ) );
	}
	function versTexte( d ) {
		return d.toISOString().slice( 0, 10 );
	}
	function plusJours( s, n ) {
		var d = versDate( s );
		d.setUTCDate( d.getUTCDate() + n );
		return versTexte( d );
	}
	function ecart( a, b ) {
		return Math.round( ( versDate( b ) - versDate( a ) ) / 864e5 );
	}
	function fcfa( n ) {
		return String( Math.round( n ) ).replace( /\B(?=(\d{3})+(?!\d))/g, ' ' ) + ' FCFA';
	}

	/* ---------- Formulaire de la page d'un bien ---------- */
	var form = document.querySelector( '[data-hs-resa]' );
	if ( form ) {
		initFormulaire( form );
	}

	function initFormulaire( form ) {
		var D;
		try {
			D = JSON.parse( form.querySelector( '[data-hs-resa-donnees]' ).textContent );
		} catch ( e ) {
			return;
		}
		var prises   = {};
		D.occupees.forEach( function ( j ) { prises[ j ] = true; } );
		var inArr    = form.querySelector( 'input[name="arrivee"]' );
		var inDep    = form.querySelector( 'input[name="depart"]' );
		var cal      = form.querySelector( '[data-hs-calendrier]' );
		var recap    = form.querySelector( '[data-hs-recap]' );
		var coord    = form.querySelector( '[data-hs-coord]' );
		var bouton   = form.querySelector( '[data-hs-bouton]' );
		var erreur   = form.querySelector( '[data-hs-erreur]' );
		var arrivee  = inArr.value || '';
		var depart   = inDep.value || '';
		var vue      = ( arrivee || D.aujourdhui ).slice( 0, 7 ); // mois affiché « Y-m »
		var etape    = 1;
		var dernierMois = plusJours( D.aujourdhui, 365 ).slice( 0, 7 );

		// Remplace les champs natifs par deux boutons qui ouvrent le calendrier.
		var champs = {};
		[ [ 'arrivee', inArr ], [ 'depart', inDep ] ].forEach( function ( c ) {
			var label = form.querySelector( '[data-hs-champ="' + c[ 0 ] + '"]' );
			var b = document.createElement( 'button' );
			b.type = 'button';
			b.className = 'hs-resa__champ hs-resa__choix';
			b.setAttribute( 'aria-expanded', 'false' );
			b.innerHTML = '<span>' + label.querySelector( 'span' ).textContent + '</span><strong></strong>';
			label.hidden = true;
			label.parentNode.insertBefore( b, label );
			b.addEventListener( 'click', function () {
				if ( c[ 0 ] === 'depart' && arrivee ) {
					depart = '';
				} else if ( c[ 0 ] === 'arrivee' ) {
					arrivee = '';
					depart = '';
				}
				ouvrir();
			} );
			champs[ c[ 0 ] ] = b;
		} );

		// Coordonnées : deuxième temps, une fois les dates choisies.
		if ( ! form.querySelector( '[data-hs-erreur]' ).textContent.trim() || ! ( arrivee && depart ) ) {
			coord.hidden = true;
		} else {
			etape = 2;
		}

		function libre( nuit ) {
			return ! prises[ nuit ];
		}
		// Première nuit prise à partir de « debut » : le départ ne peut pas la dépasser.
		function limiteDepart( debut ) {
			var j = debut;
			for ( var i = 0; i < 120; i++ ) {
				if ( prises[ j ] ) {
					return j;
				}
				j = plusJours( j, 1 );
			}
			return plusJours( debut, 90 );
		}

		function majChamps() {
			var fmt = function ( s ) {
				if ( ! s ) {
					return 'Choisir';
				}
				var d = versDate( s );
				return d.getUTCDate() + ' ' + D.mois[ d.getUTCMonth() ];
			};
			champs.arrivee.querySelector( 'strong' ).textContent = fmt( arrivee );
			champs.depart.querySelector( 'strong' ).textContent  = fmt( depart );
			champs.arrivee.classList.toggle( 'est-vide', ! arrivee );
			champs.depart.classList.toggle( 'est-vide', ! depart );
			inArr.value = arrivee;
			inDep.value = depart;
		}

		function majRecap() {
			if ( ! ( arrivee && depart ) ) {
				recap.hidden = true;
				return;
			}
			var n      = ecart( arrivee, depart );
			var sejour = n * D.prix;
			var total  = sejour + ( D.menage || 0 );
			var ac     = D.acompte > 0 && D.acompte < 100 ? Math.min( total, Math.ceil( total * D.acompte / 100 / 100 ) * 100 ) : total;
			recap.querySelector( '[data-hs-l-sejour]' ).textContent = fcfa( D.prix ) + ' × ' + n + ( n > 1 ? ' nuits' : ' nuit' );
			recap.querySelector( '[data-hs-v-sejour]' ).textContent = fcfa( sejour );
			recap.querySelector( '[data-hs-v-total]' ).textContent  = fcfa( total );
			var a = recap.querySelector( '[data-hs-v-acompte]' );
			if ( a ) {
				a.textContent = fcfa( ac );
			}
			recap.hidden = false;
		}

		function montrerErreur( txt ) {
			erreur.textContent = txt;
			erreur.hidden = ! txt;
		}

		function ouvrir() {
			cal.hidden = false;
			champs.arrivee.setAttribute( 'aria-expanded', 'true' );
			champs.depart.setAttribute( 'aria-expanded', 'true' );
			dessiner();
			majChamps();
			majRecap();
			var actif = cal.querySelector( '[tabindex="0"]' );
			if ( actif ) {
				actif.focus();
			}
		}
		function fermer() {
			cal.hidden = true;
			champs.arrivee.setAttribute( 'aria-expanded', 'false' );
			champs.depart.setAttribute( 'aria-expanded', 'false' );
		}

		function dessiner() {
			var y   = +vue.slice( 0, 4 );
			var m   = +vue.slice( 5, 7 ) - 1;
			var premier = new Date( Date.UTC( y, m, 1 ) );
			var nbJours = new Date( Date.UTC( y, m + 1, 0 ) ).getUTCDate();
			var decal   = ( premier.getUTCDay() + 6 ) % 7; // lundi en premier
			var limite  = arrivee && ! depart ? limiteDepart( arrivee ) : null;
			var html    = '';
			var consigne = ! arrivee ? 'Choisissez votre date d\'arrivée' : ( ! depart ? 'Choisissez votre date de départ' + ( D.nuitsMin > 1 ? ' (' + D.nuitsMin + ' nuits minimum)' : '' ) : ecart( arrivee, depart ) + ( ecart( arrivee, depart ) > 1 ? ' nuits' : ' nuit' ) + ' sélectionnées' );

			html += '<div class="hs-cal__tete">';
			html += '<button type="button" class="hs-cal__nav" data-nav="-1" aria-label="Mois précédent"' + ( vue <= D.aujourdhui.slice( 0, 7 ) ? ' disabled' : '' ) + '><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>';
			html += '<p class="hs-cal__mois" aria-live="polite">' + D.mois[ m ] + ' ' + y + '</p>';
			html += '<button type="button" class="hs-cal__nav" data-nav="1" aria-label="Mois suivant"' + ( vue >= dernierMois ? ' disabled' : '' ) + '><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>';
			html += '</div>';
			html += '<p class="hs-cal__consigne">' + consigne + '</p>';
			html += '<table class="hs-cal__grille" role="grid"><thead><tr>';
			D.jours.forEach( function ( j ) { html += '<th scope="col" abbr="' + j + '">' + j + '</th>'; } );
			html += '</tr></thead><tbody><tr>';
			for ( var v = 0; v < decal; v++ ) {
				html += '<td></td>';
			}
			var focusPose = false;
			for ( var j = 1; j <= nbJours; j++ ) {
				var s       = versTexte( new Date( Date.UTC( y, m, j ) ) );
				var passe   = s < D.aujourdhui;
				var pris    = prises[ s ];
				var possible;
				if ( ! arrivee || depart ) {
					possible = ! passe && libre( s );               // choix de l'arrivée
				} else {
					possible = s > arrivee && s <= limite && ecart( arrivee, s ) >= D.nuitsMin; // choix du départ
					if ( s <= arrivee ) {
						possible = ! passe && libre( s );           // revenir en arrière = nouvelle arrivée
					}
				}
				var cls = [ 'hs-cal__jour' ];
				if ( pris ) {
					cls.push( 'est-pris' );
				}
				if ( s === arrivee ) {
					cls.push( 'est-debut' );
				}
				if ( s === depart ) {
					cls.push( 'est-fin' );
				}
				if ( arrivee && depart && s > arrivee && s < depart ) {
					cls.push( 'est-dedans' );
				}
				if ( s === D.aujourdhui ) {
					cls.push( 'est-aujourdhui' );
				}
				var tab = '-1';
				if ( possible && ! focusPose && ( s === ( depart || arrivee ) || ( ! arrivee && ! passe ) || ( arrivee && ! depart && s > arrivee ) ) ) {
					tab = '0';
					focusPose = true;
				}
				var etiquette = j + ' ' + D.mois[ m ] + ( pris ? ', déjà réservé' : '' ) + ( s === arrivee ? ', arrivée' : '' ) + ( s === depart ? ', départ' : '' );
				html += '<td><button type="button" class="' + cls.join( ' ' ) + '" data-jour="' + s + '" tabindex="' + tab + '" aria-label="' + etiquette + '"' + ( possible ? '' : ' disabled' ) + ( s === arrivee || s === depart ? ' aria-pressed="true"' : '' ) + '>' + j + '</button></td>';
				if ( ( decal + j ) % 7 === 0 && j < nbJours ) {
					html += '</tr><tr>';
				}
			}
			html += '</tr></tbody></table>';
			html += '<div class="hs-cal__pied"><span class="hs-cal__legende"><i></i>Déjà réservé</span>';
			html += '<span><button type="button" class="hs-cal__lien" data-effacer>Effacer</button>';
			html += '<button type="button" class="hs-cal__lien hs-cal__lien--fort" data-fermer>Fermer</button></span></div>';
			cal.innerHTML = html;
		}

		cal.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( 'button' );
			if ( ! b || b.disabled ) {
				return;
			}
			if ( b.hasAttribute( 'data-nav' ) ) {
				var y = +vue.slice( 0, 4 ), m = +vue.slice( 5, 7 ) - 1 + ( +b.getAttribute( 'data-nav' ) );
				vue = versTexte( new Date( Date.UTC( y, m, 1 ) ) ).slice( 0, 7 );
				dessiner();
				var nav = cal.querySelector( '[data-nav="' + b.getAttribute( 'data-nav' ) + '"]' );
				( nav && ! nav.disabled ? nav : cal.querySelector( '.hs-cal__mois' ) ).focus();
				return;
			}
			if ( b.hasAttribute( 'data-effacer' ) ) {
				arrivee = '';
				depart = '';
				dessiner();
				majChamps();
				majRecap();
				return;
			}
			if ( b.hasAttribute( 'data-fermer' ) ) {
				fermer();
				champs.arrivee.focus();
				return;
			}
			var s = b.getAttribute( 'data-jour' );
			if ( ! s ) {
				return;
			}
			montrerErreur( '' );
			if ( ! arrivee || depart || s <= arrivee ) {
				arrivee = s;
				depart  = '';
			} else {
				depart = s;
			}
			dessiner();
			majChamps();
			majRecap();
			if ( arrivee && depart ) {
				fermer();
				bouton.focus();
			} else {
				var f = cal.querySelector( '[tabindex="0"]' );
				if ( f ) {
					f.focus();
				}
			}
		} );

		// Clavier dans la grille : flèches = ±1 jour / ±1 semaine.
		cal.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				fermer();
				champs.arrivee.focus();
				return;
			}
			var b = e.target.closest( '[data-jour]' );
			var pas = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[ e.key ];
			if ( ! b || ! pas ) {
				return;
			}
			e.preventDefault();
			var cible = plusJours( b.getAttribute( 'data-jour' ), pas );
			if ( cible.slice( 0, 7 ) !== vue ) {
				if ( cible < D.aujourdhui || cible.slice( 0, 7 ) > dernierMois ) {
					return;
				}
				vue = cible.slice( 0, 7 );
				dessiner();
			}
			var n = cal.querySelector( '[data-jour="' + cible + '"]' );
			if ( n ) {
				cal.querySelectorAll( '[data-jour]' ).forEach( function ( x ) { x.tabIndex = -1; } );
				n.tabIndex = 0;
				n.focus();
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			// e.target.isConnected : un jour cliqué est retiré du DOM quand le calendrier se redessine.
			if ( ! cal.hidden && e.target.isConnected && ! cal.contains( e.target ) && ! e.target.closest( '.hs-resa__choix' ) ) {
				fermer();
			}
		} );

		form.addEventListener( 'submit', function ( e ) {
			if ( ! ( arrivee && depart ) ) {
				e.preventDefault();
				montrerErreur( 'Choisissez vos dates d\'arrivée et de départ.' );
				ouvrir();
				return;
			}
			if ( etape === 1 ) {
				e.preventDefault();
				etape = 2;
				coord.hidden = false;
				bouton.textContent = 'Envoyer ma demande';
				var nom = coord.querySelector( 'input[name="nom"]' );
				( nom.value ? coord.querySelector( 'input[name="telephone"]' ) : nom ).focus();
				return;
			}
			var nomV = form.querySelector( 'input[name="nom"]' ).value.trim();
			var telV = form.querySelector( 'input[name="telephone"]' ).value.replace( /\D/g, '' );
			var mail = form.querySelector( 'input[name="email"]' );
			if ( nomV.length < 2 || telV.length < 8 ) {
				e.preventDefault();
				montrerErreur( 'Indiquez votre nom et un numéro de téléphone joignable.' );
				( nomV.length < 2 ? form.querySelector( 'input[name="nom"]' ) : form.querySelector( 'input[name="telephone"]' ) ).focus();
				return;
			}
			if ( mail.value && ! mail.checkValidity() ) {
				e.preventDefault();
				montrerErreur( 'L\'adresse email ne semble pas valide.' );
				mail.focus();
				return;
			}
			bouton.disabled = true;
			bouton.textContent = 'Envoi en cours…';
		} );

		if ( etape === 2 ) {
			bouton.textContent = 'Envoyer ma demande';
		}
		majChamps();
		majRecap();
	}

	/* ---------- Page de suivi : copier, choix de l'opérateur ---------- */
	document.querySelectorAll( '[data-hs-copier]' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			var txt = b.getAttribute( 'data-hs-copier' );
			var fini = function () {
				var avant = b.textContent;
				b.textContent = 'Copié';
				b.classList.add( 'est-copie' );
				setTimeout( function () {
					b.textContent = avant;
					b.classList.remove( 'est-copie' );
				}, 1600 );
			};
			if ( navigator.clipboard ) {
				navigator.clipboard.writeText( txt ).then( fini, function () {} );
			}
		} );
	} );

	var choix = document.querySelectorAll( 'input[name="operateur"]' );
	choix.forEach( function ( r ) {
		r.addEventListener( 'change', function () {
			document.querySelectorAll( '[data-hs-moyen]' ).forEach( function ( c ) {
				c.classList.toggle( 'est-choisi', c.getAttribute( 'data-hs-moyen' ) === r.value );
			} );
		} );
	} );
	document.querySelectorAll( '[data-hs-moyen]' ).forEach( function ( c ) {
		c.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( 'button, a' ) ) {
				return;
			}
			var r = document.querySelector( 'input[name="operateur"][value="' + c.getAttribute( 'data-hs-moyen' ) + '"]' );
			if ( r && ! r.checked ) {
				r.checked = true;
				r.dispatchEvent( new Event( 'change' ) );
			}
		} );
	} );
}() );
