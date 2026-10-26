<?php
/**
 * Espace propriétaire : versements, occupation et bilan de chaque bien, relevés.
 * Surchargeable : <thème>/homystay/espace/proprietaire.php
 *
 * @var array $ctx  ['fiche' => WP_Post propriétaire]
 */
defined( 'ABSPATH' ) || exit;

$p       = $ctx['fiche'];
$mois    = HS_Donnees::mois_courant();
$biens   = HS_Donnees::biens_du_proprietaire( $p->ID );
$releves = HS_Donnees::reversements_du_proprietaire( $p->ID, 12 );
$dernier = $releves ? $releves[0] : null;
$moyens  = HS_Fields::for_type( 'hs_proprietaire' )['mode_reversement']['options'];
$moyen   = HS_Donnees::m( $p->ID, 'mode_reversement' );
$net     = 0;
$nuits   = 0;
$dispo   = 0;
$lignes  = array();
foreach ( $biens as $b ) {
	$bilan = HS_Donnees::bilan_bien( $b->ID, $mois );
	$cal   = HS_Donnees::calendrier( $b->ID, $mois );
	$net  += $bilan['net'];
	$nuits += count( array_filter( $cal ) );
	$dispo += count( $cal );
	$lignes[] = array( 'bien' => $b, 'bilan' => $bilan, 'cal' => $cal );
}
$deja_verse = HS_Donnees::reversement_fait( $p->ID, $mois );
$date_verse = gmdate( 'Y-m-05', strtotime( $mois . '-01 +1 month' ) );
$wa         = HS_Espace::whatsapp_equipe( 'Bonjour HomyStay, je suis ' . $p->post_title . ' (propriétaire). Je souhaite signaler un problème sur un de mes biens : ' );
?>
<section class="es-bande" aria-label="Résumé">
	<div class="es-bande__fort">
		<p class="es-label">Prochain versement, le <?php echo esc_html( HS_Donnees::date_fr( $date_verse ) ); ?></p>
		<p class="es-valeur"><?php echo esc_html( HS_Donnees::nombre( max( 0, $net ) ) ); ?> <span>FCFA</span></p>
		<p class="es-note"><?php echo $deja_verse ? 'Déjà validé par la comptabilité' : esc_html( $moyen && isset( $moyens[ $moyen ] ) ? 'Sur votre compte ' . $moyens[ $moyen ] : 'Montant provisoire du mois en cours' ); ?></p>
	</div>
	<div>
		<p class="es-label">Dernier versement</p>
		<?php if ( $dernier ) : ?>
			<p class="es-valeur"><?php echo esc_html( HS_Donnees::nombre( $dernier->montant ) ); ?> <span>FCFA</span></p>
			<p class="es-note"><?php echo esc_html( ucfirst( HS_Donnees::nom_mois( substr( $dernier->reference_paiement, -7 ) ) ) . ', versé le ' . HS_Donnees::date_fr( $dernier->date_operation ) ); ?></p>
		<?php else : ?>
			<p class="es-valeur">—</p><p class="es-note">Aucun versement pour l'instant</p>
		<?php endif; ?>
	</div>
	<div>
		<p class="es-label">Nuits réservées en <?php echo esc_html( date_i18n( 'F' ) ); ?></p>
		<p class="es-valeur"><?php echo (int) $nuits; ?> <span>sur <?php echo (int) $dispo; ?></span></p>
		<p class="es-note"><?php echo esc_html( sprintf( _n( 'Votre bien', 'Vos %d biens réunis', count( $biens ), 'homystay-core' ), count( $biens ) ) ); ?></p>
	</div>
</section>

<?php if ( ! $biens ) : ?>
	<p class="es-vide">Aucun bien n'est encore rattaché à votre fiche. Il apparaîtra ici dès sa mise en gestion.</p>
