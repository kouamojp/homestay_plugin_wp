<?php
/**
 * Carte d'un bien (grilles, biens similaires). Surchargeable : <thème>/homystay/carte-bien.php
 *
 * Image avec prix en surimpression et badge « En vedette » / « Réservé »,
 * puis titre, localisation, type / formule et bouton favori.
 *
 * @var int    $bien_id
 * @var string $formule  Formule mise en avant (courte|longue|vente) ou vide
 */
defined( 'ABSPATH' ) || exit;

$f        = function ( $k ) use ( $bien_id ) {
	return HS_Fields::get( $bien_id, $k );
};
$lien     = get_permalink( $bien_id );
$archive  = HS_CPT::url_liste( 'hs_bien' );
$defs     = HS_Fields::for_type( 'hs_bien' );
$unites   = array( 'courte' => '/nuit', 'longue' => '/mois', 'vente' => '' );
$noms     = array( 'courte' => 'Séjour', 'longue' => 'Location', 'vente' => 'Vente' );

// Prix affiché : la formule demandée, sinon la première proposée.
$prix  = '';
$unite = '';
foreach ( $formule ? array( $formule ) : array( 'courte', 'longue', 'vente' ) as $fm ) {
	if ( $f( 'offre_' . $fm ) && $f( 'prix_' . $fm ) ) {
		$prix  = number_format_i18n( (float) $f( 'prix_' . $fm ) ) . ' FCFA';
		$unite = $unites[ $fm ];
		break;
	}
}

// Formules proposées (« Séjour · Location »).
$offres = array();
foreach ( $noms as $fm => $nom ) {
	if ( $f( 'offre_' . $fm ) ) {
		$offres[] = $nom;
	}
}
$type    = HS_Shortcodes::format( $f( 'type_bien' ), $defs['type_bien'] );
$reserve = 'reserve' === $f( 'statut' );
$vedette = (bool) $f( 'populaire' );
?>
<article class="hs-carte hs-annonce<?php echo $reserve ? ' hs-carte--reserve' : ''; ?>" data-hs-bien="<?php echo (int) $bien_id; ?>">
	<div class="hs-annonce__media">
		<a href="<?php echo esc_url( $lien ); ?>" tabindex="-1" aria-hidden="true">
			<?php
			if ( has_post_thumbnail( $bien_id ) ) {
				echo get_the_post_thumbnail( $bien_id, 'medium_large', array( 'loading' => 'lazy', 'alt' => '' ) );
			} else {
				echo '<span class="hs-carte__placeholder"></span>';
			}
			?>
		</a>
		<?php if ( $reserve ) : ?>
			<span class="hs-annonce__badge hs-annonce__badge--reserve">Réservé</span>
		<?php elseif ( $vedette ) : ?>
			<span class="hs-annonce__badge">En vedette</span>
		<?php endif; ?>
		<?php if ( $prix ) : ?>
			<p class="hs-annonce__prix"><?php echo esc_html( $prix ); ?><?php if ( $unite ) : ?><span><?php echo esc_html( $unite ); ?></span><?php endif; ?></p>
		<?php endif; ?>
	</div>

	<div class="hs-annonce__corps">
		<h3 class="hs-annonce__titre"><a href="<?php echo esc_url( $lien ); ?>"><?php echo esc_html( get_the_title( $bien_id ) ); ?></a></h3>

		<?php if ( $f( 'ville' ) || $f( 'quartier' ) ) : ?>
			<p class="hs-annonce__ligne">
				<svg aria-hidden="true" viewBox="0 0 24 24" width="14" height="14"><path fill="none" stroke="currentColor" stroke-width="1.6" d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
				<span>
					<?php if ( $f( 'quartier' ) ) : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'hs_ville' => $f( 'ville' ), 'hs_quartier' => $f( 'quartier' ) ), $archive ) ); ?>"><?php echo esc_html( $f( 'quartier' ) ); ?></a><?php echo $f( 'ville' ) ? ', ' : ''; ?>
					<?php endif; ?>
					<?php if ( $f( 'ville' ) ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'hs_ville', $f( 'ville' ), $archive ) ); ?>"><?php echo esc_html( $f( 'ville' ) ); ?></a>
					<?php endif; ?>
				</span>
			</p>
		<?php endif; ?>

		<?php if ( $type || $offres ) : ?>
			<p class="hs-annonce__ligne">
				<svg aria-hidden="true" viewBox="0 0 24 24" width="14" height="14"><path fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" d="M4 10.5 12 4l8 6.5V20h-5.5v-5.5h-5V20H4z"/></svg>
				<span><?php echo esc_html( implode( ' / ', array_filter( array( $type, implode( ' · ', $offres ) ) ) ) ); ?></span>
			</p>
		<?php endif; ?>

		<button type="button" class="hs-annonce__favori" data-hs-favori="<?php echo (int) $bien_id; ?>" aria-pressed="false" aria-label="Ajouter aux favoris">
			<svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M12 20.3 10.6 19C5.4 14.4 2 11.3 2 7.6 2 4.6 4.4 2.3 7.4 2.3c1.7 0 3.3.8 4.6 2.1 1.3-1.3 2.9-2.1 4.6-2.1 3 0 5.4 2.3 5.4 5.3 0 3.7-3.4 6.8-8.6 11.4z"/></svg>
		</button>
	</div>
</article>
