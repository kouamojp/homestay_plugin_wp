<?php
/**
 * Réglages HomyStay : coordonnées utilisées par les boutons de contact (page d'un bien, etc.).
 */

defined( 'ABSPATH' ) || exit;

class HS_Settings {

	const OPTION = 'hs_reglages';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function defaults() {
		return array(
			'whatsapp'      => '',
			'telephone'     => '',
			'email'         => get_option( 'admin_email' ),
			'similaires'    => 3,
			'polices_site'  => 0,
			'message_whatsapp' => 'Bonjour HomyStay, je suis intéressé(e) par le bien « {titre} » (réf. {reference}) : {lien}',
		);
	}

	public static function get( $key ) {
		$opts = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return isset( $opts[ $key ] ) ? $opts[ $key ] : '';
	}

	public static function menu() {
		add_submenu_page( HS_CPT::MENU, 'Réglages HomyStay', 'Réglages', 'manage_options', 'homystay-reglages', array( __CLASS__, 'page' ) );
	}

	public static function register() {
		register_setting( 'hs_reglages', self::OPTION, array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			'default'           => self::defaults(),
		) );
	}

	public static function sanitize( $in ) {
		$in = (array) $in;
		return array(
			'whatsapp'         => preg_replace( '/\D/', '', isset( $in['whatsapp'] ) ? $in['whatsapp'] : '' ),
			'telephone'        => sanitize_text_field( isset( $in['telephone'] ) ? $in['telephone'] : '' ),
			'email'            => sanitize_email( isset( $in['email'] ) ? $in['email'] : '' ),
			'similaires'       => max( 0, min( 12, absint( isset( $in['similaires'] ) ? $in['similaires'] : 3 ) ) ),
			'polices_site'     => empty( $in['polices_site'] ) ? 0 : 1,
			'message_whatsapp' => sanitize_textarea_field( isset( $in['message_whatsapp'] ) ? $in['message_whatsapp'] : '' ),
		);
	}

	public static function page() {
		$o = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		$n = self::OPTION;
		?>
		<div class="wrap">
			<h1>Réglages HomyStay</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'hs_reglages' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="hs-wa">Numéro WhatsApp</label></th>
						<td><input id="hs-wa" class="regular-text" name="<?php echo esc_attr( $n ); ?>[whatsapp]" value="<?php echo esc_attr( $o['whatsapp'] ); ?>" placeholder="237690000000">
						<p class="description">Format international sans « + » ni espaces (ex. 237690000000).</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="hs-tel">Téléphone</label></th>
						<td><input id="hs-tel" class="regular-text" name="<?php echo esc_attr( $n ); ?>[telephone]" value="<?php echo esc_attr( $o['telephone'] ); ?>" placeholder="+237 6 90 00 00 00"></td>
					</tr>
					<tr>
						<th scope="row"><label for="hs-mail">Email de contact</label></th>
						<td><input id="hs-mail" type="email" class="regular-text" name="<?php echo esc_attr( $n ); ?>[email]" value="<?php echo esc_attr( $o['email'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="hs-msg">Message WhatsApp prérempli</label></th>
						<td><textarea id="hs-msg" class="large-text" rows="3" name="<?php echo esc_attr( $n ); ?>[message_whatsapp]"><?php echo esc_textarea( $o['message_whatsapp'] ); ?></textarea>
						<p class="description">Variables : {titre}, {reference}, {lien}.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="hs-sim">Biens similaires</label></th>
						<td><input id="hs-sim" type="number" min="0" max="12" name="<?php echo esc_attr( $n ); ?>[similaires]" value="<?php echo (int) $o['similaires']; ?>">
						<p class="description">Nombre de biens de la même ville affichés en bas de la page d'un bien (0 = aucun).</p></td>
					</tr>
					<tr>
						<th scope="row">Polices de la page d'un bien</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[polices_site]" value="1" <?php checked( (int) $o['polices_site'], 1 ); ?>> Utiliser les polices du site</label>
						<p class="description">Coché : la page d'un bien reprend les polices globales d'Elementor (Principale pour les titres, Texte pour le reste), sinon celle du thème. Décoché : Young Serif et Figtree.</p></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<?php HS_Demo::section(); ?>
		</div>
		<?php
	}
}
