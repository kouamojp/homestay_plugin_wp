<?php
/**
 * Page d'un bien (/biens/<slug>/).
 *
 * Ordre de priorité :
 *  1. Un modèle Elementor Pro (Theme Builder › Single) ciblant les Biens → c'est lui qui s'affiche.
 *  2. <thème>/homystay/single-bien.php  → gabarit complet du thème.
 *  3. templates/single-bien.php du plugin.
 * Désactiver totalement la page du plugin : add_filter( 'homystay_single_template', '__return_false' );
 */

defined( 'ABSPATH' ) || exit;

class HS_Single {

	public static function init() {
		// Priorité 9 : Elementor Pro passe ensuite (priorité 11+) et garde la main s'il a un modèle.
		add_filter( 'template_include', array( __CLASS__, 'template' ), 9 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'archive_query' ) );
	}

	/**
	 * Archive /biens/ (et lien « ville » du fil d'Ariane) : seulement les biens visibles,
	 * avec les mêmes filtres d'URL que [hs_recherche] (hs_ville, hs_type, hs_formule…).
	 */
	public static function archive_query( $q ) {
		if ( is_admin() || ! $q->is_main_query() || ! $q->is_post_type_archive( 'hs_bien' ) ) {
			return;
		}
		$get  = function ( $k ) {
			return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		};
		$args = HS_Shortcodes::query_args( array(
			'ville' => $get( 'hs_ville' ), 'type' => $get( 'hs_type' ), 'formule' => $get( 'hs_formule' ),
			'prix_max' => $get( 'hs_prix_max' ), 'chambres' => $get( 'hs_chambres' ), 'limite' => get_option( 'posts_per_page' ),
		) );
		$q->set( 'meta_query', $args['meta_query'] );
	}

	public static function active() {
		return is_singular( 'hs_bien' ) && apply_filters( 'homystay_single_template', true )
			&& ! self::elementor_template() && ! self::elementor_edition();
	}

	/**
	 * Vrai pendant l'édition ou l'aperçu dans l'éditeur Elementor.
	 * Le plugin s'efface alors et laisse le gabarit du thème : Elementor y trouve the_content() et peut éditer.
	 */
	public static function elementor_edition() {
		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$el = \Elementor\Plugin::$instance;
		return ( isset( $el->preview ) && $el->preview->is_preview_mode() )
			|| ( isset( $el->editor ) && $el->editor->is_edit_mode() );
	}

	/** Un modèle « single » Elementor Pro s'applique-t-il à ce bien ? */
	private static function elementor_template() {
		if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			return false;
		}
		try {
			$docs = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->get_documents_for_location( 'single' );
			return ! empty( $docs );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	public static function template( $template ) {
		if ( ! self::active() ) {
			return $template;
		}
		// La fiche courte (shortcode) n'est pas ajoutée sous la description : la page complète l'affiche déjà.
		add_filter( 'homystay_auto_fiche', '__return_false' );
		return HS_Templates::locate( 'single-bien.php' );
	}

	public static function assets() {
		if ( ! self::active() ) {
			return;
		}
		if ( apply_filters( 'homystay_load_css', true ) ) {
			wp_enqueue_style( 'homystay' );
			$deps = array( 'homystay' );
			// Polices de la page (Young Serif + Figtree). Pour garder celles du site :
			// HomyStay Capital › Réglages › « Utiliser les polices du site », ou en code :
			// add_filter( 'homystay_polices', '__return_false' );
			if ( apply_filters( 'homystay_polices', ! HS_Settings::get( 'polices_site' ) ) ) {
				wp_enqueue_style( 'homystay-polices', 'https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&family=Young+Serif&display=swap', array(), null );
				$deps[] = 'homystay-polices';
			}
			wp_enqueue_style( 'homystay-bien', HS_URL . 'assets/css/bien.css', $deps, HS_VERSION );
			if ( ! in_array( 'homystay-polices', $deps, true ) ) {
				// Polices désactivées : reprendre celles du site au lieu des polices de secours.
				// Polices globales Elementor (Site Settings › Typographie globale) : « Principale » pour les titres,
				// « Texte » pour le reste ; sinon la police du thème (inherit).
				wp_add_inline_style( 'homystay-bien', '.hs-bien{--hs-font-titre:var(--e-global-typography-primary-font-family,inherit);--hs-font-texte:var(--e-global-typography-text-font-family,inherit)}' );
			}
		}
		wp_enqueue_script( 'homystay-bien', HS_URL . 'assets/js/bien.js', array(), HS_VERSION, true );
		wp_enqueue_script( 'homystay-liste', HS_URL . 'assets/js/liste.js', array(), HS_VERSION, true ); // favoris des biens similaires
	}

	/** Données préparées pour le gabarit. */
	public static function data( $bien_id ) {
		$defs = HS_Fields::for_type( 'hs_bien' );
		$f    = function ( $k ) use ( $bien_id ) {
			return HS_Fields::get( $bien_id, $k );
		};

		$offres = array();
		$map    = array(
			'courte' => array( 'Séjour', '/ nuit' ),
			'longue' => array( 'Location', '/ mois' ),
			'vente'  => array( 'Achat', '' ),
		);
		foreach ( $map as $k => $m ) {
			if ( $f( 'offre_' . $k ) && $f( 'prix_' . $k ) ) {
				$offres[ $k ] = array( 'label' => $m[0], 'prix' => HS_Templates::prix( $f( 'prix_' . $k ) ), 'unite' => $m[1] );
			}
		}

		$chiffres = array_filter( array(
			'Chambres'       => $f( 'chambres' ) ? (int) $f( 'chambres' ) : '',
			'Salles de bain' => $f( 'salles_bain' ) ? (int) $f( 'salles_bain' ) : '',
			'Surface'        => $f( 'surface' ) ? (int) $f( 'surface' ) . ' m²' : '',
			'Capacité'       => $f( 'capacite' ) ? (int) $f( 'capacite' ) . ' pers.' : '',
		) );

		$reference = $f( 'reference' );
		$lien      = get_permalink( $bien_id );
		$message   = strtr( HS_Settings::get( 'message_whatsapp' ), array(
			'{titre}'     => get_the_title( $bien_id ),
			'{reference}' => $reference,
			'{lien}'      => $lien,
		) );
		$wa = HS_Settings::get( 'whatsapp' );

		return array(
			'bien_id'     => $bien_id,
			'statut'      => $f( 'statut' ),
			'statut_label' => HS_Shortcodes::format( $f( 'statut' ), $defs['statut'] ),
			'type'        => HS_Shortcodes::format( $f( 'type_bien' ), $defs['type_bien'] ),
			'reference'   => $reference,
			'lieu'        => implode( ', ', array_filter( array( $f( 'quartier' ), $f( 'ville' ) ) ) ),
			'ville'       => $f( 'ville' ),
			'photos'      => HS_Fields::photos( $bien_id ),
			'offres'      => $offres,
			'chiffres'    => $chiffres,
			'equipements' => HS_Fields::equipements( $bien_id ),
			'video'       => $f( 'video_url' ),
			'visite'      => $f( 'visite_url' ),
			'lat'         => $f( 'latitude' ),
			'lng'         => $f( 'longitude' ),
			'reservable'  => in_array( $f( 'statut' ), array( 'disponible' ), true ),
			'whatsapp'    => $wa ? 'https://wa.me/' . $wa . '?text=' . rawurlencode( $message ) : '',
			'telephone'   => HS_Settings::get( 'telephone' ),
			'email'       => HS_Settings::get( 'email' ),
			'similaires'  => self::similaires( $bien_id, $f( 'ville' ) ),
		);
	}

	/** Biens disponibles de la même ville, hors bien courant. */
	private static function similaires( $bien_id, $ville ) {
		$n = (int) HS_Settings::get( 'similaires' );
		if ( ! $n || ! $ville ) {
			return null;
		}
		$args                 = HS_Shortcodes::query_args( array( 'ville' => $ville, 'limite' => $n ) );
		$args['post__not_in'] = array( $bien_id );
		$args['paged']        = 1;
		$args['no_found_rows'] = true;
		$q = new WP_Query( $args );
		return $q->have_posts() ? $q : null;
	}

	/* ---------- Coquille de page : thème classique (Hello Elementor…) ou thème bloc (Twenty Twenty-Five…) ---------- */

	public static function header() {
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			// Les parties de modèle sont rendues AVANT wp_head pour que leurs styles soient chargés.
			ob_start();
			block_template_part( 'header' );
			$header = ob_get_clean();
			ob_start();
			block_template_part( 'footer' );
			$GLOBALS['hs_block_footer'] = ob_get_clean();
			?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
<?php
			echo $header; // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			get_header();
		}
	}

	public static function footer() {
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			echo isset( $GLOBALS['hs_block_footer'] ) ? $GLOBALS['hs_block_footer'] : ''; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
			wp_footer();
			echo '</body></html>';
		} else {
			get_footer();
		}
	}
}
