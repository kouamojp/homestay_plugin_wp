<?php
/**
 * Calculs des tableaux de bord : occupation, finances, impayés, CRM, espaces clients.
 * Toutes les dates sont au format Y-m-d, dans le fuseau du site.
 */

defined( 'ABSPATH' ) || exit;

class HS_Donnees {

	const COMMISSION_DEFAUT = 15;

	/* ---------- Outils ---------- */

	public static function aujourdhui() {
		return current_time( 'Y-m-d' );
	}

	public static function fcfa( $n ) {
		return number_format_i18n( (float) $n ) . ' FCFA';
	}

	public static function nombre( $n ) {
		return number_format_i18n( (float) $n );
	}

	/** Premier et dernier jour d'un mois « Y-m ». */
	public static function bornes( $ym ) {
		$debut = $ym . '-01';
		return array( $debut, gmdate( 'Y-m-t', strtotime( $debut ) ) );
	}

	public static function mois_courant() {
		return current_time( 'Y-m' );
	}

	public static function mois_decale( $ym, $delta ) {
		return gmdate( 'Y-m', strtotime( $ym . '-01 ' . ( $delta >= 0 ? '+' : '' ) . (int) $delta . ' month' ) );
	}

	public static function nom_mois( $ym, $court = false ) {
		return date_i18n( $court ? 'M' : 'F Y', strtotime( $ym . '-01' ) );
	}

	/** « de mars 2026 », « d'octobre 2026 » (élision devant voyelle). */
	public static function de_mois( $ym ) {
		$m = self::nom_mois( $ym );
		return ( preg_match( '/^[aeiouyàâéèêîôû]/iu', $m ) ? "d'" : 'de ' ) . $m;
	}

	public static function date_fr( $date, $format = 'j F' ) {
		return $date ? date_i18n( $format, strtotime( $date ) ) : '';
	}

	public static function m( $id, $cle ) {
		return HS_Fields::get( $id, $cle );
	}

	/** get_posts publiés (ou tous statuts pour les CPT privés) avec une meta_query. */
	public static function posts( $type, array $meta = array(), array $extra = array() ) {
		return get_posts( array_merge( array(
			'post_type'      => $type,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
			'no_found_rows'  => true,
		), $extra ) );
	}

	/** Nombre de nuits d'un séjour comprises dans [debut, fin] (nuit = date du soir). */
	public static function nuits_dans( $arrivee, $depart, $debut, $fin ) {
		$a = max( strtotime( $arrivee ), strtotime( $debut ) );
		$b = min( strtotime( $depart ), strtotime( $fin . ' +1 day' ) );
		return $b > $a ? (int) round( ( $b - $a ) / DAY_IN_SECONDS ) : 0;
	}

	/* ---------- Réservations et occupation ---------- */

	public static function reservations_actives( array $meta = array(), array $extra = array() ) {
		$meta[] = array( 'key' => 'hs_statut', 'value' => 'annulee', 'compare' => '!=' );
		return self::posts( 'hs_reservation', $meta, $extra );
	}

	/** Réservations qui chevauchent [debut, fin]. */
	public static function reservations_periode( $debut, $fin, $bien_id = 0 ) {
		$meta = array(
			array( 'key' => 'hs_arrivee', 'value' => $fin, 'compare' => '<=', 'type' => 'DATE' ),
			array( 'key' => 'hs_depart', 'value' => $debut, 'compare' => '>', 'type' => 'DATE' ),
		);
		if ( $bien_id ) {
			$meta[] = array( 'key' => 'hs_bien_id', 'value' => (int) $bien_id );
		}
		return self::reservations_actives( $meta );
	}

	/** Biens loués à la nuit (courte durée), publiés. */
	public static function biens_courte_duree() {
		return self::posts( 'hs_bien', array( array( 'key' => 'hs_offre_courte', 'value' => 1, 'type' => 'NUMERIC' ) ) );
	}

