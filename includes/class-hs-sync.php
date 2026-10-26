<?php
/**
 * Synchronisation entre deux sites (ex. local → staging), limitée aux données HomyStay.
 *
 * Export : un .zip contenant data.json (fiches hs_*, leurs champs, comptes clients, tables wp_hs_*,
 * réglages) et les photos des biens (image mise en avant + galerie).
 * Import : recrée ou met à jour chaque fiche d'après son identifiant unique (_hs_uid), attribue
 * de nouveaux numéros et recâble tous les liens (bien d'un contrat, propriétaire d'une écriture,
 * compte d'une réservation, photos d'un bien…). Les pages, Elementor, le thème et les autres extensions
 * ne sont pas touchés.
 *
 * Sens unique conseillé : le site qui exporte est la référence.
 */

defined( 'ABSPATH' ) || exit;

class HS_Sync {

	const FORMAT  = 'homystay-sync';
	const VERSION = 1;
	const PAGE    = 'homystay-sync';

	/** Tables du plugin, dans l'ordre d'import (les suivantes renvoient vers les précédentes). */
	const TABLES = array( 'hs_ecritures', 'hs_echeances', 'hs_investissements', 'hs_distributions' );

	/** Colonnes des tables qui contiennent un numéro de fiche (post) ou de compte (user). */
	const COLONNES = array(
		'hs_ecritures'       => array( 'post' => array( 'bien_id', 'contrat_id', 'projet_id', 'proprietaire_id' ), 'user' => array( 'created_by' ) ),
		'hs_echeances'       => array( 'post' => array( 'contrat_id', 'bien_id' ), 'user' => array() ),
		'hs_investissements' => array( 'post' => array( 'projet_id', 'investisseur_id' ), 'user' => array( 'user_id' ) ),
		'hs_distributions'   => array( 'post' => array(), 'user' => array() ),
	);

	/** Champs qui contiennent un numéro de compte utilisateur. */
	const CHAMPS_USER = array( 'hs_user_id', 'hs_technicien_id' );

