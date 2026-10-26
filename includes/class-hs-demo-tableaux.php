<?php
/**
 * Données de test des tableaux de bord : propriétaires, locataires, contrats, loyers, réservations,
 * écritures, reversements, tickets, assurance, contacts CRM, investisseur et projets.
 * Toutes les dates sont calculées par rapport au jour de création, pour que les tableaux restent parlants.
 * Les lignes des tables sont mémorisées (option hs_demo_lignes) pour pouvoir les supprimer.
 */

defined( 'ABSPATH' ) || exit;

class HS_Demo_Tableaux {

	private static $lignes = array();
	private static $j;

	private static function date( $decalage ) {
		return gmdate( 'Y-m-d', strtotime( self::$j . ' ' . ( $decalage >= 0 ? '+' : '' ) . (int) $decalage . ' days' ) );
	}

	private static function poste( $type, $titre, array $champs, array $extra = array() ) {
		$id = wp_insert_post( array_merge( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $titre ), $extra ) );
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		$defs = HS_Fields::for_type( $type );
		foreach ( $champs as $k => $v ) {
			update_post_meta( $id, 'hs_' . $k, isset( $defs[ $k ] ) ? HS_Fields::sanitize( $v, $defs[ $k ] ) : $v );
		}
		update_post_meta( $id, 'hs_demo', 1 );
		return (int) $id;
	}

	private static function ligne( $table, array $data ) {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'hs_' . $table, $data );
		if ( $wpdb->insert_id ) {
			self::$lignes[ $table ][] = (int) $wpdb->insert_id;
		}
		return (int) $wpdb->insert_id;
	}

	private static function ecriture( $sens, $cat, $libelle, $montant, $date, $bien = 0, $proprio = 0, array $plus = array() ) {
		return self::ligne( 'ecritures', array_merge( array(
			'bien_id' => $bien, 'proprietaire_id' => $proprio, 'sens' => $sens, 'categorie' => $cat, 'libelle' => $libelle,
			'montant' => (int) $montant, 'date_operation' => $date, 'moyen_paiement' => '', 'reference_paiement' => 'demo',
		), $plus ) );
	}

	/** @param int[] $biens  titre du bien => ID */
	public static function creer( array $biens ) {
		self::$j      = HS_Donnees::aujourdhui();
		self::$lignes = array();
		$b = function ( $debut_titre ) use ( $biens ) {
			foreach ( $biens as $t => $id ) {
				if ( 0 === strpos( $t, $debut_titre ) ) {
					return (int) $id;
				}
			}
			return 0;
		};
		$bonapriso = $b( 'Villa Bonapriso' );
		$akwa      = $b( 'Appartement meublé' );
		$bastos    = $b( 'Studio moderne' );
		$duplex    = $b( 'Duplex familial' );
		$kribi     = $b( 'Villa pieds' );
		$limbe     = $b( 'Appartement vue mer' );
		$golf      = $b( 'Chambre cosy' );
		$bonanjo   = $b( 'Plateau de bureaux' );
		$omni      = $b( 'Appartement standing' );
		$makepe    = $b( 'Villa contemporaine' );
		update_post_meta( $bonanjo, 'hs_statut', 'occupe' );
		update_post_meta( $omni, 'hs_statut', 'occupe' );

		/* Propriétaires et mandats */
		$proprios = array(
			'M. Albert Nkotto'   => array( array( $bonapriso, $akwa ), 'orange_money', '690000101', 'nkotto@exemple.cm' ),
			'Mme Hélène Ateba'   => array( array( $kribi, $limbe ), 'virement', '690000102', 'ateba@exemple.cm' ),
			'M. Serge Djeumo'    => array( array( $bastos, $golf, $omni ), 'mtn_momo', '670000103', 'djeumo@exemple.cm' ),
			'M. Paul Fokou'      => array( array( $duplex, $bonanjo, $makepe ), 'virement', '690000104', 'fokou@exemple.cm' ),
		);
		$id_proprio = array();
		foreach ( $proprios as $nom => $p ) {
			$pid = self::poste( 'hs_proprietaire', $nom, array( 'telephone' => $p[2], 'email' => $p[3], 'mode_reversement' => $p[1], 'adresse' => 'Douala' ) );
			$id_proprio[ $nom ] = $pid;
			foreach ( array_filter( $p[0] ) as $bien ) {
				update_post_meta( $bien, 'hs_proprietaire_id', $pid );
				self::poste( 'hs_contrat', 'Mandat de gestion : ' . get_the_title( $bien ), array(
					'type_contrat' => 'mandat', 'bien_id' => $bien, 'proprietaire_id' => $pid, 'date_debut' => self::date( -400 ), 'date_fin' => self::date( 700 ), 'commission' => 15, 'statut' => 'actif',
				) );
			}
		}

		/* Locataires, baux et loyers */
		$baux = array(
			array( 'M. Patrick Essomba', '677000201', 'essomba@exemple.cm', 'Comptable', 900000, $omni, 350000, 700000, -15 ),
			array( 'Kemi Conseil SARL', '699000202', 'contact@kemi-conseil.exemple.cm', 'Cabinet de conseil', 0, $bonanjo, 900000, 1800000, -4 ),
		);
		foreach ( $baux as $l ) {
			if ( ! $l[5] ) {
				continue;
			}
			$lid  = self::poste( 'hs_locataire', $l[0], array( 'telephone' => $l[1], 'email' => $l[2], 'profession' => $l[3], 'revenus' => $l[4], 'statut_dossier' => 'accepte' ) );
			$cid  = self::poste( 'hs_contrat', 'Bail : ' . get_the_title( $l[5] ), array(
				'type_contrat' => 'bail', 'bien_id' => $l[5], 'locataire_id' => $lid, 'proprietaire_id' => (int) get_post_meta( $l[5], 'hs_proprietaire_id', true ),
				'date_debut' => gmdate( 'Y-m-01', strtotime( self::$j . ' -7 months' ) ), 'date_fin' => gmdate( 'Y-m-t', strtotime( self::$j . ' +4 months' ) ),
				'loyer' => $l[6], 'caution' => $l[7], 'statut' => 'actif',
			) );
			$proprio = (int) get_post_meta( $l[5], 'hs_proprietaire_id', true );
			// Échéance impayée : due il y a N jours. Les 5 précédentes sont payées.
			$due = self::date( $l[8] );
			for ( $i = 5; $i >= 0; $i-- ) {
				$date_e = gmdate( 'Y-m-d', strtotime( $due . " -$i months" ) );
				$paye   = $i > 0;
				$paie   = gmdate( 'Y-m-d', strtotime( $date_e . ' -1 day' ) );
				$eid    = self::ligne( 'echeances', array(
					'contrat_id' => $cid, 'bien_id' => $l[5], 'periode' => substr( $date_e, 0, 7 ), 'montant_du' => $l[6], 'montant_paye' => $paye ? $l[6] : 0,
					'date_echeance' => $date_e, 'date_paiement' => $paye ? $paie : null, 'statut' => $paye ? 'paye' : 'appele', 'niveau_relance' => 0,
				) );
				if ( $paye ) {
					self::ecriture( 'revenu', 'loyer', 'Loyer ' . substr( $date_e, 0, 7 ) . ' : ' . $l[0], $l[6], $paie, $l[5], $proprio, array( 'contrat_id' => $cid, 'moyen_paiement' => 'orange_money' ) );
				}
			}
		}

		/* Réservations (et encaissements des séjours payés) */
		$v = array(
			// bien, voyageur, email, téléphone, arrivée (jours), nuits, personnes, paiement, consignes
			array( $kribi, 'Famille Mbarga', 'brigitte.mbarga@exemple.cm', '699000301', 0, 3, 5, 'paye', "Remise des clés sur place par Joseph, le gardien.\nLe code wifi est affiché dans le salon." ),
			array( $akwa, 'M. Fotso', 'fotso@exemple.cm', '699000302', -4, 4, 2, 'paye', '' ),
			array( $bastos, 'Mme Ngo Biyong', 'ngobiyong@exemple.cm', '699000303', 4, 7, 1, 'acompte', '' ),
			array( $bonapriso, 'M. Tchoupo', 'tchoupo@exemple.cm', '699000304', 12, 4, 6, 'attente', '' ),
			array( $golf, 'M. Abena', 'abena@exemple.cm', '699000305', 13, 2, 1, 'paye', '' ),
			array( $limbe, 'Famille Mbarga', 'brigitte.mbarga@exemple.cm', '699000301', -65, 3, 4, 'paye', '' ),
			array( $akwa, 'Famille Mbarga', 'brigitte.mbarga@exemple.cm', '699000301', -138, 4, 3, 'paye', '' ),
			array( $bonapriso, 'M. Kamdem', 'kamdem@exemple.cm', '699000306', -18, 3, 5, 'paye', '' ),
			array( $bonapriso, 'Mme Nana', 'nana@exemple.cm', '699000307', -10, 5, 4, 'paye', '' ),
			array( $akwa, 'M. Tagne', 'tagne@exemple.cm', '699000308', -17, 5, 2, 'paye', '' ),
			array( $akwa, 'Mme Bella', 'bella@exemple.cm', '699000309', -12, 4, 2, 'paye', '' ),
			array( $kribi, 'Famille Ekotto', 'ekotto@exemple.cm', '699000310', -14, 3, 6, 'paye', '' ),
			array( $kribi, 'M. Moukoko', 'moukoko@exemple.cm', '699000311', -8, 4, 4, 'paye', '' ),
			array( $limbe, 'Mme Enow', 'enow@exemple.cm', '699000312', -9, 3, 2, 'paye', '' ),
			array( $bastos, 'M. Biya', 'mbiya@exemple.cm', '699000313', -15, 5, 1, 'paye', '' ),
		);
		// Mois précédents : quelques séjours par mois sur 5 mois, pour l'historique et l'évolution de l'occupation.
		foreach ( array( 1, 2, 3, 4, 5 ) as $m ) {
			$base = -30 * $m - 5;
			$v[]  = array( $bonapriso, 'Séjour test', '', '', $base, 6 - ( $m % 3 ), 5, 'paye', '' );
			$v[]  = array( $kribi, 'Séjour test', '', '', $base + 8, 5 + ( $m % 2 ), 4, 'paye', '' );
			$v[]  = array( $akwa, 'Séjour test', '', '', $base + 3, 6, 2, 'paye', '' );
			$v[]  = array( $bastos, 'Séjour test', '', '', $base + 12, 4, 1, 'paye', '' );
		}
		foreach ( $v as $r ) {
			if ( ! $r[0] ) {
				continue;
			}
			$arr     = self::date( $r[4] );
			$dep     = self::date( $r[4] + $r[5] );
			$prix    = (int) get_post_meta( $r[0], 'hs_prix_courte', true );
			$montant = $prix * $r[5];
			$statut  = $dep < self::$j ? 'terminee' : 'confirmee';
			self::poste( 'hs_reservation', $r[1] . ' : ' . get_the_title( $r[0] ), array(
				'bien_id' => $r[0], 'voyageur' => $r[1], 'email' => $r[2], 'telephone' => $r[3], 'arrivee' => $arr, 'depart' => $dep,
				'personnes' => $r[6], 'montant' => $montant, 'paiement' => $r[7], 'statut' => $statut, 'source' => 'site', 'consignes' => $r[8],
			) );
			$proprio = (int) get_post_meta( $r[0], 'hs_proprietaire_id', true );
			if ( 'paye' === $r[7] ) {
				self::ecriture( 'revenu', 'sejour', 'Séjour ' . $r[1], $montant, min( $arr, self::$j ), $r[0], $proprio, array( 'moyen_paiement' => 'orange_money' ) );
			} elseif ( 'acompte' === $r[7] ) {
				self::ecriture( 'revenu', 'sejour', 'Acompte séjour ' . $r[1], (int) round( $montant * 0.3 ), self::$j, $r[0], $proprio, array( 'moyen_paiement' => 'mtn_momo' ) );
			}
		}

		/* Dépenses */
		$dep = array(
			array( $bonapriso, 'entretien', 'Entretien de la piscine et du jardin', 250000, -6 ),
			array( $akwa, 'menage', 'Ménage entre les séjours', 60000, -3 ),
			array( $kribi, 'entretien', 'Révision de la climatisation', 120000, -10 ),
			array( $bastos, 'menage', 'Ménage et linge', 35000, -5 ),
		);
		foreach ( array( 1, 2, 3, 4, 5 ) as $m ) {
			$dep[] = array( $bonapriso, 'entretien', 'Entretien courant', 90000 + 20000 * ( $m % 3 ), -30 * $m );
			$dep[] = array( $kribi, 'menage', 'Ménage', 60000, -30 * $m - 4 );
			$dep[] = array( $akwa, 'menage', 'Ménage', 45000, -30 * $m - 8 );
		}
		foreach ( $dep as $d ) {
			if ( $d[0] ) {
				self::ecriture( 'depense', $d[1], $d[2], $d[3], self::date( $d[4] ), $d[0], (int) get_post_meta( $d[0], 'hs_proprietaire_id', true ) );
			}
		}

		self::reversements( $id_proprio );

		/* Maintenance et assurance */
		$tickets = array(
			array( $bastos, 'Mitigeur de douche qui fuit', 'plomberie', 0, '10:00', 1, 'valide', 'La douche goutte en continu. À réparer avant l\'arrivée du voyageur.', -1 ),
			array( $golf, 'Chasse d\'eau qui coule en continu', 'plomberie', 0, '14:30', 0, 'valide', '', -2 ),
			array( $omni, 'Fuite sous l\'évier de la cuisine', 'plomberie', 2, '9 h à 12 h', 0, 'valide', 'L\'eau coule sous l\'évier depuis ce matin.', -3 ),
			array( $limbe, 'Climatisation de la chambre en panne', 'climatisation', 0, '', 0, 'signale', 'La clim ne refroidit plus.', -1 ),
		);
		foreach ( $tickets as $t ) {
			if ( ! $t[0] ) {
				continue;
			}
			self::poste( 'hs_ticket', $t[1], array(
				'bien_id' => $t[0], 'categorie' => $t[2], 'date_intervention' => 'signale' === $t[6] ? '' : self::date( $t[3] ), 'creneau' => $t[4], 'urgent' => $t[5],
				'etape' => $t[6], 'technicien_nom' => 'signale' === $t[6] ? '' : 'Paul Nguema', 'imputation' => 'proprietaire',
				'signale_par' => $omni === $t[0] ? 'M. Patrick Essomba' : 'Équipe HomyStay',
			), array( 'post_content' => $t[7], 'post_date' => self::date( $t[8] ) . ' 09:00:00' ) );
		}
		if ( $bonapriso ) {
			self::poste( 'hs_police', 'Assurance habitation : Villa Bonapriso', array( 'bien_id' => $bonapriso, 'type_police' => 'habitation', 'numero' => 'HAB-2025-00418', 'compagnie' => 'Assureur partenaire', 'prime' => 450000, 'echeance' => self::date( 12 ) ) );
		}

		/* CRM */
		$contacts = array(
			// nom, type, étape, créé (jours), besoin, source, relance, action, rdv (jours), heure, signature
			array( 'M. Bekolo', 'proprietaire', 'nouveau', -1, 'Villa à Bonamoussadi, souhaite une gestion complète.', 'Formulaire « Déposer un bien »', 0, 'Appeler pour fixer une visite', null, '', null ),
			array( 'Mme Ndzana', 'investisseur', 'nouveau', 0, 'Intéressée par le projet de Kribi, 5 000 000 FCFA.', 'Brochure téléchargée', 0, 'Envoyer le dossier du projet Kribi', null, '', null ),
			array( 'M. Owona', 'voyageur', 'nouveau', 0, 'Séjour de deux semaines à Douala en décembre.', 'WhatsApp', 1, 'Proposer deux logements à Bonapriso', null, '', null ),
			array( 'M. Mvondo', 'proprietaire', 'contacte', -9, 'Deux studios à Ngoa-Ekellé.', 'Recommandation', -1, 'Rappeler pour fixer la visite des studios', null, '', null ),
			array( 'Mme Eyenga', 'locataire', 'rdv', -6, 'Candidate à la location annuelle du duplex de Santa Barbara.', 'Site', 1, '', 0, '16:30', null ),
			array( 'M. Nana', 'investisseur', 'rdv', -5, 'Présentation du projet de Kribi.', 'Salon de l\'immobilier', 3, '', 2, '10:00', null ),
			array( 'M. Mbella', 'proprietaire', 'proposition', -20, 'Mandat de gestion à 15 %, appartement à Bonapriso.', 'Site', 2, 'Relancer sur la proposition', null, '', null ),
			array( 'SCI Les Palmiers', 'proprietaire', 'proposition', -25, 'Immeuble de 6 appartements à Makepè.', 'Recommandation', 0, 'Sans réponse à la proposition depuis 8 jours', null, '', null ),
			array( 'M. Albert Nkotto', 'proprietaire', 'signe', -60, 'Mandat signé, Appartement à Akwa.', 'Site', null, '', null, '', -10 ),
			array( 'Mme Hélène Ateba', 'proprietaire', 'signe', -70, 'Mandat signé, Villa à Kribi.', 'Recommandation', null, '', null, '', -15 ),
		);
		foreach ( $contacts as $c ) {
			$champs = array( 'type_contact' => $c[1], 'etape' => $c[2], 'besoin' => $c[4], 'source' => $c[5], 'telephone' => '69' . wp_rand( 1000000, 9999999 ), 'action' => $c[7], 'rdv_heure' => $c[9] );
			if ( null !== $c[6] ) {
				$champs['relance'] = self::date( $c[6] );
			}
			if ( null !== $c[8] ) {
				$champs['rdv'] = self::date( $c[8] );
			}
			if ( null !== $c[10] ) {
				$champs['date_signature'] = self::date( $c[10] );
			}
			self::poste( 'hs_contact', $c[0], $champs, array( 'post_date' => self::date( $c[3] ) . ' 10:00:00' ) );
		}

		/* Investisseur, projets, souscriptions, dividendes */
		$img = function ( $n ) {
			$a = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_hs_demo', 'meta_value' => sprintf( 'demo-%02d.jpg', $n ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			return $a ? (int) $a[0] : 0;
		};
		$p1 = self::poste( 'hs_projet', 'Résidence Les Cocotiers, Kribi', array( 'ville' => 'Kribi', 'montant_recherche' => 120000000, 'montant_leve' => 72000000, 'ticket_min' => 1000000, 'rendement_estime' => 9.5, 'duree_mois' => 60, 'statut' => 'ouvert', 'avancement' => 'En location' ),
			array( 'post_content' => 'Six villas en bord de mer louées à la nuit. Les loyers nets sont distribués chaque trimestre.', 'post_excerpt' => 'Six villas en bord de mer louées à la nuit, dividendes trimestriels.' ) );
		$p2 = self::poste( 'hs_projet', 'Immeuble de rapport, Makepè', array( 'ville' => 'Douala', 'montant_recherche' => 80000000, 'montant_leve' => 80000000, 'ticket_min' => 2000000, 'rendement_estime' => 10, 'duree_mois' => 48, 'statut' => 'complet', 'avancement' => 'Gros œuvre terminé' ),
			array( 'post_content' => 'Immeuble de huit appartements en construction. Mise en location prévue dans cinq mois.', 'post_excerpt' => 'Huit appartements en construction, mise en location dans cinq mois.' ) );
		if ( $img( 6 ) ) {
			set_post_thumbnail( $p1, $img( 6 ) );
		}
		if ( $img( 3 ) ) {
			set_post_thumbnail( $p2, $img( 3 ) );
		}
		$inv = self::poste( 'hs_investisseur', 'Mme Solange Ondoa', array( 'telephone' => '699000401', 'email' => 'ondoa@exemple.cm', 'mode_versement' => 'virement' ) );
		$s1  = self::ligne( 'investissements', array( 'projet_id' => $p1, 'investisseur_id' => $inv, 'montant' => 10000000, 'date_souscription' => self::date( -450 ), 'statut' => 'valide' ) );
		self::ligne( 'investissements', array( 'projet_id' => $p2, 'investisseur_id' => $inv, 'montant' => 5000000, 'date_souscription' => self::date( -330 ), 'statut' => 'valide' ) );
		// 4 derniers trimestres terminés, puis le trimestre en cours (estimé, non versé).
		$t   = (int) ceil( (int) gmdate( 'n', strtotime( self::$j ) ) / 3 );
		$an  = (int) gmdate( 'Y', strtotime( self::$j ) );
		$per = array();
		for ( $i = 4; $i >= 1; $i-- ) {
			$q = $t - $i;
			$a = $an;
			while ( $q < 1 ) {
				$q += 4;
				$a--;
			}
			$per[] = array( $a, $q );
		}
		foreach ( array( 220000, 235000, 240000, 250000 ) as $k => $montant ) {
			list( $a, $q ) = $per[ $k ];
			$fin_q = gmdate( 'Y-m-15', strtotime( sprintf( '%04d-%02d-01 +1 month', $a, $q * 3 ) ) );
			self::ligne( 'distributions', array( 'investissement_id' => $s1, 'periode' => $a . '-T' . $q, 'montant' => $montant, 'date_versement' => $fin_q, 'statut' => 'verse' ) );
		}
		self::ligne( 'distributions', array( 'investissement_id' => $s1, 'periode' => $an . '-T' . $t, 'montant' => 255000, 'date_versement' => null, 'statut' => 'calcule' ) );

		update_option( 'hs_demo_lignes', self::$lignes, false );
		update_option( 'hs_demo_tableaux', 1, false );
	}

	/** Reversements des 3 mois précédents, calculés sur les écritures de test. @param int[] $id_proprio nom => ID */
	private static function reversements( array $id_proprio ) {
		$mois = HS_Donnees::mois_courant();
		foreach ( array( 3, 2, 1 ) as $m ) {
			$ym = HS_Donnees::mois_decale( $mois, -$m );
			foreach ( $id_proprio as $nom => $pid ) {
				$net = 0;
				foreach ( HS_Donnees::biens_du_proprietaire( $pid ) as $bien ) {
					$net += HS_Donnees::bilan_bien( $bien->ID, $ym )['net'];
				}
				if ( $net > 0 ) {
					self::ecriture( 'reversement', 'reversement', 'Reversement ' . $ym . ' : ' . $nom, $net, gmdate( 'Y-m-05', strtotime( $ym . '-01 +1 month' ) ), 0, $pid, array(
						'moyen_paiement' => (string) get_post_meta( $pid, 'hs_mode_reversement', true ), 'reference_paiement' => 'reversement-' . $ym,
					) );
				}
			}
		}

	}

	/** Réparation (v0.5) : recrée les reversements de test refusés par MySQL avant l'élargissement de la colonne « sens ». */
	public static function reparer_reversements() {
		global $wpdb;
		if ( ! get_option( 'hs_demo_tableaux' ) || $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hs_ecritures WHERE sens = 'reversement'" ) ) { // phpcs:ignore WordPress.DB
			return;
		}
		self::$j      = HS_Donnees::aujourdhui();
		self::$lignes = (array) get_option( 'hs_demo_lignes', array() );
		$ids = array();
		foreach ( get_posts( array( 'post_type' => 'hs_proprietaire', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => 'hs_demo', 'meta_value' => 1 ) ) as $p ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			$ids[ $p->post_title ] = $p->ID;
		}
		self::reversements( $ids );
		update_option( 'hs_demo_lignes', self::$lignes, false );
	}

	/** Supprime les lignes de tables créées par la démo. */
	public static function supprimer_lignes() {
		global $wpdb;
		foreach ( (array) get_option( 'hs_demo_lignes', array() ) as $table => $ids ) {
			$ids = array_filter( array_map( 'absint', (array) $ids ) );
			if ( $ids && in_array( $table, array( 'ecritures', 'echeances', 'investissements', 'distributions' ), true ) ) {
				$wpdb->query( "DELETE FROM {$wpdb->prefix}hs_{$table} WHERE id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore WordPress.DB
			}
		}
		delete_option( 'hs_demo_lignes' );
		delete_option( 'hs_demo_tableaux' );
	}
}