	/** Occupation d'un mois : globale et par ville. */
	public static function occupation( $ym ) {
		list( $debut, $fin ) = self::bornes( $ym );
		$jours = (int) gmdate( 't', strtotime( $debut ) );
		$villes = array();
		$par_bien = array();
		foreach ( self::biens_courte_duree() as $b ) {
			$v = self::m( $b->ID, 'ville' );
			$v = $v ? $v : 'Autre';
			if ( ! isset( $villes[ $v ] ) ) {
				$villes[ $v ] = array( 'reservees' => 0, 'dispo' => 0 );
			}
			$villes[ $v ]['dispo'] += $jours;
			$par_bien[ $b->ID ] = $v;
		}
		foreach ( self::reservations_periode( $debut, $fin ) as $r ) {
			$bien = (int) self::m( $r->ID, 'bien_id' );
			if ( isset( $par_bien[ $bien ] ) ) {
				$villes[ $par_bien[ $bien ] ]['reservees'] += self::nuits_dans( self::m( $r->ID, 'arrivee' ), self::m( $r->ID, 'depart' ), $debut, $fin );
			}
		}
		$tot_r = 0;
		$tot_d = 0;
		foreach ( $villes as &$v ) {
			$v['reservees'] = min( $v['reservees'], $v['dispo'] );
			$v['taux']      = $v['dispo'] ? (int) round( 100 * $v['reservees'] / $v['dispo'] ) : 0;
			$tot_r += $v['reservees'];
			$tot_d += $v['dispo'];
		}
		unset( $v );
		uasort( $villes, function ( $a, $b ) { return $b['taux'] - $a['taux']; } );
		return array( 'taux' => $tot_d ? (int) round( 100 * $tot_r / $tot_d ) : null, 'villes' => $villes );
	}

	/** Calendrier d'un bien sur un mois : tableau jour => réservé (bool). */
	public static function calendrier( $bien_id, $ym ) {
		list( $debut, $fin ) = self::bornes( $ym );
		$jours = (int) gmdate( 't', strtotime( $debut ) );
		$cal = array_fill( 1, $jours, false );
		foreach ( self::reservations_periode( $debut, $fin, $bien_id ) as $r ) {
			$a = strtotime( max( self::m( $r->ID, 'arrivee' ), $debut ) );
			$d = strtotime( min( self::m( $r->ID, 'depart' ), gmdate( 'Y-m-d', strtotime( $fin . ' +1 day' ) ) ) );
			for ( $t = $a; $t < $d; $t += DAY_IN_SECONDS ) {
				$cal[ (int) gmdate( 'j', $t ) ] = true;
			}
		}
		return $cal;
	}

	/** Mouvements du jour : arrivées, départs, rendez-vous CRM. */
	public static function agenda_du_jour() {
		$j = self::aujourdhui();
		$lignes = array();
		foreach ( self::reservations_actives( array( array( 'key' => 'hs_depart', 'value' => $j ) ) ) as $r ) {
			$lignes[] = array( 'heure' => '11:00', 'type' => 'depart', 'titre' => 'Départ : ' . get_the_title( (int) self::m( $r->ID, 'bien_id' ) ), 'detail' => self::m( $r->ID, 'voyageur' ) . '. État des lieux et ménage à planifier.', 'lien' => get_edit_post_link( $r->ID ) );
		}
		foreach ( self::reservations_actives( array( array( 'key' => 'hs_arrivee', 'value' => $j ) ) ) as $r ) {
			$p = self::m( $r->ID, 'paiement' );
			$lignes[] = array( 'heure' => '14:00', 'type' => 'arrivee', 'titre' => 'Arrivée : ' . get_the_title( (int) self::m( $r->ID, 'bien_id' ) ), 'detail' => self::m( $r->ID, 'voyageur' ) . ', ' . self::nuits_dans( $j, self::m( $r->ID, 'depart' ), $j, '2999-12-31' ) . ' nuits. ' . ( 'paye' === $p ? 'Payé en totalité.' : 'Paiement à vérifier.' ), 'lien' => get_edit_post_link( $r->ID ) );
		}
		foreach ( self::posts( 'hs_contact', array( array( 'key' => 'hs_rdv', 'value' => $j ) ) ) as $c ) {
			$lignes[] = array( 'heure' => self::m( $c->ID, 'rdv_heure' ) ? self::m( $c->ID, 'rdv_heure' ) : '—', 'type' => 'visite', 'titre' => 'Rendez-vous : ' . $c->post_title, 'detail' => self::m( $c->ID, 'besoin' ), 'lien' => get_edit_post_link( $c->ID ) );
		}
		usort( $lignes, function ( $a, $b ) { return strcmp( $a['heure'], $b['heure'] ); } );
		return $lignes;
	}