	/** Rôles transférés (jamais administrateur). */
	const ROLES = array( 'hs_gestionnaire', 'hs_comptable', 'hs_commercial', 'hs_technicien', 'hs_voyageur', 'hs_proprietaire', 'hs_locataire', 'hs_investisseur' );

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 21 );
		add_action( 'admin_post_hs_sync_export', array( __CLASS__, 'exporter' ) );
		add_action( 'admin_post_hs_sync_import', array( __CLASS__, 'importer' ) );
	}

	public static function menu() {
		add_submenu_page( HS_CPT::MENU, 'Synchronisation HomyStay', 'Synchronisation', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
	}

	/* ---------- Écran ---------- */

	public static function page() {
		$rapport = get_transient( 'hs_sync_rapport_' . get_current_user_id() );
		if ( $rapport ) {
			delete_transient( 'hs_sync_rapport_' . get_current_user_id() );
		}
		$max = size_format( wp_max_upload_size() );
		?>
		<div class="wrap">
			<h1>Synchronisation HomyStay</h1>
			<p style="max-width:760px">Copie <strong>uniquement les données HomyStay</strong> d'un site à l'autre (par exemple de votre site local vers le staging) : fiches (biens, propriétaires, locataires, contrats, réservations, tickets, assurance, CRM, investisseurs, projets), photos des biens, comptes clients, tables financières et, si vous le choisissez, les réglages. Les pages, Elementor, le thème et les autres extensions ne sont pas touchés.</p>
			<p style="max-width:760px">Le site qui <strong>exporte</strong> fait référence : exportez ici, puis importez le fichier dans <em>HomyStay Capital › Synchronisation</em> de l'autre site. Les deux sites doivent avoir la même version du plugin (<?php echo esc_html( HS_VERSION ); ?>).</p>

			<?php if ( $rapport ) : ?>
				<div class="notice notice-<?php echo empty( $rapport['erreur'] ) ? 'success' : 'error'; ?>"><p><strong><?php echo esc_html( $rapport['titre'] ); ?></strong></p>
					<?php if ( ! empty( $rapport['lignes'] ) ) : ?><ul style="list-style:disc;margin-left:20px"><?php foreach ( $rapport['lignes'] as $l ) : ?><li><?php echo esc_html( $l ); ?></li><?php endforeach; ?></ul><?php endif; ?>
				</div>
			<?php endif; ?>

			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:24px;max-width:1100px;margin-top:16px">
				<div class="card" style="max-width:none;margin:0">
					<h2>1. Exporter depuis ce site</h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="hs_sync_export">
						<?php wp_nonce_field( 'hs_sync_export' ); ?>
						<p><label><input type="checkbox" name="photos" value="1" checked> Inclure les photos des biens</label></p>
						<p><label><input type="checkbox" name="demo" value="1"> Inclure les données de test</label></p>
						<p class="description">Les mots de passe des comptes ne sont jamais exportés.</p>
						<?php submit_button( 'Télécharger le fichier d\'export', 'primary', 'submit', false ); ?>
					</form>
				</div>

				<div class="card" style="max-width:none;margin:0">
					<h2>2. Importer sur ce site</h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<input type="hidden" name="action" value="hs_sync_import">
						<?php wp_nonce_field( 'hs_sync_import' ); ?>
						<p><input type="file" name="fichier" accept=".zip,application/zip" required></p>
						<p class="description">Taille maximale acceptée par ce serveur : <?php echo esc_html( $max ); ?>. Si le fichier est plus gros, exportez sans les photos, ou augmentez <code>upload_max_filesize</code> et <code>post_max_size</code>.</p>
						<p><label><input type="checkbox" name="tables" value="1" checked> Remplacer les tables financières de ce site (écritures, loyers, souscriptions, dividendes)</label></p>
						<p><label><input type="checkbox" name="reglages" value="1"> Importer aussi les réglages (WhatsApp, numéros Orange Money / MTN MoMo, acompte…)</label></p>
						<p><label><input type="checkbox" name="miroir" value="1"> Supprimer sur ce site les fiches déjà importées qui ne sont plus dans le fichier</label></p>
						<p class="description">Une fiche déjà importée est mise à jour, jamais dupliquée. Les fiches créées directement sur ce site sont conservées. Faites une sauvegarde de la base avant le premier import.</p>
						<?php submit_button( 'Importer', 'primary', 'submit', false, array( 'onclick' => "return confirm('Importer les données HomyStay de ce fichier sur ce site ?')" ) ); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/* ---------- Outils ---------- */

	public static function types() {
		return array_keys( HS_CPT::types() );
	}

	/** Champs (hs_…) qui pointent vers une autre fiche. */
	public static function champs_post() {
		$cles = array();
		foreach ( HS_Fields::all() as $champs ) {
			foreach ( $champs as $k => $def ) {
				if ( 'post' === $def['type'] ) {
					$cles[] = 'hs_' . $k;
				}
			}
		}
		return array_values( array_unique( $cles ) );
	}

	/** Identifiant unique d'une fiche ou d'une image, créé au premier export. */
	public static function uid( $post_id ) {
		$uid = get_post_meta( $post_id, '_hs_uid', true );
		if ( ! $uid ) {
			$uid = wp_generate_uuid4();
			update_post_meta( $post_id, '_hs_uid', $uid );
		}
		return $uid;
	}

	/** Lignes de test des tables (option hs_demo_lignes, clés sans préfixe « hs_ ») → [table hs_… => [id…]]. */
	private static function lignes_demo() {
		$l = array();
		foreach ( (array) get_option( 'hs_demo_lignes', array() ) as $t => $ids ) {
			$l[ 0 === strpos( $t, 'hs_' ) ? $t : 'hs_' . $t ] = array_map( 'intval', (array) $ids );
		}
		return $l;
	}

	private static function rapport( $titre, array $lignes = array(), $erreur = false ) {
		set_transient( 'hs_sync_rapport_' . get_current_user_id(), array( 'titre' => $titre, 'lignes' => $lignes, 'erreur' => $erreur ), 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	/* ---------- Export ---------- */

	public static function exporter() {
		check_admin_referer( 'hs_sync_export' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Action réservée aux administrateurs.' );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			self::rapport( 'Export impossible : l\'extension PHP « zip » n\'est pas activée sur ce serveur.', array(), true );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		global $wpdb;
		$photos = ! empty( $_POST['photos'] );
		$demo   = ! empty( $_POST['demo'] );

		$champs_post = self::champs_post();
		$posts       = array();
		$ids         = array();
		$images      = array();
		$users       = array();

		$liste = get_posts( array(
			'post_type'      => self::types(),
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		) );
		foreach ( $liste as $p ) {
			if ( ! $demo && get_post_meta( $p->ID, 'hs_demo', true ) ) {
				continue;
			}
			$ids[ $p->ID ] = true;
		}
		foreach ( $liste as $p ) {
			if ( ! isset( $ids[ $p->ID ] ) ) {
				continue;
			}
			$meta = array();
			foreach ( get_post_meta( $p->ID ) as $cle => $valeurs ) {
				if ( 0 === strpos( $cle, 'hs_' ) || '_thumbnail_id' === $cle ) {
					$meta[ $cle ] = maybe_unserialize( $valeurs[0] );
				}
			}
			if ( $photos ) {
				$imgs = array_filter( array_merge( array( (int) get_post_thumbnail_id( $p->ID ) ), array_map( 'absint', explode( ',', (string) get_post_meta( $p->ID, 'hs_galerie', true ) ) ) ) );
				foreach ( $imgs as $img ) {
					$images[ $img ] = true;
				}
			} else {
				unset( $meta['_thumbnail_id'], $meta['hs_galerie'] );
			}
			foreach ( self::CHAMPS_USER as $cle ) {
				if ( ! empty( $meta[ $cle ] ) ) {
					$users[ (int) $meta[ $cle ] ] = true;
				}
			}
			$posts[] = array(
				'id'      => $p->ID,
				'uid'     => self::uid( $p->ID ),
				'type'    => $p->post_type,
				'titre'   => $p->post_title,
				'contenu' => $p->post_content,
				'extrait' => $p->post_excerpt,
				'statut'  => $p->post_status,
				'slug'    => $p->post_name,
				'date'    => $p->post_date,
				'ordre'   => $p->menu_order,
				'meta'    => $meta,
			);
		}

		// Comptes : ceux qui ont un rôle HomyStay + ceux auxquels une fiche renvoie.
		foreach ( get_users( array( 'role__in' => self::ROLES, 'fields' => 'ID' ) ) as $uid ) {
			$users[ (int) $uid ] = true;
		}

		// Tables : les lignes de test sont écartées sans « données de test », et les lignes qui renvoient à une fiche non exportée.
		$tables     = array();
		$lignes_demo = $demo ? array() : self::lignes_demo();
		$garde      = array();
		foreach ( self::TABLES as $t ) {
			$tables[ $t ] = array();
			$exclues      = isset( $lignes_demo[ $t ] ) ? array_flip( array_map( 'intval', (array) $lignes_demo[ $t ] ) ) : array();
			foreach ( $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}{$t} ORDER BY id ASC", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
				if ( isset( $exclues[ (int) $r['id'] ] ) ) {
					continue;
				}
				$ok = true;
				foreach ( self::COLONNES[ $t ]['post'] as $col ) {
					if ( ! empty( $r[ $col ] ) && ! isset( $ids[ (int) $r[ $col ] ] ) ) {
						$ok = false;
					}
				}
				if ( 'hs_distributions' === $t && ! isset( $garde['hs_investissements'][ (int) $r['investissement_id'] ] ) ) {
					$ok = false;
				}
				if ( ! $ok ) {
					continue;
				}
				$garde[ $t ][ (int) $r['id'] ] = true;
				foreach ( self::COLONNES[ $t ]['user'] as $col ) {
					if ( ! empty( $r[ $col ] ) ) {
						$users[ (int) $r[ $col ] ] = true;
					}
				}
				$tables[ $t ][] = $r;
			}
		}

		$comptes = array();
		foreach ( array_keys( $users ) as $uid ) {
			$u = get_userdata( $uid );
			if ( ! $u ) {
				continue;
			}
			$comptes[] = array(
				'id'      => $u->ID,
				'login'   => $u->user_login,
				'email'   => $u->user_email,
				'nom'     => $u->display_name,
				'prenom'  => $u->first_name,
				'nom_famille' => $u->last_name,
				'roles'   => array_values( array_intersect( (array) $u->roles, self::ROLES ) ),
				'telephone' => get_user_meta( $u->ID, 'hs_telephone', true ),
			);
		}

		$dossier = wp_upload_dir();
		$medias  = array();
		foreach ( array_keys( $images ) as $att ) {
			$fichier = get_attached_file( $att );
			if ( ! $fichier || ! file_exists( $fichier ) ) {
				continue;
			}
			$medias[] = array(
				'id'      => $att,
				'uid'     => self::uid( $att ),
				'fichier' => 'fichiers/' . $att . '-' . wp_basename( $fichier ),
				'chemin'  => $fichier,
				'titre'   => get_the_title( $att ),
				'legende' => wp_get_attachment_caption( $att ),
				'alt'     => get_post_meta( $att, '_wp_attachment_image_alt', true ),
				'mime'    => get_post_mime_type( $att ),
			);
		}

		$data = array(
			'format'     => self::FORMAT,
			'version'    => self::VERSION,
			'plugin'     => HS_VERSION,
			'db_version' => HS_DB_VERSION,
			'site'       => home_url(),
			'date'       => current_time( 'mysql' ),
			'posts'      => $posts,
			'medias'     => array_map( function ( $m ) { unset( $m['chemin'] ); return $m; }, $medias ),
			'comptes'    => $comptes,
			'tables'     => $tables,
			'reglages'   => get_option( HS_Settings::OPTION, array() ),
			// Lignes de test des tables : gardées pour que « Supprimer les données de test » fonctionne aussi après import.
			'demo_lignes' => $demo ? self::lignes_demo() : array(),
			'demo_tableaux' => $demo ? (int) get_option( 'hs_demo_tableaux' ) : 0,
		);

		$tmp = wp_tempnam( 'homystay-sync' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			self::rapport( 'Export impossible : le fichier temporaire n\'a pas pu être créé.', array(), true );
		}
		$zip->addFromString( 'data.json', wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		foreach ( $medias as $m ) {
			$zip->addFile( $m['chemin'], $m['fichier'] );
		}
		$zip->close();

		$nom = 'homystay-sync-' . sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '-' . current_time( 'Ymd-Hi' ) . '.zip';
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $nom . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		exit;
	}

	/* ---------- Import ---------- */

	public static function importer() {
		check_admin_referer( 'hs_sync_import' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Action réservée aux administrateurs.' );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			self::rapport( 'Import impossible : l\'extension PHP « zip » n\'est pas activée sur ce serveur.', array(), true );
		}
		if ( empty( $_FILES['fichier']['tmp_name'] ) || ! empty( $_FILES['fichier']['error'] ) ) {
			$code = isset( $_FILES['fichier']['error'] ) ? (int) $_FILES['fichier']['error'] : 4;
			self::rapport( in_array( $code, array( 1, 2 ), true ) ? 'Le fichier dépasse la taille maximale acceptée par ce serveur.' : 'Aucun fichier reçu.', array(), true );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $_FILES['fichier']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			self::rapport( 'Ce fichier n\'est pas une archive .zip valide.', array(), true );
		}
		$data = json_decode( (string) $zip->getFromName( 'data.json' ), true );
		if ( ! is_array( $data ) || self::FORMAT !== ( $data['format'] ?? '' ) ) {
			$zip->close();
			self::rapport( 'Ce fichier n\'est pas un export HomyStay.', array(), true );
		}
		if ( (int) $data['version'] > self::VERSION || (string) $data['db_version'] !== HS_DB_VERSION ) {
			$zip->close();
			self::rapport( sprintf( 'Versions différentes : le fichier vient du plugin %s, ce site a la version %s. Mettez le plugin à jour des deux côtés.', $data['plugin'], HS_VERSION ), array(), true );
		}

		$r = self::appliquer( $data, $zip, array(
			'tables'   => ! empty( $_POST['tables'] ),
			'reglages' => ! empty( $_POST['reglages'] ),
			'miroir'   => ! empty( $_POST['miroir'] ),
		) );
		$zip->close();
		self::rapport( 'Import terminé depuis ' . $data['site'] . ' (export du ' . $data['date'] . ').', $r );
	}

	/**
	 * Applique un export : comptes, photos, fiches (2 passes), tables, réglages.
	 *
	 * @return string[] Lignes du compte rendu.
	 */
	public static function appliquer( array $data, $zip, array $options ) {
		global $wpdb;
		$map_user = array();
		$map_post = array();
		$cpt      = array( 'comptes_crees' => 0, 'comptes_relies' => 0, 'photos' => 0, 'photos_existantes' => 0, 'crees' => 0, 'maj' => 0, 'reprises' => 0, 'supprimees' => 0 );

		// 1. Comptes : retrouvés par email, sinon créés (mot de passe aléatoire, aucun email envoyé).
		foreach ( (array) $data['comptes'] as $c ) {
			$u = get_user_by( 'email', $c['email'] );
			if ( $u ) {
				$map_user[ (int) $c['id'] ] = $u->ID;
				foreach ( $c['roles'] as $role ) {
					if ( ! in_array( $role, (array) $u->roles, true ) && ! user_can( $u, 'manage_options' ) ) {
						$u->add_role( $role );
					}
				}
				$cpt['comptes_relies']++;
				continue;
			}
			if ( ! $c['roles'] ) {
				continue; // compte sans rôle HomyStay (administrateur…) : on ne le crée pas.
			}
			$login = sanitize_user( $c['login'], true );
			$base  = $login ? $login : 'client';
			$i     = 1;
			while ( ! $login || username_exists( $login ) ) {
				$login = $base . ( ++$i );
			}
			$id = wp_insert_user( array(
				'user_login'   => $login,
				'user_email'   => $c['email'],
				'user_pass'    => wp_generate_password( 24 ),
				'display_name' => $c['nom'],
				'first_name'   => $c['prenom'],
				'last_name'    => $c['nom_famille'],
				'role'         => array_shift( $c['roles'] ),
			) );
			if ( is_wp_error( $id ) ) {
				continue;
			}
			$u = get_userdata( $id );
			foreach ( $c['roles'] as $role ) {
				$u->add_role( $role );
			}
			if ( $c['telephone'] ) {
				update_user_meta( $id, 'hs_telephone', $c['telephone'] );
			}
			$map_user[ (int) $c['id'] ] = $id;
			$cpt['comptes_crees']++;
		}

		// 2. Photos : retrouvées par identifiant unique, sinon copiées dans la médiathèque.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		foreach ( (array) $data['medias'] as $m ) {
			$deja = self::par_uid( $m['uid'], 'attachment' );
			if ( $deja ) {
				$map_post[ (int) $m['id'] ] = $deja;
				$cpt['photos_existantes']++;
				continue;
			}
			$octets = $zip ? $zip->getFromName( $m['fichier'] ) : false;
			if ( false === $octets ) {
				continue;
			}
			$envoi = wp_upload_bits( preg_replace( '/^\d+-/', '', wp_basename( $m['fichier'] ) ), null, $octets );
			if ( ! empty( $envoi['error'] ) ) {
				continue;
			}
			$att = wp_insert_attachment( array(
				'post_mime_type' => $m['mime'],
				'post_title'     => $m['titre'],
				'post_excerpt'   => (string) $m['legende'],
				'post_status'    => 'inherit',
			), $envoi['file'] );
			if ( ! $att || is_wp_error( $att ) ) {
				continue;
			}
			wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $envoi['file'] ) );
			if ( $m['alt'] ) {
				update_post_meta( $att, '_wp_attachment_image_alt', $m['alt'] );
			}
			update_post_meta( $att, '_hs_uid', $m['uid'] );
			$map_post[ (int) $m['id'] ] = $att;
			$cpt['photos']++;
		}

		// 3. Fiches, passe 1 : création ou mise à jour (titre, contenu, statut…).
		$uids = array();
		foreach ( (array) $data['posts'] as $p ) {
			if ( ! in_array( $p['type'], self::types(), true ) ) {
				continue;
			}
			$uids[ $p['uid'] ] = true;
			$existe = self::par_uid( $p['uid'], $p['type'] );
			if ( ! $existe && $p['slug'] ) {
				// Premier import : une fiche de ce site sans identifiant, de même type et même slug, est la même fiche.
				$existe = self::par_slug( $p['slug'], $p['type'] );
				if ( $existe ) {
					update_post_meta( $existe, '_hs_uid', $p['uid'] );
					$cpt['reprises']++;
				}
			}
			$champs = array(
				'post_type'    => $p['type'],
				'post_title'   => wp_slash( $p['titre'] ),
				'post_content' => wp_slash( $p['contenu'] ),
				'post_excerpt' => wp_slash( $p['extrait'] ),
				'post_status'  => $p['statut'],
				'post_name'    => $p['slug'],
				'post_date'    => $p['date'],
				'menu_order'   => (int) $p['ordre'],
			);
			if ( $existe ) {
				$champs['ID'] = $existe;
				wp_update_post( $champs );
				$map_post[ (int) $p['id'] ] = $existe;
				$cpt['maj']++;
			} else {
				$champs['post_author'] = get_current_user_id();
				$id = wp_insert_post( $champs, true );
				if ( is_wp_error( $id ) ) {
					continue;
				}
				update_post_meta( $id, '_hs_uid', $p['uid'] );
				$map_post[ (int) $p['id'] ] = $id;
				$cpt['crees']++;
			}
		}

		// 3 bis. Passe 2 : champs, avec les numéros recâblés.
		$champs_post = self::champs_post();
		$post_de     = function ( $v ) use ( &$map_post ) { return isset( $map_post[ (int) $v ] ) ? $map_post[ (int) $v ] : 0; };
		$user_de     = function ( $v ) use ( &$map_user ) { return isset( $map_user[ (int) $v ] ) ? $map_user[ (int) $v ] : 0; };
		foreach ( (array) $data['posts'] as $p ) {
			if ( empty( $map_post[ (int) $p['id'] ] ) ) {
				continue;
			}
			$id   = $map_post[ (int) $p['id'] ];
			$meta = (array) $p['meta'];
			foreach ( $meta as $cle => $val ) {
				if ( in_array( $cle, $champs_post, true ) || '_thumbnail_id' === $cle ) {
					$val = $val ? $post_de( $val ) : 0;
				} elseif ( in_array( $cle, self::CHAMPS_USER, true ) ) {
					$val = $val ? $user_de( $val ) : 0;
				} elseif ( 'hs_galerie' === $cle ) {
					$val = implode( ',', array_filter( array_map( $post_de, array_filter( explode( ',', (string) $val ) ) ) ) );
				}
				if ( '_thumbnail_id' === $cle && ! $val ) {
					delete_post_meta( $id, '_thumbnail_id' );
					continue;
				}
				update_post_meta( $id, $cle, wp_slash( $val ) );
			}
			// Champs hs_ retirés sur le site source : retirés ici aussi.
			foreach ( array_keys( get_post_meta( $id ) ) as $cle ) {
				if ( 0 === strpos( $cle, 'hs_' ) && ! array_key_exists( $cle, $meta ) ) {
					delete_post_meta( $id, $cle );
				}
			}
		}

		// 4. Fiches importées autrefois et absentes du fichier (option « miroir »).
		if ( $options['miroir'] ) {
			$anciennes = get_posts( array( 'post_type' => self::types(), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_hs_uid', 'meta_compare' => 'EXISTS' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			foreach ( $anciennes as $id ) {
				if ( ! isset( $uids[ get_post_meta( $id, '_hs_uid', true ) ] ) ) {
					wp_trash_post( $id );
					$cpt['supprimees']++;
				}
			}
		}

		// 5. Tables : remplacées, numéros recâblés (écritures → échéances, souscriptions → dividendes).
		$lignes_tables = array();
		if ( $options['tables'] ) {
			$map_ligne = array();
			foreach ( self::TABLES as $t ) {
				$table = $wpdb->prefix . $t;
				$wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB
				$n = 0;
				foreach ( (array) ( $data['tables'][ $t ] ?? array() ) as $ligne ) {
					$ancien = (int) $ligne['id'];
					unset( $ligne['id'] );
					foreach ( self::COLONNES[ $t ]['post'] as $col ) {
						if ( isset( $ligne[ $col ] ) && $ligne[ $col ] ) {
							$ligne[ $col ] = $post_de( $ligne[ $col ] );
						}
					}
					foreach ( self::COLONNES[ $t ]['user'] as $col ) {
						if ( isset( $ligne[ $col ] ) && $ligne[ $col ] ) {
							$ligne[ $col ] = $user_de( $ligne[ $col ] );
						}
					}
					if ( 'hs_echeances' === $t && ! empty( $ligne['ecriture_id'] ) ) {
						$ligne['ecriture_id'] = $map_ligne['hs_ecritures'][ (int) $ligne['ecriture_id'] ] ?? 0;
					}
					if ( 'hs_distributions' === $t ) {
						$ligne['investissement_id'] = $map_ligne['hs_investissements'][ (int) $ligne['investissement_id'] ] ?? 0;
						if ( ! $ligne['investissement_id'] ) {
							continue;
						}
					}
					if ( $wpdb->insert( $table, $ligne ) ) {
						$map_ligne[ $t ][ $ancien ] = (int) $wpdb->insert_id;
						$n++;
					}
				}
				$lignes_tables[] = $t . ' : ' . $n;
			}
			// Lignes de test : renumérotées comme les autres, pour que « Supprimer les données de test » les retrouve.
			$demo_lignes = array();
			foreach ( (array) ( $data['demo_lignes'] ?? array() ) as $t => $anciens ) {
				foreach ( (array) $anciens as $a ) {
					$th = 0 === strpos( $t, 'hs_' ) ? $t : 'hs_' . $t;
					if ( isset( $map_ligne[ $th ][ (int) $a ] ) ) {
						$demo_lignes[ substr( $th, 3 ) ][] = $map_ligne[ $th ][ (int) $a ]; // même format que HS_Demo_Tableaux
					}
				}
			}
			if ( $demo_lignes ) {
				update_option( 'hs_demo_lignes', $demo_lignes, false );
				update_option( 'hs_demo_tableaux', 1, false );
			} else {
				delete_option( 'hs_demo_lignes' );
				delete_option( 'hs_demo_tableaux' );
			}
		}

		// 6. Réglages.
		if ( $options['reglages'] && is_array( $data['reglages'] ) ) {
			update_option( HS_Settings::OPTION, HS_Settings::sanitize( $data['reglages'] ) );
		}

		$lignes = array(
			sprintf( 'Fiches : %d créées, %d mises à jour%s%s.', $cpt['crees'], $cpt['maj'], $cpt['reprises'] ? sprintf( ' (dont %d fiches déjà présentes ici, reconnues par leur adresse)', $cpt['reprises'] ) : '', $cpt['supprimees'] ? sprintf( ', %d mises à la corbeille', $cpt['supprimees'] ) : '' ),
			sprintf( 'Photos : %d ajoutées à la médiathèque, %d déjà présentes.', $cpt['photos'], $cpt['photos_existantes'] ),
			sprintf( 'Comptes : %d créés, %d déjà présents (reliés par email). Les comptes créés doivent choisir leur mot de passe (« Mot de passe oublié » sur /mon-espace/).', $cpt['comptes_crees'], $cpt['comptes_relies'] ),
			$options['tables'] ? 'Tables remplacées : ' . implode( ', ', $lignes_tables ) . '.' : 'Tables financières non modifiées.',
			$options['reglages'] ? 'Réglages importés : vérifiez les numéros et l\'email de contact.' : 'Réglages de ce site conservés.',
		);
		return $lignes;
	}

	/** Fiche de ce site de même type et même slug, jamais synchronisée (sans _hs_uid). */
	private static function par_slug( $slug, $type ) {
		$p = get_posts( array(
			'post_type'      => $type,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'name'           => $slug,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array( array( 'key' => '_hs_uid', 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		return $p ? (int) $p[0] : 0;
	}

	/** Fiche ou image de ce site portant cet identifiant unique. */
	private static function par_uid( $uid, $type ) {
		$p = get_posts( array(
			'post_type'      => $type,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_hs_uid', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $uid, // phpcs:ignore WordPress.DB.SlowDBQuery
			'no_found_rows'  => true,
		) );
		return $p ? (int) $p[0] : 0;
	}
}
