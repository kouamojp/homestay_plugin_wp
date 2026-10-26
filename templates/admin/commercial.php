<?php
/**
 * Vue Commercial : pipeline CRM et relances.
 *
 * @var string $mois Y-m
 */
defined( 'ABSPATH' ) || exit;

$k        = HS_Donnees::crm_indicateurs();
$colonnes = HS_Donnees::contacts_par_etape();
$relances = HS_Donnees::relances_du_jour();
$types    = HS_Fields::for_type( 'hs_contact' )['type_contact']['options'];
$filtre   = isset( $_GET['type'] ) && isset( $types[ wp_unslash( $_GET['type'] ) ] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$aujourd  = HS_Donnees::aujourdhui();
?>
<div class="tb-actions">
	<form method="get" class="tb-filtre">
		<input type="hidden" name="page" value="<?php echo esc_attr( HS_CPT::MENU ); ?>">
		<input type="hidden" name="vue" value="commercial">
		<label for="tb-type">Type</label>
		<select id="tb-type" name="type" onchange="this.form.submit()">
			<option value="">Tous</option>
			<?php foreach ( $types as $v => $label ) : ?>
				<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $filtre, $v ); ?>><?php echo esc_html( $label ); ?>s</option>
			<?php endforeach; ?>
		</select>
		<noscript><button type="submit" class="tb-btn tb-btn--contour">Filtrer</button></noscript>
	</form>
	<a class="tb-btn" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=hs_contact' ) ); ?>">Ajouter un contact</a>
</div>

<section class="tb-bande" aria-label="Indicateurs de la semaine">
	<div><p class="tb-label">Nouvelles demandes cette semaine</p><p class="tb-valeur"><?php echo (int) $k['nouveaux']; ?></p></div>
	<div><p class="tb-label">Rendez-vous cette semaine</p><p class="tb-valeur"><?php echo (int) $k['rdv']; ?></p></div>
	<div><p class="tb-label">Demandes devenues contrat, sur 3 mois</p><p class="tb-valeur"><?php echo null === $k['conversion'] ? '—' : (int) $k['conversion'] . ' %'; ?></p></div>
	<div><p class="tb-label">Relances en retard</p><p class="tb-valeur<?php echo $k['retard'] ? ' tb-valeur--danger' : ''; ?>"><?php echo (int) $k['retard']; ?></p></div>
</section>

<section class="tb-pipeline" aria-label="Pipeline commercial">
	<?php foreach ( $colonnes as $etape => $col ) : ?>
		<?php
		$contacts = $filtre ? array_filter( $col['contacts'], function ( $c ) use ( $filtre ) { return HS_Donnees::m( $c->ID, 'type_contact' ) === $filtre; } ) : $col['contacts'];
		?>
		<div class="tb-colonne<?php echo 'signe' === $etape ? ' tb-colonne--signe' : ''; ?>">
			<h3><?php echo esc_html( $col['label'] ); ?> <span><?php echo (int) count( $contacts ); ?></span></h3>
			<?php foreach ( $contacts as $c ) : ?>
				<?php
				$type    = HS_Donnees::m( $c->ID, 'type_contact' );
				$relance = HS_Donnees::m( $c->ID, 'relance' );
				?>
				<a class="tb-fiche" href="<?php echo esc_url( get_edit_post_link( $c->ID ) ); ?>">
					<strong><?php echo esc_html( $c->post_title ); ?></strong>
					<?php if ( $type ) : ?><span class="tb-puce tb-type--<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $types[ $type ] ); ?></span><?php endif; ?>
					<?php if ( HS_Donnees::m( $c->ID, 'besoin' ) ) : ?><span class="tb-fiche__txt"><?php echo esc_html( wp_trim_words( HS_Donnees::m( $c->ID, 'besoin' ), 16 ) ); ?></span><?php endif; ?>
					<?php if ( 'rdv' === $etape && HS_Donnees::m( $c->ID, 'rdv' ) ) : ?>
						<span class="tb-fiche__txt">Rendez-vous le <?php echo esc_html( HS_Donnees::date_fr( HS_Donnees::m( $c->ID, 'rdv' ), 'j M' ) . ( HS_Donnees::m( $c->ID, 'rdv_heure' ) ? ' à ' . HS_Donnees::m( $c->ID, 'rdv_heure' ) : '' ) ); ?></span>
					<?php elseif ( $relance && 'signe' !== $etape ) : ?>
						<span class="tb-fiche__txt<?php echo $relance < $aujourd ? ' tb-baisse' : ''; ?>">Relance : <?php echo esc_html( HS_Donnees::date_fr( $relance, 'j M' ) ); ?></span>
					<?php elseif ( HS_Donnees::m( $c->ID, 'source' ) ) : ?>
						<span class="tb-fiche__txt"><?php echo esc_html( HS_Donnees::m( $c->ID, 'source' ) ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
			<?php if ( ! $contacts ) : ?><p class="tb-colonne__vide">Aucun contact</p><?php endif; ?>
		</div>
	<?php endforeach; ?>
</section>

<section class="tb-carte">
	<h2 class="tb-h2">Relances du jour</h2>
	<?php if ( $relances ) : ?>
		<ul class="tb-relances">
			<?php foreach ( $relances as $c ) : ?>
				<?php
				$tel  = HS_Donnees::m( $c->ID, 'telephone' );
				$type = HS_Donnees::m( $c->ID, 'type_contact' );
				$wa   = HS_Donnees::whatsapp( $tel, 'Bonjour ' . $c->post_title . ', ' );
				?>
				<li>
					<span><strong><?php echo esc_html( $c->post_title ); ?></strong><?php echo $type ? ', ' . esc_html( mb_strtolower( $types[ $type ] ) ) : ''; ?> : <?php echo esc_html( HS_Donnees::m( $c->ID, 'action' ) ? HS_Donnees::m( $c->ID, 'action' ) : 'à relancer' ); ?></span>
					<span class="tb-boutons">
						<?php if ( $wa ) : ?><a class="tb-btn tb-btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
						<?php if ( $tel ) : ?><a class="tb-btn tb-btn--contour" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $tel ) ); ?>">Appeler</a><?php endif; ?>
						<?php if ( HS_Donnees::m( $c->ID, 'email' ) ) : ?><a class="tb-btn tb-btn--contour" href="<?php echo esc_url( 'mailto:' . HS_Donnees::m( $c->ID, 'email' ) ); ?>">Email</a><?php endif; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p class="tb-vide">Aucune relance prévue aujourd'hui.</p>
	<?php endif; ?>
</section>
