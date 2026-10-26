<?php
/**
 * Données de test : crée ou supprime 10 biens de démonstration (avec photos) depuis
 * HomyStay Capital › Réglages › « Données de test ».
 * Les biens et images créés sont marqués (hs_demo / _hs_demo) : la suppression ne touche qu'eux.
 */

defined( 'ABSPATH' ) || exit;

class HS_Demo {

	public static function init() {
		add_action( 'admin_post_hs_demo_creer', array( __CLASS__, 'creer' ) );
		add_action( 'admin_post_hs_demo_supprimer', array( __CLASS__, 'supprimer' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	public static function ids_biens() {
		return get_posts( array(
			'post_type' => 'hs_bien', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids',
			'meta_key' => 'hs_demo', 'meta_value' => 1, // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
	}

	/** Bloc affiché sous le formulaire des Réglages. */
	public static function section() {
		$nb  = count( self::ids_biens() );
		$tab = (bool) get_option( 'hs_demo_tableaux' );
		?>
		<hr>
		<h2>Données de test</h2>
		<p>Crée 10 biens de démonstration à Douala, Yaoundé, Kribi et Limbé (photos, prix, équipements, GPS) et tout ce qui alimente les tableaux de bord : propriétaires et mandats, locataires et loyers, réservations, encaissements et dépenses, reversements, tickets, assurance, contacts CRM, investisseur et projets. Les dates sont calculées à partir d'aujourd'hui. Vos propres données ne sont jamais modifiées. Aucun compte utilisateur n'est créé : les espaces clients se consultent avec l'aperçu équipe de la page « Mon espace ».</p>
		<p><strong><?php echo esc_html( sprintf( _n( '%d bien de test présent.', '%d biens de test présents.', $nb, 'homystay-core' ), $nb ) ); ?></strong><?php echo $nb && ! $tab ? ' Les données des tableaux de bord ne sont pas encore créées.' : ''; ?></p>
		<div style="display:flex;gap:8px;flex-wrap:wrap">
			<?php if ( ! $nb || ! $tab ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="hs_demo_creer">
					<?php wp_nonce_field( 'hs_demo_creer' ); ?>
					<?php submit_button( $nb ? 'Ajouter les données des tableaux de bord' : 'Créer les données de test', 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
			<?php if ( $nb ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="hs_demo_supprimer">
					<?php wp_nonce_field( 'hs_demo_supprimer' ); ?>
					<?php submit_button( 'Supprimer toutes les données de test', 'delete', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function verifier( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Action réservée aux administrateurs.' );
		}
		check_admin_referer( $action );
	}

	private static function retour( $etat ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'homystay-reglages', 'hs_demo' => $etat ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Importe une image du plugin dans la médiathèque (une seule fois par fichier). */
	private static function image( $numero, $titre ) {
		$fichier = sprintf( 'demo-%02d.jpg', $numero );
		$deja    = get_posts( array(
			'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids',
			'meta_key' => '_hs_demo', 'meta_value' => $fichier, // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		if ( $deja ) {
			return (int) $deja[0];
		}
		$source = HS_PATH . 'assets/demo/' . $fichier;
		if ( ! file_exists( $source ) ) {
			return 0;
		}
		$upload = wp_upload_bits( 'homystay-' . $fichier, null, file_get_contents( $source ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}
		$id = wp_insert_attachment( array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'Photo de test ' . $numero,
			'post_status'    => 'inherit',
		), $upload['file'] );
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
		update_post_meta( $id, '_hs_demo', $fichier );
		update_post_meta( $id, '_wp_attachment_image_alt', 'Illustration de test' );
		return (int) $id;
	}

	public static function creer() {
		self::verifier( 'hs_demo_creer' );
		@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$existants = self::ids_biens();
		if ( $existants ) {
			if ( get_option( 'hs_demo_tableaux' ) ) {
				self::retour( 'existe' );
			}
			// Biens déjà là : on ajoute seulement les données des tableaux de bord.
			$par_titre = array();
			foreach ( $existants as $id ) {
				$par_titre[ get_the_title( $id ) ] = $id;
			}
			HS_Demo_Tableaux::creer( $par_titre );
			self::retour( 'tableaux' );
		}
		$defs  = HS_Fields::for_type( 'hs_bien' );
		$biens = require HS_PATH . 'includes/demo-data.php';
		$crees = 0;
		$par_titre = array();

		foreach ( $biens as $b ) {
			$id = wp_insert_post( array(
				'post_type'    => 'hs_bien',
				'post_status'  => 'publish',
				'post_title'   => $b['titre'],
				'post_excerpt' => $b['extrait'],
				'post_content' => wpautop( $b['contenu'] ),
			), true );
			if ( is_wp_error( $id ) ) {
				continue;
			}
			foreach ( $b['champs'] as $cle => $valeur ) {
				if ( isset( $defs[ $cle ] ) ) {
					update_post_meta( $id, 'hs_' . $cle, HS_Fields::sanitize( $valeur, $defs[ $cle ] ) );
				}
			}
			// Formules non proposées : valeurs explicites à 0, pour que les filtres et tris soient cohérents.
			foreach ( array( 'offre_courte', 'offre_longue', 'offre_vente', 'populaire' ) as $cle ) {
				if ( ! isset( $b['champs'][ $cle ] ) ) {
					update_post_meta( $id, 'hs_' . $cle, 0 );
				}
			}

			$photos = array();
			foreach ( $b['photos'] as $n ) {
				$att = self::image( $n, $b['titre'] );
				if ( $att ) {
					$photos[] = $att;
				}
			}
			if ( $photos ) {
				set_post_thumbnail( $id, $photos[0] );
				update_post_meta( $id, 'hs_galerie', implode( ',', array_slice( $photos, 1 ) ) );
			}

			// Référence calculée maintenant que la ville est connue (HS-DOU-0042…).
			delete_post_meta( $id, 'hs_reference' );
			HS_CPT::auto_reference( $id, get_post( $id ) );
			update_post_meta( $id, 'hs_demo', 1 );
			$par_titre[ $b['titre'] ] = $id;
			$crees++;
		}
		HS_Demo_Tableaux::creer( $par_titre );
		self::retour( 'cree-' . $crees );
	}

	public static function supprimer() {
		self::verifier( 'hs_demo_supprimer' );
		$n = count( self::ids_biens() );
		$types = array( 'hs_bien', 'hs_proprietaire', 'hs_locataire', 'hs_contrat', 'hs_ticket', 'hs_police', 'hs_reservation', 'hs_contact', 'hs_investisseur', 'hs_projet' );
		$ids   = get_posts( array( 'post_type' => $types, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => 'hs_demo', 'meta_value' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
		HS_Demo_Tableaux::supprimer_lignes();
		$images = get_posts( array(
			'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1, 'fields' => 'ids',
			'meta_key' => '_hs_demo', 'meta_compare' => 'EXISTS', // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		foreach ( $images as $att ) {
			wp_delete_attachment( $att, true );
		}
		self::retour( 'supprime-' . $n );
	}

	public static function notice() {
		if ( empty( $_GET['hs_demo'] ) || empty( $_GET['page'] ) || 'homystay-reglages' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$etat = sanitize_key( wp_unslash( $_GET['hs_demo'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( 0 === strpos( $etat, 'cree-' ) ) {
			$msg = sprintf( '%d biens de test créés, avec les données des tableaux de bord. Ouvrez HomyStay Capital › Tableau de bord, ou « Mon espace » en aperçu.', (int) substr( $etat, 5 ) );
		} elseif ( 'tableaux' === $etat ) {
			$msg = 'Données des tableaux de bord ajoutées : propriétaires, locataires, loyers, réservations, finances, tickets, CRM et investisseur. Ouvrez HomyStay Capital › Tableau de bord.';
		} elseif ( 0 === strpos( $etat, 'supprime-' ) ) {
			$msg = sprintf( '%d biens de test supprimés, avec leurs photos et toutes les données de test associées.', (int) substr( $etat, 9 ) );
		} else {
			$msg = 'Les biens de test existent déjà : supprimez-les d\'abord pour les recréer.';
		}
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $msg ) );
	}
}