	public static function prochaines_reservations( $n = 6 ) {
		return self::reservations_actives(
			array( array( 'key' => 'hs_depart', 'value' => self::aujourdhui(), 'compare' => '>', 'type' => 'DATE' ) ),
			array( 'posts_per_page' => $n, 'meta_key' => 'hs_arrivee', 'orderby' => 'meta_value', 'order' => 'ASC' ) // phpcs:ignore WordPress.DB.SlowDBQuery
		);
	}

	public static function biens_par_statut() {
		$n = array_fill_keys( array_keys( HS_Fields::STATUTS_BIEN ), 0 );
		foreach ( get_posts( array( 'post_type' => 'hs_bien', 'post_status' => array( 'publish', 'draft', 'pending' ), 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
			$s = self::m( $id, 'statut' );
			if ( isset( $n[ $s ] ) ) {
				$n[ $s ]++;
			}
		}
		return $n;
	}

	/* ---------- Finances ---------- */

	private static function table( $nom ) {
		global $wpdb;
		return $wpdb->prefix . 'hs_' . $nom;
	}

	/** Somme des écritures d'un sens sur une période, filtrable par bien ou propriétaire. */
	public static function somme( $sens, $debut, $fin, array $filtres = array() ) {
		global $wpdb;
		$sql    = 'SELECT COALESCE(SUM(montant),0) FROM ' . self::table( 'ecritures' ) . " WHERE statut = 'valide' AND sens = %s AND date_operation BETWEEN %s AND %s";
		$params = array( $sens, $debut, $fin );
		foreach ( array( 'bien_id', 'proprietaire_id' ) as $col ) {
			if ( ! empty( $filtres[ $col ] ) ) {
				$sql     .= " AND $col = %d";
				$params[] = (int) $filtres[ $col ];
			}
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB
	}

	public static function serie_mensuelle( $ym, $nb = 6 ) {
		$serie = array();
		for ( $i = $nb - 1; $i >= 0; $i-- ) {
			$m = self::mois_decale( $ym, -$i );
			list( $d, $f ) = self::bornes( $m );
			$serie[ $m ] = array( 'encaisse' => self::somme( 'revenu', $d, $f ), 'depense' => self::somme( 'depense', $d, $f ) );
		}
		return $serie;
	}

	/** Taux de commission d'un bien : mandat actif, sinon 15 %. */
	public static function commission_bien( $bien_id ) {
		$mandats = self::posts( 'hs_contrat', array(
			array( 'key' => 'hs_type_contrat', 'value' => 'mandat' ),
			array( 'key' => 'hs_bien_id', 'value' => (int) $bien_id ),
			array( 'key' => 'hs_statut', 'value' => 'actif' ),
		), array( 'post_status' => 'any', 'posts_per_page' => 1 ) );
		$t = $mandats ? (float) self::m( $mandats[0]->ID, 'commission' ) : 0;
		return $t > 0 ? $t : self::COMMISSION_DEFAUT;
	}

	public static function biens_du_proprietaire( $proprietaire_id ) {
		return self::posts( 'hs_bien', array( array( 'key' => 'hs_proprietaire_id', 'value' => (int) $proprietaire_id ) ) );
	}

	/** Bilan d'un bien sur un mois. */
	public static function bilan_bien( $bien_id, $ym ) {
		list( $d, $f ) = self::bornes( $ym );
		$enc  = self::somme( 'revenu', $d, $f, array( 'bien_id' => $bien_id ) );
		$dep  = self::somme( 'depense', $d, $f, array( 'bien_id' => $bien_id ) );
		$taux = self::commission_bien( $bien_id );
		$com  = (int) round( $enc * $taux / 100 );
		return array( 'encaisse' => $enc, 'depenses' => $dep, 'commission' => $com, 'taux' => $taux, 'net' => $enc - $com - $dep );
	}

	/** Reversements d'un mois, un par propriétaire ayant des mouvements. */
	public static function reversements( $ym ) {
		$lignes = array();
		$tot    = array( 'encaisse' => 0, 'commission' => 0, 'depenses' => 0, 'net' => 0 );
		foreach ( self::posts( 'hs_proprietaire', array(), array( 'post_status' => 'any', 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
			$l = array( 'proprietaire' => $p, 'biens' => array(), 'encaisse' => 0, 'commission' => 0, 'depenses' => 0, 'net' => 0 );
			foreach ( self::biens_du_proprietaire( $p->ID ) as $b ) {
				$bb = self::bilan_bien( $b->ID, $ym );
				$l['biens'][] = $b->post_title;
				foreach ( array( 'encaisse', 'commission', 'depenses', 'net' ) as $k ) {
					$l[ $k ] += $bb[ $k ];
				}
			}
			if ( ! $l['encaisse'] && ! $l['depenses'] ) {
				continue;
			}
			$l['verse'] = self::reversement_fait( $p->ID, $ym );
			$l['moyen'] = HS_Shortcodes::format( self::m( $p->ID, 'mode_reversement' ), HS_Fields::for_type( 'hs_proprietaire' )['mode_reversement'] );
			foreach ( $tot as $k => $v ) {
				$tot[ $k ] += $l[ $k ];
			}
			$lignes[] = $l;
		}
		return array( 'lignes' => $lignes, 'total' => $tot );
	}

	public static function reversement_fait( $proprietaire_id, $ym ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table( 'ecritures' ) . " WHERE sens = 'reversement' AND proprietaire_id = %d AND reference_paiement = %s LIMIT 1", $proprietaire_id, 'reversement-' . $ym ) ); // phpcs:ignore WordPress.DB
	}

	public static function reversements_du_proprietaire( $proprietaire_id, $n = 12 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'ecritures' ) . " WHERE sens = 'reversement' AND proprietaire_id = %d ORDER BY date_operation DESC LIMIT %d", $proprietaire_id, $n ) ); // phpcs:ignore WordPress.DB
	}

