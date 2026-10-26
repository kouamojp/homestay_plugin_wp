<?php
/**
 * Activation : rôles, tables financières (hors postmeta), réécriture des URL.
 */

defined( 'ABSPATH' ) || exit;

class HS_Install {

	public static function activate() {
		HS_Roles::install();
		self::tables();
		HS_CPT::register();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
		wp_clear_scheduled_hook( 'hs_expirer_demandes' );
		// Données et rôles conservés volontairement.
	}

	public static function maybe_upgrade() {
		if ( get_option( 'hs_db_version' ) !== HS_DB_VERSION ) {
			self::tables();
			HS_Roles::install();
		}
	}

	/** Crée la page « Mon espace » (shortcode [hs_espace_membre]) si aucune page n'utilise l'adresse mon-espace. */
	public static function page_espace() {
		if ( get_page_by_path( 'mon-espace', OBJECT, 'page' ) ) {
			return;
		}
		wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Mon espace',
			'post_name'    => 'mon-espace',
			'post_content' => '[hs_espace_membre]',
		) );
	}

	/** Tables pour les données chiffrées et volumineuses. Montants en FCFA (entiers). */
	public static function tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();

		// Revenus et dépenses, toujours rattachés à un bien.
		dbDelta( "CREATE TABLE {$wpdb->prefix}hs_ecritures (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			bien_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			contrat_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			projet_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			proprietaire_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			sens VARCHAR(20) NOT NULL DEFAULT 'revenu',
			categorie VARCHAR(40) NOT NULL DEFAULT '',
			libelle VARCHAR(255) NOT NULL DEFAULT '',
			montant BIGINT NOT NULL DEFAULT 0,
			devise CHAR(3) NOT NULL DEFAULT 'XAF',
			date_operation DATE NOT NULL,
			moyen_paiement VARCHAR(30) NOT NULL DEFAULT '',
			reference_paiement VARCHAR(100) NOT NULL DEFAULT '',
			statut VARCHAR(20) NOT NULL DEFAULT 'valide',
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY bien_date (bien_id, date_operation),
			KEY sens_categorie (sens, categorie),
			KEY proprietaire (proprietaire_id)
		) $c;" );

		// Loyers appelés / payés.
		dbDelta( "CREATE TABLE {$wpdb->prefix}hs_echeances (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			contrat_id BIGINT UNSIGNED NOT NULL,
			bien_id BIGINT UNSIGNED NOT NULL,
			periode CHAR(7) NOT NULL,
			montant_du BIGINT NOT NULL DEFAULT 0,
			montant_paye BIGINT NOT NULL DEFAULT 0,
			date_echeance DATE NOT NULL,
			date_paiement DATE NULL,
			statut VARCHAR(20) NOT NULL DEFAULT 'appele',
			niveau_relance TINYINT UNSIGNED NOT NULL DEFAULT 0,
			ecriture_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY contrat_periode (contrat_id, periode),
			KEY statut_echeance (statut, date_echeance)
		) $c;" );

		// Souscriptions et distributions aux investisseurs.
		dbDelta( "CREATE TABLE {$wpdb->prefix}hs_investissements (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			projet_id BIGINT UNSIGNED NOT NULL,
			investisseur_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			montant BIGINT NOT NULL DEFAULT 0,
			date_souscription DATE NOT NULL,
			statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',
			PRIMARY KEY  (id),
			KEY projet (projet_id),
			KEY user_id (user_id),
			KEY investisseur (investisseur_id)
		) $c;" );

		dbDelta( "CREATE TABLE {$wpdb->prefix}hs_distributions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			investissement_id BIGINT UNSIGNED NOT NULL,
			periode VARCHAR(10) NOT NULL,
			montant BIGINT NOT NULL DEFAULT 0,
			date_versement DATE NULL,
			statut VARCHAR(20) NOT NULL DEFAULT 'calcule',
			PRIMARY KEY  (id),
			KEY investissement (investissement_id)
		) $c;" );

		// v3 : la colonne « sens » passe à 20 caractères ; on répare les « reversement » tronqués.
		$wpdb->query( "UPDATE {$wpdb->prefix}hs_ecritures SET sens = 'reversement' WHERE sens = 'reversemen'" ); // phpcs:ignore WordPress.DB
		update_option( 'hs_db_version', HS_DB_VERSION );
		if ( class_exists( 'HS_Demo_Tableaux' ) ) {
			add_action( 'init', array( 'HS_Demo_Tableaux', 'reparer_reversements' ), 40 );
		}
		// La page se crée une fois WordPress initialisé (types de contenu enregistrés).
		if ( did_action( 'init' ) ) {
			self::page_espace();
		} else {
			add_action( 'init', array( __CLASS__, 'page_espace' ), 30 );
		}
	}
}
