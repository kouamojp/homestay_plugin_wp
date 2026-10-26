<?php
/**
 * Document imprimable (quittance, relevé, facture, dividendes). Page autonome, sans le thème.
 * Surchargeable : <thème>/homystay/espace/document.php
 *
 * @var array $doc ['titre','periode','qui','bien','lignes'=>[[libellé, montant]], 'total'?, 'texte']
 */
defined( 'ABSPATH' ) || exit;

$total = isset( $doc['total'] ) ? $doc['total'] : array( 'Total', array_sum( array_column( $doc['lignes'], 1 ) ) );
$tel   = HS_Settings::get( 'telephone' );
$mail  = HS_Settings::get( 'email' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html( $doc['titre'] . ' : ' . $doc['periode'] ); ?></title>
<style>
	body { margin: 0; background: #eef3ef; color: #33413c; font: 15px/1.55 Georgia, "Times New Roman", serif; }
	.feuille { max-width: 760px; margin: 32px auto; background: #fff; padding: 56px 64px; box-shadow: 0 6px 30px rgba(23,57,46,.12); }
	header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #17392e; padding-bottom: 20px; }
	.marque { font: 700 20px/1.2 system-ui, sans-serif; color: #17392e; }
	.coord { font: 13px/1.5 system-ui, sans-serif; color: #5d6b66; text-align: right; }
	h1 { margin: 36px 0 4px; font-size: 28px; font-weight: 400; color: #17392e; }
	.periode { margin: 0; color: #5d6b66; font-family: system-ui, sans-serif; }
	.parties { display: flex; gap: 40px; margin: 28px 0; font-family: system-ui, sans-serif; font-size: 14px; }
	.parties span { display: block; color: #5d6b66; font-size: 12px; }
	table { width: 100%; border-collapse: collapse; font-family: system-ui, sans-serif; font-size: 14px; }
	td { padding: 10px 0; border-bottom: 1px solid #e6ece8; }
	td.n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
	tr.total td { border-bottom: 0; border-top: 2px solid #17392e; font-weight: 700; font-size: 16px; padding-top: 14px; }
	.texte { margin-top: 28px; }
	footer { margin-top: 48px; font: 12px/1.5 system-ui, sans-serif; color: #5d6b66; }
	.imprimer { display: block; max-width: 760px; margin: 0 auto 32px; text-align: right; }
	.imprimer button { font: 600 15px system-ui, sans-serif; padding: 12px 20px; border: 0; border-radius: 8px; background: #17392e; color: #fff; cursor: pointer; }
	@media print { body { background: #fff; } .feuille { box-shadow: none; margin: 0; padding: 0; max-width: none; } .imprimer { display: none; } }
	@media (max-width: 640px) { .feuille { padding: 28px 20px; margin: 0; } header, .parties { flex-direction: column; gap: 12px; } .coord { text-align: left; } }
</style>
</head>
<body>
<main class="feuille">
	<header>
		<div class="marque">HomyStay Capital</div>
		<div class="coord"><?php echo implode( '<br>', array_map( 'esc_html', array_filter( array( $tel, $mail ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
	</header>
	<h1><?php echo esc_html( $doc['titre'] ); ?></h1>
	<p class="periode"><?php echo esc_html( ucfirst( $doc['periode'] ) ); ?></p>
	<div class="parties">
		<div><span>Établi pour</span><?php echo esc_html( $doc['qui'] ); ?></div>
		<?php if ( $doc['bien'] ) : ?><div><span>Logement</span><?php echo esc_html( $doc['bien'] ); ?></div><?php endif; ?>
		<div><span>Date d'édition</span><?php echo esc_html( date_i18n( 'j F Y' ) ); ?></div>
	</div>
	<table>
		<tbody>
			<?php foreach ( $doc['lignes'] as $l ) : ?>
				<tr><td><?php echo esc_html( $l[0] ); ?></td><td class="n"><?php echo esc_html( HS_Donnees::fcfa( $l[1] ) ); ?></td></tr>
			<?php endforeach; ?>
			<tr class="total"><td><?php echo esc_html( $total[0] ); ?></td><td class="n"><?php echo esc_html( HS_Donnees::fcfa( $total[1] ) ); ?></td></tr>
		</tbody>
	</table>
	<?php if ( $doc['texte'] ) : ?><p class="texte"><?php echo esc_html( $doc['texte'] ); ?></p><?php endif; ?>
	<footer>Document édité depuis votre espace client HomyStay Capital.</footer>
</main>
<p class="imprimer"><button type="button" onclick="window.print()">Imprimer ou enregistrer en PDF</button></p>
</body>
</html>
