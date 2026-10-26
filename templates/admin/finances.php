<?php
/**
 * Vue Finances (comptable, administrateur).
 *
 * @var string $mois Y-m
 */
defined( 'ABSPATH' ) || exit;

list( $debut, $fin ) = HS_Donnees::bornes( $mois );
$rev       = HS_Donnees::reversements( $mois );
$encaisse  = HS_Donnees::somme( 'revenu', $debut, $fin );
$depenses  = HS_Donnees::somme( 'depense', $debut, $fin );
$serie     = HS_Donnees::serie_mensuelle( $mois, 6 );
$max       = max( 1, max( array_map( function ( $s ) { return max( $s['encaisse'], $s['depense'] ); }, $serie ) ) );
$impayes   = HS_Donnees::impayes();
$a_valider = array_filter( $rev['lignes'], function ( $l ) { return ! $l['verse'] && $l['net'] > 0; } );
$tons      = array( 'Mise en demeure' => 'danger', 'Relance J+10' => 'danger', 'Relance J+3' => 'alerte', 'En retard' => 'alerte' );
$export    = wp_nonce_url( add_query_arg( array( 'action' => 'hs_export_ecritures', 'mois' => $mois ), admin_url( 'admin-post.php' ) ), 'hs_export_ecritures' );
$versement = gmdate( 'Y-m-05', strtotime( $debut . ' +1 month' ) );
?>
<div class="tb-actions">
	<p class="tb-doux"><?php echo esc_html( ucfirst( HS_Donnees::nom_mois( $mois ) ) ); ?>, au <?php echo esc_html( date_i18n( 'j F' ) ); ?></p>
	<a class="tb-btn tb-btn--contour" href="<?php echo esc_url( $export ); ?>">Exporter pour l'expert-comptable</a>
</div>

<section class="tb-bande" aria-label="Totaux du mois">
	<div><p class="tb-label">Encaissé</p><p class="tb-valeur"><?php echo esc_html( HS_Donnees::nombre( $encaisse ) ); ?></p><p class="tb-note">FCFA, loyers et séjours</p></div>
	<div><p class="tb-label">Dépenses</p><p class="tb-valeur"><?php echo esc_html( HS_Donnees::nombre( $depenses ) ); ?></p><p class="tb-note">FCFA, entretien, assurance, taxes</p></div>
	<div><p class="tb-label">Commissions HomyStay</p><p class="tb-valeur"><?php echo esc_html( HS_Donnees::nombre( $rev['total']['commission'] ) ); ?></p><p class="tb-note">FCFA, selon les mandats (<?php echo (int) HS_Donnees::COMMISSION_DEFAUT; ?> % par défaut)</p></div>
	<div class="tb-bande__fort"><p class="tb-label">À reverser aux propriétaires</p><p class="tb-valeur"><?php echo esc_html( HS_Donnees::nombre( $rev['total']['net'] ) ); ?></p><p class="tb-note">FCFA, le <?php echo esc_html( HS_Donnees::date_fr( $versement ) ); ?></p></div>
</section>

