<?php
/**
 * Définition unique des champs de chaque CPT.
 * Sert à la fois à register_post_meta() (REST, validation), à la meta box d'admin
 * et aux templates. Pour ajouter un champ : l'ajouter ici, rien d'autre.
 *
 * Types : text, textarea, number, select, checkbox, email, tel, date, post (relation vers un autre CPT).
 */

defined( 'ABSPATH' ) || exit;

class HS_Fields {

	const STATUTS_BIEN = array(
		'brouillon'   => 'Brouillon',
		'disponible'  => 'Disponible',
		'reserve'     => 'Réservé',
		'occupe'      => 'Occupé',
		'maintenance' => 'En maintenance',
		'archive'     => 'Archivé',
	);

	public static function all() {
		return array(

			'hs_bien' => array(
				'reference'       => array( 'label' => 'Référence', 'type' => 'text', 'help' => 'Générée automatiquement si vide (ex. HS-DLA-0012).' ),
				'statut'          => array( 'label' => 'Statut', 'type' => 'select', 'options' => self::STATUTS_BIEN, 'default' => 'brouillon' ),
				'type_bien'       => array( 'label' => 'Type de bien', 'type' => 'select', 'options' => array(
					'studio' => 'Studio', 'appartement' => 'Appartement', 'villa' => 'Villa', 'duplex' => 'Duplex',
					'chambre' => 'Chambre', 'bureau' => 'Bureau', 'terrain' => 'Terrain', 'immeuble' => 'Immeuble',
				) ),
				'ville'           => array( 'label' => 'Ville', 'type' => 'text' ),
				'quartier'        => array( 'label' => 'Quartier', 'type' => 'text' ),
				'adresse'         => array( 'label' => 'Adresse', 'type' => 'text' ),
				'latitude'        => array( 'label' => 'Latitude', 'type' => 'number', 'step' => 'any' ),
				'longitude'       => array( 'label' => 'Longitude', 'type' => 'number', 'step' => 'any' ),
				'surface'         => array( 'label' => 'Surface (m²)', 'type' => 'number' ),
				'chambres'        => array( 'label' => 'Chambres', 'type' => 'number' ),
				'salles_bain'     => array( 'label' => 'Salles de bain', 'type' => 'number' ),
				'capacite'        => array( 'label' => 'Capacité (personnes)', 'type' => 'number' ),
				'offre_courte'    => array( 'label' => 'Location courte durée', 'type' => 'checkbox' ),
				'offre_longue'    => array( 'label' => 'Location longue durée', 'type' => 'checkbox' ),
				'offre_vente'     => array( 'label' => 'Vente', 'type' => 'checkbox' ),
				'prix_courte'     => array( 'label' => 'Prix par nuit (FCFA)', 'type' => 'number' ),
				'prix_longue'     => array( 'label' => 'Loyer mensuel (FCFA)', 'type' => 'number' ),
				'prix_vente'      => array( 'label' => 'Prix de vente (FCFA)', 'type' => 'number' ),
				'proprietaire_id' => array( 'label' => 'Propriétaire', 'type' => 'post', 'post_type' => 'hs_proprietaire' ),
				'galerie'         => array( 'label' => 'Galerie photos', 'type' => 'gallery', 'help' => "L'image mise en avant reste la photo principale ; ces photos s'ajoutent à la galerie de la page du bien." ),
				'equipements'     => array( 'label' => 'Équipements', 'type' => 'textarea', 'help' => 'Un équipement par ligne (ex. Wifi, Climatisation, Parking, Groupe électrogène).' ),
				'video_url'       => array( 'label' => 'Vidéo (URL)', 'type' => 'text' ),
				'visite_url'      => array( 'label' => 'Visite virtuelle (URL)', 'type' => 'text' ),
				'populaire'       => array( 'label' => 'Mettre en avant (biens populaires)', 'type' => 'checkbox' ),
			),

			'hs_proprietaire' => array(
				'telephone'      => array( 'label' => 'Téléphone', 'type' => 'tel' ),
				'email'          => array( 'label' => 'Email', 'type' => 'email' ),
				'adresse'        => array( 'label' => 'Adresse', 'type' => 'text' ),
				'mode_reversement' => array( 'label' => 'Mode de reversement', 'type' => 'select', 'options' => array(
					'virement' => 'Virement', 'orange_money' => 'Orange Money', 'mtn_momo' => 'MTN MoMo',
				) ),
				'user_id'        => array( 'label' => 'Compte utilisateur (ID)', 'type' => 'number' ),
				// Pièce d'identité et coordonnées bancaires : à stocker hors médiathèque publique (voir module sécurisé, étape suivante).
			),

			'hs_locataire' => array(
				'telephone'  => array( 'label' => 'Téléphone', 'type' => 'tel' ),
				'email'      => array( 'label' => 'Email', 'type' => 'email' ),
				'profession' => array( 'label' => 'Profession', 'type' => 'text' ),
				'revenus'    => array( 'label' => 'Revenus mensuels (FCFA)', 'type' => 'number' ),
				'statut_dossier' => array( 'label' => 'Dossier', 'type' => 'select', 'options' => array(
					'en_etude' => 'En étude', 'accepte' => 'Accepté', 'refuse' => 'Refusé',
				), 'default' => 'en_etude' ),
				'user_id'    => array( 'label' => 'Compte utilisateur (ID)', 'type' => 'number' ),
			),

			'hs_contrat' => array(
				'type_contrat' => array( 'label' => 'Type', 'type' => 'select', 'options' => array(
					'bail' => 'Bail', 'mandat' => 'Mandat de gestion',
				) ),
				'bien_id'      => array( 'label' => 'Bien', 'type' => 'post', 'post_type' => 'hs_bien' ),
				'locataire_id' => array( 'label' => 'Locataire', 'type' => 'post', 'post_type' => 'hs_locataire' ),
				'proprietaire_id' => array( 'label' => 'Propriétaire', 'type' => 'post', 'post_type' => 'hs_proprietaire' ),
				'date_debut'   => array( 'label' => 'Date de début', 'type' => 'date' ),
				'date_fin'     => array( 'label' => 'Date de fin', 'type' => 'date' ),
				'loyer'        => array( 'label' => 'Loyer (FCFA)', 'type' => 'number' ),
				'caution'      => array( 'label' => 'Caution (FCFA)', 'type' => 'number' ),
				'charges'      => array( 'label' => 'Charges (FCFA)', 'type' => 'number' ),
				'commission'   => array( 'label' => 'Commission HomyStay (%)', 'type' => 'number', 'step' => 'any' ),
				'statut'       => array( 'label' => 'Statut', 'type' => 'select', 'options' => array(
					'brouillon' => 'Brouillon', 'actif' => 'Actif', 'termine' => 'Terminé', 'resilie' => 'Résilié',
				), 'default' => 'brouillon' ),
			),

			'hs_ticket' => array(
				'bien_id'   => array( 'label' => 'Bien', 'type' => 'post', 'post_type' => 'hs_bien' ),
				'categorie' => array( 'label' => 'Catégorie', 'type' => 'select', 'options' => array(
					'plomberie' => 'Plomberie', 'electricite' => 'Électricité', 'climatisation' => 'Climatisation', 'nettoyage' => 'Nettoyage', 'autre' => 'Autre',
				) ),
				'etape'     => array( 'label' => 'Étape', 'type' => 'select', 'options' => array(
					'signale' => 'Signalé', 'valide' => 'Validé', 'intervention' => 'Intervention', 'cloture' => 'Clôturé',
				), 'default' => 'signale' ),
				'bloquant'  => array( 'label' => 'Bloquant (passe le bien en maintenance)', 'type' => 'checkbox' ),
				'technicien_id' => array( 'label' => 'Technicien (ID utilisateur)', 'type' => 'number', 'help' => 'Le technicien voit ce ticket dans son espace.' ),
				'technicien_nom' => array( 'label' => 'Technicien (nom)', 'type' => 'text' ),
				'date_intervention' => array( 'label' => "Date d'intervention", 'type' => 'date' ),
				'creneau'   => array( 'label' => 'Créneau', 'type' => 'text', 'help' => 'Ex. 10:00 ou 9 h à 12 h.' ),
				'urgent'    => array( 'label' => 'Urgent', 'type' => 'checkbox' ),
				'signale_par' => array( 'label' => 'Signalé par', 'type' => 'text' ),
				'cout'      => array( 'label' => 'Coût (FCFA)', 'type' => 'number' ),
				'imputation' => array( 'label' => 'Imputé à', 'type' => 'select', 'options' => array(
					'proprietaire' => 'Propriétaire', 'locataire' => 'Locataire', 'homystay' => 'HomyStay',
				) ),
			),

			'hs_police' => array(
				'bien_id'    => array( 'label' => 'Bien', 'type' => 'post', 'post_type' => 'hs_bien' ),
				'type_police' => array( 'label' => 'Type', 'type' => 'select', 'options' => array(
					'habitation' => 'Habitation', 'loyers_impayes' => 'Loyers impayés', 'multirisque' => 'Multirisque immeuble',
				) ),
				'numero'     => array( 'label' => 'Numéro de police', 'type' => 'text' ),
				'compagnie'  => array( 'label' => 'Compagnie', 'type' => 'text' ),
				'prime'      => array( 'label' => 'Prime annuelle (FCFA)', 'type' => 'number' ),
				'echeance'   => array( 'label' => "Date d'échéance", 'type' => 'date' ),
			),

			'hs_reservation' => array(
				'bien_id'   => array( 'label' => 'Bien', 'type' => 'post', 'post_type' => 'hs_bien' ),
				'voyageur'  => array( 'label' => 'Voyageur', 'type' => 'text' ),
				'telephone' => array( 'label' => 'Téléphone', 'type' => 'tel' ),
				'email'     => array( 'label' => 'Email', 'type' => 'email', 'help' => 'Relie la réservation au compte du voyageur ayant ce même email.' ),
				'user_id'   => array( 'label' => 'Compte utilisateur (ID)', 'type' => 'number' ),
				'arrivee'   => array( 'label' => 'Arrivée', 'type' => 'date' ),
				'depart'    => array( 'label' => 'Départ', 'type' => 'date' ),
				'personnes' => array( 'label' => 'Voyageurs', 'type' => 'number' ),
				'montant'   => array( 'label' => 'Montant total (FCFA)', 'type' => 'number' ),
				'paiement'  => array( 'label' => 'Paiement', 'type' => 'select', 'options' => array(
					'attente' => 'En attente', 'acompte' => 'Acompte', 'paye' => 'Payé', 'rembourse' => 'Remboursé',
				), 'default' => 'attente' ),
				'statut'    => array( 'label' => 'Statut', 'type' => 'select', 'options' => array(
					'demande' => 'Demande', 'confirmee' => 'Confirmée', 'terminee' => 'Terminée', 'annulee' => 'Annulée',
				), 'default' => 'demande' ),
				'source'    => array( 'label' => 'Source', 'type' => 'select', 'options' => array(
					'site' => 'Site', 'whatsapp' => 'WhatsApp', 'telephone' => 'Téléphone', 'airbnb' => 'Airbnb', 'booking' => 'Booking', 'autre' => 'Autre',
				), 'default' => 'site' ),
				'consignes' => array( 'label' => "Consignes d'arrivée", 'type' => 'textarea', 'help' => 'Visibles par le voyageur dans son espace.' ),
			),

			'hs_contact' => array(
				'type_contact' => array( 'label' => 'Type', 'type' => 'select', 'options' => array(
					'proprietaire' => 'Propriétaire', 'investisseur' => 'Investisseur', 'voyageur' => 'Voyageur', 'locataire' => 'Locataire',
				) ),
				'etape'     => array( 'label' => 'Étape', 'type' => 'select', 'options' => array(
					'nouveau' => 'Nouveau', 'contacte' => 'Contacté', 'rdv' => 'Rendez-vous', 'proposition' => 'Proposition', 'signe' => 'Contrat signé', 'perdu' => 'Perdu',
				), 'default' => 'nouveau' ),
				'telephone' => array( 'label' => 'Téléphone', 'type' => 'tel' ),
				'email'     => array( 'label' => 'Email', 'type' => 'email' ),
				'source'    => array( 'label' => 'Source', 'type' => 'text', 'help' => 'Ex. Formulaire « Déposer un bien », WhatsApp.' ),
				'besoin'    => array( 'label' => 'Besoin', 'type' => 'textarea' ),
				'rdv'       => array( 'label' => 'Rendez-vous le', 'type' => 'date' ),
				'rdv_heure' => array( 'label' => 'Heure du rendez-vous', 'type' => 'text' ),
				'relance'   => array( 'label' => 'Prochaine relance', 'type' => 'date' ),
				'action'    => array( 'label' => 'Prochaine action', 'type' => 'text' ),
				'date_signature' => array( 'label' => 'Date de signature', 'type' => 'date' ),
			),

			'hs_investisseur' => array(
				'telephone' => array( 'label' => 'Téléphone', 'type' => 'tel' ),
				'email'     => array( 'label' => 'Email', 'type' => 'email', 'help' => "Relie la fiche au compte de l'investisseur ayant ce même email." ),
				'user_id'   => array( 'label' => 'Compte utilisateur (ID)', 'type' => 'number' ),
				'mode_versement' => array( 'label' => 'Versement des dividendes', 'type' => 'select', 'options' => array(
					'virement' => 'Virement', 'orange_money' => 'Orange Money', 'mtn_momo' => 'MTN MoMo',
				) ),
			),

			'hs_projet' => array(
				'ville'            => array( 'label' => 'Ville', 'type' => 'text' ),
				'montant_recherche' => array( 'label' => 'Montant recherché (FCFA)', 'type' => 'number' ),
				'montant_leve'     => array( 'label' => 'Montant levé (FCFA)', 'type' => 'number' ),
				'ticket_min'       => array( 'label' => 'Ticket minimum (FCFA)', 'type' => 'number' ),
				'rendement_estime' => array( 'label' => 'Rendement estimé (%/an)', 'type' => 'number', 'step' => 'any' ),
				'duree_mois'       => array( 'label' => 'Durée (mois)', 'type' => 'number' ),
				'avancement'       => array( 'label' => 'Avancement', 'type' => 'text', 'help' => 'Ex. Gros œuvre terminé, En location.' ),
				'statut'           => array( 'label' => 'Statut', 'type' => 'select', 'options' => array(
					'a_venir' => 'À venir', 'ouvert' => 'Ouvert', 'complet' => 'Complet', 'cloture' => 'Clôturé',
				), 'default' => 'a_venir' ),
			),
		);
	}

