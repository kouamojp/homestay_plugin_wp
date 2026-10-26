<?php
/**
 * Espace client « Mon espace » : shortcode [hs_espace_membre] (page /mon-espace/ créée automatiquement).
 *
 * L'espace affiché dépend de ce qui est relié au compte connecté :
 *  - propriétaire  : fiche Propriétaire dont le compte (ID) ou l'email correspond ;
 *  - locataire     : fiche Locataire, idem ;
 *  - investisseur  : fiche Investisseur, idem ;
 *  - voyageur      : réservations au même compte ou au même email ;
 *  - technicien    : tickets attribués à ce compte.
 * Un membre de l'équipe peut prévisualiser n'importe quel espace : /mon-espace/?apercu=proprietaire&fiche=ID
 */

defined( 'ABSPATH' ) || exit;

class HS_Espace {

	const ESPACES = array(
		'proprietaire' => 'Mes biens',
		'locataire'    => 'Mon logement',
		'voyageur'     => 'Mes séjours',
		'investisseur' => 'Mon portefeuille',
		'technicien'   => 'Mes interventions',
	);

	public static function init() {
		add_shortcode( 'hs_espace_membre', array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_post_hs_signaler', array( __CLASS__, 'signaler' ) );
		add_action( 'admin_post_hs_ticket_etape', array( __CLASS__, 'ticket_etape' ) );
		add_action( 'template_redirect', array( __CLASS__, 'document' ) );
	}

	public static function url( array $args = array() ) {
		$page = get_page_by_path( 'mon-espace', OBJECT, 'page' );
		$base = $page ? get_permalink( $page ) : home_url( '/mon-espace/' );
		return $args ? add_query_arg( $args, $base ) : $base;
	}

	private static function assets() {
		if ( apply_filters( 'homystay_load_css', true ) ) {
			$deps = array();
			if ( apply_filters( 'homystay_polices', ! HS_Settings::get( 'polices_site' ) ) ) {
				wp_enqueue_style( 'homystay-polices', 'https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Young+Serif&display=swap', array(), null );
				$deps[] = 'homystay-polices';
			}
			wp_enqueue_style( 'homystay-espace', HS_URL . 'assets/css/espace.css', $deps, HS_VERSION );
			if ( ! $deps ) {
				wp_add_inline_style( 'homystay-espace', '.es{--es-titre:var(--e-global-typography-primary-font-family,inherit);--es-texte:var(--e-global-typography-text-font-family,inherit)}' );
			}
		}
		wp_enqueue_script( 'homystay-espace', HS_URL . 'assets/js/espace.js', array(), HS_VERSION, true );
		wp_localize_script( 'homystay-espace', 'hsEspace', array( 'rest' => esc_url_raw( rest_url( 'wp/v2/hs_bien' ) ) ) );
	}

	/* ---------- Qui regarde ? ---------- */

	/** Espaces auxquels un utilisateur a droit, avec la fiche correspondante. */
	public static function espaces_utilisateur( $user ) {
		$e = array();
		foreach ( array( 'proprietaire' => 'hs_proprietaire', 'locataire' => 'hs_locataire', 'investisseur' => 'hs_investisseur' ) as $k => $type ) {
			$f = HS_Donnees::fiche_utilisateur( $type, $user );
			if ( $f ) {
				$e[ $k ] = array( 'fiche' => $f, 'nom' => $f->post_title );
			}
		}
		if ( HS_Donnees::reservations_voyageur( $user->ID, $user->user_email ) || in_array( 'hs_voyageur', (array) $user->roles, true ) ) {
			$e['voyageur'] = array( 'user_id' => $user->ID, 'email' => $user->user_email, 'nom' => $user->display_name );
		}
		if ( in_array( 'hs_technicien', (array) $user->roles, true ) || HS_Donnees::tickets_technicien( $user->ID ) ) {
			$e['technicien'] = array( 'user_id' => $user->ID, 'nom' => $user->display_name );
		}
		return $e;
	}

	/** Choix proposés dans la barre d'aperçu (équipe). */
	public static function choix_apercu( $espace ) {
		$c = array();
		switch ( $espace ) {
			case 'proprietaire':
			case 'locataire':
			case 'investisseur':
				foreach ( HS_Donnees::posts( 'hs_' . $espace, array(), array( 'post_status' => 'any', 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
					$c[ (string) $p->ID ] = $p->post_title;
				}
				break;
			case 'voyageur':
				foreach ( HS_Donnees::posts( 'hs_reservation', array( array( 'key' => 'hs_email', 'value' => '', 'compare' => '!=' ) ), array( 'post_status' => 'any' ) ) as $r ) {
					$c[ HS_Donnees::m( $r->ID, 'email' ) ] = HS_Donnees::m( $r->ID, 'voyageur' );
				}
				asort( $c );
				break;
			case 'technicien':
				foreach ( HS_Donnees::posts( 'hs_ticket', array( array( 'key' => 'hs_technicien_nom', 'value' => '', 'compare' => '!=' ) ), array( 'post_status' => 'any' ) ) as $t ) {
					$n = HS_Donnees::m( $t->ID, 'technicien_nom' );
					$c[ $n ] = $n;
				}
				break;
		}
		return $c;
	}

	/** Contexte d'affichage : espace demandé, données d'identité, aperçu ou non. */
	public static function contexte() {
		$get = function ( $k ) {
			return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		};
		$apercu = $get( 'apercu' );
		if ( $apercu && isset( self::ESPACES[ $apercu ] ) && current_user_can( 'edit_hs_items' ) ) {
			$choix = self::choix_apercu( $apercu );
			$cle   = $get( 'fiche' );
			if ( ! isset( $choix[ $cle ] ) ) {
				$cle = $choix ? (string) key( $choix ) : '';
			}
			$ctx = array( 'espace' => $apercu, 'apercu' => true, 'choix' => $choix, 'cle' => $cle, 'espaces' => array(), 'nom' => $cle ? $choix[ $cle ] : '' );
			if ( in_array( $apercu, array( 'proprietaire', 'locataire', 'investisseur' ), true ) ) {
				$ctx['fiche'] = $cle ? get_post( (int) $cle ) : null;
			} elseif ( 'voyageur' === $apercu ) {
				$ctx['user_id'] = 0;
				$ctx['email']   = $cle;
			} else {
				$ctx['user_id'] = 0;
				$ctx['nom']     = $cle;
			}
			return $ctx;
		}

		$user     = wp_get_current_user();
		$espaces  = self::espaces_utilisateur( $user );
		$demande  = $get( 'espace' );
		$espace   = isset( $espaces[ $demande ] ) ? $demande : ( $espaces ? (string) key( $espaces ) : '' );
		$ctx      = array( 'espace' => $espace, 'apercu' => false, 'espaces' => $espaces );
		return $espace ? array_merge( $ctx, $espaces[ $espace ] ) : $ctx;
	}

	/* ---------- Affichage ---------- */

	public static function shortcode() {
		self::assets();
		if ( ! is_user_logged_in() ) {
			return HS_Templates::render( 'espace/connexion.php' );
		}
		$ctx = self::contexte();
		$html = '';
		if ( current_user_can( 'edit_hs_items' ) ) {
			$html .= HS_Templates::render( 'espace/apercu.php', array( 'ctx' => $ctx ) );
		}
		if ( ! $ctx['espace'] || ( isset( $ctx['fiche'] ) && ! $ctx['fiche'] && $ctx['apercu'] ) ) {
			return $html . HS_Templates::render( 'espace/aucun.php', array( 'ctx' => $ctx ) );
		}
		return $html . '<div class="es">' . HS_Templates::render( 'espace/entete.php', array( 'ctx' => $ctx ) ) . HS_Templates::render( 'espace/' . $ctx['espace'] . '.php', array( 'ctx' => $ctx ) ) . '</div>';
	}

	/** Contact de l'équipe avec message prérempli : WhatsApp (Réglages), sinon email de contact. */
	public static function whatsapp_equipe( $message ) {
		$n = HS_Settings::get( 'whatsapp' );
		if ( $n ) {
			return 'https://wa.me/' . $n . '?text=' . rawurlencode( $message );
		}
		$mail = HS_Settings::get( 'email' );
		return $mail ? 'mailto:' . $mail . '?subject=' . rawurlencode( 'HomyStay Capital' ) . '&body=' . rawurlencode( $message ) : '';
	}

	/** Libellé du bouton de contact selon le canal disponible. */
	public static function libelle_contact() {
		return HS_Settings::get( 'whatsapp' ) ? 'Écrire sur WhatsApp' : 'Nous écrire par email';
	}

	/* ---------- Actions ---------- */

	private static function retour( $etat ) {
		$ref = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'hs_ok', $etat, $ref ? $ref : self::url() ) );
		exit;
	}

	/** Un locataire signale une panne : crée un ticket « Signalé » sur le bien de son bail. */
	public static function signaler() {
		check_admin_referer( 'hs_signaler' );
		$user = wp_get_current_user();
		$loc  = HS_Donnees::fiche_utilisateur( 'hs_locataire', $user );
		$bail = $loc ? HS_Donnees::contrat_locataire( $loc->ID ) : null;
		$bien = $bail ? (int) HS_Donnees::m( $bail->ID, 'bien_id' ) : 0;
		$desc = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$cats = HS_Fields::for_type( 'hs_ticket' )['categorie']['options'];
		$cat  = isset( $_POST['categorie'] ) && isset( $cats[ wp_unslash( $_POST['categorie'] ) ] ) ? sanitize_key( wp_unslash( $_POST['categorie'] ) ) : 'autre';
		if ( ! $bien || '' === $desc ) {
			self::retour( 'erreur-signalement' );
		}
		$id = wp_insert_post( array(
			'post_type'    => 'hs_ticket',
			'post_status'  => 'publish',
			'post_title'   => wp_trim_words( $desc, 8, '…' ),
			'post_content' => $desc,
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, 'hs_bien_id', $bien );
			update_post_meta( $id, 'hs_categorie', $cat );
			update_post_meta( $id, 'hs_etape', 'signale' );
			update_post_meta( $id, 'hs_signale_par', $loc->post_title );
			update_post_meta( $id, 'hs_urgent', empty( $_POST['urgent'] ) ? 0 : 1 );
			$admin = HS_Settings::get( 'email' );
			if ( $admin ) {
				wp_mail( $admin, 'Nouvelle panne signalée : ' . get_the_title( $bien ), $loc->post_title . " signale :\n\n" . $desc . "\n\n" . admin_url( 'post.php?post=' . $id . '&action=edit' ) );
			}
		}
		self::retour( 'panne-signalee' );
	}

	/** Le technicien démarre ou termine une intervention qui lui est attribuée. */
	public static function ticket_etape() {
		check_admin_referer( 'hs_ticket_etape' );
		$id    = isset( $_POST['ticket'] ) ? absint( $_POST['ticket'] ) : 0;
		$etape = isset( $_POST['etape'] ) ? sanitize_key( wp_unslash( $_POST['etape'] ) ) : '';
		$user  = wp_get_current_user();
		if ( ! $id || 'hs_ticket' !== get_post_type( $id ) || ! in_array( $etape, array( 'intervention', 'cloture' ), true ) ) {
			self::retour( 'erreur' );
		}
		if ( (int) HS_Donnees::m( $id, 'technicien_id' ) !== (int) $user->ID && ! current_user_can( 'edit_hs_items' ) ) {
			wp_die( 'Cette intervention ne vous est pas attribuée.' );
		}
		update_post_meta( $id, 'hs_etape', $etape );
		$cout = isset( $_POST['cout'] ) ? absint( $_POST['cout'] ) : 0;
		if ( 'cloture' === $etape && $cout ) {
			global $wpdb;
			update_post_meta( $id, 'hs_cout', $cout );
			$bien = (int) HS_Donnees::m( $id, 'bien_id' );
			// Coût noté en dépense sur le bien, sauf s'il est imputé au locataire.
			if ( $bien && 'locataire' !== HS_Donnees::m( $id, 'imputation' ) ) {
				$wpdb->insert( $wpdb->prefix . 'hs_ecritures', array(
					'bien_id'         => $bien,
					'proprietaire_id' => (int) HS_Donnees::m( $bien, 'proprietaire_id' ),
					'sens'            => 'depense',
					'categorie'       => 'entretien',
					'libelle'         => 'Intervention : ' . get_the_title( $id ),
					'montant'         => $cout,
					'date_operation'  => HS_Donnees::aujourdhui(),
					'created_by'      => get_current_user_id(),
				) );
			}
		}
		if ( 'cloture' === $etape && ! empty( $_POST['note'] ) ) {
			wp_update_post( array( 'ID' => $id, 'post_content' => get_post_field( 'post_content', $id ) . "\n\nCompte rendu du technicien : " . sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) ) );
		}
		if ( 'cloture' === $etape && HS_Settings::get( 'email' ) ) {
			wp_mail( HS_Settings::get( 'email' ), 'Intervention terminée : ' . get_the_title( $id ), sprintf( "%s a terminé l'intervention « %s » (%s).%s\n\n%s", $user->display_name, get_the_title( $id ), get_the_title( (int) HS_Donnees::m( $id, 'bien_id' ) ), $cout ? ' Coût des pièces : ' . HS_Donnees::fcfa( $cout ) . '.' : '', admin_url( 'post.php?post=' . $id . '&action=edit' ) ) );
		}
		self::retour( 'cloture' === $etape ? 'intervention-terminee' : 'intervention-demarree' );
	}

