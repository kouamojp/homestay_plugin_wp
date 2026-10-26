<?php
/**
 * Réservation en ligne des biens loués à la nuit.
 *
 * Parcours :
 *  1. Page du bien : calendrier (nuits occupées grisées), voyageurs, prix calculé → « Réserver ».
 *  2. Coordonnées → réservation « Demande », paiement « En attente » ; les dates sont bloquées
 *     pendant le délai de paiement (Réglages), puis la demande expire si rien n'est déclaré.
 *  3. Page de suivi (/?hs_resa=ID&cle=…) : instructions Orange Money / MTN MoMo, puis le voyageur
 *     saisit le numéro qui a payé et l'ID de transaction → paiement « À vérifier », équipe prévenue.
 *  4. L'équipe vérifie dans son compte Mobile Money et clique « Paiement reçu, confirmer » :
 *     écriture de revenu, statut « Confirmée », compte voyageur créé ou relié, confirmation envoyée.
 *
 * Le prix est toujours recalculé côté serveur ; la disponibilité est revérifiée juste avant l'enregistrement.
 * Paiement automatique plus tard : brancher un prestataire sur l'action « homystay_paiement_recu » (voir README).
 */

defined( 'ABSPATH' ) || exit;

class HS_Reservation {

	const CRON = 'hs_expirer_demandes';

	/** Statut avant l'enregistrement en cours (pour ne confirmer qu'au passage à « Confirmée »). */
	private static $avant = array();

