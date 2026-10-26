<?php
/**
 * Espace locataire : loyer, contrat, demande d'intervention, quittances.
 * Surchargeable : <thème>/homystay/espace/locataire.php
 *
 * @var array $ctx ['fiche' => WP_Post locataire]
 */
defined( 'ABSPATH' ) || exit;

$loc      = $ctx['fiche'];
$bail     = HS_Donnees::contrat_locataire( $loc->ID );
$aujourd  = HS_Donnees::aujourdhui();
$bien     = $bail ? (int) HS_Donnees::m( $bail->ID, 'bien_id' ) : 0;
$ech      = $bail ? HS_Donnees::echeances_du_contrat( $bail->ID ) : array();
$retards  = array_filter( $ech, function ( $e ) use ( $aujourd ) { return 'paye' !== $e->statut && $e->date_echeance < $aujourd; } );
$a_venir  = array_filter( $ech, function ( $e ) use ( $aujourd ) { return 'paye' !== $e->statut && $e->date_echeance >= $aujourd; } );
$payees   = array_filter( $ech, function ( $e ) { return 'paye' === $e->statut; } );
$du       = 0;
foreach ( $retards as $e ) {
	$du += (int) $e->montant_du - (int) $e->montant_paye;
}
$loyer    = $bail ? (int) HS_Donnees::m( $bail->ID, 'loyer' ) : 0;
$prochain = $a_venir ? end( $a_venir ) : null; // échéances triées de la plus récente à la plus ancienne
$tickets  = $bien ? HS_Donnees::tickets_du_bien( $bien ) : array();
$ticket   = $tickets ? $tickets[0] : null;
$etapes   = HS_Fields::for_type( 'hs_ticket' )['etape']['options'];
$cats     = HS_Fields::for_type( 'hs_ticket' )['categorie']['options'];
$plus_vieux = $retards ? end( $retards ) : null;
if ( $retards ) {
	$jours   = (int) floor( ( strtotime( $aujourd ) - strtotime( $plus_vieux->date_echeance ) ) / DAY_IN_SECONDS );
	$libelle = count( $retards ) > 1 ? count( $retards ) . ' loyers' : 'Loyer ' . HS_Donnees::de_mois( $plus_vieux->periode );
	$msg_wa  = 'Bonjour HomyStay, je suis ' . $loc->post_title . '. Je souhaite régler ' . mb_strtolower( $libelle ) . ' (' . HS_Donnees::fcfa( $du ) . ').';
} elseif ( $prochain ) {
	$msg_wa = 'Bonjour HomyStay, je suis ' . $loc->post_title . '. Je souhaite régler mon loyer ' . HS_Donnees::de_mois( $prochain->periode ) . ' (' . HS_Donnees::fcfa( $prochain->montant_du ) . ').';
} else {
	$msg_wa = 'Bonjour HomyStay, je suis ' . $loc->post_title . ', locataire. ';
}
$wa = HS_Espace::whatsapp_equipe( $msg_wa );
?>
<?php if ( ! $bail ) : ?>
	<p class="es-vide">Votre bail n'est pas encore enregistré. Il apparaîtra ici dès sa signature.</p>
	<?php return; ?>
<?php endif; ?>

<p class="es-intro"><?php echo esc_html( get_the_title( $bien ) . ( HS_Donnees::m( $bien, 'ville' ) ? ', ' . HS_Donnees::m( $bien, 'ville' ) : '' ) ); ?></p>

<div class="es-grille es-grille--3-2">
	<section class="es-carte" aria-label="Loyer">
		<?php if ( $retards ) : ?>
			<p class="es-alerte es-alerte--danger"><?php echo esc_html( sprintf( '%s en retard de %d jours.', $libelle, $jours ) ); ?></p>
			<div class="es-carte__corps">
				<p class="es-label">À payer</p>
				<p class="es-valeur es-valeur--xl"><?php echo esc_html( HS_Donnees::nombre( $du ) ); ?> <span>FCFA</span></p>
				<p class="es-doux">Échéance du <?php echo esc_html( HS_Donnees::date_fr( $plus_vieux->date_echeance, 'j F' ) ); ?>. Charges comprises.</p>
		<?php elseif ( $prochain ) : ?>
			<div class="es-carte__corps">
				<p class="es-label">Prochain loyer, <?php echo esc_html( HS_Donnees::nom_mois( $prochain->periode ) ); ?></p>
				<p class="es-valeur es-valeur--xl"><?php echo esc_html( HS_Donnees::nombre( $prochain->montant_du ) ); ?> <span>FCFA</span></p>
				<p class="es-doux">À régler avant le <?php echo esc_html( HS_Donnees::date_fr( $prochain->date_echeance, 'j F' ) ); ?>. Charges comprises.</p>
		<?php else : ?>
			<p class="es-alerte es-alerte--ok">Vos loyers sont à jour.</p>
			<div class="es-carte__corps">
				<p class="es-label">Loyer mensuel</p>
				<p class="es-valeur es-valeur--xl"><?php echo esc_html( HS_Donnees::nombre( $loyer ) ); ?> <span>FCFA</span></p>
		<?php endif; ?>
			<?php if ( $wa && ( $retards || $prochain ) ) : ?>
				<div class="es-boutons">
					<a class="es-btn es-btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"<?php echo $ctx['apercu'] ? ' aria-disabled="true"' : ''; ?>>Payer mon loyer</a>
				</div>
				<p class="es-doux es-petit">Nous vous répondons avec les coordonnées Orange Money, MTN MoMo ou bancaires. Votre quittance apparaît ici dès le paiement reçu.</p>
			<?php endif; ?>
		</div>
	</section>

	<section class="es-carte es-carte--feuille">
		<div class="es-carte__corps">
			<h2 class="es-h2">Mon contrat</h2>
			<dl class="es-liste-def">
				<div><dt>Début</dt><dd><?php echo esc_html( HS_Donnees::date_fr( HS_Donnees::m( $bail->ID, 'date_debut' ), 'j F Y' ) ); ?></dd></div>
				<div><dt>Fin</dt><dd><?php echo esc_html( HS_Donnees::date_fr( HS_Donnees::m( $bail->ID, 'date_fin' ), 'j F Y' ) ); ?></dd></div>
				<div><dt>Loyer mensuel</dt><dd><?php echo esc_html( HS_Donnees::fcfa( $loyer ) ); ?></dd></div>
				<?php if ( HS_Donnees::m( $bail->ID, 'caution' ) ) : ?><div><dt>Caution versée</dt><dd><?php echo esc_html( HS_Donnees::fcfa( HS_Donnees::m( $bail->ID, 'caution' ) ) ); ?></dd></div><?php endif; ?>
			</dl>
			<?php $wa_q = HS_Espace::whatsapp_equipe( 'Bonjour HomyStay, je suis ' . $loc->post_title . ', locataire. J\'ai une question : ' ); ?>
			<?php if ( $wa_q ) : ?><a class="es-btn es-btn--wa es-btn--plein" href="<?php echo esc_url( $wa_q ); ?>" target="_blank" rel="noopener">Écrire à mon gestionnaire</a><?php endif; ?>
		</div>
	</section>
