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
			'resa_en_ligne' => 1,
			'orange_numero' => '',
			'orange_nom'    => '',
			'mtn_numero'    => '',
			'mtn_nom'       => '',
			'acompte'       => 50,
			'delai_paiement' => 24,
			'heure_arrivee' => '14 h',
			'heure_depart'  => '11 h',
			'consignes'     => '',
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
			'resa_en_ligne'    => empty( $in['resa_en_ligne'] ) ? 0 : 1,
			'orange_numero'    => sanitize_text_field( isset( $in['orange_numero'] ) ? $in['orange_numero'] : '' ),
			'orange_nom'       => sanitize_text_field( isset( $in['orange_nom'] ) ? $in['orange_nom'] : '' ),
			'mtn_numero'       => sanitize_text_field( isset( $in['mtn_numero'] ) ? $in['mtn_numero'] : '' ),
			'mtn_nom'          => sanitize_text_field( isset( $in['mtn_nom'] ) ? $in['mtn_nom'] : '' ),
			'acompte'          => max( 0, min( 100, absint( isset( $in['acompte'] ) ? $in['acompte'] : 50 ) ) ),
			'delai_paiement'   => max( 1, min( 168, absint( isset( $in['delai_paiement'] ) ? $in['delai_paiement'] : 24 ) ) ),
			'heure_arrivee'    => sanitize_text_field( isset( $in['heure_arrivee'] ) ? $in['heure_arrivee'] : '' ),
			'heure_depart'     => sanitize_text_field( isset( $in['heure_depart'] ) ? $in['heure_depart'] : '' ),
			'consignes'        => sanitize_textarea_field( isset( $in['consignes'] ) ? $in['consignes'] : '' ),
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

				<h2>Réservation en ligne</h2>
				<p>Les biens proposés à la nuit (« Location courte durée » coché, prix par nuit renseigné, statut Disponible) affichent un calendrier et un bouton « Réserver ». Le voyageur paie l'acompte par Orange Money ou MTN MoMo, puis saisit l'ID de transaction ; vous vérifiez le paiement et confirmez la réservation.</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Réservation sur le site</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[resa_en_ligne]" value="1" <?php checked( (int) $o['resa_en_ligne'], 1 ); ?>> Activer le calendrier et le bouton « Réserver »</label></td>
					</tr>
					<tr>
						<th scope="row"><label for="hs-om">Orange Money</label></th>
						<td><input id="hs-om" class="regular-text" name="<?php echo esc_attr( $n ); ?>[orange_numero]" value="<?php echo esc_attr( $o['orange_numero'] ); ?>" placeholder="690 00 00 00">
						<input class="regular-text" name="<?php echo esc_attr( $n ); ?>[orange_nom]" value="<?php echo esc_attr( $o['orange_nom'] ); ?>" placeholder="Nom affiché au voyageur (titulaire du compte)" aria-label="Titulaire du compte Orange Money">
						<p class="description">Numéro marchand ou numéro qui reçoit les paiements. Laissez vide pour ne pas proposer Orange Money.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="hs-mtn">MTN MoMo</label></th>
						<td><input id="hs-mtn" class="regular-text" name="<?php echo esc_attr( $n ); ?>[mtn_numero]" value="<?php echo esc_attr( $o['mtn_numero'] ); ?>" placeholder="670 00 00 00">
						<input class="regular-text" name="<?php echo esc_attr( $n ); ?>[mtn_nom]" value="<?php echo esc_attr( $o['mtn_nom'] ); ?>" placeholder="Nom affiché au voyageur (titulaire du compte)" aria-label="Titulaire du compte MTN MoMo"></td>
					</tr>
					<tr>
						<th scope="row"><label for="hs-acompte">Acompte pour confirmer</label></th>
						<td><input id="hs-acompte" type="number" min="0" max="100" name="<?php echo esc_attr( $n ); ?>[acompte]" value="<?php echo (int) $o['acompte']; ?>"> % du total
						<p class="description">100 = paiement complet à la réservation. Le solde se règle à l'arrivée.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="hs-delai">Délai pour payer</label></th>
						<td><input id="hs-delai" type="number" min="1" max="168" name="<?php echo esc_attr( $n ); ?>[delai_paiement]" value="<?php echo (int) $o['delai_paiement']; ?>"> heures
						<p class="description">Les dates sont bloquées pendant ce délai. Sans paiement déclaré, la demande expire et les dates se libèrent.</p></td>
					</tr>
					<tr>
						<th scope="row"><label for="hs-arr">Heures d'arrivée et de départ</label></th>
						<td>Arrivée à partir de <input id="hs-arr" class="small-text" name="<?php echo esc_attr( $n ); ?>[heure_arrivee]" value="<?php echo esc_attr( $o['heure_arrivee'] ); ?>">,
						départ avant <input class="small-text" name="<?php echo esc_attr( $n ); ?>[heure_depart]" value="<?php echo esc_attr( $o['heure_depart'] ); ?>" aria-label="Heure de départ"></td>
					</tr>
					<tr>
						<th scope="row"><label for="hs-consignes">Consignes d'arrivée par défaut</label></th>
						<td><textarea id="hs-consignes" class="large-text" rows="3" name="<?php echo esc_attr( $n ); ?>[consignes]"><?php echo esc_textarea( $o['consignes'] ); ?></textarea>
						<p class="description">Copiées dans chaque réservation confirmée si elle n'a pas ses propres consignes. Ex. « Votre hôte vous attend à l'entrée de la résidence. Appelez-le 30 minutes avant d'arriver. »</p></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<?php HS_Demo::section(); ?>
		</div>
		<?php
	}
}
