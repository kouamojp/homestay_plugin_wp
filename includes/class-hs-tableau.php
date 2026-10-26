<?php
/**
 * Tableau de bord du back-office (HomyStay Capital › Tableau de bord).
 * Trois vues selon le rôle : Gestion (administrateur, gestionnaire), Finances (comptable), Commercial (commercial).
 * L'administrateur voit les trois, en onglets.
 */

defined( 'ABSPATH' ) || exit;

class HS_Tableau {

	const VUES = array(
		'gestion'    => 'Gestion',
		'finances'   => 'Finances',
		'commercial' => 'Commercial',
	);

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_hs_valider_reversements', array( __CLASS__, 'valider_reversements' ) );
		add_action( 'admin_post_hs_export_ecritures', array( __CLASS__, 'export' ) );
	}

	/** Vues accessibles à l'utilisateur courant, dans l'ordre. */
	public static function vues() {
		if ( current_user_can( 'manage_options' ) ) {
			return array_keys( self::VUES );
		}
		$roles = (array) wp_get_current_user()->roles;
		$vues  = array();
		if ( in_array( 'hs_gestionnaire', $roles, true ) ) {
			$vues[] = 'gestion';
		}
		if ( current_user_can( 'hs_manage_finances' ) ) {
			$vues[] = 'finances';
		}
		if ( in_array( 'hs_commercial', $roles, true ) ) {
			$vues[] = 'commercial';
		}
		return $vues ? $vues : array( 'gestion' );
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_' . HS_CPT::MENU !== $hook ) {
			return;
		}
		// Le back-office garde toujours ses polices (le réglage « polices du site » ne concerne que le site public).
		if ( apply_filters( 'homystay_polices_admin', true ) ) {
			wp_enqueue_style( 'homystay-polices', 'https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Young+Serif&display=swap', array(), null );
		}
		wp_enqueue_style( 'homystay-tableau', HS_URL . 'assets/css/tableau.css', array(), HS_VERSION );
	}

	public static function page() {
		$vues = self::vues();
		$vue  = isset( $_GET['vue'] ) ? sanitize_key( wp_unslash( $_GET['vue'] ) ) : $vues[0]; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $vue, $vues, true ) ) {
			$vue = $vues[0];
		}
		$mois = HS_Donnees::mois_courant();
		echo '<div class="wrap hs-tb">';
		echo HS_Templates::render( 'admin/entete.php', array( 'vues' => $vues, 'vue' => $vue ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo HS_Templates::render( 'admin/' . $vue . '.php', array( 'mois' => $mois ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
	}

	public static function url( $vue ) {
		return add_query_arg( array( 'page' => HS_CPT::MENU, 'vue' => $vue ), admin_url( 'admin.php' ) );
	}

	/* ---------- Actions ---------- */

	public static function valider_reversements() {
		if ( ! current_user_can( 'hs_manage_finances' ) ) {
			wp_die( 'Action réservée à la comptabilité.' );
		}
		check_admin_referer( 'hs_valider_reversements' );
		global $wpdb;
		$ym = isset( $_POST['mois'] ) && preg_match( '/^\d{4}-\d{2}$/', wp_unslash( $_POST['mois'] ) ) ? wp_unslash( $_POST['mois'] ) : HS_Donnees::mois_courant(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$n  = 0;
		foreach ( HS_Donnees::reversements( $ym )['lignes'] as $l ) {
			if ( $l['verse'] || $l['net'] <= 0 ) {
				continue;
			}
			$wpdb->insert( $wpdb->prefix . 'hs_ecritures', array(
				'proprietaire_id'    => $l['proprietaire']->ID,
				'sens'               => 'reversement',
				'categorie'          => 'reversement',
				'libelle'            => 'Reversement ' . HS_Donnees::nom_mois( $ym ) . ' : ' . $l['proprietaire']->post_title,
				'montant'            => $l['net'],
				'date_operation'     => HS_Donnees::aujourdhui(),
				'moyen_paiement'     => (string) HS_Donnees::m( $l['proprietaire']->ID, 'mode_reversement' ),
				'reference_paiement' => 'reversement-' . $ym,
				'created_by'         => get_current_user_id(),
			) );
			$n++;
		}
		wp_safe_redirect( add_query_arg( array( 'hs_ok' => 'reversements-' . $n ), self::url( 'finances' ) ) );
		exit;
	}

	/** Export CSV des écritures d'un mois (séparateur « ; » pour Excel en français). */
	public static function export() {
		if ( ! current_user_can( 'hs_manage_finances' ) ) {
			wp_die( 'Action réservée à la comptabilité.' );
		}
		check_admin_referer( 'hs_export_ecritures' );
		global $wpdb;
		$ym = isset( $_GET['mois'] ) && preg_match( '/^\d{4}-\d{2}$/', wp_unslash( $_GET['mois'] ) ) ? wp_unslash( $_GET['mois'] ) : HS_Donnees::mois_courant(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		list( $d, $f ) = HS_Donnees::bornes( $ym );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}hs_ecritures WHERE date_operation BETWEEN %s AND %s ORDER BY date_operation ASC, id ASC", $d, $f ) ); // phpcs:ignore WordPress.DB
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="homystay-ecritures-' . $ym . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- BOM UTF-8 pour Excel
		fputcsv( $out, array( 'Date', 'Sens', 'Catégorie', 'Libellé', 'Bien', 'Référence bien', 'Propriétaire', 'Montant (FCFA)', 'Moyen de paiement', 'Référence paiement' ), ';' );
		foreach ( $rows as $r ) {
			fputcsv( $out, array(
				$r->date_operation, $r->sens, $r->categorie, $r->libelle,
				$r->bien_id ? get_the_title( (int) $r->bien_id ) : '',
				$r->bien_id ? HS_Donnees::m( (int) $r->bien_id, 'reference' ) : '',
				$r->proprietaire_id ? get_the_title( (int) $r->proprietaire_id ) : '',
				$r->montant, $r->moyen_paiement, $r->reference_paiement,
			), ';' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
