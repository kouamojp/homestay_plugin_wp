<?php
/**
 * En-tête du tableau de bord : salutation, date, onglets des vues autorisées.
 *
 * @var string[] $vues
 * @var string   $vue
 */
defined( 'ABSPATH' ) || exit;

$user = wp_get_current_user();
$ok   = isset( $_GET['hs_ok'] ) ? sanitize_key( wp_unslash( $_GET['hs_ok'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
?>
<header class="tb-entete">
	<div>
		<p class="tb-date"><?php echo esc_html( ucfirst( date_i18n( 'l j F Y' ) ) ); ?></p>
		<h1 class="tb-titre">Bonjour <?php echo esc_html( $user->first_name ? $user->first_name : $user->display_name ); ?></h1>
	</div>
	<?php if ( count( $vues ) > 1 ) : ?>
		<nav class="tb-onglets" aria-label="Vues du tableau de bord">
			<?php foreach ( $vues as $v ) : ?>
				<a href="<?php echo esc_url( HS_Tableau::url( $v ) ); ?>"<?php echo $v === $vue ? ' aria-current="page"' : ''; ?>><?php echo esc_html( HS_Tableau::VUES[ $v ] ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
</header>
<?php if ( 0 === strpos( $ok, 'reversements-' ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( '%d reversement(s) enregistré(s). Les propriétaires les voient dans leur espace.', (int) substr( $ok, 13 ) ) ); ?></p></div>
<?php endif; ?>
