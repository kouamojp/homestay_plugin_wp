<?php
/**
 * Plugin Name:       HomyStay Core
 * Description:       Logique métier HomyStay Capital : biens, propriétaires, locataires, contrats, maintenance, assurance, investissement, finances et shortcodes front.
 * Version:           0.5.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            MINDCODE 237
 * Text Domain:       homystay-core
 */

defined( 'ABSPATH' ) || exit;

define( 'HS_VERSION', '0.5.0' );
define( 'HS_DB_VERSION', '5' );
define( 'HS_FILE', __FILE__ );
define( 'HS_PATH', plugin_dir_path( __FILE__ ) );
define( 'HS_URL', plugin_dir_url( __FILE__ ) );

require_once HS_PATH . 'includes/class-hs-fields.php';
require_once HS_PATH . 'includes/class-hs-cpt.php';
require_once HS_PATH . 'includes/class-hs-metabox.php';
require_once HS_PATH . 'includes/class-hs-roles.php';
require_once HS_PATH . 'includes/class-hs-install.php';
require_once HS_PATH . 'includes/class-hs-templates.php';
require_once HS_PATH . 'includes/class-hs-shortcodes.php';
require_once HS_PATH . 'includes/class-hs-settings.php';
require_once HS_PATH . 'includes/class-hs-single.php';
require_once HS_PATH . 'includes/class-hs-donnees.php';
require_once HS_PATH . 'includes/class-hs-tableau.php';
require_once HS_PATH . 'includes/class-hs-espace.php';
require_once HS_PATH . 'includes/class-hs-demo-tableaux.php';
require_once HS_PATH . 'includes/class-hs-demo.php';

register_activation_hook( __FILE__, array( 'HS_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'HS_Install', 'deactivate' ) );

add_action( 'plugins_loaded', function () {
	HS_CPT::init();
	HS_Metabox::init();
	HS_Install::maybe_upgrade();
	HS_Shortcodes::init();
	HS_Settings::init();
	HS_Single::init();
	HS_Tableau::init();
	HS_Espace::init();
	HS_Demo::init();
} );

/**
 * Styles front : légers, préfixés .hs-, désactivables pour laisser Elementor gérer tout le design.
 * add_filter( 'homystay_load_css', '__return_false' );
 */
add_action( 'wp_enqueue_scripts', function () {
	wp_register_style( 'homystay', HS_URL . 'assets/css/homystay.css', array(), HS_VERSION );
} );
