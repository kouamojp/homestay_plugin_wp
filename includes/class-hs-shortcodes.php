<?php
/**
 * Shortcodes front, pensés pour être posés dans un widget « Shortcode » Elementor.
 *
 *  [hs_recherche action="/sejours/" formule="courte"]          Moteur de recherche (GET)
 *  [hs_biens ville="Douala" formule="courte" type="villa" limite="9" colonnes="3" barre="oui" filtres_url="oui" tri="recent|prix_desc|…"]
 *  [hs_biens_populaires limite="6"]                             Biens marqués « populaire »
 *  [hs_bien_fiche id=""]                                        Fiche complète (id vide = bien courant)
 *  [hs_champ cle="ville" id=""]                                 Un seul champ, pour un template Elementor
 *  [hs_projets statut="ouvert" limite="3"]                      Projets d'investissement
 */

defined( 'ABSPATH' ) || exit;

class HS_Shortcodes {

	const FORMULES = array( 'courte' => 'Séjour courte durée', 'longue' => 'Location longue durée', 'vente' => 'Achat' );

	public static function init() {
		add_shortcode( 'hs_recherche', array( __CLASS__, 'recherche' ) );
		add_shortcode( 'hs_biens', array( __CLASS__, 'biens' ) );
		add_shortcode( 'hs_biens_populaires', array( __CLASS__, 'populaires' ) );
		add_shortcode( 'hs_bien_fiche', array( __CLASS__, 'fiche' ) );
		add_shortcode( 'hs_champ', array( __CLASS__, 'champ' ) );
		add_shortcode( 'hs_projets', array( __CLASS__, 'projets' ) );

		// Sans template Elementor dédié, la page d'un bien affiche sa fiche sous la description.
		// Si vous créez un template « single bien » dans Elementor : add_filter( 'homystay_auto_fiche', '__return_false' );
		add_filter( 'the_content', function ( $content ) {
			if ( is_singular( 'hs_bien' ) && in_the_loop() && is_main_query() && ! HS_Single::elementor_edition()
				&& apply_filters( 'homystay_auto_fiche', true )
				&& false === strpos( $content, 'hs-fiche' ) ) {
				$content .= self::fiche( array() );
			}
			return $content;
		}, 20 );
	}

	private static function css() {
		if ( apply_filters( 'homystay_load_css', true ) ) {
			wp_enqueue_style( 'homystay' );
		}
	}

