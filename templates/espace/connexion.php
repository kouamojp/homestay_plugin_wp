<?php
/** Espace client : visiteur non connecté. Surchargeable : <thème>/homystay/espace/connexion.php */
defined( 'ABSPATH' ) || exit;
$wa = HS_Espace::whatsapp_equipe( 'Bonjour HomyStay, je souhaite accéder à mon espace client.' );
?>
<div class="es es-connexion">
	<h1 class="es-h1">Mon espace</h1>
	<p class="es-intro">Propriétaire, locataire, voyageur ou investisseur : connectez-vous pour suivre vos biens, vos séjours, vos paiements et vos documents.</p>
	<div class="es-carte">
		<?php
		wp_login_form( array(
			'redirect'       => HS_Espace::url(),
			'label_username' => 'Email ou identifiant',
			'label_password' => 'Mot de passe',
			'label_remember' => 'Rester connecté',
			'label_log_in'   => 'Me connecter',
		) );
		?>
		<p class="es-doux"><a href="<?php echo esc_url( wp_lostpassword_url( HS_Espace::url() ) ); ?>">Mot de passe oublié ?</a></p>
	</div>
	<?php if ( $wa ) : ?>
		<p class="es-doux">Pas encore de compte ? <a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo esc_html( HS_Settings::get( 'whatsapp' ) ? 'Écrivez-nous sur WhatsApp' : 'Écrivez-nous' ); ?></a> : nous créons votre accès.</p>
	<?php endif; ?>
</div>
