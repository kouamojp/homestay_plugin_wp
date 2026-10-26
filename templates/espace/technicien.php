<?php
/**
 * Espace technicien (pensé pour le téléphone) : interventions du jour et à venir.
 * Surchargeable : <thème>/homystay/espace/technicien.php
 *
 * @var array $ctx ['user_id' => int, 'nom' => string]
 */
defined( 'ABSPATH' ) || exit;

$aujourd  = HS_Donnees::aujourdhui();
$tickets  = HS_Donnees::tickets_technicien( $ctx['user_id'], $ctx['apercu'] ? $ctx['nom'] : '' );
$jours    = array();
foreach ( $tickets as $t ) {
	$d = HS_Donnees::m( $t->ID, 'date_intervention' );
	$d = $d ? $d : $aujourd;
	$jours[ $d < $aujourd ? $aujourd : $d ][] = $t;
}
ksort( $jours );
$cats  = HS_Fields::for_type( 'hs_ticket' )['categorie']['options'];
$tel   = HS_Settings::get( 'telephone' );
$nb_j  = isset( $jours[ $aujourd ] ) ? count( $jours[ $aujourd ] ) : 0;
?>
<p class="es-intro"><?php echo esc_html( $nb_j ? sprintf( _n( '%d intervention aujourd\'hui', '%d interventions aujourd\'hui', $nb_j, 'homystay-core' ), $nb_j ) : 'Aucune intervention aujourd\'hui' ); ?></p>

<?php if ( ! $jours ) : ?>
	<p class="es-vide">Aucune intervention ne vous est attribuée pour l'instant.</p>
<?php endif; ?>

<div class="es-interventions">
	<?php foreach ( $jours as $jour => $liste ) : ?>
		<h2 class="es-h3"><?php echo $jour === $aujourd ? 'Aujourd\'hui' : esc_html( ucfirst( HS_Donnees::date_fr( $jour, 'l j F' ) ) ); ?></h2>
		<?php foreach ( $liste as $t ) : ?>
			<?php
			$bien  = (int) HS_Donnees::m( $t->ID, 'bien_id' );
			$lat   = (float) HS_Donnees::m( $bien, 'latitude' );
			$lng   = (float) HS_Donnees::m( $bien, 'longitude' );
			$etape = HS_Donnees::m( $t->ID, 'etape' );
			$cat   = HS_Donnees::m( $t->ID, 'categorie' );
			?>
			<article class="es-carte es-inter<?php echo HS_Donnees::m( $t->ID, 'urgent' ) ? ' es-inter--urgent' : ''; ?>">
				<div class="es-carte__corps">
					<div class="es-ligne">
						<span class="es-fort"><?php echo esc_html( HS_Donnees::m( $t->ID, 'creneau' ) ? HS_Donnees::m( $t->ID, 'creneau' ) : 'Dans la journée' ); ?></span>
						<?php if ( HS_Donnees::m( $t->ID, 'urgent' ) ) : ?>
							<span class="es-puce es-puce--danger">Urgent</span>
						<?php elseif ( $cat && isset( $cats[ $cat ] ) ) : ?>
							<span class="es-puce"><?php echo esc_html( $cats[ $cat ] ); ?></span>
						<?php endif; ?>
					</div>
					<h3 class="es-h3"><?php echo esc_html( $t->post_title ); ?></h3>
					<p class="es-doux"><?php echo esc_html( get_the_title( $bien ) . ( HS_Donnees::m( $bien, 'ville' ) ? ', ' . HS_Donnees::m( $bien, 'ville' ) : '' ) ); ?></p>
					<?php if ( $t->post_content ) : ?><p class="es-doux es-petit"><?php echo esc_html( wp_trim_words( $t->post_content, 30 ) ); ?></p><?php endif; ?>
					<?php if ( 'intervention' === $etape ) : ?><p class="es-puce es-puce--alerte">En cours</p><?php endif; ?>
					<div class="es-boutons es-boutons--3">
						<?php if ( $lat && $lng ) : ?><a class="es-btn es-btn--contour" href="<?php echo esc_url( sprintf( 'https://www.google.com/maps/dir/?api=1&destination=%F,%F', $lat, $lng ) ); ?>" target="_blank" rel="noopener">Itinéraire</a><?php endif; ?>
						<?php if ( $tel ) : ?><a class="es-btn es-btn--contour" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $tel ) ); ?>">Appeler</a><?php endif; ?>
						<?php if ( 'valide' === $etape ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="hs_ticket_etape"><input type="hidden" name="ticket" value="<?php echo (int) $t->ID; ?>"><input type="hidden" name="etape" value="intervention">
								<?php wp_nonce_field( 'hs_ticket_etape' ); ?>
								<button type="submit" class="es-btn"<?php echo $ctx['apercu'] ? ' disabled' : ''; ?>>Démarrer</button>
							</form>
						<?php endif; ?>
					</div>
					<?php if ( 'intervention' === $etape ) : ?>
						<form class="es-form es-form--fin" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="hs_ticket_etape"><input type="hidden" name="ticket" value="<?php echo (int) $t->ID; ?>"><input type="hidden" name="etape" value="cloture">
							<?php wp_nonce_field( 'hs_ticket_etape' ); ?>
							<div class="es-form__ligne"><label for="es-cout-<?php echo (int) $t->ID; ?>">Coût des pièces (FCFA)</label><input id="es-cout-<?php echo (int) $t->ID; ?>" type="number" name="cout" min="0" step="500" inputmode="numeric"></div>
							<div class="es-form__ligne"><label for="es-note-<?php echo (int) $t->ID; ?>">Ce qui a été fait</label><textarea id="es-note-<?php echo (int) $t->ID; ?>" name="note" rows="2"></textarea></div>
							<button type="submit" class="es-btn es-btn--plein"<?php echo $ctx['apercu'] ? ' disabled' : ''; ?>>Terminer l'intervention</button>
						</form>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach; ?>
	<?php endforeach; ?>
</div>

<p class="es-astuce">Après chaque intervention, indiquez le coût des pièces puis touchez « Terminer » : le gestionnaire est prévenu et le coût est noté sur le bien.</p>
