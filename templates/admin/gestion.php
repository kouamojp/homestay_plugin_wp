<?php
/**
 * Vue Gestion (administrateur, gestionnaire).
 *
 * @var string $mois Y-m
 */
defined( 'ABSPATH' ) || exit;

list( $debut, $fin ) = HS_Donnees::bornes( $mois );
$occ        = HS_Donnees::occupation( $mois );
$occ_prec   = HS_Donnees::occupation( HS_Donnees::mois_decale( $mois, -1 ) );
$encaisse   = HS_Donnees::somme( 'revenu', $debut, $fin );
$impayes    = HS_Donnees::impayes();
$du         = array_sum( wp_list_pluck( $impayes, 'reste' ) );
$tickets    = HS_Donnees::tickets_ouverts();
$bloquants  = count( array_filter( $tickets, function ( $t ) { return (int) HS_Donnees::m( $t->ID, 'bloquant' ); } ) );
$a_valider  = array_filter( $tickets, function ( $t ) { return 'signale' === HS_Donnees::m( $t->ID, 'etape' ); } );
$agenda     = HS_Donnees::agenda_du_jour();
HS_Reservation::expirer();
$demandes   = HS_Donnees::posts( 'hs_reservation', array( array( 'key' => 'hs_statut', 'value' => 'demande' ) ), array( 'post_status' => 'any' ) );
usort( $demandes, function ( $a, $b ) { return ( 'verification' === HS_Donnees::m( $b->ID, 'paiement' ) ) - ( 'verification' === HS_Donnees::m( $a->ID, 'paiement' ) ); } );
$polices    = HS_Donnees::polices_a_renouveler();
$statuts    = HS_Donnees::biens_par_statut();
$resas      = HS_Donnees::prochaines_reservations( 6 );
$crm        = HS_Donnees::contacts_par_etape();
$defs_r     = HS_Fields::for_type( 'hs_reservation' );
$puces_paie = array( 'paye' => 'ok', 'acompte' => 'alerte', 'verification' => 'alerte', 'attente' => 'danger', 'rembourse' => 'neutre' );
$puces_jour = array( 'depart' => array( 'Départ', 'ok' ), 'arrivee' => array( 'Arrivée', 'alerte' ), 'visite' => array( 'Rendez-vous', 'info' ) );
?>
<div class="tb-actions">
	<a class="tb-btn tb-btn--contour" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=hs_reservation' ) ); ?>">Nouvelle réservation</a>
	<a class="tb-btn" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=hs_bien' ) ); ?>">Ajouter un bien</a>
</div>

