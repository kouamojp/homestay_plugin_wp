<?php
/**
 * Formulaire de recherche. Surchargeable : <thème>/homystay/recherche.php
 *
 * @var string $action   URL cible (vide = page courante)
 * @var array  $villes
 * @var array  $types
 * @var array  $formules
 * @var array  $valeurs
 */
defined( 'ABSPATH' ) || exit;
?>
<form class="hs-recherche" method="get" action="<?php echo esc_url( $action ); ?>">
	<label class="hs-recherche__champ">
		<span>Ville</span>
		<select name="hs_ville">
			<option value="">Toutes</option>
			<?php foreach ( $villes as $v ) : ?>
				<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $valeurs['ville'], $v ); ?>><?php echo esc_html( $v ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<label class="hs-recherche__champ">
		<span>Formule</span>
		<select name="hs_formule">
			<option value="">Toutes</option>
			<?php foreach ( $formules as $k => $label ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $valeurs['formule'], $k ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<label class="hs-recherche__champ">
		<span>Type</span>
		<select name="hs_type">
			<option value="">Tous</option>
			<?php foreach ( $types as $k => $label ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $valeurs['type'], $k ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<label class="hs-recherche__champ">
		<span>Budget max (FCFA)</span>
		<input type="number" name="hs_prix_max" min="0" step="1000" value="<?php echo esc_attr( $valeurs['prix_max'] ); ?>">
	</label>
	<label class="hs-recherche__champ">
		<span>Chambres min.</span>
		<input type="number" name="hs_chambres" min="0" value="<?php echo esc_attr( $valeurs['chambres'] ); ?>">
	</label>
	<button type="submit" class="hs-bouton">Rechercher</button>
</form>
