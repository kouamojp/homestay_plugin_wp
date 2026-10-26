<?php
/**
 * Barre d'aperçu, visible uniquement par l'équipe : choisir l'espace et la personne à prévisualiser.
 *
 * @var array $ctx
 */
defined( 'ABSPATH' ) || exit;
$espace = $ctx['apercu'] ? $ctx['espace'] : 'proprietaire';
$choix  = $ctx['apercu'] ? $ctx['choix'] : HS_Espace::choix_apercu( 'proprietaire' );
?>
<form class="es-apercu" method="get" action="<?php echo esc_url( HS_Espace::url() ); ?>">
	<strong>Aperçu équipe</strong>
	<label>Espace
		<select name="apercu" onchange="this.form.fiche.value='';this.form.submit()">
			<?php foreach ( HS_Espace::ESPACES as $k => $label ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $espace, $k ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<label>Personne
		<select name="fiche" onchange="this.form.submit()">
			<?php foreach ( $choix as $k => $label ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $ctx['apercu'] ? $ctx['cle'] : '', (string) $k ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
			<?php if ( ! $choix ) : ?><option value="">Aucune fiche</option><?php endif; ?>
		</select>
	</label>
	<noscript><button type="submit">Afficher</button></noscript>
	<?php if ( $ctx['apercu'] ) : ?><span class="es-apercu__note">Lecture seule : les boutons d'action sont désactivés.</span><?php endif; ?>
</form>