<div class="tb-grille tb-grille--3-2">
	<section class="tb-carte">
		<div class="tb-titre-ligne">
			<h2 class="tb-h2">Encaissé et dépensé sur 6 mois</h2>
			<div class="tb-legende"><span><i class="tb-c-foret"></i>Encaissé</span><span><i class="tb-c-ocre"></i>Dépensé</span></div>
		</div>
		<p class="tb-doux tb-sous-titre">En FCFA</p>
		<div class="tb-histo" role="img" aria-label="Encaissé et dépensé par mois sur 6 mois">
			<?php foreach ( $serie as $m => $s ) : ?>
				<div class="tb-histo__mois<?php echo $m === $mois ? ' est-courant' : ''; ?>">
					<div class="tb-histo__barres">
						<span class="tb-c-foret" style="height:<?php echo (int) round( 100 * $s['encaisse'] / $max ); ?>%" title="<?php echo esc_attr( HS_Donnees::fcfa( $s['encaisse'] ) ); ?>"></span>
						<span class="tb-c-ocre" style="height:<?php echo (int) round( 100 * $s['depense'] / $max ); ?>%" title="<?php echo esc_attr( HS_Donnees::fcfa( $s['depense'] ) ); ?>"></span>
					</div>
					<span class="tb-histo__nom"><?php echo esc_html( ucfirst( HS_Donnees::nom_mois( $m, true ) ) . ( $m === $mois ? ' (en cours)' : '' ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="tb-carte">
		<h2 class="tb-h2">Impayés</h2>
		<?php if ( $impayes ) : ?>
			<ul class="tb-impayes">
				<?php foreach ( $impayes as $r ) : ?>
					<?php $wa = HS_Donnees::whatsapp( $r->telephone, sprintf( 'Bonjour, sauf erreur de notre part, le loyer %s (%s) pour %s n\'a pas encore été réglé. Merci de nous indiquer la date de paiement. HomyStay Capital', HS_Donnees::de_mois( $r->periode ), HS_Donnees::fcfa( $r->reste ), get_the_title( (int) $r->bien_id ) ) ); ?>
					<li>
						<div class="tb-ligne"><strong><?php echo esc_html( get_the_title( (int) $r->bien_id ) ); ?></strong><span class="n"><?php echo esc_html( HS_Donnees::fcfa( $r->reste ) ); ?></span></div>
						<div class="tb-ligne tb-doux"><span><?php echo esc_html( $r->locataire . ', loyer ' . HS_Donnees::de_mois( $r->periode ) ); ?></span><span class="tb-puce tb-puce--<?php echo esc_attr( $tons[ $r->niveau ] ); ?>"><?php echo esc_html( $r->niveau . ' (' . $r->jours . ' j)' ); ?></span></div>
						<?php if ( $wa ) : ?><a class="tb-lien-wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener">Relancer sur WhatsApp</a><?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="tb-vide">Aucun loyer en retard.</p>
		<?php endif; ?>
		<p class="tb-doux tb-petit">Paliers de relance : J+3 (SMS, WhatsApp), J+10 (email et appel), J+30 (mise en demeure).</p>
	</section>
</div>

<section class="tb-carte">
	<div class="tb-titre-ligne">
		<div>
			<h2 class="tb-h2">Reversements à valider</h2>
			<p class="tb-doux tb-sous-titre"><?php echo esc_html( ucfirst( HS_Donnees::nom_mois( $mois ) ) ); ?>, versement prévu le <?php echo esc_html( HS_Donnees::date_fr( $versement ) ); ?></p>
		</div>
		<?php if ( $a_valider ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hs_valider_reversements">
				<input type="hidden" name="mois" value="<?php echo esc_attr( $mois ); ?>">
				<?php wp_nonce_field( 'hs_valider_reversements' ); ?>
				<button type="submit" class="tb-btn"><?php echo esc_html( sprintf( _n( 'Valider le reversement', 'Valider les %d reversements', count( $a_valider ), 'homystay-core' ), count( $a_valider ) ) ); ?></button>
			</form>
		<?php endif; ?>
	</div>
	<?php if ( $rev['lignes'] ) : ?>
		<div class="tb-defile">
			<table class="tb-table">
				<thead><tr><th scope="col">Propriétaire</th><th scope="col">Biens</th><th scope="col" class="n">Encaissé</th><th scope="col" class="n">Commission</th><th scope="col" class="n">Dépenses</th><th scope="col" class="n">Net à verser</th><th scope="col">Moyen</th><th scope="col">État</th></tr></thead>
				<tbody>
					<?php foreach ( $rev['lignes'] as $l ) : ?>
						<tr>
							<td><a class="tb-fort" href="<?php echo esc_url( get_edit_post_link( $l['proprietaire']->ID ) ); ?>"><?php echo esc_html( $l['proprietaire']->post_title ); ?></a></td>
							<td><?php echo esc_html( implode( ', ', $l['biens'] ) ); ?></td>
							<td class="n"><?php echo esc_html( HS_Donnees::nombre( $l['encaisse'] ) ); ?></td>
							<td class="n"><?php echo esc_html( HS_Donnees::nombre( $l['commission'] ) ); ?></td>
							<td class="n"><?php echo esc_html( HS_Donnees::nombre( $l['depenses'] ) ); ?></td>
							<td class="n"><strong><?php echo esc_html( HS_Donnees::nombre( $l['net'] ) ); ?></strong></td>
							<td><?php echo esc_html( $l['moyen'] ? $l['moyen'] : '—' ); ?></td>
							<td><span class="tb-puce tb-puce--<?php echo $l['verse'] ? 'ok' : 'alerte'; ?>"><?php echo $l['verse'] ? 'Validé' : 'À valider'; ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php else : ?>
		<p class="tb-vide">Aucun mouvement ce mois-ci sur les biens des propriétaires.</p>
	<?php endif; ?>
</section>