	public static function for_type( $post_type ) {
		$all = self::all();
		return isset( $all[ $post_type ] ) ? $all[ $post_type ] : array();
	}

	/** Lecture d'un champ (préfixe _hs_ non utilisé : les clés restent lisibles par Elementor / ACF). */
	public static function get( $post_id, $key ) {
		return get_post_meta( $post_id, 'hs_' . $key, true );
	}

	/** ID des photos d'un bien : image mise en avant d'abord, puis la galerie. */
	public static function photos( $post_id ) {
		$ids   = array();
		$thumb = get_post_thumbnail_id( $post_id );
		if ( $thumb ) {
			$ids[] = (int) $thumb;
		}
		foreach ( explode( ',', (string) self::get( $post_id, 'galerie' ) ) as $id ) {
			$id = absint( $id );
			if ( $id && wp_attachment_is_image( $id ) ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/** Équipements : une entrée par ligne. */
	public static function equipements( $post_id ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) self::get( $post_id, 'equipements' ) ) ) ) );
	}

	public static function sanitize( $value, $def ) {
		switch ( $def['type'] ) {
			case 'number':
				return ( '' === $value || null === $value ) ? '' : ( isset( $def['step'] ) ? (float) $value : (int) $value );
			case 'checkbox':
				return $value ? 1 : 0;
			case 'post':
				return absint( $value );
			case 'email':
				return sanitize_email( $value );
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'gallery':
				// Liste d'ID de pièces jointes séparés par des virgules.
				$ids = array_filter( array_map( 'absint', explode( ',', (string) $value ) ) );
				return implode( ',', array_unique( $ids ) );
			case 'date':
				return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value ) ? $value : '';
			case 'select':
				return array_key_exists( $value, $def['options'] ) ? $value : ( isset( $def['default'] ) ? $def['default'] : '' );
			default:
				return sanitize_text_field( $value );
		}
	}
}
