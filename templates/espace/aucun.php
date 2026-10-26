<?php
/**
 * Compte connecté sans espace relié (ou aperçu sans fiche).
 *
 * @var array $ctx
 */
defined( 'ABSPATH' ) || exit;
$wa = HS_Espace::whatsapp_equipe( 'Bonjour HomyStay, mon compte (' . wp_get_current_user()->user_email . ') n\'est relié à aucun bien ni séjour.' );
?>
<div class="es es-connexion">
	<h1 class="es-h1">Mon espace</h1>
	<?php if ( ! empty( $ctx['apercu'] ) ) : ?>
		<p class="es-intro">Aucune fiche de ce type pour l'instant. Créez-en une dans HomyStay Capital pour prévisualiser cet espace.</p>
	<?php elseif ( current_user_can( 'edit_hs_items' ) ) : ?>
		<p class="es-intro">Vous êtes connecté avec un compte de l'équipe. Choisissez un espace et une personne dans la barre d'aperçu ci-dessus pour voir ce que voit le client.</p>
	<?php else : ?>
		<p class="es-intro">Votre compte n'est encore relié à aucun bien, contrat, séjour ou investissement. Nous le relions dès que votre dossier est enregistré, avec l'adresse <strong><?php echo esc_html( wp_get_current_user()->user_email ); ?></strong>.</p>
		<?php if ( $wa ) : ?><a class="es-btn es-btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo esc_html( HS_Espace::libelle_contact() ); ?></a><?php endif; ?>
		<p class="es-doux"><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Me déconnecter</a></p>
	<?php endif; ?>
</div>