	/* ---------- Documents imprimables : quittance, relevé, facture, dividendes ---------- */

	public static function document() {
		if ( empty( $_GET['hs_doc'] ) || ! is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		global $wpdb;
		$type  = sanitize_key( wp_unslash( $_GET['hs_doc'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$user  = wp_get_current_user();
		$equipe = current_user_can( 'edit_hs_items' );
		$doc   = null;

		if ( 'quittance' === $type ) {
			$e = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hs_echeances WHERE id = %d AND statut = 'paye'", $id ) ); // phpcs:ignore WordPress.DB
			if ( $e ) {
				$loc_id = (int) HS_Donnees::m( (int) $e->contrat_id, 'locataire_id' );
				$moi    = HS_Donnees::fiche_utilisateur( 'hs_locataire', $user );
				if ( $equipe || ( $moi && $moi->ID === $loc_id ) ) {
					$doc = array( 'titre' => 'Quittance de loyer', 'periode' => HS_Donnees::nom_mois( $e->periode ), 'qui' => get_the_title( $loc_id ), 'bien' => get_the_title( (int) $e->bien_id ),
						'lignes' => array( array( 'Loyer ' . HS_Donnees::de_mois( $e->periode ) . ', charges comprises', (int) $e->montant_paye ) ),
						'texte' => 'HomyStay Capital, mandataire du propriétaire, atteste avoir reçu de ' . get_the_title( $loc_id ) . ' la somme ci-dessous au titre du loyer du logement désigné, le ' . HS_Donnees::date_fr( $e->date_paiement, 'j F Y' ) . '.' );
				}
			}
		} elseif ( 'releve' === $type ) {
			$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hs_ecritures WHERE id = %d AND sens = 'reversement'", $id ) ); // phpcs:ignore WordPress.DB
			if ( $r ) {
				$moi = HS_Donnees::fiche_utilisateur( 'hs_proprietaire', $user );
				if ( $equipe || ( $moi && $moi->ID === (int) $r->proprietaire_id ) ) {
					$ym     = substr( $r->reference_paiement, -7 );
					$lignes = array();
					foreach ( HS_Donnees::biens_du_proprietaire( (int) $r->proprietaire_id ) as $b ) {
						$bb = HS_Donnees::bilan_bien( $b->ID, $ym );
						if ( $bb['encaisse'] || $bb['depenses'] ) {
							$lignes[] = array( $b->post_title . ' : encaissé', $bb['encaisse'] );
							$lignes[] = array( $b->post_title . ' : commission HomyStay (' . HS_Donnees::nombre( $bb['taux'] ) . ' %)', -$bb['commission'] );
							$lignes[] = array( $b->post_title . ' : dépenses', -$bb['depenses'] );
						}
					}
					$doc = array( 'titre' => 'Relevé de gestion', 'periode' => HS_Donnees::nom_mois( $ym ), 'qui' => get_the_title( (int) $r->proprietaire_id ), 'bien' => '', 'lignes' => $lignes,
						'total' => array( 'Net versé le ' . HS_Donnees::date_fr( $r->date_operation, 'j F Y' ), (int) $r->montant ), 'texte' => '' );
				}
			}
		} elseif ( 'facture' === $type ) {
			if ( 'hs_reservation' === get_post_type( $id ) ) {
				$mien = (int) HS_Donnees::m( $id, 'user_id' ) === (int) $user->ID || ( $user->user_email && HS_Donnees::m( $id, 'email' ) === $user->user_email );
				if ( $equipe || $mien ) {
					$nuits = HS_Donnees::nuits_dans( HS_Donnees::m( $id, 'arrivee' ), HS_Donnees::m( $id, 'depart' ), HS_Donnees::m( $id, 'arrivee' ), '2999-12-31' );
					$doc   = array( 'titre' => 'Facture de séjour', 'periode' => HS_Donnees::date_fr( HS_Donnees::m( $id, 'arrivee' ), 'j F Y' ) . ' au ' . HS_Donnees::date_fr( HS_Donnees::m( $id, 'depart' ), 'j F Y' ),
						'qui' => HS_Donnees::m( $id, 'voyageur' ), 'bien' => get_the_title( (int) HS_Donnees::m( $id, 'bien_id' ) ),
						'lignes' => array( array( sprintf( 'Séjour de %d nuits, %d voyageurs', $nuits, (int) HS_Donnees::m( $id, 'personnes' ) ), (int) HS_Donnees::m( $id, 'montant' ) ) ), 'texte' => 'Réservation n° ' . $id . '.' );
				}
			}
		} elseif ( 'dividendes' === $type ) {
			$inv = get_post( $id );
			$moi = HS_Donnees::fiche_utilisateur( 'hs_investisseur', $user );
			if ( $inv && 'hs_investisseur' === $inv->post_type && ( $equipe || ( $moi && $moi->ID === $inv->ID ) ) ) {
				$lignes = array();
				foreach ( HS_Donnees::dividendes( $inv->ID ) as $d ) {
					$lignes[] = array( 'Dividende ' . str_replace( '-T', ', trimestre ', $d->periode ) . ( $d->verse ? ', versé le ' . HS_Donnees::date_fr( $d->verse, 'j F Y' ) : '' ), (int) $d->montant );
				}
				$doc = array( 'titre' => 'Relevé des dividendes', 'periode' => 'Au ' . date_i18n( 'j F Y' ), 'qui' => $inv->post_title, 'bien' => '', 'lignes' => $lignes, 'texte' => '' );
			}
		}

		if ( ! $doc ) {
			wp_die( 'Document introuvable ou non autorisé.', 'HomyStay Capital', array( 'response' => 403 ) );
		}
		echo HS_Templates::render( 'espace/document.php', array( 'doc' => $doc ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public static function lien_document( $type, $id ) {
		return add_query_arg( array( 'hs_doc' => $type, 'id' => (int) $id ), home_url( '/' ) );
	}
}
