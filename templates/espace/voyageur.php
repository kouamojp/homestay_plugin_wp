<?php
/**
 * Espace voyageur : séjour en cours ou à venir, séjours passés, factures, favoris.
 * Surchargeable : <thème>/homystay/espace/voyageur.php
 *
 * @var array $ctx ['user_id' => int, 'email' => string]
 */
defined( 'ABSPATH' ) || exit;

$aujourd = HS_Donnees::aujourdhui();
$resas   = HS_Donnees::reservations_voyageur( $ctx['user_id'], $ctx['email'] );
$futurs  = array_reverse( array_filter( $resas, function ( $r ) use ( $aujourd ) { return HS_Donnees::m( $r->ID, 'depart' ) >= $aujourd; } ) );
$passes  = array_filter( $resas, function ( $r ) use ( $aujourd ) { return HS_Donnees::m( $r->ID, 'depart' ) < $aujourd; } );
$suivant = $futurs ? array_shift( $futurs ) : null;
$liste   = HS_CPT::url_liste( 'hs_bien' );
$paies   = HS_Fields::for_type( 'hs_reservation' )['paiement']['options'];
?>
<?php if ( $suivant ) : ?>
	<?php
	$bien    = (int) HS_Donnees::m( $suivant->ID, 'bien_id' );
	$arrivee = HS_Donnees::m( $suivant->ID, 'arrivee' );
	$depart  = HS_Donnees::m( $suivant->ID, 'depart' );
	$jours   = (int) round( ( strtotime( $arrivee ) - strtotime( $aujourd ) ) / DAY_IN_SECONDS );
	if ( $jours > 1 ) {
		$badge = sprintf( 'Votre séjour commence dans %d jours', $jours );
	} elseif ( 1 === $jours ) {
		$badge = 'Votre séjour commence demain';
	} elseif ( 0 === $jours ) {
		$badge = 'Votre séjour commence aujourd\'hui';
	} else {
		$badge = 'Séjour en cours';
	}
	$lat = (float) HS_Donnees::m( $bien, 'latitude' );
	$lng = (float) HS_Donnees::m( $bien, 'longitude' );
	$wa  = HS_Espace::whatsapp_equipe( 'Bonjour HomyStay, je suis ' . HS_Donnees::m( $suivant->ID, 'voyageur' ) . ', réservation ' . get_the_title( $bien ) . ' du ' . HS_Donnees::date_fr( $arrivee ) . '. ' );
	?>
	<section class="es-sejour" aria-label="Prochain séjour">
		<?php if ( has_post_thumbnail( $bien ) ) : ?>
			<?php echo get_the_post_thumbnail( $bien, 'large', array( 'class' => 'es-sejour__photo', 'alt' => '' ) ); ?>
		<?php else : ?>
			<div class="es-sejour__photo"></div>
		<?php endif; ?>
		<div class="es-sejour__infos">
			<p class="es-badge"><?php echo esc_html( $badge ); ?></p>
			<h2 class="es-sejour__titre"><a href="<?php echo esc_url( get_permalink( $bien ) ); ?>"><?php echo esc_html( get_the_title( $bien ) ); ?></a></h2>
			<p class="es-sejour__lieu"><?php echo esc_html( implode( ', ', array_filter( array( HS_Donnees::m( $bien, 'adresse' ), HS_Donnees::m( $bien, 'quartier' ), HS_Donnees::m( $bien, 'ville' ) ) ) ) ); ?></p>
			<dl class="es-sejour__dl">
				<div><dt>Arrivée</dt><dd><?php echo esc_html( ucfirst( HS_Donnees::date_fr( $arrivee, 'D j M' ) ) ); ?>, à partir de 14 h</dd></div>
				<div><dt>Départ</dt><dd><?php echo esc_html( ucfirst( HS_Donnees::date_fr( $depart, 'D j M' ) ) ); ?>, avant 11 h</dd></div>
				<div><dt>Voyageurs</dt><dd><?php echo esc_html( sprintf( _n( '%d personne', '%d personnes', (int) HS_Donnees::m( $suivant->ID, 'personnes' ), 'homystay-core' ), (int) HS_Donnees::m( $suivant->ID, 'personnes' ) ) ); ?></dd></div>
				<div><dt><?php echo 'paye' === HS_Donnees::m( $suivant->ID, 'paiement' ) ? 'Total payé' : 'Total'; ?></dt><dd><?php echo esc_html( HS_Donnees::fcfa( HS_Donnees::m( $suivant->ID, 'montant' ) ) ); ?><?php echo 'paye' !== HS_Donnees::m( $suivant->ID, 'paiement' ) ? ' (' . esc_html( mb_strtolower( $paies[ HS_Donnees::m( $suivant->ID, 'paiement' ) ] ) ) . ')' : ''; ?></dd></div>
			</dl>
			<?php if ( HS_Donnees::m( $suivant->ID, 'consignes' ) ) : ?>
				<p class="es-sejour__consignes"><?php echo nl2br( esc_html( HS_Donnees::m( $suivant->ID, 'consignes' ) ) ); ?></p>
			<?php endif; ?>
			<div class="es-boutons">
				<?php if ( $wa ) : ?><a class="es-btn es-btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo esc_html( HS_Espace::libelle_contact() ); ?></a><?php endif; ?>
				<?php if ( $lat && $lng ) : ?><a class="es-btn es-btn--clair" href="<?php echo esc_url( sprintf( 'https://www.google.com/maps/dir/?api=1&destination=%F,%F', $lat, $lng ) ); ?>" target="_blank" rel="noopener">Itinéraire</a><?php endif; ?>
			</div>
		</div>
	</section>
	<?php if ( $futurs ) : ?>
		<section class="es-section">
			<h2 class="es-h2">Autres séjours à venir</h2>
			<ul class="es-liste">
				<?php foreach ( $futurs as $r ) : ?>
					<li><span><strong><?php echo esc_html( get_the_title( (int) HS_Donnees::m( $r->ID, 'bien_id' ) ) ); ?></strong>, <?php echo esc_html( HS_Donnees::date_fr( HS_Donnees::m( $r->ID, 'arrivee' ) ) . ' au ' . HS_Donnees::date_fr( HS_Donnees::m( $r->ID, 'depart' ) ) ); ?></span><span class="es-doux"><?php echo esc_html( $paies[ HS_Donnees::m( $r->ID, 'paiement' ) ] ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
<?php else : ?>
	<section class="es-carte es-carte--feuille"><div class="es-carte__corps">
		<h2 class="es-h2">Aucun séjour à venir</h2>
		<p>Envie de repartir ? Nos logements à Douala, Yaoundé, Kribi et Limbé vous attendent.</p>
		<a class="es-btn" href="<?php echo esc_url( $liste ); ?>">Voir les logements</a>
	</div></section>
<?php endif; ?>

<div class="es-grille es-grille--3-2 es-section">
	<section>
		<h2 class="es-h2">Séjours passés</h2>
		<?php if ( $passes ) : ?>
			<ul class="es-liste">
				<?php foreach ( $passes as $r ) : ?>
					<?php $nuits = HS_Donnees::nuits_dans( HS_Donnees::m( $r->ID, 'arrivee' ), HS_Donnees::m( $r->ID, 'depart' ), HS_Donnees::m( $r->ID, 'arrivee' ), '2999-12-31' ); ?>
					<li>
						<span><strong><?php echo esc_html( get_the_title( (int) HS_Donnees::m( $r->ID, 'bien_id' ) ) ); ?></strong><br><span class="es-doux"><?php echo esc_html( HS_Donnees::date_fr( HS_Donnees::m( $r->ID, 'arrivee' ) ) . ' au ' . HS_Donnees::date_fr( HS_Donnees::m( $r->ID, 'depart' ), 'j F Y' ) . ', ' . sprintf( _n( '%d nuit', '%d nuits', $nuits, 'homystay-core' ), $nuits ) ); ?></span></span>
						<a href="<?php echo esc_url( get_permalink( (int) HS_Donnees::m( $r->ID, 'bien_id' ) ) ); ?>">Réserver à nouveau</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="es-vide">Vos séjours terminés apparaîtront ici.</p>
		<?php endif; ?>

		<h2 class="es-h2 es-section">Factures</h2>
		<?php $factures = array_filter( $resas, function ( $r ) { return in_array( HS_Donnees::m( $r->ID, 'paiement' ), array( 'paye', 'acompte' ), true ); } ); ?>
		<?php if ( $factures ) : ?>
			<ul class="es-liste">
				<?php foreach ( $factures as $r ) : ?>
					<li><span><?php echo esc_html( get_the_title( (int) HS_Donnees::m( $r->ID, 'bien_id' ) ) . ', ' . HS_Donnees::date_fr( HS_Donnees::m( $r->ID, 'arrivee' ), 'F Y' ) . ' : ' . HS_Donnees::fcfa( HS_Donnees::m( $r->ID, 'montant' ) ) ); ?></span><a href="<?php echo esc_url( HS_Espace::lien_document( 'facture', $r->ID ) ); ?>" target="_blank" rel="noopener">Voir et imprimer</a></li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="es-vide">Aucune facture pour l'instant.</p>
		<?php endif; ?>
	</section>

	<section>
		<h2 class="es-h2">Mes favoris</h2>
		<div class="es-favoris" data-hs-favoris data-vide="Touchez le cœur d'un logement pour le retrouver ici.">
			<p class="es-vide">Touchez le cœur d'un logement pour le retrouver ici.</p>
		</div>
		<a class="es-btn es-btn--plein" href="<?php echo esc_url( $liste ); ?>">Réserver un nouveau séjour</a>
	</section>
</div>
