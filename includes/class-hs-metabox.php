<?php
/**
 * Meta box native générée depuis HS_Fields : le plugin fonctionne sans ACF.
 * Si vous utilisez ACF plus tard, créez les champs avec les MÊMES noms (hs_ville, hs_statut…)
 * et désactivez cette box : add_filter( 'homystay_native_metabox', '__return_false' );
 */

defined( 'ABSPATH' ) || exit;

class HS_Metabox {

	public static function init() {
		if ( ! apply_filters( 'homystay_native_metabox', true ) ) {
			return;
		}
		add_action( 'add_meta_boxes', array( __CLASS__, 'add' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/** Sélecteur de médias pour le champ galerie, uniquement sur l'écran d'édition d'un bien. */
	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $screen || 'hs_bien' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'hs-admin-galerie', HS_URL . 'assets/js/admin-galerie.js', array( 'jquery', 'jquery-ui-sortable' ), HS_VERSION, true );
		wp_add_inline_style( 'wp-admin', '.hs-galerie-admin__liste{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 10px;padding:0}.hs-galerie-admin__liste li{position:relative;width:90px;height:90px;margin:0;cursor:move;list-style:none}.hs-galerie-admin__liste img{width:90px;height:90px;object-fit:cover;border-radius:4px;display:block}.hs-galerie-admin__retirer{position:absolute;top:-6px;right:-6px;width:22px;height:22px;border-radius:50%;border:0;background:#b32d2e;color:#fff;cursor:pointer;line-height:1}' );
	}

	public static function add() {
		foreach ( array_keys( HS_CPT::types() ) as $type ) {
			if ( HS_Fields::for_type( $type ) ) {
				add_meta_box( 'hs_fields', 'Informations HomyStay', array( __CLASS__, 'render' ), $type, 'normal', 'high' );
			}
		}
	}

	public static function render( $post ) {
		wp_nonce_field( 'hs_save_fields', 'hs_fields_nonce' );
		echo '<table class="form-table" role="presentation"><tbody>';
		foreach ( HS_Fields::for_type( $post->post_type ) as $key => $def ) {
			$name  = 'hs_' . $key;
			$value = get_post_meta( $post->ID, $name, true );
			if ( '' === $value && isset( $def['default'] ) ) {
				$value = $def['default'];
			}
			echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $def['label'] ) . '</label></th><td>';
			self::input( $name, $def, $value );
			if ( ! empty( $def['help'] ) ) {
				echo '<p class="description">' . esc_html( $def['help'] ) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function input( $name, $def, $value ) {
		$id = esc_attr( $name );
		switch ( $def['type'] ) {
			case 'select':
				echo '<select name="' . $id . '" id="' . $id . '"><option value="">—</option>';
				foreach ( $def['options'] as $k => $label ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $value, $k, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;
			case 'checkbox':
				printf( '<input type="checkbox" name="%1$s" id="%1$s" value="1"%2$s>', $id, checked( (int) $value, 1, false ) );
				break;
			case 'textarea':
				printf( '<textarea name="%1$s" id="%1$s" rows="4" class="large-text">%2$s</textarea>', $id, esc_textarea( $value ) );
				break;
			case 'gallery':
				printf( '<div class="hs-galerie-admin" data-target="%s">', $id );
				echo '<ul class="hs-galerie-admin__liste">';
				foreach ( array_filter( array_map( 'absint', explode( ',', (string) $value ) ) ) as $att ) {
					printf( '<li data-id="%d">%s<button type="button" class="hs-galerie-admin__retirer" aria-label="Retirer">×</button></li>', $att, wp_get_attachment_image( $att, 'thumbnail' ) );
				}
				echo '</ul>';
				printf( '<input type="hidden" name="%1$s" id="%1$s" value="%2$s">', $id, esc_attr( $value ) );
				echo '<button type="button" class="button hs-galerie-admin__ajouter">Ajouter des photos</button> <span class="description">Glisser les vignettes pour les réordonner.</span>';
				echo '</div>';
				break;
			case 'post':
				$posts = get_posts( array( 'post_type' => $def['post_type'], 'numberposts' => 500, 'post_status' => 'any', 'orderby' => 'title', 'order' => 'ASC' ) );
				echo '<select name="' . $id . '" id="' . $id . '"><option value="">—</option>';
				foreach ( $posts as $p ) {
					printf( '<option value="%d"%s>%s</option>', $p->ID, selected( (int) $value, $p->ID, false ), esc_html( $p->post_title ) );
				}
				echo '</select>';
				break;
			default:
				$type = in_array( $def['type'], array( 'number', 'email', 'tel', 'date' ), true ) ? $def['type'] : 'text';
				$step = ( 'number' === $type ) ? ' step="' . esc_attr( isset( $def['step'] ) ? $def['step'] : '1' ) . '"' : '';
				printf( '<input type="%s" name="%s" id="%s" value="%s" class="regular-text"%s>', $type, $id, $id, esc_attr( $value ), $step );
		}
	}

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['hs_fields_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hs_fields_nonce'] ) ), 'hs_save_fields' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( HS_Fields::for_type( $post->post_type ) as $key => $def ) {
			$name = 'hs_' . $key;
			$raw  = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : ( 'checkbox' === $def['type'] ? 0 : null ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( null === $raw ) {
				continue;
			}
			update_post_meta( $post_id, $name, HS_Fields::sanitize( $raw, $def ) );
		}
	}
}