	/** Paramètre GET nettoyé. */
	private static function get( $key ) {
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function villes() {
		return self::valeurs_meta( 'ville' );
	}

	/**
	 * Valeurs distinctes d'un champ texte parmi les biens publiés (villes, quartiers…).
	 * $ville : limite les valeurs aux biens de cette ville (quartiers d'une ville).
	 */
	public static function valeurs_meta( $champ, $ville = '' ) {
		global $wpdb;
		$sql = "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id";
		$params = array();
		if ( $ville ) {
			$sql     .= " INNER JOIN {$wpdb->postmeta} v ON v.post_id = p.ID AND v.meta_key = 'hs_ville' AND v.meta_value = %s";
			$params[] = $ville;
		}
		$sql     .= " WHERE pm.meta_key = %s AND pm.meta_value <> '' AND p.post_type = 'hs_bien' AND p.post_status = 'publish' ORDER BY pm.meta_value ASC";
		$params[] = 'hs_' . $champ;
		$valeurs  = $wpdb->get_col( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		return $valeurs ? $valeurs : array();
	}

	/* ---------- Recherche ---------- */

	public static function recherche( $atts ) {
		self::css();
		$a = shortcode_atts( array( 'action' => '', 'formule' => '' ), $atts, 'hs_recherche' );
		return HS_Templates::render( 'recherche.php', array(
			'action'   => $a['action'] ? home_url( $a['action'] ) : '',
			'villes'   => self::villes(),
			'types'    => HS_Fields::for_type( 'hs_bien' )['type_bien']['options'],
			'formules' => self::FORMULES,
			'valeurs'  => array(
				'ville'     => self::get( 'hs_ville' ),
				'type'      => self::get( 'hs_type' ),
				'formule'   => self::get( 'hs_formule' ) ? self::get( 'hs_formule' ) : $a['formule'],
				'prix_max'  => self::get( 'hs_prix_max' ),
				'chambres'  => self::get( 'hs_chambres' ),
			),
		) );
	}

	/* ---------- Liste de biens ---------- */

	/** Options de tri proposées dans la barre de [hs_biens]. */
	const TRIS = array(
		'recent'        => 'Plus récents',
		'ancien'        => 'Plus anciens',
		'prix_desc'     => 'Prix décroissant',
		'prix_asc'      => 'Prix croissant',
		'chambres_desc' => 'Plus de chambres',
		'chambres_asc'  => 'Moins de chambres',
		'sdb_desc'      => 'Plus de salles de bain',
		'sdb_asc'       => 'Moins de salles de bain',
	);

	public static function biens( $atts ) {
		self::css();
		$a = shortcode_atts( array(
			'ville' => '', 'quartier' => '', 'formule' => '', 'type' => '', 'limite' => 9, 'colonnes' => 3,
			'filtres_url' => 'oui', 'tri' => 'recent', 'populaire' => '', 'pagination' => 'oui', 'barre' => 'oui',
			'vide' => 'Aucun bien ne correspond à votre recherche.',
		), $atts, 'hs_biens' );

		// Les filtres de l'URL ([hs_recherche] ou barre de filtres) complètent ou remplacent les attributs.
		if ( 'oui' === $a['filtres_url'] ) {
			foreach ( array( 'ville', 'quartier', 'type', 'formule', 'tri' ) as $k ) {
				if ( self::get( 'hs_' . $k ) ) {
					$a[ $k ] = self::get( 'hs_' . $k );
				}
			}
			$a['prix_max'] = self::get( 'hs_prix_max' );
			$a['chambres'] = self::get( 'hs_chambres' );
		}

		$query = new WP_Query( self::query_args( $a ) );

		wp_enqueue_script( 'homystay-liste', HS_URL . 'assets/js/liste.js', array(), HS_VERSION, true ); // filtres + favoris
		$barre = '';
		if ( 'oui' === $a['barre'] ) {
			$barre = HS_Templates::render( 'barre-biens.php', array(
				'formules'  => self::FORMULES,
				'types'     => HS_Fields::for_type( 'hs_bien' )['type_bien']['options'],
				'villes'    => self::villes(),
				'quartiers' => self::valeurs_meta( 'quartier', $a['ville'] ),
				'tris'      => self::TRIS,
				'valeurs'   => $a,
				'total'     => (int) $query->found_posts,
				'caches'    => array_filter( array( 'hs_prix_max' => self::get( 'hs_prix_max' ), 'hs_chambres' => self::get( 'hs_chambres' ) ) ),
			) );
		}

		return $barre . HS_Templates::render( 'liste-biens.php', array(
			'query'      => $query,
			'colonnes'   => max( 1, min( 4, (int) $a['colonnes'] ) ),
			'formule'    => $a['formule'],
			'vide'       => $a['vide'],
			'pagination' => 'oui' === $a['pagination'],
		) );
	}

	public static function query_args( array $a ) {
		$a = array_merge( array(
			'ville' => '', 'quartier' => '', 'type' => '', 'formule' => '', 'prix_max' => '', 'chambres' => '',
			'populaire' => '', 'limite' => 9, 'tri' => 'recent',
		), $a );
		$meta = array( 'relation' => 'AND', array( 'key' => 'hs_statut', 'value' => array( 'disponible', 'reserve' ), 'compare' => 'IN' ) );

		if ( ! empty( $a['ville'] ) ) {
			$meta[] = array( 'key' => 'hs_ville', 'value' => $a['ville'] );
		}
		if ( ! empty( $a['quartier'] ) ) {
			$meta[] = array( 'key' => 'hs_quartier', 'value' => $a['quartier'] );
		}
		if ( ! empty( $a['type'] ) ) {
			$meta[] = array( 'key' => 'hs_type_bien', 'value' => $a['type'] );
		}
		$formule = isset( self::FORMULES[ $a['formule'] ] ) ? $a['formule'] : '';
		if ( $formule ) {
			$meta[] = array( 'key' => 'hs_offre_' . $formule, 'value' => 1, 'type' => 'NUMERIC' );
			if ( ! empty( $a['prix_max'] ) ) {
				$meta[] = array( 'key' => 'hs_prix_' . $formule, 'value' => (int) $a['prix_max'], 'compare' => '<=', 'type' => 'NUMERIC' );
			}
		}
		if ( ! empty( $a['chambres'] ) ) {
			$meta[] = array( 'key' => 'hs_chambres', 'value' => (int) $a['chambres'], 'compare' => '>=', 'type' => 'NUMERIC' );
		}
		if ( ! empty( $a['populaire'] ) ) {
			$meta[] = array( 'key' => 'hs_populaire', 'value' => 1, 'type' => 'NUMERIC' );
		}

		$args = array(
			'post_type'      => 'hs_bien',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $a['limite'] ),
			'paged'          => max( 1, (int) self::get( 'hs_page' ) ),
			'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
		);

		// Tri. Les biens sans valeur pour le champ trié restent dans la liste (clause EXISTS / NOT EXISTS).
		$tri    = isset( self::TRIS[ $a['tri'] ] ) ? $a['tri'] : 'recent';
		$champs = array(
			'prix'     => 'hs_prix_' . ( $formule ? $formule : 'courte' ),
			'chambres' => 'hs_chambres',
			'sdb'      => 'hs_salles_bain',
		);
		if ( 'ancien' === $tri ) {
			$args['orderby'] = array( 'date' => 'ASC' );
		} elseif ( 'recent' !== $tri ) {
			list( $champ, $sens ) = explode( '_', $tri );
			$args['meta_query']['tri_clause'] = array(
				'relation' => 'OR',
				'tri_valeur' => array( 'key' => $champs[ $champ ], 'compare' => 'EXISTS', 'type' => 'NUMERIC' ),
				array( 'key' => $champs[ $champ ], 'compare' => 'NOT EXISTS' ),
			);
			$args['orderby'] = array( 'tri_valeur' => strtoupper( $sens ), 'date' => 'DESC' );
		}
		return apply_filters( 'homystay_biens_query_args', $args, $a );
	}

	public static function populaires( $atts ) {
		$a = shortcode_atts( array( 'limite' => 6, 'colonnes' => 3, 'formule' => '' ), $atts, 'hs_biens_populaires' );
		return self::biens( array(
			'populaire' => 1, 'limite' => $a['limite'], 'colonnes' => $a['colonnes'], 'formule' => $a['formule'],
			'filtres_url' => 'non', 'pagination' => 'non', 'barre' => 'non', 'vide' => '',
		) );
	}

	/* ---------- Fiche d'un bien ---------- */

	public static function fiche( $atts ) {
		self::css();
		$a  = shortcode_atts( array( 'id' => 0 ), $atts, 'hs_bien_fiche' );
		$id = $a['id'] ? (int) $a['id'] : get_the_ID();
		if ( ! $id || 'hs_bien' !== get_post_type( $id ) ) {
			return '';
		}
		return HS_Templates::render( 'fiche-bien.php', array( 'bien_id' => $id ) );
	}

	/** Affiche un champ, formaté. Utile pour composer un template « bien » dans Elementor. */
	public static function champ( $atts ) {
		$a   = shortcode_atts( array( 'cle' => '', 'id' => 0, 'brut' => 'non' ), $atts, 'hs_champ' );
		$id  = $a['id'] ? (int) $a['id'] : get_the_ID();
		$def = HS_Fields::for_type( get_post_type( $id ) );
		if ( ! $id || ! isset( $def[ $a['cle'] ] ) ) {
			return '';
		}
		$value = HS_Fields::get( $id, $a['cle'] );
		if ( 'oui' === $a['brut'] ) {
			return esc_html( $value );
		}
		return esc_html( self::format( $value, $def[ $a['cle'] ], $a['cle'] ) );
	}

	public static function format( $value, $def, $key = '' ) {
		switch ( $def['type'] ) {
			case 'select':
				return isset( $def['options'][ $value ] ) ? $def['options'][ $value ] : '';
			case 'checkbox':
				return $value ? 'Oui' : 'Non';
			case 'post':
				return $value ? get_the_title( (int) $value ) : '';
			case 'date':
				return $value ? date_i18n( get_option( 'date_format' ), strtotime( $value ) ) : '';
			case 'number':
				if ( 0 === strpos( $key, 'prix_' ) || 0 === strpos( $key, 'montant' ) ) {
					return HS_Templates::prix( $value );
				}
				return '' === $value ? '' : number_format_i18n( (float) $value, isset( $def['step'] ) ? 1 : 0 );
			default:
				return (string) $value;
		}
	}

	/* ---------- Projets d'investissement ---------- */

	public static function projets( $atts ) {
		self::css();
		$a = shortcode_atts( array( 'statut' => 'ouvert', 'limite' => 3, 'colonnes' => 3 ), $atts, 'hs_projets' );
		$meta = array();
		if ( $a['statut'] ) {
			$meta[] = array( 'key' => 'hs_statut', 'value' => array_map( 'trim', explode( ',', $a['statut'] ) ), 'compare' => 'IN' );
		}
		$query = new WP_Query( array(
			'post_type' => 'hs_projet', 'post_status' => 'publish', 'posts_per_page' => (int) $a['limite'], 'meta_query' => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		return HS_Templates::render( 'liste-projets.php', array( 'query' => $query, 'colonnes' => max( 1, min( 4, (int) $a['colonnes'] ) ) ) );
	}
}
