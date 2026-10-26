<?php
/**
 * Rôles HomyStay. Les rôles internes gèrent les fiches (capacités hs_item) ;
 * les rôles clients n'ont que « read » et n'accèdent pas à /wp-admin.
 */

defined( 'ABSPATH' ) || exit;

class HS_Roles {

	/** Capacités générées par capability_type hs_item / hs_items. */
	public static function item_caps() {
		return array(
			'edit_hs_items', 'edit_others_hs_items', 'edit_published_hs_items', 'edit_private_hs_items',
			'publish_hs_items', 'read_private_hs_items',
			'delete_hs_items', 'delete_others_hs_items', 'delete_published_hs_items', 'delete_private_hs_items',
		);
	}

	public static function roles() {
		$full = array_fill_keys( self::item_caps(), true );
		$edit = array_fill_keys( array( 'edit_hs_items', 'edit_others_hs_items', 'edit_published_hs_items', 'read_private_hs_items' ), true );

		return array(
			'hs_gestionnaire' => array( 'Gestionnaire immobilier', array( 'read' => true, 'upload_files' => true, 'hs_manage_finances' => false ) + $full ),
			'hs_comptable'    => array( 'Comptable', array( 'read' => true, 'hs_manage_finances' => true ) + $edit ),
			'hs_commercial'   => array( 'Commercial', array( 'read' => true, 'upload_files' => true ) + $edit ),
			'hs_technicien'   => array( 'Technicien', array( 'read' => true ) ),
			'hs_voyageur'     => array( 'Voyageur', array( 'read' => true ) ),
			'hs_proprietaire' => array( 'Propriétaire', array( 'read' => true ) ),
			'hs_locataire'    => array( 'Locataire', array( 'read' => true ) ),
			'hs_investisseur' => array( 'Investisseur', array( 'read' => true ) ),
		);
	}

	public static function install() {
		foreach ( self::roles() as $slug => $r ) {
			remove_role( $slug );
			add_role( $slug, $r[0], $r[1] );
		}
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( self::item_caps() as $cap ) {
				$admin->add_cap( $cap );
			}
			$admin->add_cap( 'hs_manage_finances' );
		}
	}

	/** Rôles clients : redirigés hors de /wp-admin, sans barre d'admin. */
	public static function client_roles() {
		return array( 'hs_voyageur', 'hs_proprietaire', 'hs_locataire', 'hs_investisseur', 'hs_technicien' );
	}
}

add_action( 'admin_init', function () {
	// admin-post.php sert aux formulaires de « Mon espace » (signaler une panne…) : ne pas rediriger.
	if ( wp_doing_ajax() || ! is_user_logged_in() || ( isset( $GLOBALS['pagenow'] ) && 'admin-post.php' === $GLOBALS['pagenow'] ) ) {
		return;
	}
	$user = wp_get_current_user();
	if ( array_intersect( HS_Roles::client_roles(), (array) $user->roles ) && ! current_user_can( 'edit_hs_items' ) ) {
		wp_safe_redirect( home_url( apply_filters( 'homystay_espace_url', '/mon-espace/' ) ) );
		exit;
	}
} );

add_filter( 'show_admin_bar', function ( $show ) {
	$user = wp_get_current_user();
	return array_intersect( HS_Roles::client_roles(), (array) $user->roles ) ? false : $show;
} );
