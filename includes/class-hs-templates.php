<?php
/**
 * Gabarits surchargeables : le plugin cherche d'abord dans
 *   wp-content/themes/<thème actif>/homystay/<fichier>
 * puis utilise templates/<fichier> du plugin.
 */

defined( 'ABSPATH' ) || exit;

class HS_Templates {

	public static function locate( $file ) {
		$theme = locate_template( 'homystay/' . $file );
		return $theme ? $theme : HS_PATH . 'templates/' . $file;
	}

	/** Rend un gabarit et retourne le HTML. Les clés de $vars deviennent des variables. */
	public static function render( $file, array $vars = array() ) {
		$path = self::locate( $file );
		if ( ! file_exists( $path ) ) {
			return '';
		}
		ob_start();
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
		include $path;
		return ob_get_clean();
	}

	public static function prix( $montant, $suffixe = '' ) {
		if ( '' === $montant || null === $montant || 0 === (int) $montant ) {
			return '';
		}
		return number_format_i18n( (float) $montant, 0 ) . ' FCFA' . ( $suffixe ? ' ' . $suffixe : '' );
	}
}
