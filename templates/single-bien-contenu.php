<?php
/**
 * Contenu de la page d'un bien. Surchargeable : <thème>/homystay/single-bien-contenu.php
 *
 * @var int           $bien_id
 * @var string        $statut, $statut_label, $type, $reference, $lieu, $ville
 * @var int[]         $photos       ID des images (mise en avant d'abord)
 * @var array         $offres       [courte|longue|vente => [label, prix, unite]]
 * @var array         $chiffres     [libellé => valeur]
 * @var string[]      $equipements
 * @var string        $video, $visite, $lat, $lng
 * @var bool          $reservable
 * @var string        $whatsapp, $telephone, $email
 * @var WP_Query|null $similaires
 */
defined( 'ABSPATH' ) || exit;

$nb_photos  = count( $photos );
$lat        = (float) $lat;
$lng        = (float) $lng;
$liste      = HS_CPT::url_liste( 'hs_bien' );
$titre      = get_the_title();
$sujet_mail = rawurlencode( 'Demande d\'information : ' . $titre . ( $reference ? ' (réf. ' . $reference . ')' : '' ) );

// Données de la lightbox : une entrée par photo (grande taille, srcset, légende, texte alternatif).
$lightbox = array();
foreach ( $photos as $att ) {
	$full = wp_get_attachment_image_src( $att, 'full' );
	if ( ! $full ) {
		continue;
	}
	$thumb      = wp_get_attachment_image_src( $att, 'thumbnail' );
	$alt        = trim( (string) get_post_meta( $att, '_wp_attachment_image_alt', true ) );
	$lightbox[] = array(
		'src'     => $full[0],
		'w'       => (int) $full[1],
		'h'       => (int) $full[2],
		'srcset'  => (string) wp_get_attachment_image_srcset( $att, 'full' ),
		'thumb'   => $thumb ? $thumb[0] : $full[0],
		'alt'     => $alt ? $alt : $titre,
		'legende' => (string) wp_get_attachment_caption( $att ),
	);
}