	/** Loyers échus et non payés. */
	public static function impayes() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'echeances' ) . " WHERE statut <> 'paye' AND date_echeance < %s ORDER BY date_echeance ASC", self::aujourdhui() ) ); // phpcs:ignore WordPress.DB
		foreach ( $rows as $r ) {
			$r->jours     = (int) floor( ( strtotime( self::aujourdhui() ) - strtotime( $r->date_echeance ) ) / DAY_IN_SECONDS );
			$r->reste     = (int) $r->montant_du - (int) $r->montant_paye;
			$r->niveau    = $r->jours >= 30 ? 'Mise en demeure' : ( $r->jours >= 10 ? 'Relance J+10' : ( $r->jours >= 3 ? 'Relance J+3' : 'En retard' ) );
			$loc          = (int) self::m( (int) $r->contrat_id, 'locataire_id' );
			$r->locataire = $loc ? get_the_title( $loc ) : '';
			$r->telephone = $loc ? self::m( $loc, 'telephone' ) : '';
		}
		return $rows;
	}

	public static function echeances_du_contrat( $contrat_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'echeances' ) . ' WHERE contrat_id = %d ORDER BY periode DESC', $contrat_id ) ); // phpcs:ignore WordPress.DB
	}

	public static function polices_a_renouveler( $jours = 30 ) {
		$j = self::aujourdhui();
		return self::posts( 'hs_police', array(
			array( 'key' => 'hs_echeance', 'value' => array( $j, gmdate( 'Y-m-d', strtotime( $j . " +$jours days" ) ) ), 'compare' => 'BETWEEN', 'type' => 'DATE' ),
		), array( 'post_status' => 'any' ) );
	}

	public static function tickets_ouverts() {
		return self::posts( 'hs_ticket', array( array( 'key' => 'hs_etape', 'value' => 'cloture', 'compare' => '!=' ) ), array( 'post_status' => 'any' ) );
	}

	/* ---------- CRM ---------- */

	public static function contacts_par_etape() {
		$etapes = HS_Fields::for_type( 'hs_contact' )['etape']['options'];
		unset( $etapes['perdu'] );
		$col = array();
		foreach ( $etapes as $k => $label ) {
			$col[ $k ] = array( 'label' => $label, 'contacts' => self::posts( 'hs_contact', array( array( 'key' => 'hs_etape', 'value' => $k ) ), array( 'post_status' => 'any', 'orderby' => 'modified', 'order' => 'DESC' ) ) );
		}
		return $col;
	}

	public static function crm_indicateurs() {
		$j      = self::aujourdhui();
		$lundi  = gmdate( 'Y-m-d', strtotime( $j . ' monday this week' ) );
		$dim    = gmdate( 'Y-m-d', strtotime( $lundi . ' +6 days' ) );
		$il_y_a = gmdate( 'Y-m-d', strtotime( $j . ' -90 days' ) );
		$actifs = array( 'key' => 'hs_etape', 'value' => array( 'signe', 'perdu' ), 'compare' => 'NOT IN' );
		$nouveaux = count( get_posts( array( 'post_type' => 'hs_contact', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'date_query' => array( array( 'after' => $lundi, 'inclusive' => true ) ) ) ) );
		$crees_90 = count( get_posts( array( 'post_type' => 'hs_contact', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'date_query' => array( array( 'after' => $il_y_a, 'inclusive' => true ) ) ) ) );
		$signes_90 = count( self::posts( 'hs_contact', array( array( 'key' => 'hs_etape', 'value' => 'signe' ), array( 'key' => 'hs_date_signature', 'value' => $il_y_a, 'compare' => '>=', 'type' => 'DATE' ) ), array( 'post_status' => 'any', 'fields' => 'ids' ) ) );
		return array(
			'nouveaux'   => $nouveaux,
			'rdv'        => count( self::posts( 'hs_contact', array( array( 'key' => 'hs_rdv', 'value' => array( $lundi, $dim ), 'compare' => 'BETWEEN', 'type' => 'DATE' ) ), array( 'post_status' => 'any', 'fields' => 'ids' ) ) ),
			'conversion' => $crees_90 ? (int) round( 100 * $signes_90 / $crees_90 ) : null,
			'retard'     => count( self::posts( 'hs_contact', array( $actifs, array( 'key' => 'hs_relance', 'value' => $j, 'compare' => '<', 'type' => 'DATE' ) ), array( 'post_status' => 'any', 'fields' => 'ids' ) ) ),
		);
	}

	public static function relances_du_jour() {
		return self::posts( 'hs_contact', array(
			array( 'key' => 'hs_etape', 'value' => array( 'signe', 'perdu' ), 'compare' => 'NOT IN' ),
			array( 'key' => 'hs_relance', 'value' => self::aujourdhui(), 'compare' => '<=', 'type' => 'DATE' ),
		), array( 'post_status' => 'any', 'meta_key' => 'hs_relance', 'orderby' => 'meta_value', 'order' => 'ASC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}

	public static function signes_du_mois( $ym ) {
		list( $d, $f ) = self::bornes( $ym );
		return count( self::posts( 'hs_contact', array( array( 'key' => 'hs_etape', 'value' => 'signe' ), array( 'key' => 'hs_date_signature', 'value' => array( $d, $f ), 'compare' => 'BETWEEN', 'type' => 'DATE' ) ), array( 'post_status' => 'any', 'fields' => 'ids' ) ) );
	}

	/** Lien WhatsApp vers un numéro (format libre), message prérempli. */
	public static function whatsapp( $numero, $message = '' ) {
		$n = preg_replace( '/\D/', '', (string) $numero );
		if ( ! $n ) {
			return '';
		}
		if ( 9 === strlen( $n ) ) {
			$n = '237' . $n; // numéro camerounais sans indicatif
		}
		return 'https://wa.me/' . $n . ( $message ? '?text=' . rawurlencode( $message ) : '' );
	}

	/* ---------- Espaces clients ---------- */

	/** Fiche (propriétaire, locataire, investisseur) liée à un utilisateur : par ID de compte, sinon par email. */
	public static function fiche_utilisateur( $type, $user ) {
		if ( ! $user || ! $user->ID ) {
			return null;
		}
		$p = self::posts( $type, array( array( 'key' => 'hs_user_id', 'value' => $user->ID ) ), array( 'post_status' => 'any', 'posts_per_page' => 1 ) );
		if ( ! $p && $user->user_email ) {
			$p = self::posts( $type, array( array( 'key' => 'hs_email', 'value' => $user->user_email ) ), array( 'post_status' => 'any', 'posts_per_page' => 1 ) );
		}
		return $p ? $p[0] : null;
	}

	public static function reservations_voyageur( $user_id, $email ) {
		$or = array( 'relation' => 'OR' );
		if ( $user_id ) {
			$or[] = array( 'key' => 'hs_user_id', 'value' => (int) $user_id );
		}
		if ( $email ) {
			$or[] = array( 'key' => 'hs_email', 'value' => $email );
		}
		if ( count( $or ) < 2 ) {
			return array();
		}
		return self::reservations_actives( array( $or ), array( 'post_status' => 'any', 'meta_key' => 'hs_arrivee', 'orderby' => 'meta_value', 'order' => 'DESC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}

	public static function contrat_locataire( $locataire_id ) {
		$c = self::posts( 'hs_contrat', array(
			array( 'key' => 'hs_type_contrat', 'value' => 'bail' ),
			array( 'key' => 'hs_locataire_id', 'value' => (int) $locataire_id ),
		), array( 'post_status' => 'any', 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
		return $c ? $c[0] : null;
	}

	public static function tickets_du_bien( $bien_id, $ouverts = true ) {
		$meta = array( array( 'key' => 'hs_bien_id', 'value' => (int) $bien_id ) );
		if ( $ouverts ) {
			$meta[] = array( 'key' => 'hs_etape', 'value' => 'cloture', 'compare' => '!=' );
		}
		return self::posts( 'hs_ticket', $meta, array( 'post_status' => 'any', 'orderby' => 'date', 'order' => 'DESC' ) );
	}

	public static function tickets_technicien( $user_id, $nom = '' ) {
		$or = array( 'relation' => 'OR' );
		if ( $user_id ) {
			$or[] = array( 'key' => 'hs_technicien_id', 'value' => (int) $user_id );
		}
		if ( $nom ) {
			$or[] = array( 'key' => 'hs_technicien_nom', 'value' => $nom );
		}
		if ( count( $or ) < 2 ) {
			return array();
		}
		return self::posts( 'hs_ticket', array(
			$or,
			array( 'key' => 'hs_etape', 'value' => array( 'valide', 'intervention' ), 'compare' => 'IN' ),
		), array( 'post_status' => 'any', 'meta_key' => 'hs_date_intervention', 'orderby' => 'meta_value', 'order' => 'ASC' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	}

	public static function investissements( $investisseur_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'investissements' ) . " WHERE investisseur_id = %d AND statut <> 'annule' ORDER BY date_souscription ASC", $investisseur_id ) ); // phpcs:ignore WordPress.DB
		foreach ( $rows as $r ) {
			$r->projet = get_post( (int) $r->projet_id );
		}
		return $rows;
	}

	/** Dividendes d'un investisseur, groupés par période (ordre chronologique). */
	public static function dividendes( $investisseur_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT d.periode, SUM(d.montant) AS montant, MAX(d.date_versement) AS verse, MIN(d.statut) AS statut FROM ' . self::table( 'distributions' ) . ' d INNER JOIN ' . self::table( 'investissements' ) . ' i ON i.id = d.investissement_id WHERE i.investisseur_id = %d GROUP BY d.periode ORDER BY d.periode ASC',
			$investisseur_id
		) ); // phpcs:ignore WordPress.DB
		return $rows;
	}
}
