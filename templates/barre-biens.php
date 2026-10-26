<?php
/**
 * Barre de filtres et de tri au-dessus de la liste de biens. Surchargeable : <thème>/homystay/barre-biens.php
 * Les listes se soumettent seules au changement (assets/js/liste.js) ; le bouton sert sans JavaScript.
 *
 * @var array $formules, $types, $tris  [valeur => libellé]
 * @var array $villes, $quartiers       [valeurs]
 * @var array $valeurs                  Filtres actifs (ville, quartier, type, formule, tri)
 * @var int   $total                    Nombre de biens trouvés
 * @var array $caches                   Autres filtres de l'URL à conserver
 */
defined( 'ABSPATH' ) || exit;

$select = function ( $name, $tous, $options, $actuel, $assoc = true ) {
	echo '<div class="hs-barre__select"><select name="' . esc_attr( $name ) . '" aria-label="' . esc_attr( $tous ) . '" data-hs-auto>';
	if ( $tous ) {
		echo '<option value="">' . esc_html( $tous ) . '</option>';
	}
	foreach ( $options as $k => $label ) {
		$val = $assoc ? $k : $label;
		printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $actuel, $val, false ), esc_html( $label ) );
	}
	echo '</select></div>';
};
?>
<form class="hs-barre" method="get" action="">
	<div class="hs-barre__filtres">
		<?php
		$select( 'hs_formule', 'Toutes formules', $formules, $valeurs['formule'] );
		$select( 'hs_type', 'Tous types', $types, $valeurs['type'] );
		$select( 'hs_ville', 'Toutes villes', $villes, $valeurs['ville'], false );
		$select( 'hs_quartier', 'Tous quartiers', $quartiers, $valeurs['quartier'], false );
		?>
	</div>
	<div class="hs-barre__tri">
		<span class="hs-barre__total"><?php echo esc_html( sprintf( _n( '%s bien', '%s biens', $total, 'homystay-core' ), number_format_i18n( $total ) ) ); ?></span>
		<?php $select( 'hs_tri', '', $tris, $valeurs['tri'] ); ?>
	</div>
	<?php foreach ( $caches as $k => $v ) : ?>
		<input type="hidden" name="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $v ); ?>">
	<?php endforeach; ?>
	<noscript><button type="submit" class="hs-bouton">Filtrer</button></noscript>
</form>
