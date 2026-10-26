<?php
/**
 * Page de suivi d'une réservation (lien reçu par email, espace voyageur) : paiement Mobile Money, état, consignes.
 * Surchargeable : <thème>/homystay/reservation-suivi.php
 *
 * @var int    $resa_id
 * @var string $cle
 */
defined( 'ABSPATH' ) || exit;

$id       = $resa_id;
$m        = function ( $k ) use ( $id ) { return HS_Donnees::m( $id, $k ); };
$bien     = (int) $m( 'bien_id' );
$statut   = $m( 'statut' );
$paie     = $m( 'paiement' );
$nuits    = HS_Donnees::nuits_dans( $m( 'arrivee' ), $m( 'depart' ), $m( 'arrivee' ), '2999-12-31' );
$total    = (int) $m( 'montant' );
$a_payer  = (int) $m( 'a_payer' ) ? (int) $m( 'a_payer' ) : $total;
$paye     = (int) $m( 'montant_paye' );
$menage   = (int) get_post_meta( $id, 'hs_menage', true ); // frais de ménage au moment de la réservation
$moyens   = HS_Reservation::moyens();
$etat     = isset( $_GET['etat'] ) ? sanitize_key( wp_unslash( $_GET['etat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$expire   = get_post_meta( $id, 'hs_expire_le', true );
$libre    = 'expiree' === $statut ? HS_Reservation::disponible( $bien, $m( 'arrivee' ), $m( 'depart' ), $id ) : true;
$payer    = in_array( $statut, array( 'demande', 'expiree' ), true ) && 'attente' === $paie && $libre;
$wa       = HS_Espace::whatsapp_equipe( sprintf( 'Bonjour HomyStay, je suis %s, réservation n° %d (%s du %s au %s). ', $m( 'voyageur' ), $id, get_the_title( $bien ), HS_Donnees::date_fr( $m( 'arrivee' ) ), HS_Donnees::date_fr( $m( 'depart' ) ) ) );
$msgs     = array(
	'declare'    => array( 'ok', 'Merci ! Votre paiement est enregistré. Nous le vérifions et vous confirmons la réservation.' ),
	'incomplet'  => array( 'erreur', 'Choisissez l\'opérateur, indiquez le numéro qui a payé et l\'ID de transaction reçu par SMS.' ),
	'plus-libre' => array( 'erreur', 'Le délai était passé et ces dates ont été réservées entre-temps. Contactez-nous : nous vous rembourserons ou vous proposerons d\'autres dates.' ),
	'deja'       => array( 'ok', 'Cette réservation est déjà traitée.' ),
);
if ( 'confirmee' === $statut ) {
	$pastille = array( 'disponible', 'Confirmée' );
} elseif ( 'verification' === $paie && 'demande' === $statut ) {
	$pastille = array( 'reserve', 'Paiement en vérification' );
} elseif ( 'demande' === $statut ) {
	$pastille = array( 'reserve', 'En attente de paiement' );
} elseif ( 'expiree' === $statut ) {
	$pastille = array( 'maintenance', 'Délai de paiement dépassé' );
} elseif ( 'terminee' === $statut ) {
	$pastille = array( 'archive', 'Séjour terminé' );
} else {
	$pastille = array( 'archive', 'Annulée' );
}
?>
<main id="hs-bien" class="hs-bien hs-suivi">
	<nav class="hs-bien__fil" aria-label="Fil d'Ariane">
		<ol>
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil</a></li>
			<li><a href="<?php echo esc_url( get_permalink( $bien ) ); ?>"><?php echo esc_html( get_the_title( $bien ) ); ?></a></li>
			<li aria-current="page">Ma réservation</li>
		</ol>
	</nav>

	<header class="hs-suivi__tete">
		<div>
			<p class="hs-suivi__num">Réservation n° <?php echo (int) $id; ?></p>
			<h1 class="hs-suivi__titre">
				<?php
				if ( 'confirmee' === $statut ) {
					echo 'C\'est réservé, ' . esc_html( strtok( $m( 'voyageur' ), ' ' ) ) . ' !';
				} elseif ( $payer ) {
					echo 'Plus qu\'une étape : le paiement';
				} elseif ( 'verification' === $paie && 'demande' === $statut ) {
					echo 'Nous vérifions votre paiement';
				} else {
					echo 'Votre réservation';
				}
				?>
			</h1>
		</div>
		<span class="hs-pastille hs-pastille--<?php echo esc_attr( $pastille[0] ); ?>"><?php echo esc_html( $pastille[1] ); ?></span>
	</header>

	<?php if ( isset( $msgs[ $etat ] ) ) : ?>
		<p class="hs-suivi__message hs-suivi__message--<?php echo esc_attr( $msgs[ $etat ][0] ); ?>" role="status"><?php echo esc_html( $msgs[ $etat ][1] ); ?></p>
	<?php endif; ?>

	<div class="hs-suivi__colonnes">
		<div class="hs-suivi__principal">

			<?php if ( $payer ) : ?>
				<section class="hs-suivi__bloc" id="paiement" aria-labelledby="hs-payer-titre">
					<?php if ( 'expiree' === $statut ) : ?>
						<p class="hs-encart__alerte">Le délai pour payer est passé, mais vos dates sont encore libres. Payez maintenant pour les garder.</p>
					<?php elseif ( $expire ) : ?>
						<p class="hs-suivi__delai">Vos dates sont gardées jusqu'au <strong><?php echo esc_html( HS_Reservation::date_limite( $id ) ); ?></strong>.</p>
					<?php endif; ?>

					<h2 class="hs-section__titre" id="hs-payer-titre">1. Envoyez <?php echo esc_html( HS_Donnees::fcfa( $a_payer ) ); ?></h2>
					<?php if ( $a_payer < $total ) : ?>
						<p class="hs-section__intro">Acompte pour confirmer. Le solde, <?php echo esc_html( HS_Donnees::fcfa( $total - $a_payer ) ); ?>, se règle à l'arrivée.</p>
					<?php endif; ?>

					<?php if ( $moyens ) : ?>
						<div class="hs-moyens">
							<?php foreach ( $moyens as $k => $mo ) : ?>
								<div class="hs-moyen hs-moyen--<?php echo esc_attr( $k ); ?>" data-hs-moyen="<?php echo esc_attr( $k ); ?>">
									<p class="hs-moyen__nom"><span class="hs-moyen__pastille" aria-hidden="true"></span><?php echo esc_html( $mo['nom'] ); ?></p>
									<dl>
										<div><dt>Numéro</dt><dd><strong><?php echo esc_html( $mo['numero'] ); ?></strong> <button type="button" class="hs-copier" data-hs-copier="<?php echo esc_attr( preg_replace( '/\D/', '', $mo['numero'] ) ); ?>">Copier</button></dd></div>
										<?php if ( $mo['titulaire'] ) : ?><div><dt>Au nom de</dt><dd><?php echo esc_html( $mo['titulaire'] ); ?></dd></div><?php endif; ?>
										<div><dt>Montant</dt><dd><strong><?php echo esc_html( HS_Donnees::fcfa( $a_payer ) ); ?></strong> <button type="button" class="hs-copier" data-hs-copier="<?php echo (int) $a_payer; ?>">Copier</button></dd></div>
									</dl>
									<p class="hs-moyen__aide">Composez <a href="<?php echo esc_url( 'tel:' . rawurlencode( $mo['code'] ) ); ?>"><?php echo esc_html( $mo['code'] ); ?></a> ou ouvrez l'application <?php echo esc_html( $mo['nom'] ); ?>, puis choisissez le transfert d'argent.</p>
								</div>
							<?php endforeach; ?>
						</div>

						<h2 class="hs-section__titre hs-suivi__etape2">2. Indiquez votre paiement</h2>
						<p class="hs-section__intro">Après le transfert, vous recevez un SMS de confirmation de votre opérateur. Recopiez ici l'ID (ou référence) de la transaction qu'il contient.</p>
						<form class="hs-paiement" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<fieldset class="hs-paiement__ops">
								<legend>J'ai payé par</legend>
								<?php $premier = true; ?>
								<?php foreach ( $moyens as $k => $mo ) : ?>
									<label class="hs-paiement__op hs-paiement__op--<?php echo esc_attr( $k ); ?>"><input type="radio" name="operateur" value="<?php echo esc_attr( $k ); ?>" required<?php echo 1 === count( $moyens ) && $premier ? ' checked' : ''; ?>> <?php echo esc_html( $mo['nom'] ); ?></label>
									<?php $premier = false; ?>
								<?php endforeach; ?>
							</fieldset>
							<div class="hs-paiement__champs">
								<label class="hs-resa__champ hs-resa__champ--plein"><span>Numéro qui a payé</span><input type="tel" name="numero_paiement" inputmode="tel" autocomplete="tel" required value="<?php echo esc_attr( $m( 'telephone' ) ); ?>"></label>
								<label class="hs-resa__champ hs-resa__champ--plein"><span>ID de transaction</span><input type="text" name="transaction" required autocapitalize="characters" spellcheck="false" placeholder="Recopiez l'ID du SMS"></label>
							</div>
							<input type="hidden" name="action" value="hs_declarer_paiement">
							<input type="hidden" name="resa" value="<?php echo (int) $id; ?>">
							<input type="hidden" name="cle" value="<?php echo esc_attr( get_post_meta( $id, 'hs_cle', true ) ); ?>">
							<input type="hidden" name="_hs_nonce" value="<?php echo esc_attr( wp_create_nonce( 'hs_paiement_' . $id ) ); ?>">
							<button type="submit" class="hs-btn hs-btn--plein">J'ai payé, envoyer</button>
							<p class="hs-resa__note">Nous vérifions la transaction dans notre compte, puis vous confirmons la réservation<?php echo $m( 'email' ) ? ' par email' : ''; ?>. Comptez en général moins d'une heure, de 8 h à 20 h.</p>
						</form>
					<?php else : ?>
						<p>Nous vous contactons au <?php echo esc_html( $m( 'telephone' ) ); ?> pour le paiement.</p>
					<?php endif; ?>
				</section>

			<?php elseif ( 'demande' === $statut && 'verification' === $paie ) : ?>
				<section class="hs-suivi__bloc">
					<ol class="hs-suivi__fil">
						<li class="est-fait">Demande envoyée</li>
						<li class="est-fait">Paiement déclaré : <?php echo esc_html( HS_Donnees::fcfa( $a_payer ) ); ?>, ID <span class="hs-suivi__code"><?php echo esc_html( $m( 'transaction' ) ); ?></span></li>
						<li class="est-ici" aria-current="step">Vérification par notre équipe</li>
						<li>Confirmation<?php echo $m( 'email' ) ? ' par email' : ''; ?> et accès à votre espace</li>
					</ol>
					<p>Vous pouvez fermer cette page : ce lien reste valable pour suivre votre réservation.</p>
				</section>

			<?php elseif ( 'confirmee' === $statut || 'terminee' === $statut ) : ?>
				<section class="hs-suivi__bloc">
					<dl class="hs-suivi__arrivee">
						<div><dt>Arrivée</dt><dd><?php echo esc_html( ucfirst( HS_Donnees::date_fr( $m( 'arrivee' ), 'l j F' ) ) ); ?><span>à partir de <?php echo esc_html( HS_Settings::get( 'heure_arrivee' ) ); ?></span></dd></div>
						<div><dt>Départ</dt><dd><?php echo esc_html( ucfirst( HS_Donnees::date_fr( $m( 'depart' ), 'l j F' ) ) ); ?><span>avant <?php echo esc_html( HS_Settings::get( 'heure_depart' ) ); ?></span></dd></div>
					</dl>
					<?php $adresse = implode( ', ', array_filter( array( HS_Donnees::m( $bien, 'adresse' ), HS_Donnees::m( $bien, 'quartier' ), HS_Donnees::m( $bien, 'ville' ) ) ) ); ?>
					<?php if ( $adresse ) : ?><p><strong>Adresse :</strong> <?php echo esc_html( $adresse ); ?></p><?php endif; ?>
					<?php if ( $m( 'consignes' ) ) : ?>
						<h2 class="hs-section__titre">Consignes d'arrivée</h2>
						<p class="hs-suivi__consignes"><?php echo nl2br( esc_html( $m( 'consignes' ) ) ); ?></p>
					<?php endif; ?>
					<?php if ( $total > $paye ) : ?>
						<p class="hs-encart__alerte">Reste à régler à l'arrivée : <strong><?php echo esc_html( HS_Donnees::fcfa( $total - $paye ) ); ?></strong>.</p>
					<?php endif; ?>
					<div class="hs-suivi__boutons">
						<?php $lat = (float) HS_Donnees::m( $bien, 'latitude' ); $lng = (float) HS_Donnees::m( $bien, 'longitude' ); ?>
						<?php if ( $lat && $lng ) : ?><a class="hs-btn hs-btn--contour" href="<?php echo esc_url( sprintf( 'https://www.google.com/maps/dir/?api=1&destination=%F,%F', $lat, $lng ) ); ?>" target="_blank" rel="noopener">Itinéraire</a><?php endif; ?>
						<a class="hs-btn hs-btn--contour" href="<?php echo esc_url( HS_Espace::url() ); ?>">Mon espace voyageur</a>
					</div>
					<?php if ( ! is_user_logged_in() && $m( 'email' ) ) : ?>
						<p class="hs-resa__note">Votre espace a été créé avec <?php echo esc_html( $m( 'email' ) ); ?> : le lien pour choisir votre mot de passe vous a été envoyé par email.</p>
					<?php endif; ?>
				</section>

			<?php elseif ( 'expiree' === $statut ) : ?>
				<section class="hs-suivi__bloc">
					<p>Le délai pour payer est passé et ces dates ont été réservées par un autre voyageur.</p>
					<a class="hs-btn hs-btn--plein" href="<?php echo esc_url( get_permalink( $bien ) ); ?>">Choisir d'autres dates</a>
				</section>

			<?php else : ?>
				<section class="hs-suivi__bloc">
					<p>Cette réservation est annulée. Pour toute question, écrivez-nous.</p>
					<a class="hs-btn hs-btn--contour" href="<?php echo esc_url( HS_CPT::url_liste( 'hs_bien' ) ); ?>">Voir les logements</a>
				</section>
			<?php endif; ?>

			<?php if ( $wa ) : ?>
				<p class="hs-suivi__aide">Une question ? <a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo esc_html( HS_Espace::libelle_contact() ); ?></a><?php echo HS_Settings::get( 'telephone' ) ? ' ou appelez le ' . esc_html( HS_Settings::get( 'telephone' ) ) : ''; ?>.</p>
			<?php endif; ?>
		</div>

		<aside class="hs-suivi__lateral" aria-label="Récapitulatif">
			<div class="hs-encart hs-recap">
				<?php if ( has_post_thumbnail( $bien ) ) : ?>
					<?php echo get_the_post_thumbnail( $bien, 'medium_large', array( 'class' => 'hs-recap__photo', 'alt' => '' ) ); ?>
				<?php endif; ?>
				<p class="hs-recap__bien"><a href="<?php echo esc_url( get_permalink( $bien ) ); ?>"><?php echo esc_html( get_the_title( $bien ) ); ?></a></p>
				<p class="hs-recap__lieu"><?php echo esc_html( implode( ', ', array_filter( array( HS_Donnees::m( $bien, 'quartier' ), HS_Donnees::m( $bien, 'ville' ) ) ) ) ); ?></p>
				<dl class="hs-resa__recap">
					<div><dt>Dates</dt><dd><?php echo esc_html( HS_Donnees::date_fr( $m( 'arrivee' ), 'j M' ) . ' → ' . HS_Donnees::date_fr( $m( 'depart' ), 'j M Y' ) ); ?></dd></div>
					<div><dt>Voyageurs</dt><dd><?php echo esc_html( sprintf( _n( '%d personne', '%d personnes', (int) $m( 'personnes' ), 'homystay-core' ), (int) $m( 'personnes' ) ) ); ?></dd></div>
					<div><dt><?php echo esc_html( sprintf( _n( '%d nuit', '%d nuits', $nuits, 'homystay-core' ), $nuits ) ); ?></dt><dd><?php echo esc_html( HS_Donnees::fcfa( $total - $menage ) ); ?></dd></div>
					<?php if ( $menage ) : ?><div><dt>Frais de ménage</dt><dd><?php echo esc_html( HS_Donnees::fcfa( $menage ) ); ?></dd></div><?php endif; ?>
					<div class="hs-resa__total"><dt>Total</dt><dd><?php echo esc_html( HS_Donnees::fcfa( $total ) ); ?></dd></div>
					<?php if ( $paye ) : ?>
						<div class="hs-resa__acompte"><dt>Déjà payé</dt><dd><?php echo esc_html( HS_Donnees::fcfa( $paye ) ); ?></dd></div>
					<?php elseif ( $a_payer < $total ) : ?>
						<div class="hs-resa__acompte"><dt>À payer pour confirmer</dt><dd><?php echo esc_html( HS_Donnees::fcfa( $a_payer ) ); ?></dd></div>
					<?php endif; ?>
				</dl>
			</div>
		</aside>
	</div>
</main>