<section class="tb-bande" aria-label="Indicateurs du mois">
	<div>
		<p class="tb-label">Taux d'occupation en <?php echo esc_html( date_i18n( 'F', strtotime( $debut ) ) ); ?></p>
		<p class="tb-valeur"><?php echo null === $occ['taux'] ? '—' : (int) $occ['taux'] . ' %'; ?></p>
		<p class="tb-note">
			<?php
			if ( null === $occ['taux'] ) {
				echo 'Aucun bien loué à la nuit';
			} elseif ( null !== $occ_prec['taux'] ) {
				$delta = $occ['taux'] - $occ_prec['taux'];
				printf( '<span class="%s">%s%d points sur le mois dernier</span>', $delta >= 0 ? 'tb-hausse' : 'tb-baisse', $delta >= 0 ? '+' : '', (int) $delta ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
		</p>
	</div>
	<div>
		<p class="tb-label">Encaissé ce mois-ci</p>
		<p class="tb-valeur"><?php echo esc_html( HS_Donnees::nombre( $encaisse ) ); ?> <span>FCFA</span></p>
		<p class="tb-note">Loyers et séjours</p>
	</div>
	<div>
		<p class="tb-label">Loyers impayés</p>
		<p class="tb-valeur<?php echo $impayes ? ' tb-valeur--danger' : ''; ?>"><?php echo (int) count( $impayes ); ?></p>
		<p class="tb-note"><?php echo $impayes ? esc_html( HS_Donnees::fcfa( $du ) . ' à recouvrer' ) : 'Tout est à jour'; ?></p>
	</div>
	<div>
		<p class="tb-label">Tickets de maintenance ouverts</p>
		<p class="tb-valeur"><?php echo (int) count( $tickets ); ?></p>
		<p class="tb-note<?php echo $bloquants ? ' tb-baisse' : ''; ?>"><?php echo $bloquants ? esc_html( sprintf( 'Dont %d bloquant', $bloquants ) ) : 'Aucun bloquant'; ?></p>
	</div>
</section>

<div class="tb-grille tb-grille--2">
	<section class="tb-carte">
		<h2 class="tb-h2">Aujourd'hui</h2>
		<?php if ( $agenda ) : ?>
			<ul class="tb-agenda">
				<?php foreach ( $agenda as $l ) : ?>
					<li>
						<span class="tb-heure"><?php echo esc_html( $l['heure'] ); ?></span>
						<div>
							<p class="tb-fort"><a href="<?php echo esc_url( $l['lien'] ); ?>"><?php echo esc_html( $l['titre'] ); ?></a></p>
							<?php if ( $l['detail'] ) : ?><p class="tb-doux"><?php echo esc_html( $l['detail'] ); ?></p><?php endif; ?>
						</div>
						<span class="tb-puce tb-puce--<?php echo esc_attr( $puces_jour[ $l['type'] ][1] ); ?>"><?php echo esc_html( $puces_jour[ $l['type'] ][0] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="tb-vide">Aucune arrivée, aucun départ ni rendez-vous aujourd'hui.</p>
		<?php endif; ?>

		<h3 class="tb-h3">À traiter</h3>
		<?php if ( $impayes || $polices || $a_valider || $demandes ) : ?>
			<ul class="tb-taches">
				<?php foreach ( $demandes as $d ) : ?>
					<?php $verif = 'verification' === HS_Donnees::m( $d->ID, 'paiement' ); ?>
					<li class="<?php echo $verif ? 'tb-tache--alerte' : ''; ?>"><span><?php echo $verif ? 'Paiement à vérifier' : 'Réservation en attente de paiement'; ?> : <?php echo esc_html( HS_Donnees::m( $d->ID, 'voyageur' ) . ', ' . get_the_title( (int) HS_Donnees::m( $d->ID, 'bien_id' ) ) . ' (' . HS_Donnees::fcfa( HS_Donnees::m( $d->ID, 'a_payer' ) ) . ')' ); ?></span><a href="<?php echo esc_url( get_edit_post_link( $d->ID ) ); ?>"><?php echo $verif ? 'Vérifier' : 'Ouvrir'; ?></a></li>
				<?php endforeach; ?>
				<?php foreach ( $impayes as $r ) : ?>
					<li class="tb-tache--danger"><span>Loyer <?php echo esc_html( HS_Donnees::de_mois( $r->periode ) ); ?> impayé : <?php echo esc_html( get_the_title( (int) $r->bien_id ) ); ?></span><a href="<?php echo esc_url( HS_Tableau::url( 'finances' ) ); ?>">Relancer</a></li>
				<?php endforeach; ?>
				<?php foreach ( $polices as $p ) : ?>
					<?php $jours = (int) round( ( strtotime( HS_Donnees::m( $p->ID, 'echeance' ) ) - strtotime( HS_Donnees::aujourdhui() ) ) / DAY_IN_SECONDS ); ?>
					<li class="tb-tache--alerte"><span>Assurance de <?php echo esc_html( get_the_title( (int) HS_Donnees::m( $p->ID, 'bien_id' ) ) ); ?> : échéance dans <?php echo (int) $jours; ?> jours</span><a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>">Renouveler</a></li>
				<?php endforeach; ?>
				<?php foreach ( $a_valider as $t ) : ?>
					<li><span>Ticket à valider : <?php echo esc_html( $t->post_title ); ?>, <?php echo esc_html( get_the_title( (int) HS_Donnees::m( $t->ID, 'bien_id' ) ) ); ?></span><a href="<?php echo esc_url( get_edit_post_link( $t->ID ) ); ?>">Ouvrir</a></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="tb-vide">Rien en attente.</p>
		<?php endif; ?>
	</section>

	<section class="tb-carte">
		<h2 class="tb-h2">Occupation par ville</h2>
		<p class="tb-doux tb-sous-titre">Nuits réservées sur nuits disponibles, <?php echo esc_html( date_i18n( 'F', strtotime( $debut ) ) ); ?></p>
		<?php if ( $occ['villes'] ) : ?>
			<ul class="tb-barres">
				<?php foreach ( $occ['villes'] as $ville => $v ) : ?>
					<li><span><?php echo esc_html( $ville ); ?></span><span class="tb-jauge" role="img" aria-label="<?php echo esc_attr( $ville . ' : ' . $v['taux'] . ' %' ); ?>"><span style="width:<?php echo (int) $v['taux']; ?>%"></span></span><strong><?php echo (int) $v['taux']; ?> %</strong></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="tb-vide">Aucun bien proposé en courte durée.</p>
		<?php endif; ?>

		<h3 class="tb-h3">Biens par statut</h3>
		<div class="tb-statuts">
			<?php
			foreach ( array( 'disponible' => 'ok', 'reserve' => 'alerte', 'occupe' => 'alerte', 'maintenance' => 'neutre' ) as $k => $ton ) :
				?>
				<a class="tb-statut tb-statut--<?php echo esc_attr( $ton ); ?>" href="<?php echo esc_url( admin_url( 'edit.php?post_type=hs_bien' ) ); ?>"><strong><?php echo (int) $statuts[ $k ]; ?></strong><?php echo esc_html( HS_Fields::STATUTS_BIEN[ $k ] ); ?></a>
			<?php endforeach; ?>
		</div>
	</section>
</div>

<div class="tb-grille tb-grille--2-1">
	<section class="tb-carte">
		<div class="tb-titre-ligne">
			<h2 class="tb-h2">Prochaines réservations</h2>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=hs_reservation' ) ); ?>">Toutes les réservations</a>
		</div>
		<?php if ( $resas ) : ?>
			<div class="tb-defile">
				<table class="tb-table">
					<thead><tr><th scope="col">Bien</th><th scope="col">Voyageur</th><th scope="col">Séjour</th><th scope="col" class="n">Montant</th><th scope="col">Paiement</th></tr></thead>
					<tbody>
						<?php foreach ( $resas as $r ) : ?>
							<?php $p = HS_Donnees::m( $r->ID, 'paiement' ); ?>
							<tr>
								<td><a href="<?php echo esc_url( get_edit_post_link( $r->ID ) ); ?>"><?php echo esc_html( get_the_title( (int) HS_Donnees::m( $r->ID, 'bien_id' ) ) ); ?></a></td>
								<td><?php echo esc_html( HS_Donnees::m( $r->ID, 'voyageur' ) ); ?></td>
								<td><?php echo esc_html( HS_Donnees::date_fr( HS_Donnees::m( $r->ID, 'arrivee' ), 'j M' ) . ' au ' . HS_Donnees::date_fr( HS_Donnees::m( $r->ID, 'depart' ), 'j M' ) ); ?></td>
								<td class="n"><?php echo esc_html( HS_Donnees::fcfa( HS_Donnees::m( $r->ID, 'montant' ) ) ); ?></td>
								<td><span class="tb-puce tb-puce--<?php echo esc_attr( isset( $puces_paie[ $p ] ) ? $puces_paie[ $p ] : 'neutre' ); ?>"><?php echo esc_html( HS_Shortcodes::format( $p, $defs_r['paiement'] ) ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<p class="tb-vide">Aucune réservation à venir. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=hs_reservation' ) ); ?>">Enregistrer une réservation</a></p>
		<?php endif; ?>
	</section>

	<section class="tb-carte tb-carte--foret">
		<h2 class="tb-h2">Demandes en cours</h2>
		<p class="tb-sous-titre">CRM, tous types de contacts</p>
		<ul class="tb-compteurs">
			<?php foreach ( $crm as $k => $col ) : ?>
				<?php if ( 'signe' === $k ) { continue; } ?>
				<li><span><?php echo esc_html( $col['label'] ); ?></span><strong><?php echo (int) count( $col['contacts'] ); ?></strong></li>
			<?php endforeach; ?>
			<li class="tb-compteurs__total"><span>Contrats signés en <?php echo esc_html( date_i18n( 'F', strtotime( $debut ) ) ); ?></span><strong><?php echo (int) HS_Donnees::signes_du_mois( $mois ); ?></strong></li>
		</ul>
		<a class="tb-lien-clair" href="<?php echo esc_url( current_user_can( 'manage_options' ) ? HS_Tableau::url( 'commercial' ) : admin_url( 'edit.php?post_type=hs_contact' ) ); ?>">Ouvrir le CRM</a>
	</section>
</div>
