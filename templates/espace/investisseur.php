<?php
/**
 * Espace investisseur : montant investi, dividendes, rendement, projets.
 * Surchargeable : <thème>/homystay/espace/investisseur.php
 *
 * @var array $ctx ['fiche' => WP_Post investisseur]
 */
defined( 'ABSPATH' ) || exit;

$inv     = $ctx['fiche'];
$lignes  = HS_Donnees::investissements( $inv->ID );
$divs    = HS_Donnees::dividendes( $inv->ID );
$investi = array_sum( wp_list_pluck( $lignes, 'montant' ) );
$verses  = array_filter( $divs, function ( $d ) { return ! empty( $d->verse ); } );
$derniers = array_slice( $verses, -4 );
$recu_12 = array_sum( wp_list_pluck( $derniers, 'montant' ) );
$taux    = $investi ? round( 100 * $recu_12 / $investi, 1 ) : null;
$a_venir = array_filter( $divs, function ( $d ) { return empty( $d->verse ); } );
$prochain = $a_venir ? reset( $a_venir ) : null;
$max     = $derniers ? max( wp_list_pluck( $derniers, 'montant' ) ) : 0;
$statuts = HS_Fields::for_type( 'hs_projet' )['statut']['options'];
$trim    = function ( $p ) {
	list( $a, $t ) = array_pad( explode( '-T', $p ), 2, '' );
	return 'T' . $t . ' ' . $a;
};
$trim_long = function ( $p ) {
	list( $a, $t ) = array_pad( explode( '-T', $p ), 2, '' );
	return ( '1' === $t ? '1er' : $t . 'e' ) . ' trimestre ' . $a;
};
?>
<section class="es-bande es-bande--4" aria-label="Résumé">
	<div><p class="es-label">Montant investi</p><p class="es-valeur"><?php echo esc_html( HS_Donnees::nombre( $investi ) ); ?></p><p class="es-note"><?php echo esc_html( sprintf( _n( 'FCFA, dans %d projet', 'FCFA, dans %d projets', count( $lignes ), 'homystay-core' ), count( $lignes ) ) ); ?></p></div>
	<div><p class="es-label">Dividendes des 12 derniers mois</p><p class="es-valeur"><?php echo esc_html( HS_Donnees::nombre( $recu_12 ) ); ?></p><p class="es-note">FCFA</p></div>
	<div><p class="es-label">Rendement sur 12 mois</p><p class="es-valeur"><?php echo null === $taux ? '—' : esc_html( number_format_i18n( $taux, 1 ) ) . ' %'; ?></p><p class="es-note">Dividendes sur montant investi</p></div>
	<div class="es-bande__fort">
		<p class="es-label">Prochain dividende</p>
		<?php if ( $prochain ) : ?>
			<p class="es-valeur"><?php echo esc_html( HS_Donnees::nombre( $prochain->montant ) ); ?> <span>FCFA</span></p><p class="es-note"><?php echo esc_html( $trim_long( $prochain->periode ) ); ?>, montant estimé</p>
		<?php else : ?>
			<p class="es-valeur">—</p><p class="es-note">Calculé à la fin du trimestre</p>
		<?php endif; ?>
	</div>
</section>

<div class="es-grille es-grille--2">
	<section class="es-carte"><div class="es-carte__corps">
		<h2 class="es-h2">Dividendes reçus</h2>
		<p class="es-doux">Par trimestre, en FCFA</p>
		<?php if ( $derniers ) : ?>
			<div class="es-histo" role="img" aria-label="Dividendes des 4 derniers trimestres">
				<?php foreach ( $derniers as $i => $d ) : ?>
					<div class="es-histo__col<?php echo end( $derniers ) === $d ? ' est-dernier' : ''; ?>">
						<strong><?php echo esc_html( HS_Donnees::nombre( $d->montant ) ); ?></strong>
						<span style="height:<?php echo (int) round( 90 * $d->montant / max( 1, $max ) ); ?>%"></span>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="es-histo__noms"><?php foreach ( $derniers as $d ) : ?><span><?php echo esc_html( $trim( $d->periode ) ); ?></span><?php endforeach; ?></div>
			<a class="es-lien es-section" href="<?php echo esc_url( HS_Espace::lien_document( 'dividendes', $inv->ID ) ); ?>" target="_blank" rel="noopener">Relevé complet des dividendes</a>
		<?php else : ?>
			<p class="es-vide">Votre premier dividende apparaîtra ici à la fin du trimestre.</p>
		<?php endif; ?>
	</div></section>

	<section>
		<h2 class="es-h2">Mes projets</h2>
		<?php foreach ( $lignes as $l ) : ?>
			<?php if ( ! $l->projet ) { continue; } ?>
			<?php $st = HS_Donnees::m( $l->projet->ID, 'statut' ); ?>
			<article class="es-carte es-projet">
				<div class="es-carte__corps">
					<div class="es-ligne"><h3 class="es-h3"><?php echo esc_html( $l->projet->post_title ); ?></h3><span class="es-puce"><?php echo esc_html( HS_Donnees::m( $l->projet->ID, 'avancement' ) ? HS_Donnees::m( $l->projet->ID, 'avancement' ) : ( isset( $statuts[ $st ] ) ? $statuts[ $st ] : '' ) ); ?></span></div>
					<dl class="es-chiffres">
						<div><dt>Ma part</dt><dd><?php echo esc_html( HS_Donnees::fcfa( $l->montant ) ); ?></dd></div>
						<div><dt>Rendement estimé</dt><dd><?php echo HS_Donnees::m( $l->projet->ID, 'rendement_estime' ) ? esc_html( number_format_i18n( (float) HS_Donnees::m( $l->projet->ID, 'rendement_estime' ), 1 ) . ' % par an' ) : '—'; ?></dd></div>
						<div><dt>Durée</dt><dd><?php echo HS_Donnees::m( $l->projet->ID, 'duree_mois' ) ? (int) HS_Donnees::m( $l->projet->ID, 'duree_mois' ) . ' mois' : '—'; ?></dd></div>
					</dl>
					<p class="es-doux es-petit">Souscrit le <?php echo esc_html( HS_Donnees::date_fr( $l->date_souscription, 'j F Y' ) ); ?></p>
				</div>
			</article>
		<?php endforeach; ?>
		<?php if ( ! $lignes ) : ?><p class="es-vide">Aucune souscription enregistrée.</p><?php endif; ?>
		<a class="es-btn" href="<?php echo esc_url( HS_CPT::url_liste( 'hs_projet' ) ); ?>">Voir les projets ouverts</a>
	</section>
</div>