<?php else : ?>
	<div class="es-grille es-grille--2">
		<?php foreach ( $lignes as $l ) : ?>
			<?php
			$b     = $l['bien'];
			$bb    = $l['bilan'];
			$prises = count( array_filter( $l['cal'] ) );
			?>
			<article class="es-carte es-carte--bien">
				<?php if ( has_post_thumbnail( $b->ID ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $b->ID ) ); ?>" tabindex="-1" aria-hidden="true"><?php echo get_the_post_thumbnail( $b->ID, 'medium_large', array( 'class' => 'es-carte__photo', 'alt' => '' ) ); ?></a>
				<?php endif; ?>
				<div class="es-carte__corps">
					<div class="es-ligne">
						<h2 class="es-h2"><a href="<?php echo esc_url( get_permalink( $b->ID ) ); ?>"><?php echo esc_html( $b->post_title ); ?></a></h2>
						<span class="es-doux es-petit"><?php echo esc_html( HS_Donnees::m( $b->ID, 'reference' ) ); ?></span>
					</div>
					<?php if ( HS_Donnees::m( $b->ID, 'offre_courte' ) ) : ?>
						<p class="es-doux"><?php echo esc_html( sprintf( '%s : %d nuits réservées sur %d', ucfirst( date_i18n( 'F' ) ), $prises, count( $l['cal'] ) ) ); ?></p>
						<div class="es-jours" role="img" aria-label="<?php echo esc_attr( sprintf( 'Calendrier du mois : %d nuits réservées', $prises ) ); ?>">
							<?php foreach ( $l['cal'] as $jour => $pris ) : ?><span<?php echo $pris ? ' class="r"' : ''; ?> title="<?php echo (int) $jour; ?>"></span><?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="es-doux">Statut : <?php echo esc_html( HS_Shortcodes::format( HS_Donnees::m( $b->ID, 'statut' ), HS_Fields::for_type( 'hs_bien' )['statut'] ) ); ?></p>
					<?php endif; ?>
					<dl class="es-chiffres">
						<div><dt>Encaissé</dt><dd><?php echo esc_html( HS_Donnees::fcfa( $bb['encaisse'] ) ); ?></dd></div>
						<div><dt>Frais et commission</dt><dd><?php echo esc_html( HS_Donnees::fcfa( $bb['commission'] + $bb['depenses'] ) ); ?></dd></div>
						<div><dt>Net pour vous</dt><dd class="es-fort"><?php echo esc_html( HS_Donnees::fcfa( $bb['net'] ) ); ?></dd></div>
					</dl>
					<p class="es-doux es-petit">Commission HomyStay : <?php echo esc_html( HS_Donnees::nombre( $bb['taux'] ) ); ?> %. Dépenses du mois : <?php echo esc_html( HS_Donnees::fcfa( $bb['depenses'] ) ); ?></p>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
	<div class="es-legende"><span><i class="r"></i>Nuit réservée</span><span><i></i>Nuit libre</span></div>
<?php endif; ?>

<section class="es-section">
	<div class="es-ligne">
		<h2 class="es-h2">Relevés mensuels</h2>
		<?php if ( $wa && ! $ctx['apercu'] ) : ?><a class="es-btn es-btn--contour" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener">Signaler un problème sur un bien</a><?php endif; ?>
	</div>
	<?php if ( $releves ) : ?>
		<div class="es-defile">
			<table class="es-table">
				<thead><tr><th scope="col">Mois</th><th scope="col" class="n">Net versé</th><th scope="col">Versé le</th><th scope="col">Relevé</th></tr></thead>
				<tbody>
					<?php foreach ( $releves as $r ) : ?>
						<tr>
							<td><?php echo esc_html( ucfirst( HS_Donnees::nom_mois( substr( $r->reference_paiement, -7 ) ) ) ); ?></td>
							<td class="n"><?php echo esc_html( HS_Donnees::fcfa( $r->montant ) ); ?></td>
							<td><?php echo esc_html( HS_Donnees::date_fr( $r->date_operation ) . ( $r->moyen_paiement && isset( $moyens[ $r->moyen_paiement ] ) ? ', ' . $moyens[ $r->moyen_paiement ] : '' ) ); ?></td>
							<td><a href="<?php echo esc_url( HS_Espace::lien_document( 'releve', $r->id ) ); ?>" target="_blank" rel="noopener">Voir et imprimer</a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php else : ?>
		<p class="es-vide">Vos relevés apparaîtront ici après le premier versement.</p>
	<?php endif; ?>
</section>