$icone = array(
	'Chambres'       => '<path d="M3 18v-6.5A1.5 1.5 0 0 1 4.5 10h15a1.5 1.5 0 0 1 1.5 1.5V18M3 15h18M6 10V7.5A1.5 1.5 0 0 1 7.5 6h9A1.5 1.5 0 0 1 18 7.5V10"/>',
	'Salles de bain' => '<path d="M4 12h16v2.5a4.5 4.5 0 0 1-4.5 4.5h-7A4.5 4.5 0 0 1 4 14.5zM6 12V6a2 2 0 0 1 3.7-1M7 19l-1 2M17 19l1 2"/>',
	'Surface'        => '<path d="M4 4h16v16H4zM4 9h5V4M15 20v-5h5"/>',
	'Capacité'       => '<path d="M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM2.5 20a6.5 6.5 0 0 1 13 0M16 4.3a3.5 3.5 0 0 1 0 6.4M21.5 20a6.5 6.5 0 0 0-3.5-5.8"/>',
);
?>
<main id="hs-bien" class="hs-bien">

	<nav class="hs-bien__fil" aria-label="Fil d'Ariane">
		<ol>
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil</a></li>
			<li><a href="<?php echo esc_url( $liste ); ?>">Biens</a></li>
			<?php if ( $ville ) : ?>
				<li><a href="<?php echo esc_url( add_query_arg( 'hs_ville', $ville, $liste ) ); ?>"><?php echo esc_html( $ville ); ?></a></li>
			<?php endif; ?>
			<li aria-current="page"><?php echo esc_html( $titre ); ?></li>
		</ol>
	</nav>

	<section class="hs-bien__hero<?php echo $nb_photos ? '' : ' hs-bien__hero--sans-photo'; ?>">
		<?php if ( $nb_photos ) : ?>
			<div class="hs-mosaique hs-mosaique--<?php echo (int) min( $nb_photos, 5 ); ?>">
				<?php foreach ( array_slice( $photos, 0, 5 ) as $i => $att ) : ?>
					<button type="button" class="hs-mosaique__item" data-hs-ouvrir="<?php echo (int) $i; ?>">
						<?php echo wp_get_attachment_image( $att, 0 === $i ? 'large' : 'medium_large', false, array( 'loading' => 0 === $i ? 'eager' : 'lazy', 'alt' => '' ) ); ?>
						<span class="hs-sr">Agrandir la photo <?php echo (int) ( $i + 1 ); ?> sur <?php echo (int) $nb_photos; ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<?php if ( $nb_photos > 1 ) : ?>
				<button type="button" class="hs-bien__toutes" data-hs-ouvrir="0">
					<svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg>
					<?php echo esc_html( sprintf( 'Voir les %d photos', $nb_photos ) ); ?>
				</button>
			<?php endif; ?>
		<?php endif; ?>

		<header class="hs-plaque">
			<?php if ( $type ) : ?><p class="hs-plaque__type"><?php echo esc_html( $type ); ?></p><?php endif; ?>
			<h1 class="hs-plaque__titre"><?php echo esc_html( $titre ); ?></h1>
			<?php if ( $lieu ) : ?>
				<p class="hs-plaque__lieu">
					<svg aria-hidden="true" viewBox="0 0 24 24" width="16" height="16"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
					<?php echo esc_html( $lieu ); ?>
				</p>
			<?php endif; ?>
		</header>
	</section>

	<div class="hs-bien__statut">
		<span class="hs-pastille hs-pastille--<?php echo esc_attr( $statut ); ?>"><?php echo esc_html( $statut_label ); ?></span>
		<?php if ( $reference ) : ?><span class="hs-bien__ref">Référence <?php echo esc_html( $reference ); ?></span><?php endif; ?>
	</div>

	<?php if ( $chiffres ) : ?>
		<ul class="hs-chiffres" aria-label="Caractéristiques">
			<?php foreach ( $chiffres as $label => $val ) : ?>
				<li>
					<?php if ( isset( $icone[ $label ] ) ) : ?>
						<svg aria-hidden="true" viewBox="0 0 24 24" width="22" height="22"><?php echo $icone[ $label ]; // phpcs:ignore WordPress.Security.EscapeOutput -- SVG statique ?></svg>
					<?php endif; ?>
					<span class="hs-chiffres__valeur"><?php echo esc_html( $val ); ?></span>
					<span class="hs-chiffres__label"><?php echo esc_html( mb_strtolower( $label ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<div class="hs-bien__colonnes">

		<div class="hs-bien__principal">
			<?php
			// the_content() est TOUJOURS appelé : Elementor en a besoin pour afficher un bien construit avec lui.
			ob_start();
			the_content();
			$description = trim( ob_get_clean() );
			?>
			<section class="hs-section<?php echo '' === $description ? ' hs-section--vide' : ''; ?>">
				<?php if ( '' !== $description ) : ?><h2 class="hs-section__titre">Le logement</h2><?php endif; ?>
				<div class="hs-bien__description"><?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput -- sortie de the_content ?></div>
			</section>

			<?php if ( $equipements ) : ?>
				<section class="hs-section">
					<h2 class="hs-section__titre">Équipements</h2>
					<ul class="hs-equipements">
						<?php foreach ( $equipements as $eq ) : ?><li><?php echo esc_html( $eq ); ?></li><?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<?php if ( $video || $visite ) : ?>
				<section class="hs-section">
					<h2 class="hs-section__titre">Visiter à distance</h2>
					<?php if ( $video ) : ?>
						<div class="hs-bien__video"><?php echo wp_oembed_get( esc_url( $video ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<?php endif; ?>
					<?php if ( $visite ) : ?>
						<p><a class="hs-lien-fort" href="<?php echo esc_url( $visite ); ?>" target="_blank" rel="noopener">Lancer la visite virtuelle<span class="hs-sr"> (nouvel onglet)</span></a></p>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php if ( $lat && $lng ) : ?>
				<section class="hs-section">
					<h2 class="hs-section__titre">Où se trouve le logement</h2>
					<?php if ( $lieu ) : ?><p class="hs-section__intro"><?php echo esc_html( $lieu ); ?></p><?php endif; ?>
					<div class="hs-bien__carte">
						<iframe title="Carte : <?php echo esc_attr( $lieu ? $lieu : $titre ); ?>" loading="lazy" src="<?php echo esc_url( sprintf( 'https://www.openstreetmap.org/export/embed.html?bbox=%1$F,%2$F,%3$F,%4$F&marker=%5$F,%6$F', $lng - 0.012, $lat - 0.007, $lng + 0.012, $lat + 0.007, $lat, $lng ) ); ?>"></iframe>
					</div>
					<p><a class="hs-lien-fort" href="<?php echo esc_url( sprintf( 'https://www.google.com/maps/search/?api=1&query=%F,%F', $lat, $lng ) ); ?>" target="_blank" rel="noopener">Itinéraire dans Google Maps<span class="hs-sr"> (nouvel onglet)</span></a></p>
				</section>
			<?php endif; ?>
		</div>

		<aside class="hs-bien__lateral" aria-label="Prix et contact">
			<div class="hs-encart">
				<?php if ( $offres ) : ?>
					<dl class="hs-encart__offres">
						<?php foreach ( $offres as $o ) : ?>
							<div>
								<dt><?php echo esc_html( $o['label'] ); ?></dt>
								<dd><span class="hs-encart__prix"><?php echo esc_html( $o['prix'] ); ?></span><?php if ( $o['unite'] ) : ?> <span class="hs-encart__unite"><?php echo esc_html( $o['unite'] ); ?></span><?php endif; ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				<?php else : ?>
					<p class="hs-encart__prix hs-encart__prix--seul">Prix sur demande</p>
				<?php endif; ?>

				<?php if ( ! $reservable ) : ?>
					<p class="hs-encart__alerte">Ce bien n'est pas libre en ce moment. Écrivez-nous : nous vous proposerons un logement proche.</p>
				<?php endif; ?>

				<div class="hs-encart__actions">
					<?php if ( $whatsapp ) : ?>
						<a class="hs-btn hs-btn--whatsapp" href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener">
							<svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" stroke="none" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.7a2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .2-1.3c-.1-.1-.3-.2-.5-.3z"/></svg>
							Écrire sur WhatsApp
						</a>
					<?php endif; ?>
					<?php if ( $telephone ) : ?>
						<a class="hs-btn hs-btn--contour" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $telephone ) ); ?>">Appeler le <?php echo esc_html( $telephone ); ?></a>
					<?php endif; ?>
					<?php if ( $email ) : ?>
						<a class="hs-encart__email" href="<?php echo esc_url( 'mailto:' . $email . '?subject=' . $sujet_mail ); ?>">Poser une question par email</a>
					<?php endif; ?>
				</div>
				<?php if ( $reference ) : ?>
					<p class="hs-encart__note">Indiquez la référence <strong><?php echo esc_html( $reference ); ?></strong> : nous retrouvons le logement tout de suite.</p>
				<?php endif; ?>
			</div>
		</aside>
	</div>

	<?php if ( $similaires ) : ?>
		<section class="hs-bien__similaires">
			<h2 class="hs-section__titre">Dans le même secteur, à <?php echo esc_html( $ville ); ?></h2>
			<?php
			echo HS_Templates::render( 'liste-biens.php', array( // phpcs:ignore WordPress.Security.EscapeOutput
				'query' => $similaires, 'colonnes' => 3, 'formule' => '', 'vide' => '', 'pagination' => false,
			) );
			?>
		</section>
	<?php endif; ?>

	<?php if ( $lightbox ) : ?>
		<dialog class="hs-lightbox" aria-label="Photos : <?php echo esc_attr( $titre ); ?>" data-hs-lightbox>
			<div class="hs-lightbox__barre">
				<p class="hs-lightbox__compteur" aria-live="polite"><span data-hs-compteur>1</span> / <?php echo (int) count( $lightbox ); ?></p>
				<button type="button" class="hs-lightbox__fermer" data-hs-fermer aria-label="Fermer les photos">
					<svg aria-hidden="true" viewBox="0 0 24 24" width="22" height="22"><path d="M6 6l12 12M18 6 6 18"/></svg>
				</button>
			</div>
			<figure class="hs-lightbox__scene">
				<img data-hs-image src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="" decoding="async">
				<figcaption data-hs-legende></figcaption>
			</figure>
			<?php if ( count( $lightbox ) > 1 ) : ?>
				<button type="button" class="hs-lightbox__nav hs-lightbox__nav--prec" data-hs-prec aria-label="Photo précédente">
					<svg aria-hidden="true" viewBox="0 0 24 24" width="26" height="26"><path d="M15 5l-7 7 7 7"/></svg>
				</button>
				<button type="button" class="hs-lightbox__nav hs-lightbox__nav--suiv" data-hs-suiv aria-label="Photo suivante">
					<svg aria-hidden="true" viewBox="0 0 24 24" width="26" height="26"><path d="M9 5l7 7-7 7"/></svg>
				</button>
				<div class="hs-lightbox__vignettes" data-hs-vignettes></div>
			<?php endif; ?>
			<script type="application/json" data-hs-photos><?php echo wp_json_encode( $lightbox, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ); ?></script>
		</dialog>
	<?php endif; ?>
</main>
