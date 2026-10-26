<?php
/**
 * Jeu de données de test : 10 biens répartis entre Douala, Yaoundé, Kribi et Limbé.
 * « photos » = numéros des images assets/demo/demo-XX.jpg (la première devient l'image mise en avant).
 * Prix en FCFA. Coordonnées GPS approximatives du quartier.
 */
defined( 'ABSPATH' ) || exit;

return array(
	array(
		'titre'   => 'Villa Bonapriso avec jardin',
		'extrait' => 'Villa de plain-pied au calme, jardin clos et piscine, à dix minutes d\'Akwa.',
		'contenu' => "Au bout d'une rue arborée de Bonapriso, cette villa de plain-pied s'ouvre sur un jardin clos avec piscine. Les quatre chambres sont climatisées, dont une suite parentale avec dressing et salle de bain.\n\nLe quartier des affaires d'Akwa est à dix minutes en voiture, l'aéroport international de Douala à vingt-cinq minutes. Un gardien est présent jour et nuit.",
		'photos'  => array( 1, 8, 10, 2 ),
		'champs'  => array(
			'statut' => 'disponible', 'type_bien' => 'villa', 'ville' => 'Douala', 'quartier' => 'Bonapriso', 'adresse' => 'Rue Njo-Njo',
			'latitude' => 4.0207, 'longitude' => 9.6928, 'surface' => 320, 'chambres' => 4, 'salles_bain' => 3, 'capacite' => 8,
			'offre_courte' => 1, 'prix_courte' => 85000, 'offre_longue' => 1, 'prix_longue' => 1200000, 'populaire' => 1,
			'equipements' => "Piscine\nJardin clos\nClimatisation dans chaque chambre\nGroupe électrogène\nWifi fibre\nGardien 24 h/24\nParking pour 3 voitures",
		),
	),
	array(
		'titre'   => 'Appartement meublé à Akwa',
		'extrait' => 'Deux chambres en plein centre d\'affaires, idéal pour un séjour professionnel.',
		'contenu' => "Au 4e étage d'un immeuble récent avec ascenseur, cet appartement meublé de deux chambres est à deux pas du boulevard de la Liberté. Le salon est lumineux et la cuisine entièrement équipée.\n\nBanques, restaurants et supermarchés sont accessibles à pied. Idéal pour un séjour professionnel de quelques jours à quelques mois.",
		'photos'  => array( 3, 9, 11 ),
		'champs'  => array(
			'statut' => 'disponible', 'type_bien' => 'appartement', 'ville' => 'Douala', 'quartier' => 'Akwa', 'adresse' => 'Boulevard de la Liberté',
			'latitude' => 4.0500, 'longitude' => 9.6960, 'surface' => 95, 'chambres' => 2, 'salles_bain' => 1, 'capacite' => 4,
			'offre_courte' => 1, 'prix_courte' => 35000, 'offre_longue' => 1, 'prix_longue' => 450000,
			'equipements' => "Ascenseur\nClimatisation\nWifi\nCuisine équipée\nMachine à laver\nTélévision avec bouquet satellite",
		),
	),
	array(
		'titre'   => 'Studio moderne à Bastos',
		'extrait' => 'Studio neuf dans le quartier des ambassades, parfait pour une personne ou un couple.',
		'contenu' => "Studio entièrement rénové dans une résidence sécurisée de Bastos, le quartier des ambassades. Un coin nuit, un coin salon et une kitchenette bien pensés pour une personne ou un couple.\n\nLe centre-ville de Yaoundé est à 15 minutes, et les cafés de Bastos au pied de la résidence.",
		'photos'  => array( 12, 8 ),
		'champs'  => array(
			'statut' => 'disponible', 'type_bien' => 'studio', 'ville' => 'Yaoundé', 'quartier' => 'Bastos',
			'latitude' => 3.8920, 'longitude' => 11.5070, 'surface' => 38, 'chambres' => 0, 'salles_bain' => 1, 'capacite' => 2,
			'offre_courte' => 1, 'prix_courte' => 22000, 'offre_longue' => 1, 'prix_longue' => 180000, 'populaire' => 1,
			'equipements' => "Climatisation\nWifi\nKitchenette\nRésidence sécurisée\nEau chaude",
		),
	),
	array(
		'titre'   => 'Duplex familial à Santa Barbara',
		'extrait' => 'Grand duplex de quatre chambres avec terrasse, à louer à l\'année ou à vendre.',
		'contenu' => "Sur deux niveaux, ce duplex offre quatre chambres, un grand séjour et une terrasse qui domine les collines de Yaoundé. Les finitions sont récentes et le quartier très calme.\n\nÉcoles internationales et supermarchés à moins de 10 minutes. Proposé à la location annuelle ou à la vente.",
		'photos'  => array( 5, 9, 10 ),
		'champs'  => array(
			'statut' => 'disponible', 'type_bien' => 'duplex', 'ville' => 'Yaoundé', 'quartier' => 'Santa Barbara',
			'latitude' => 3.9020, 'longitude' => 11.5050, 'surface' => 240, 'chambres' => 4, 'salles_bain' => 3, 'capacite' => 7,
			'offre_longue' => 1, 'prix_longue' => 750000, 'offre_vente' => 1, 'prix_vente' => 145000000,
			'equipements' => "Terrasse\nForage et bâche à eau\nGroupe électrogène\nParking couvert\nCuisine américaine\nQuartier calme",
		),
	),
	array(
		'titre'   => 'Villa pieds dans l\'eau à Kribi',
		'extrait' => 'Villa en bord de plage avec accès direct à la mer, pour les week-ends et les vacances.',
		'contenu' => "Directement sur la plage de Ngoyè, cette villa de trois chambres offre un accès privé à la mer. La terrasse ombragée par les cocotiers est parfaite pour les déjeuners en famille.\n\nLes chutes de la Lobé sont à 15 minutes, le centre de Kribi à 10 minutes. Une cuisinière peut être réservée sur demande.",
		'photos'  => array( 6, 7, 8, 11 ),
		'champs'  => array(
			'statut' => 'disponible', 'type_bien' => 'villa', 'ville' => 'Kribi', 'quartier' => 'Ngoyè',
			'latitude' => 2.9530, 'longitude' => 9.9100, 'surface' => 210, 'chambres' => 3, 'salles_bain' => 3, 'capacite' => 6,
			'offre_courte' => 1, 'prix_courte' => 120000, 'populaire' => 1,
			'equipements' => "Accès direct à la plage\nTerrasse ombragée\nClimatisation\nBarbecue\nWifi\nCuisinière sur demande",
		),
	),
	array(
		'titre'   => 'Appartement vue mer à Limbé',
		'extrait' => 'Deux chambres face à l\'océan, près du jardin botanique.',
		'contenu' => "Au dernier étage d'une petite résidence de Down Beach, cet appartement regarde l'océan depuis le salon et la chambre principale. Le jardin botanique et les restaurants de poisson braisé sont à quelques minutes à pied.\n\nIdéal pour un long week-end au bord de la mer ou un séjour de plusieurs semaines.",
		'photos'  => array( 7, 6, 10 ),
		'champs'  => array(
			'statut' => 'reserve', 'type_bien' => 'appartement', 'ville' => 'Limbé', 'quartier' => 'Down Beach',
			'latitude' => 4.0130, 'longitude' => 9.2050, 'surface' => 110, 'chambres' => 2, 'salles_bain' => 2, 'capacite' => 4,
			'offre_courte' => 1, 'prix_courte' => 40000, 'offre_longue' => 1, 'prix_longue' => 380000,
			'equipements' => "Vue sur la mer\nBalcon\nClimatisation\nWifi\nCuisine équipée",
		),
	),
	array(
		'titre'   => 'Chambre cosy au Golf',
		'extrait' => 'Chambre privée avec salle de bain dans une maison d\'hôtes calme.',
		'contenu' => "Chambre privée avec salle de bain attenante, dans une maison d'hôtes tenue avec soin au quartier Golf. Petit-déjeuner possible sur demande.\n\nUne solution simple et économique pour un séjour de quelques nuits à Yaoundé.",
		'photos'  => array( 11, 9 ),
		'champs'  => array(
			'statut' => 'disponible', 'type_bien' => 'chambre', 'ville' => 'Yaoundé', 'quartier' => 'Golf',
			'latitude' => 3.8930, 'longitude' => 11.5200, 'surface' => 22, 'chambres' => 1, 'salles_bain' => 1, 'capacite' => 2,
			'offre_courte' => 1, 'prix_courte' => 15000,
			'equipements' => "Salle de bain privée\nClimatisation\nWifi\nPetit-déjeuner sur demande",
		),
	),
	array(
		'titre'   => 'Plateau de bureaux à Bonanjo',
		'extrait' => '180 m² de bureaux dans le quartier administratif, à louer ou à vendre.',
		'contenu' => "Plateau de bureaux de 180 m² au 2e étage d'un immeuble de Bonanjo, à proximité des ministères et des sièges de banques. L'espace est cloisonnable et comprend deux sanitaires.\n\nDisponible à la location ou à la vente, avec places de parking.",
		'photos'  => array( 4, 12 ),
		'champs'  => array(
			'statut' => 'occupe', 'type_bien' => 'bureau', 'ville' => 'Douala', 'quartier' => 'Bonanjo',
			'latitude' => 4.0420, 'longitude' => 9.6890, 'surface' => 180, 'chambres' => 0, 'salles_bain' => 2, 'capacite' => 0,
			'offre_longue' => 1, 'prix_longue' => 900000, 'offre_vente' => 1, 'prix_vente' => 220000000,
			'equipements' => "Ascenseur\nClimatisation centrale\nGroupe électrogène\nFibre optique\nParking\nAgent de sécurité",
		),
	),
	array(
		'titre'   => 'Appartement standing à Omnisport',
		'extrait' => 'Trois chambres près du stade, actuellement occupé.',
		'contenu' => "Appartement de trois chambres dans une résidence de standing proche du stade Omnisport. Grand séjour, cuisine fermée et deux salles de bain.\n\nCe bien est actuellement loué : il sert ici à tester l'affichage d'un bien occupé.",
		'photos'  => array( 3, 8, 10 ),
		'champs'  => array(
			'statut' => 'occupe', 'type_bien' => 'appartement', 'ville' => 'Yaoundé', 'quartier' => 'Omnisport',
			'latitude' => 3.8890, 'longitude' => 11.5440, 'surface' => 120, 'chambres' => 3, 'salles_bain' => 2, 'capacite' => 5,
			'offre_courte' => 1, 'prix_courte' => 28000, 'offre_longue' => 1, 'prix_longue' => 350000,
			'equipements' => "Climatisation\nForage\nParking\nGardien",
		),
	),
	array(
		'titre'   => 'Villa contemporaine à Makepè',
		'extrait' => 'Grande villa de cinq chambres sur un terrain de 800 m², à vendre ou à louer.',
		'contenu' => "Construite il y a trois ans, cette villa contemporaine de cinq chambres se trouve sur un terrain de 800 m² entièrement clôturé. Grandes baies vitrées, séjour double hauteur et cuisine ouverte.\n\nMakepè est un quartier résidentiel en plein essor, à 20 minutes du centre de Douala. Titre foncier disponible.",
		'photos'  => array( 2, 9, 11, 1 ),
		'champs'  => array(
			'statut' => 'disponible', 'type_bien' => 'villa', 'ville' => 'Douala', 'quartier' => 'Makepè',
			'latitude' => 4.0750, 'longitude' => 9.7480, 'surface' => 400, 'chambres' => 5, 'salles_bain' => 4, 'capacite' => 10,
			'offre_longue' => 1, 'prix_longue' => 950000, 'offre_vente' => 1, 'prix_vente' => 180000000,
			'equipements' => "Terrain de 800 m²\nTitre foncier\nGroupe électrogène\nForage\nClimatisation\nParking pour 4 voitures",
		),
	),
);