</div>

<section class="es-section es-carte">
	<div class="es-carte__corps">
		<h2 class="es-h2"><?php echo $ticket ? 'Ma demande en cours' : 'Signaler une panne'; ?></h2>
		<?php if ( $ticket ) : ?>
			<?php
			$etape = HS_Donnees::m( $ticket->ID, 'etape' );
			$ordre = array_keys( $etapes );
			$pos   = array_search( $etape, $ordre, true );
			?>
			<p><strong><?php echo esc_html( $ticket->post_title ); ?></strong>, signalée le <?php echo esc_html( get_the_date( 'j F', $ticket ) ); ?></p>
			<ol class="es-etapes" aria-label="Avancement de la demande">
				<?php foreach ( $ordre as $i => $k ) : ?>
					<?php
					$classe = $i < $pos ? 'ok' : ( $i === $pos ? 'ici' : '' );
					$txt    = $etapes[ $k ];
					if ( 'intervention' === $k && HS_Donnees::m( $ticket->ID, 'date_intervention' ) ) {
						$txt .= ' le ' . HS_Donnees::date_fr( HS_Donnees::m( $ticket->ID, 'date_intervention' ), 'l j F' ) . ( HS_Donnees::m( $ticket->ID, 'creneau' ) ? ', ' . HS_Donnees::m( $ticket->ID, 'creneau' ) : '' );
					}
					?>
					<li class="<?php echo esc_attr( $classe ); ?>"<?php echo $i === $pos ? ' aria-current="step"' : ''; ?>><b><?php echo (int) ( $i + 1 ); ?></b><?php echo esc_html( $txt ); ?></li>
				<?php endforeach; ?>
			</ol>
			<?php if ( HS_Donnees::m( $ticket->ID, 'technicien_nom' ) ) : ?>
				<p class="es-doux">Intervenant : <?php echo esc_html( HS_Donnees::m( $ticket->ID, 'technicien_nom' ) ); ?>. Il vous prévient avant d'arriver.</p>
			<?php endif; ?>
			<details class="es-details"><summary>Signaler un autre problème</summary>
		<?php endif; ?>
		<form class="es-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="hs_signaler">
			<?php wp_nonce_field( 'hs_signaler' ); ?>
			<div class="es-form__ligne">
				<label for="es-cat">Type de problème</label>
				<select id="es-cat" name="categorie"><?php foreach ( $cats as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
			</div>
			<div class="es-form__ligne">
				<label for="es-desc">Ce qui ne va pas</label>
				<textarea id="es-desc" name="description" rows="3" required placeholder="Ex. L'eau coule sous l'évier de la cuisine depuis ce matin."></textarea>
			</div>
			<label class="es-case"><input type="checkbox" name="urgent" value="1"> C'est urgent (fuite importante, plus d'électricité…)</label>
			<button type="submit" class="es-btn"<?php echo $ctx['apercu'] ? ' disabled' : ''; ?>>Envoyer ma demande</button>
		</form>
		<?php if ( $ticket ) : ?></details><?php endif; ?>
	</div>
</section>

<section class="es-section">
	<h2 class="es-h2">Mes quittances</h2>
	<?php if ( $payees ) : ?>
		<ul class="es-liste">
			<?php foreach ( $payees as $e ) : ?>
				<li><span><?php echo esc_html( ucfirst( HS_Donnees::nom_mois( $e->periode ) ) . ', payé le ' . HS_Donnees::date_fr( $e->date_paiement ) ); ?></span><a href="<?php echo esc_url( HS_Espace::lien_document( 'quittance', $e->id ) ); ?>" target="_blank" rel="noopener">Voir et imprimer</a></li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p class="es-vide">Vos quittances apparaîtront ici après votre premier paiement.</p>
	<?php endif; ?>
</section>