	public static function init() {
		foreach ( array( 'hs_reserver' => 'reserver', 'hs_declarer_paiement' => 'declarer_paiement' ) as $action => $methode ) {
			add_action( 'admin_post_' . $action, array( __CLASS__, $methode ) );
			add_action( 'admin_post_nopriv_' . $action, array( __CLASS__, $methode ) );
		}
		add_action( 'admin_post_hs_resa_action', array( __CLASS__, 'action_equipe' ) );
		add_action( 'template_redirect', array( __CLASS__, 'page_suivi' ) );
		add_action( self::CRON, array( __CLASS__, 'expirer' ) );
		add_action( 'init', function () {
			if ( ! wp_next_scheduled( self::CRON ) ) {
				wp_schedule_event( time() + 300, 'hourly', self::CRON );
			}
		} );
		// Après l'enregistrement des champs (meta box, priorité 10) : confirmer si l'équipe a passé le statut à « Confirmée ».
		// save_post (et non save_post_hs_reservation, qui passe AVANT la meta box) : 1 = avant, 30 = après l'enregistrement des champs.
		add_action( 'save_post', function ( $id, $post ) {
			if ( 'hs_reservation' === $post->post_type ) {
				self::$avant[ $id ] = get_post_meta( $id, 'hs_statut', true );
			}
		}, 1, 2 );
		add_action( 'save_post', array( __CLASS__, 'apres_enregistrement' ), 30, 2 );
		add_action( 'add_meta_boxes_hs_reservation', array( __CLASS__, 'boite_suivi' ) );
		add_filter( 'manage_hs_reservation_posts_columns', array( __CLASS__, 'colonnes' ) );
		add_action( 'manage_hs_reservation_posts_custom_column', array( __CLASS__, 'colonne' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/* ---------- Règles ---------- */

	/** Le bien se réserve-t-il en ligne ? */
	public static function actif( $bien_id ) {
		return HS_Settings::get( 'resa_en_ligne' )
			&& HS_Fields::get( $bien_id, 'offre_courte' )
			&& (int) HS_Fields::get( $bien_id, 'prix_courte' ) > 0
			&& 'disponible' === HS_Fields::get( $bien_id, 'statut' )
			&& apply_filters( 'homystay_reservation_active', true, $bien_id );
	}

	public static function nuits_min( $bien_id ) {
		return max( 1, (int) HS_Fields::get( $bien_id, 'nuits_min' ) );
	}

	public static function capacite( $bien_id ) {
		$c = (int) HS_Fields::get( $bien_id, 'capacite' );
		return $c > 0 ? $c : 10;
	}

	/** Moyens de paiement proposés : ceux dont le numéro est renseigné dans les Réglages. */
	public static function moyens() {
		$m = array();
		if ( HS_Settings::get( 'orange_numero' ) ) {
			$m['orange'] = array( 'nom' => 'Orange Money', 'numero' => HS_Settings::get( 'orange_numero' ), 'titulaire' => HS_Settings::get( 'orange_nom' ), 'code' => '#150#' );
		}
		if ( HS_Settings::get( 'mtn_numero' ) ) {
			$m['mtn'] = array( 'nom' => 'MTN MoMo', 'numero' => HS_Settings::get( 'mtn_numero' ), 'titulaire' => HS_Settings::get( 'mtn_nom' ), 'code' => '*126#' );
		}
		return $m;
	}

	/** Nuits déjà prises (Y-m-d de chaque soir) sur les 12 prochains mois. */
	public static function nuits_occupees( $bien_id, $exclure = 0 ) {
		self::expirer();
		$debut = HS_Donnees::aujourdhui();
		$fin   = gmdate( 'Y-m-d', strtotime( $debut . ' +13 months' ) );
		$nuits = array();
		foreach ( HS_Donnees::reservations_periode( $debut, $fin, $bien_id ) as $r ) {
			if ( (int) $r->ID === (int) $exclure ) {
				continue;
			}
			$a = strtotime( HS_Donnees::m( $r->ID, 'arrivee' ) );
			$d = strtotime( HS_Donnees::m( $r->ID, 'depart' ) );
			for ( $t = $a; $t < $d; $t += DAY_IN_SECONDS ) {
				$nuits[ gmdate( 'Y-m-d', $t ) ] = true;
			}
		}
		ksort( $nuits );
		return array_keys( $nuits );
	}

	/** Les nuits de [arrivee, depart[ sont-elles toutes libres ? */
	public static function disponible( $bien_id, $arrivee, $depart, $exclure = 0 ) {
		$prises = array_flip( self::nuits_occupees( $bien_id, $exclure ) );
		for ( $t = strtotime( $arrivee ); $t < strtotime( $depart ); $t += DAY_IN_SECONDS ) {
			if ( isset( $prises[ gmdate( 'Y-m-d', $t ) ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Prix d'un séjour, recalculé côté serveur.
	 *
	 * @return array|WP_Error [nuits, prix_nuit, sejour, menage, total, a_payer, acompte]
	 */
	public static function devis( $bien_id, $arrivee, $depart, $personnes ) {
		$ok = '/^\d{4}-\d{2}-\d{2}$/';
		if ( ! preg_match( $ok, (string) $arrivee ) || ! preg_match( $ok, (string) $depart ) ) {
			return new WP_Error( 'dates', 'Choisissez vos dates d\'arrivée et de départ.' );
		}
		if ( $arrivee < HS_Donnees::aujourdhui() ) {
			return new WP_Error( 'passe', 'La date d\'arrivée est déjà passée.' );
		}
		$nuits = (int) round( ( strtotime( $depart ) - strtotime( $arrivee ) ) / DAY_IN_SECONDS );
		if ( $nuits < 1 ) {
			return new WP_Error( 'ordre', 'La date de départ doit suivre la date d\'arrivée.' );
		}
		if ( $nuits < self::nuits_min( $bien_id ) ) {
			return new WP_Error( 'min', sprintf( 'Ce logement se réserve pour %d nuits au minimum.', self::nuits_min( $bien_id ) ) );
		}
		if ( $nuits > 90 ) {
			return new WP_Error( 'long', 'Pour un séjour de plus de 90 nuits, contactez-nous : nous vous proposerons une location au mois.' );
		}
		$personnes = (int) $personnes;
		if ( $personnes < 1 || $personnes > self::capacite( $bien_id ) ) {
			return new WP_Error( 'personnes', sprintf( 'Ce logement accueille %d personnes au maximum.', self::capacite( $bien_id ) ) );
		}
		$prix    = (int) HS_Fields::get( $bien_id, 'prix_courte' );
		$menage  = (int) HS_Fields::get( $bien_id, 'frais_menage' );
		$total   = $prix * $nuits + $menage;
		$acompte = (int) HS_Settings::get( 'acompte' );
		$a_payer = $acompte >= 100 || 0 === $acompte ? $total : (int) ( ceil( $total * $acompte / 100 / 100 ) * 100 ); // arrondi à la centaine
		return apply_filters( 'homystay_devis', array(
			'nuits' => $nuits, 'prix_nuit' => $prix, 'sejour' => $prix * $nuits, 'menage' => $menage,
			'total' => $total, 'a_payer' => min( $a_payer, $total ), 'acompte' => $acompte >= 100 || 0 === $acompte ? 100 : $acompte,
		), $bien_id, $arrivee, $depart, $personnes );
	}

	/** Données de la réservation pour le calendrier de la page du bien (JSON). */
	public static function donnees_calendrier( $bien_id ) {
		return array(
			'occupees'  => self::nuits_occupees( $bien_id ),
			'prix'      => (int) HS_Fields::get( $bien_id, 'prix_courte' ),
			'menage'    => (int) HS_Fields::get( $bien_id, 'frais_menage' ),
			'nuitsMin'  => self::nuits_min( $bien_id ),
			'acompte'   => (int) HS_Settings::get( 'acompte' ),
			'aujourdhui' => HS_Donnees::aujourdhui(),
			'mois'      => array_values( array_map( function ( $m ) { return date_i18n( 'F', mktime( 0, 0, 0, $m, 1, 2026 ) ); }, range( 1, 12 ) ) ),
			'jours'     => array( 'lu', 'ma', 'me', 'je', 've', 'sa', 'di' ),
		);
	}

	/* ---------- Demande ---------- */

	private static function retour_bien( $bien_id, $erreur, array $valeurs = array() ) {
		$url = add_query_arg( array_merge( array( 'hs_resa_erreur' => rawurlencode( $erreur ) ), $valeurs ), get_permalink( $bien_id ) );
		wp_safe_redirect( $url . '#hs-reserver' );
		exit;
	}

	private static function post( $k ) {
		return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/** Formulaire de la page du bien → réservation « Demande ». */
	public static function reserver() {
		$bien_id = absint( self::post( 'bien' ) );
		if ( ! $bien_id || 'hs_bien' !== get_post_type( $bien_id ) ) {
			wp_die( 'Logement introuvable.', 'HomyStay Capital', array( 'response' => 400, 'back_link' => true ) );
		}
		if ( ! wp_verify_nonce( self::post( '_hs_nonce' ), 'hs_reserver_' . $bien_id ) ) {
			self::retour_bien( $bien_id, 'La page a expiré. Rechargez-la et recommencez.' );
		}
		if ( '' !== self::post( 'site_web' ) ) { // champ piège invisible : robot
			wp_safe_redirect( get_permalink( $bien_id ) );
			exit;
		}
		$arrivee   = self::post( 'arrivee' );
		$depart    = self::post( 'depart' );
		$personnes = absint( self::post( 'personnes' ) );
		$nom       = self::post( 'nom' );
		$tel       = self::post( 'telephone' );
		$email     = sanitize_email( self::post( 'email' ) );
		$message   = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$garder    = array( 'arrivee' => $arrivee, 'depart' => $depart, 'personnes' => $personnes );

		if ( ! self::actif( $bien_id ) ) {
			self::retour_bien( $bien_id, 'Ce logement ne se réserve pas en ligne pour le moment. Écrivez-nous.' );
		}
		$devis = self::devis( $bien_id, $arrivee, $depart, $personnes );
		if ( is_wp_error( $devis ) ) {
			self::retour_bien( $bien_id, $devis->get_error_message(), $garder );
		}
		if ( mb_strlen( $nom ) < 2 || strlen( preg_replace( '/\D/', '', $tel ) ) < 8 ) {
			self::retour_bien( $bien_id, 'Indiquez votre nom et un numéro de téléphone joignable.', $garder );
		}
		if ( self::post( 'email' ) && ! is_email( $email ) ) {
			self::retour_bien( $bien_id, 'L\'adresse email ne semble pas valide.', $garder );
		}
		if ( ! self::disponible( $bien_id, $arrivee, $depart ) ) {
			self::retour_bien( $bien_id, 'Ces dates viennent d\'être réservées. Choisissez d\'autres dates.', $garder );
		}

		$id = wp_insert_post( array(
			'post_type'   => 'hs_reservation',
			'post_status' => 'publish',
			'post_title'  => sprintf( '%s, %s au %s', $nom, HS_Donnees::date_fr( $arrivee, 'j M' ), HS_Donnees::date_fr( $depart, 'j M Y' ) ),
		), true );
		if ( is_wp_error( $id ) ) {
			self::retour_bien( $bien_id, 'La demande n\'a pas pu être enregistrée. Réessayez ou écrivez-nous.', $garder );
		}
		$user = is_user_logged_in() ? wp_get_current_user() : null;
		$meta = array(
			'bien_id' => $bien_id, 'voyageur' => $nom, 'telephone' => $tel, 'email' => $email ? $email : ( $user ? $user->user_email : '' ),
			'user_id' => $user ? $user->ID : 0, 'arrivee' => $arrivee, 'depart' => $depart, 'personnes' => $personnes,
			'montant' => $devis['total'], 'a_payer' => $devis['a_payer'], 'paiement' => 'attente', 'statut' => 'demande',
			'source' => 'site', 'message' => $message,
		);
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, 'hs_' . $k, $v );
		}
		update_post_meta( $id, 'hs_menage', $devis['menage'] );
		update_post_meta( $id, 'hs_cle', wp_generate_password( 24, false ) );
		update_post_meta( $id, 'hs_expire_le', gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS * (int) HS_Settings::get( 'delai_paiement' ) ) ); // UTC

		$suivi = self::url_suivi( $id );
		self::mail_equipe( 'Nouvelle demande de réservation : ' . get_the_title( $bien_id ), sprintf(
			"%s (%s%s) demande %s du %s au %s, %d personne(s).\nTotal : %s, acompte attendu : %s.\n%s\nLa demande expire dans %d heures sans paiement.\n\n%s",
			$nom, $tel, $meta['email'] ? ', ' . $meta['email'] : '', get_the_title( $bien_id ), HS_Donnees::date_fr( $arrivee, 'j F' ), HS_Donnees::date_fr( $depart, 'j F Y' ), $personnes,
			HS_Donnees::fcfa( $devis['total'] ), HS_Donnees::fcfa( $devis['a_payer'] ), $message ? "\nMessage : " . $message . "\n" : '', (int) HS_Settings::get( 'delai_paiement' ),
			admin_url( 'post.php?post=' . $id . '&action=edit' )
		) );
		if ( $meta['email'] ) {
			self::mail( $meta['email'], 'Votre demande de réservation : ' . get_the_title( $bien_id ), sprintf(
				"Bonjour %s,\n\nNous avons bien reçu votre demande pour %s, du %s au %s (%d nuits, %d personne(s)).\n\nTotal du séjour : %s\nÀ payer pour confirmer : %s\n\nPayez par Orange Money ou MTN MoMo, puis indiquez l'ID de transaction sur cette page :\n%s\n\nVos dates sont gardées pendant %d heures.\n\nL'équipe HomyStay Capital",
				$nom, get_the_title( $bien_id ), HS_Donnees::date_fr( $arrivee, 'l j F' ), HS_Donnees::date_fr( $depart, 'l j F Y' ), $devis['nuits'], $personnes,
				HS_Donnees::fcfa( $devis['total'] ), HS_Donnees::fcfa( $devis['a_payer'] ), $suivi, (int) HS_Settings::get( 'delai_paiement' )
			) );
		}
		do_action( 'homystay_reservation_demandee', $id );
		wp_safe_redirect( $suivi );
		exit;
	}

	public static function url_suivi( $id ) {
		return add_query_arg( array( 'hs_resa' => (int) $id, 'cle' => get_post_meta( $id, 'hs_cle', true ) ), home_url( '/' ) );
	}

	/** Accès à la page de suivi : la clé du lien, le compte du voyageur ou l'équipe. */
	public static function acces( $id, $cle = '' ) {
		if ( ! $id || 'hs_reservation' !== get_post_type( $id ) ) {
			return false;
		}
		$vraie = (string) get_post_meta( $id, 'hs_cle', true );
		if ( $vraie && $cle && hash_equals( $vraie, (string) $cle ) ) {
			return true;
		}
		if ( current_user_can( 'edit_hs_items' ) ) {
			return true;
		}
		$user = wp_get_current_user();
		return $user->ID && ( (int) HS_Donnees::m( $id, 'user_id' ) === (int) $user->ID || ( $user->user_email && HS_Donnees::m( $id, 'email' ) === $user->user_email ) );
	}

	/** Demandes non payées dont le délai est passé → « Expirée » (dates libérées). */
	public static function expirer() {
		static $fait = false;
		if ( $fait ) {
			return;
		}
		$fait = true;
		$ids  = HS_Donnees::posts( 'hs_reservation', array(
			array( 'key' => 'hs_statut', 'value' => 'demande' ),
			array( 'key' => 'hs_paiement', 'value' => 'attente' ),
			array( 'key' => 'hs_expire_le', 'value' => gmdate( 'Y-m-d H:i:s' ), 'compare' => '<', 'type' => 'DATETIME' ),
		), array( 'post_status' => 'any', 'fields' => 'ids' ) );
		foreach ( $ids as $id ) {
			update_post_meta( $id, 'hs_statut', 'expiree' );
		}
	}

	/* ---------- Page de suivi et paiement ---------- */

	public static function page_suivi() {
		if ( empty( $_GET['hs_resa'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$id  = absint( $_GET['hs_resa'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$cle = isset( $_GET['cle'] ) ? sanitize_text_field( wp_unslash( $_GET['cle'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! self::acces( $id, $cle ) ) {
			wp_die( 'Ce lien de réservation n\'est pas valide. Utilisez le lien reçu par email ou connectez-vous à votre espace.', 'HomyStay Capital', array( 'response' => 403 ) );
		}
		self::expirer();
		nocache_headers();
		self::$suivi = array( 'resa_id' => $id, 'cle' => $cle );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_filter( 'pre_get_document_title', function () { return 'Ma réservation | ' . get_bloginfo( 'name' ); }, 99 );
		add_filter( 'body_class', function ( $c ) { $c[] = 'hs-page-reservation'; return $c; } );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets_suivi' ), 20 );
		// Gabarit choisi en dernier (après Elementor Pro) : le thème et Elementor chargent normalement en-tête, pied et styles globaux.
		add_filter( 'template_include', function () { return HS_Templates::locate( 'page-reservation.php' ); }, 999 );
	}

	/** Données de la page de suivi en cours (lues par templates/page-reservation.php). */
	public static $suivi = null;

	public static function assets_suivi() {
		$deps = array();
		if ( apply_filters( 'homystay_polices', ! HS_Settings::get( 'polices_site' ) ) ) {
			wp_enqueue_style( 'homystay-polices', 'https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&family=Young+Serif&display=swap', array(), null );
			$deps[] = 'homystay-polices';
		}
		// Styles globaux d'Elementor (polices et couleurs du kit) : la page n'est pas une page Elementor.
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->frontend ) ) {
			try {
				\Elementor\Plugin::$instance->frontend->enqueue_styles();
				$kit = (int) get_option( 'elementor_active_kit' );
				if ( $kit && class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
					\Elementor\Core\Files\CSS\Post::create( $kit )->enqueue();
				}
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			}
		}
		wp_enqueue_style( 'homystay-bien', HS_URL . 'assets/css/bien.css', $deps, HS_VERSION );
		wp_enqueue_style( 'homystay-reservation', HS_URL . 'assets/css/reservation.css', array( 'homystay-bien' ), HS_VERSION );
		if ( ! $deps ) {
			wp_add_inline_style( 'homystay-bien', '.hs-bien{--hs-font-titre:var(--e-global-typography-primary-font-family,system-ui,sans-serif);--hs-font-texte:var(--e-global-typography-text-font-family,system-ui,sans-serif)}' );
		}
		wp_enqueue_script( 'homystay-reservation', HS_URL . 'assets/js/reservation.js', array(), HS_VERSION, true );
	}

	/** Date limite de paiement (stockée en UTC), affichée dans le fuseau et la langue du site. */
	public static function date_limite( $id, $format = 'l j F à H:i' ) {
		$gmt = get_post_meta( $id, 'hs_expire_le', true );
		return $gmt ? date_i18n( $format, strtotime( get_date_from_gmt( $gmt ) ) ) : '';
	}

	/** Le voyageur déclare son paiement Mobile Money. */
	public static function declarer_paiement() {
		$id  = absint( self::post( 'resa' ) );
		$cle = self::post( 'cle' );
		if ( ! self::acces( $id, $cle ) || ! wp_verify_nonce( self::post( '_hs_nonce' ), 'hs_paiement_' . $id ) ) {
			wp_die( 'Lien de réservation non valide ou expiré. Rechargez la page.', 'HomyStay Capital', array( 'response' => 403, 'back_link' => true ) );
		}
		$retour = function ( $etat ) use ( $id ) {
			$url = add_query_arg( array( 'hs_resa' => $id, 'cle' => get_post_meta( $id, 'hs_cle', true ), 'etat' => $etat ), home_url( '/' ) );
			wp_safe_redirect( $url . '#paiement' );
			exit;
		};
		self::expirer();
		$statut = HS_Donnees::m( $id, 'statut' );
		if ( 'expiree' === $statut ) {
			// Paiement déclaré après l'expiration : on relance la demande si les dates sont encore libres.
			if ( ! self::disponible( (int) HS_Donnees::m( $id, 'bien_id' ), HS_Donnees::m( $id, 'arrivee' ), HS_Donnees::m( $id, 'depart' ), $id ) ) {
				$retour( 'plus-libre' );
			}
			update_post_meta( $id, 'hs_statut', 'demande' );
		} elseif ( 'demande' !== $statut ) {
			$retour( 'deja' );
		}
		$moyens = self::moyens();
		$op     = self::post( 'operateur' );
		$num    = self::post( 'numero_paiement' );
		$trans  = strtoupper( preg_replace( '/[^A-Za-z0-9.\-]/', '', self::post( 'transaction' ) ) );
		if ( ! isset( $moyens[ $op ] ) || strlen( preg_replace( '/\D/', '', $num ) ) < 8 || strlen( $trans ) < 5 ) {
			$retour( 'incomplet' );
		}
		update_post_meta( $id, 'hs_operateur', $op );
		update_post_meta( $id, 'hs_numero_paiement', $num );
		update_post_meta( $id, 'hs_transaction', $trans );
		update_post_meta( $id, 'hs_paiement', 'verification' );
		update_post_meta( $id, 'hs_paiement_declare_le', current_time( 'mysql' ) );

		self::mail_equipe( 'Paiement à vérifier : ' . get_the_title( (int) HS_Donnees::m( $id, 'bien_id' ) ), sprintf(
			"%s déclare avoir payé %s par %s.\nNuméro : %s\nID de transaction : %s\n\nVérifiez la transaction dans votre compte %s, puis confirmez la réservation :\n%s",
			HS_Donnees::m( $id, 'voyageur' ), HS_Donnees::fcfa( HS_Donnees::m( $id, 'a_payer' ) ), $moyens[ $op ]['nom'], $num, $trans, $moyens[ $op ]['nom'],
			admin_url( 'post.php?post=' . $id . '&action=edit' )
		) );
		do_action( 'homystay_paiement_declare', $id );
		$retour( 'declare' );
	}

	/* ---------- Confirmation par l'équipe ---------- */

	/**
	 * Paiement vérifié → écriture de revenu, statut « Confirmée », compte voyageur, message de confirmation.
	 * Aussi déclenchable par un prestataire de paiement : do_action( 'homystay_paiement_recu', $id, $montant, $reference ).
	 */
	public static function paiement_recu( $id, $montant = 0, $reference = '' ) {
		$montant = (int) ( $montant ? $montant : HS_Donnees::m( $id, 'a_payer' ) );
		$total   = (int) HS_Donnees::m( $id, 'montant' );
		$deja    = (int) HS_Donnees::m( $id, 'montant_paye' );
		$recu    = $deja + $montant;
		update_post_meta( $id, 'hs_montant_paye', $recu );
		update_post_meta( $id, 'hs_paiement', $recu >= $total ? 'paye' : 'acompte' );
		if ( $reference ) {
			update_post_meta( $id, 'hs_transaction', $reference );
		}
		$bien = (int) HS_Donnees::m( $id, 'bien_id' );
		$op   = HS_Donnees::m( $id, 'operateur' );
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'hs_ecritures', array(
			'bien_id'            => $bien,
			'proprietaire_id'    => (int) HS_Donnees::m( $bien, 'proprietaire_id' ),
			'sens'               => 'revenu',
			'categorie'          => 'sejour',
			'libelle'            => ( $recu >= $total ? 'Séjour ' : 'Acompte séjour ' ) . HS_Donnees::m( $id, 'voyageur' ),
			'montant'            => $montant,
			'date_operation'     => HS_Donnees::aujourdhui(),
			'moyen_paiement'     => array( 'orange' => 'orange_money', 'mtn' => 'mtn_momo' )[ $op ] ?? (string) $op,
			'reference_paiement' => (string) HS_Donnees::m( $id, 'transaction' ),
			'created_by'         => get_current_user_id(),
		) );
		update_post_meta( $id, 'hs_statut', 'confirmee' );
		self::confirmer( $id );
	}

	/** Statut « Confirmée » : consignes par défaut, compte voyageur, confirmation (une seule fois). */
	public static function confirmer( $id ) {
		if ( get_post_meta( $id, 'hs_confirmation_envoyee', true ) ) {
			return;
		}
		update_post_meta( $id, 'hs_confirmation_envoyee', current_time( 'mysql' ) );
		if ( ! HS_Donnees::m( $id, 'consignes' ) && HS_Settings::get( 'consignes' ) ) {
			update_post_meta( $id, 'hs_consignes', HS_Settings::get( 'consignes' ) );
		}
		$acces = self::compte_voyageur( $id );
		$email = HS_Donnees::m( $id, 'email' );
		if ( $email ) {
			$bien  = (int) HS_Donnees::m( $id, 'bien_id' );
			$reste = max( 0, (int) HS_Donnees::m( $id, 'montant' ) - (int) HS_Donnees::m( $id, 'montant_paye' ) );
			$texte = sprintf(
				"Bonjour %s,\n\nVotre réservation est confirmée : %s, du %s (à partir de %s) au %s (départ avant %s).\nAdresse : %s\n\nPayé : %s%s\n\n%s%s\n\nÀ bientôt,\nL'équipe HomyStay Capital",
				HS_Donnees::m( $id, 'voyageur' ), get_the_title( $bien ),
				HS_Donnees::date_fr( HS_Donnees::m( $id, 'arrivee' ), 'l j F' ), HS_Settings::get( 'heure_arrivee' ),
				HS_Donnees::date_fr( HS_Donnees::m( $id, 'depart' ), 'l j F Y' ), HS_Settings::get( 'heure_depart' ),
				implode( ', ', array_filter( array( HS_Donnees::m( $bien, 'adresse' ), HS_Donnees::m( $bien, 'quartier' ), HS_Donnees::m( $bien, 'ville' ) ) ) ),
				HS_Donnees::fcfa( HS_Donnees::m( $id, 'montant_paye' ) ), $reste ? '. Reste à régler à l\'arrivée : ' . HS_Donnees::fcfa( $reste ) : ' (séjour réglé)',
				HS_Donnees::m( $id, 'consignes' ) ? "Consignes d'arrivée :\n" . HS_Donnees::m( $id, 'consignes' ) . "\n\n" : '',
				$acces ? "Votre espace HomyStay (séjours, consignes, factures) :\n" . $acces : "Votre espace HomyStay : " . HS_Espace::url()
			);
			self::mail( $email, 'Réservation confirmée : ' . get_the_title( $bien ), $texte );
		}
		do_action( 'homystay_reservation_confirmee', $id );
	}

	/**
	 * Crée ou relie le compte voyageur (email de la réservation).
	 *
	 * @return string Lien à envoyer : choix du mot de passe (nouveau compte) ou page « Mon espace ».
	 */
	public static function compte_voyageur( $id ) {
		$email = HS_Donnees::m( $id, 'email' );
		if ( ! $email || ! is_email( $email ) ) {
			return '';
		}
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			update_post_meta( $id, 'hs_user_id', $user->ID );
			return HS_Espace::url();
		}
		if ( ! apply_filters( 'homystay_creer_compte_voyageur', true, $id ) ) {
			return '';
		}
		$login = sanitize_user( strtolower( strstr( $email, '@', true ) ), true );
		$base  = $login ? $login : 'voyageur';
		$i     = 1;
		while ( username_exists( $login ) || ! $login ) {
			$login = $base . ( ++$i );
		}
		$nom     = HS_Donnees::m( $id, 'voyageur' );
		$user_id = wp_insert_user( array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 20 ),
			'display_name' => $nom,
			'first_name'   => $nom,
			'role'         => 'hs_voyageur',
		) );
		if ( is_wp_error( $user_id ) ) {
			return '';
		}
		update_post_meta( $id, 'hs_user_id', $user_id );
		update_user_meta( $user_id, 'hs_telephone', HS_Donnees::m( $id, 'telephone' ) );
		$cle = get_password_reset_key( get_userdata( $user_id ) );
		if ( is_wp_error( $cle ) ) {
			return HS_Espace::url();
		}
		return network_site_url( 'wp-login.php?action=rp&key=' . $cle . '&login=' . rawurlencode( $login ), 'login' );
	}

	/** L'équipe a passé le statut à « Confirmée » depuis l'écran d'édition. */
	public static function apres_enregistrement( $post_id, $post ) {
		if ( 'hs_reservation' !== $post->post_type || wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status || ! current_user_can( 'edit_hs_items' ) || ! isset( $_POST['hs_fields_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$avant = isset( self::$avant[ $post_id ] ) ? self::$avant[ $post_id ] : '';
		if ( 'confirmee' === HS_Donnees::m( $post_id, 'statut' ) && 'confirmee' !== $avant ) {
			self::confirmer( $post_id );
		}
	}

	/** Boutons de l'équipe : confirmer, paiement introuvable, annuler. */
	public static function action_equipe() {
		$id   = isset( $_GET['resa'] ) ? absint( $_GET['resa'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$quoi = isset( $_GET['quoi'] ) ? sanitize_key( wp_unslash( $_GET['quoi'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		check_admin_referer( 'hs_resa_' . $quoi . '_' . $id );
		if ( ! current_user_can( 'edit_hs_items' ) || 'hs_reservation' !== get_post_type( $id ) ) {
			wp_die( 'Action non autorisée.' );
		}
		$email = HS_Donnees::m( $id, 'email' );
		$bien  = get_the_title( (int) HS_Donnees::m( $id, 'bien_id' ) );
		if ( 'confirmer' === $quoi ) {
			$montant = isset( $_GET['montant'] ) ? absint( $_GET['montant'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
			self::paiement_recu( $id, $montant );
		} elseif ( 'introuvable' === $quoi ) {
			update_post_meta( $id, 'hs_paiement', 'attente' );
			update_post_meta( $id, 'hs_expire_le', gmdate( 'Y-m-d H:i:s', time() + 6 * HOUR_IN_SECONDS ) );
			if ( $email ) {
				self::mail( $email, 'Paiement non retrouvé : ' . $bien, sprintf( "Bonjour %s,\n\nNous ne retrouvons pas de paiement correspondant à l'ID de transaction %s. Vérifiez le SMS de confirmation de votre opérateur et saisissez à nouveau l'ID ici :\n%s\n\nVos dates restent gardées 6 heures.\n\nL'équipe HomyStay Capital", HS_Donnees::m( $id, 'voyageur' ), HS_Donnees::m( $id, 'transaction' ), self::url_suivi( $id ) ) );
			}
		} elseif ( 'annuler' === $quoi ) {
			update_post_meta( $id, 'hs_statut', 'annulee' );
			if ( $email ) {
				self::mail( $email, 'Réservation annulée : ' . $bien, sprintf( "Bonjour %s,\n\nVotre réservation pour %s du %s au %s est annulée. Pour toute question, répondez à ce message.\n\nL'équipe HomyStay Capital", HS_Donnees::m( $id, 'voyageur' ), $bien, HS_Donnees::date_fr( HS_Donnees::m( $id, 'arrivee' ) ), HS_Donnees::date_fr( HS_Donnees::m( $id, 'depart' ) ) ) );
			}
		}
		wp_safe_redirect( add_query_arg( array( 'post' => $id, 'action' => 'edit', 'hs_resa_fait' => $quoi ), admin_url( 'post.php' ) ) );
		exit;
	}

	public static function lien_action( $id, $quoi, array $args = array() ) {
		return wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'hs_resa_action', 'resa' => $id, 'quoi' => $quoi ), $args ), admin_url( 'admin-post.php' ) ), 'hs_resa_' . $quoi . '_' . $id );
	}

	/* ---------- Administration ---------- */

	public static function boite_suivi() {
		add_meta_box( 'hs_resa_suivi', 'Suivi de la réservation', array( __CLASS__, 'boite' ), 'hs_reservation', 'side', 'high' );
	}

	public static function boite( $post ) {
		$id     = $post->ID;
		$statut = HS_Donnees::m( $id, 'statut' );
		$paie   = HS_Donnees::m( $id, 'paiement' );
		$moyens = array( 'orange' => 'Orange Money', 'mtn' => 'MTN MoMo' );
		$tel    = HS_Donnees::m( $id, 'telephone' );
		echo '<div class="hs-boite-resa">';
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>Enregistrez la réservation : les actions apparaîtront ici.</p></div>';
			return;
		}
		$etats = array(
			'demande'   => 'verification' === $paie ? 'Paiement déclaré : à vérifier' : 'En attente du paiement',
			'confirmee' => 'Confirmée',
			'terminee'  => 'Terminée',
			'annulee'   => 'Annulée',
			'expiree'   => 'Expirée (pas de paiement dans le délai)',
		);
		printf( '<p><strong>%s</strong></p>', esc_html( isset( $etats[ $statut ] ) ? $etats[ $statut ] : $statut ) );
		if ( 'demande' === $statut && 'attente' === $paie && get_post_meta( $id, 'hs_expire_le', true ) ) {
			printf( '<p>Dates gardées jusqu\'au %s.</p>', esc_html( self::date_limite( $id, 'j F à H:i' ) ) );
		}
		if ( HS_Donnees::m( $id, 'transaction' ) ) {
			printf(
				'<p style="background:#fbf2de;padding:8px 10px;border-radius:4px">%s<br>Numéro : <strong>%s</strong><br>ID de transaction : <strong style="font-family:monospace">%s</strong><br>Montant attendu : <strong>%s</strong></p>',
				esc_html( isset( $moyens[ HS_Donnees::m( $id, 'operateur' ) ] ) ? $moyens[ HS_Donnees::m( $id, 'operateur' ) ] : 'Paiement' ),
				esc_html( HS_Donnees::m( $id, 'numero_paiement' ) ), esc_html( HS_Donnees::m( $id, 'transaction' ) ), esc_html( HS_Donnees::fcfa( HS_Donnees::m( $id, 'a_payer' ) ) )
			);
		}
		if ( 'demande' === $statut ) {
			$a = (int) HS_Donnees::m( $id, 'a_payer' );
			printf( '<p><a class="button button-primary" href="%s">Paiement reçu, confirmer</a></p>', esc_url( self::lien_action( $id, 'confirmer' ) ) );
			echo '<p class="description">Vérifiez d\'abord la transaction dans votre compte Mobile Money. Confirmer enregistre ' . esc_html( HS_Donnees::fcfa( $a ) ) . ' en revenu, crée le compte du voyageur et lui envoie la confirmation.</p>';
			if ( 'verification' === $paie ) {
				printf( '<p><a class="button" href="%s">Paiement introuvable</a></p>', esc_url( self::lien_action( $id, 'introuvable' ) ) );
			}
		}
		if ( in_array( $statut, array( 'demande', 'confirmee' ), true ) ) {
			printf( '<p><a class="button button-link-delete" href="%s" onclick="return confirm(\'Annuler cette réservation et prévenir le voyageur ?\')">Annuler la réservation</a></p>', esc_url( self::lien_action( $id, 'annuler' ) ) );
		}
		echo '<hr>';
		if ( $tel ) {
			$msg = 'confirmee' === $statut
				? sprintf( 'Bonjour %s, votre réservation %s du %s au %s est confirmée. Votre espace : %s', HS_Donnees::m( $id, 'voyageur' ), get_the_title( (int) HS_Donnees::m( $id, 'bien_id' ) ), HS_Donnees::date_fr( HS_Donnees::m( $id, 'arrivee' ) ), HS_Donnees::date_fr( HS_Donnees::m( $id, 'depart' ) ), HS_Espace::url() )
				: sprintf( 'Bonjour %s, merci pour votre demande %s du %s au %s. Pour la confirmer, payez %s ici : %s', HS_Donnees::m( $id, 'voyageur' ), get_the_title( (int) HS_Donnees::m( $id, 'bien_id' ) ), HS_Donnees::date_fr( HS_Donnees::m( $id, 'arrivee' ) ), HS_Donnees::date_fr( HS_Donnees::m( $id, 'depart' ) ), HS_Donnees::fcfa( HS_Donnees::m( $id, 'a_payer' ) ), self::url_suivi( $id ) );
			printf( '<p><a class="button" href="%s" target="_blank" rel="noopener">Écrire au voyageur sur WhatsApp</a></p>', esc_url( HS_Donnees::whatsapp( $tel, $msg ) ) );
		}
		if ( get_post_meta( $id, 'hs_cle', true ) ) {
			printf( '<p><a href="%s" target="_blank" rel="noopener">Page de paiement du voyageur</a></p>', esc_url( self::url_suivi( $id ) ) );
		}
		$uid = (int) HS_Donnees::m( $id, 'user_id' );
		if ( $uid && get_userdata( $uid ) ) {
			printf( '<p>Compte voyageur : <a href="%s">%s</a></p>', esc_url( get_edit_user_link( $uid ) ), esc_html( get_userdata( $uid )->user_login ) );
		}
		echo '</div>';
	}

	public static function notice() {
		$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $s || 'hs_reservation' !== $s->post_type || empty( $_GET['hs_resa_fait'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$m = array(
			'confirmer'   => 'Réservation confirmée : paiement enregistré en revenu, confirmation envoyée au voyageur.',
			'introuvable' => 'Le voyageur est invité à saisir à nouveau son ID de transaction. Ses dates restent gardées 6 heures.',
			'annuler'     => 'Réservation annulée : les dates sont libérées.',
		);
		$k = sanitize_key( wp_unslash( $_GET['hs_resa_fait'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $m[ $k ] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $m[ $k ] ) );
		}
	}

	public static function colonnes( $cols ) {
		$date = isset( $cols['date'] ) ? $cols['date'] : '';
		unset( $cols['date'] );
		$cols['hs_bien']     = 'Bien';
		$cols['hs_sejour']   = 'Séjour';
		$cols['hs_montant']  = 'Montant';
		$cols['hs_paiement'] = 'Paiement';
		$cols['hs_statut']   = 'Statut';
		if ( $date ) {
			$cols['date'] = 'Reçue le';
		}
		return $cols;
	}

	public static function colonne( $col, $id ) {
		$defs = HS_Fields::for_type( 'hs_reservation' );
		switch ( $col ) {
			case 'hs_bien':
				echo esc_html( get_the_title( (int) HS_Donnees::m( $id, 'bien_id' ) ) );
				break;
			case 'hs_sejour':
				echo esc_html( HS_Donnees::date_fr( HS_Donnees::m( $id, 'arrivee' ), 'j M' ) . ' → ' . HS_Donnees::date_fr( HS_Donnees::m( $id, 'depart' ), 'j M Y' ) );
				break;
			case 'hs_montant':
				echo esc_html( HS_Donnees::fcfa( HS_Donnees::m( $id, 'montant' ) ) );
				break;
			case 'hs_paiement':
				$p = HS_Donnees::m( $id, 'paiement' );
				$c = array( 'verification' => '#b26200', 'paye' => '#1a7f4b', 'acompte' => '#1a7f4b', 'attente' => '#646970' );
				printf( '<strong style="color:%s">%s</strong>', esc_attr( isset( $c[ $p ] ) ? $c[ $p ] : '#646970' ), esc_html( HS_Shortcodes::format( $p, $defs['paiement'] ) ) );
				break;
			case 'hs_statut':
				echo esc_html( HS_Shortcodes::format( HS_Donnees::m( $id, 'statut' ), $defs['statut'] ) );
				break;
		}
	}

	/* ---------- Emails ---------- */

	public static function mail( $to, $sujet, $texte ) {
		$from = HS_Settings::get( 'email' );
		$h    = $from ? array( 'Reply-To: HomyStay Capital <' . $from . '>' ) : array();
		return wp_mail( $to, $sujet, $texte, $h );
	}

	public static function mail_equipe( $sujet, $texte ) {
		$to = HS_Settings::get( 'email' );
		return $to ? wp_mail( $to, $sujet, $texte ) : false;
	}
}

add_action( 'homystay_paiement_recu', array( 'HS_Reservation', 'paiement_recu' ), 10, 3 );
