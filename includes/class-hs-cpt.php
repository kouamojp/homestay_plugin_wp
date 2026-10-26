<?php
/**
 * Types de contenu, taxonomie « ville » non utilisée volontairement (champ texte filtrable),
 * menu d'administration « HomyStay Capital » et enregistrement des métadonnées.
 */

defined( 'ABSPATH' ) || exit;

class HS_CPT {

	const MENU = 'homystay';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 ); // avant les sous-menus des CPT : « Tableau de bord » en tête
		// save_post (et non save_post_{type}) : il s'exécute après l'enregistrement des champs de la meta box (priorité 10).
		add_action( 'save_post', function ( $post_id, $post ) {
			if ( 'hs_bien' === $post->post_type ) {
				self::auto_reference( $post_id, $post );
			} elseif ( 'hs_ticket' === $post->post_type ) {
				self::ticket_bloquant( $post_id, $post );
			}
		}, 20, 2 );
	}

	public static function types() {
		return array(
			// slug => [singulier, pluriel, public, slug URL, supports]
			'hs_bien'         => array( 'Bien', 'Biens', true, 'biens', array( 'title', 'editor', 'thumbnail', 'excerpt' ) ),
			'hs_projet'       => array( "Projet d'investissement", 'Projets', true, 'investir', array( 'title', 'editor', 'thumbnail', 'excerpt' ) ),
			'hs_proprietaire' => array( 'Propriétaire', 'Propriétaires', false, '', array( 'title' ) ),
			'hs_locataire'    => array( 'Locataire', 'Locataires', false, '', array( 'title' ) ),
			'hs_contrat'      => array( 'Contrat', 'Contrats', false, '', array( 'title' ) ),
			'hs_ticket'       => array( 'Ticket', 'Maintenance', false, '', array( 'title', 'editor', 'thumbnail' ) ),
			'hs_police'       => array( 'Police', 'Assurance', false, '', array( 'title' ) ),
			'hs_reservation'  => array( 'Réservation', 'Réservations', false, '', array( 'title' ) ),
			'hs_contact'      => array( 'Contact', 'CRM', false, '', array( 'title' ) ),
			'hs_investisseur' => array( 'Investisseur', 'Investisseurs', false, '', array( 'title' ) ),
		);
	}

	/**
	 * Page WordPress publiée dont l'adresse est le slug d'un CPT public (ex. une page « biens » faite avec Elementor).
	 * Si elle existe, elle remplace l'archive automatique : /biens/ affiche la page, /biens/<slug>/ reste la fiche du bien.
	 */
	public static function page_liste( $slug ) {
		static $cache = array();
		if ( ! array_key_exists( $slug, $cache ) ) {
			$page            = $slug ? get_page_by_path( $slug, OBJECT, 'page' ) : null;
			$cache[ $slug ]  = ( $page && 'publish' === $page->post_status ) ? $page : null;
		}
		return apply_filters( 'homystay_page_liste', $cache[ $slug ], $slug );
	}

	/** URL de la liste d'un CPT : la page Elementor si elle existe, sinon l'archive automatique. */
	public static function url_liste( $type = 'hs_bien' ) {
		$types = self::types();
		$page  = isset( $types[ $type ] ) ? self::page_liste( $types[ $type ][3] ) : null;
		if ( $page ) {
			return get_permalink( $page );
		}
		$url = get_post_type_archive_link( $type );
		return $url ? $url : home_url( '/' );
	}

	/** Régénère les règles d'URL quand une page de liste apparaît ou disparaît. */
	public static function maybe_flush() {
		$etat = array();
		foreach ( self::types() as $type => $t ) {
			if ( $t[2] ) {
				$etat[ $type ] = self::page_liste( $t[3] ) ? 'page' : 'archive';
			}
		}
		$signature = md5( wp_json_encode( $etat ) );
		if ( get_option( 'hs_rewrite_signature' ) !== $signature ) {
			flush_rewrite_rules( false );
			update_option( 'hs_rewrite_signature', $signature );
		}
	}

	public static function register() {
		foreach ( self::types() as $type => $t ) {
			list( $sing, $plur, $public, $slug, $supports ) = $t;
			$archive = ( $public && ! self::page_liste( $slug ) ) ? $slug : false;
			register_post_type( $type, array(
				'labels'          => array(
					'name'          => $plur,
					'singular_name' => $sing,
					'add_new_item'  => 'Ajouter : ' . $sing,
					'edit_item'     => 'Modifier : ' . $sing,
					'all_items'     => $plur,
				),
				'public'          => $public,
				'show_ui'         => true,
				'show_in_menu'    => self::MENU,
				'show_in_rest'    => $public,       // API pour l'app mobile (phase 2) ; les fiches privées n'y sont pas exposées.
				'has_archive'     => $archive,
				'rewrite'         => $public ? array( 'slug' => $slug, 'with_front' => false ) : false,
				'supports'        => array_merge( $supports, array( 'custom-fields' ) ),
				'capability_type' => array( 'hs_item', 'hs_items' ),
				'map_meta_cap'    => true,
				'exclude_from_search' => ! $public,
			) );

			foreach ( HS_Fields::for_type( $type ) as $key => $def ) {
				$schema_type = 'string';
				if ( 'number' === $def['type'] ) {
					$schema_type = isset( $def['step'] ) ? 'number' : 'integer';
				} elseif ( in_array( $def['type'], array( 'checkbox', 'post' ), true ) ) {
					$schema_type = 'integer';
				}
				register_post_meta( $type, 'hs_' . $key, array(
					'type'              => $schema_type,
					'single'            => true,
					'show_in_rest'      => $public, // les fiches privées ne sont pas exposées publiquement.
					'sanitize_callback' => function ( $v ) use ( $def ) { return HS_Fields::sanitize( $v, $def ); },
					'auth_callback'     => function () { return current_user_can( 'edit_hs_items' ); },
					'default'           => isset( $def['default'] ) ? $def['default'] : ( 'string' === $schema_type ? '' : 0 ),
				) );
			}
		}
	}

	public static function menu() {
		add_menu_page( 'HomyStay Capital', 'HomyStay Capital', 'edit_hs_items', self::MENU, array( __CLASS__, 'dashboard' ), 'dashicons-building', 3 );
		add_submenu_page( self::MENU, 'Tableau de bord', 'Tableau de bord', 'edit_hs_items', self::MENU, array( __CLASS__, 'dashboard' ) );
		// Les sous-menus des types de contenu s'ajoutent après : on remet « Tableau de bord » en tête.
		add_action( 'admin_menu', function () {
			global $submenu;
			if ( empty( $submenu[ self::MENU ] ) ) {
				return;
			}
			foreach ( $submenu[ self::MENU ] as $i => $item ) {
				if ( self::MENU === $item[2] ) {
					unset( $submenu[ self::MENU ][ $i ] );
					array_unshift( $submenu[ self::MENU ], $item );
					break;
				}
			}
		}, 999 );
	}

	/** Tableau de bord : voir HS_Tableau (vues Gestion, Finances, Commercial). */
	public static function dashboard() {
		HS_Tableau::page();
	}

	/** Référence auto : HS-<3 lettres de la ville>-<ID sur 4 chiffres>. */
	public static function auto_reference( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		if ( HS_Fields::get( $post_id, 'reference' ) ) {
			return;
		}
		$ville = strtoupper( substr( remove_accents( (string) HS_Fields::get( $post_id, 'ville' ) ), 0, 3 ) );
		$ref   = sprintf( 'HS-%s-%04d', $ville ? $ville : 'XXX', $post_id );
		update_post_meta( $post_id, 'hs_reference', $ref );
	}

	/** Un ticket bloquant non clôturé passe le bien en maintenance. */
	public static function ticket_bloquant( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		$bien = (int) HS_Fields::get( $post_id, 'bien_id' );
		if ( $bien && HS_Fields::get( $post_id, 'bloquant' ) && 'cloture' !== HS_Fields::get( $post_id, 'etape' ) ) {
			update_post_meta( $bien, 'hs_statut', 'maintenance' );
		}
	}
}
