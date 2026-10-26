<?php
/**
 * En-tête de l'espace : titre, onglets entre espaces (si plusieurs), message de retour d'action.
 *
 * @var array $ctx
 */
defined( 'ABSPATH' ) || exit;
$ok   = isset( $_GET['hs_ok'] ) ? sanitize_key( wp_unslash( $_GET['hs_ok'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$msgs = array(
	'panne-signalee'        => array( 'ok', 'Votre demande est enregistrée. Nous vous recontactons pour fixer l\'intervention.' ),
	'erreur-signalement'    => array( 'erreur', 'Décrivez le problème pour envoyer votre demande.' ),
	'intervention-demarree' => array( 'ok', 'Intervention démarrée.' ),
	'intervention-terminee' => array( 'ok', 'Intervention terminée : le gestionnaire est prévenu.' ),
);
?>
<div class="es-entete">
	<div>
		<h1 class="es-h1"><?php echo esc_html( HS_Espace::ESPACES[ $ctx['espace'] ] ); ?></h1>
		<?php if ( ! empty( $ctx['nom'] ) ) : ?><p class="es-doux"><?php echo esc_html( $ctx['nom'] ); ?></p><?php endif; ?>
	</div>
	<?php if ( ! $ctx['apercu'] ) : ?><a class="es-lien" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Me déconnecter</a><?php endif; ?>
</div>
<?php if ( count( $ctx['espaces'] ) > 1 ) : ?>
	<nav class="es-onglets" aria-label="Mes espaces">
		<?php foreach ( $ctx['espaces'] as $k => $e ) : ?>
			<a href="<?php echo esc_url( HS_Espace::url( array( 'espace' => $k ) ) ); ?>"<?php echo $k === $ctx['espace'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( HS_Espace::ESPACES[ $k ] ); ?></a>
		<?php endforeach; ?>
	</nav>
<?php else : ?>
	<hr class="es-filet">
<?php endif; ?>
<?php if ( isset( $msgs[ $ok ] ) ) : ?>
	<p class="es-message es-message--<?php echo esc_attr( $msgs[ $ok ][0] ); ?>" role="status"><?php echo esc_html( $msgs[ $ok ][1] ); ?></p>
<?php endif; ?>
