# HomyStay Core

Plugin métier de la plateforme **HomyStay Capital** : gestion des biens immobiliers, propriétaires, locataires, contrats, maintenance, assurance et projets d'investissement, avec des shortcodes pour afficher ces données sur le site (pages construites avec Elementor).

| | |
| --- | --- |
| Version | 0.5.0 |
| WordPress | 6.4 minimum (développé sur 7.0) |
| PHP | 7.4 minimum |
| Dépendances | Aucune (ACF, Elementor et WooCommerce sont optionnels) |
| Auteur | MINDCODE 237 |

---

## Sommaire

1. [Principe](#1-principe)
2. [Installation](#2-installation)
3. [Structure des fichiers](#3-structure-des-fichiers)
4. [Types de contenu (CPT)](#4-types-de-contenu-cpt)
5. [Champs](#5-champs)
6. [Statuts et automatismes](#6-statuts-et-automatismes)
7. [Rôles et droits](#7-rôles-et-droits)
8. [Tables financières](#8-tables-financières)
9. [Shortcodes](#9-shortcodes)
9 bis. [Page d'un bien](#9-bis-page-dun-bien)
9 ter. [Tableaux de bord et espaces clients](#9-ter-tableaux-de-bord-et-espaces-clients)
10. [Intégration Elementor](#10-intégration-elementor)
11. [Personnaliser l'affichage](#11-personnaliser-laffichage)
12. [Filtres et hooks](#12-filtres-et-hooks)
13. [API REST](#13-api-rest)
14. [Utiliser ACF (optionnel)](#14-utiliser-acf-optionnel)
15. [Ajouter un champ ou un CPT](#15-ajouter-un-champ-ou-un-cpt)
16. [Sécurité et données sensibles](#16-sécurité-et-données-sensibles)
17. [Feuille de route](#17-feuille-de-route)
18. [Dépannage](#18-dépannage)

---

## 1. Principe

Le plugin sépare clairement deux responsabilités :

- **Le plugin** porte les données et la logique : types de contenu, champs, statuts, rôles, tables financières, règles automatiques et rendu HTML de base.
- **Le thème + Elementor** portent le design : mise en page, typographie, couleurs. Les données du plugin sont insérées dans les pages via des shortcodes.

Conséquence : on peut changer de thème ou refaire tout le design sans toucher aux données ni au back-office.

Les **fiches** (biens, contrats, tickets…) sont stockées comme des contenus WordPress (CPT + `postmeta`). Les **données chiffrées et volumineuses** (écritures, loyers, investissements) sont stockées dans des tables dédiées, plus rapides à interroger pour les tableaux de bord et KPI.

---

## 2. Installation

1. Copier le dossier `homystay-core` dans `wp-content/plugins/`.
2. Dans l'admin : **Extensions › HomyStay Core › Activer**.
3. Aller dans **Réglages › Permaliens** et cliquer sur **Enregistrer** (sans rien modifier) pour activer les URL `/biens/` et `/investir/`.
4. Le menu **HomyStay Capital** apparaît dans la barre latérale de l'admin.

À l'activation, le plugin :

- crée les rôles HomyStay et donne les droits à l'administrateur ;
- crée les tables financières (`wp_hs_*`) ;
- enregistre les types de contenu et régénère les règles de réécriture.

À la **désactivation**, aucune donnée n'est supprimée (fiches, tables et rôles sont conservés).

### Premier test

1. **HomyStay Capital › Biens › Ajouter**.
2. Renseigner le titre, une image mise en avant, la ville, le **statut « Disponible »**, cocher au moins une formule (ex. « Location courte durée ») et saisir son prix.
3. Publier.
4. Créer une page, y placer `[hs_biens]`, l'afficher.

> Un bien n'apparaît dans les listes que s'il est **publié** et que son statut HomyStay est **Disponible** ou **Réservé**. Un bien filtré par formule doit avoir la case de cette formule cochée.

---

## 3. Structure des fichiers

```
homystay-core/
├── homystay-core.php              Point d'entrée : constantes, chargement, hooks d'activation
├── README.md                      Cette documentation
├── assets/
│   ├── css/homystay.css           Styles de base : listes, cartes, barre, shortcodes
│   ├── css/bien.css               Styles de la page d'un bien (chargé uniquement sur celle-ci)
│   └── js/
│       ├── bien.js                Lightbox photos de la page d'un bien
│       ├── liste.js               Barre de filtres (envoi auto) et favoris
│       └── admin-galerie.js       Champ « Galerie photos » dans l'admin
├── includes/
│   ├── class-hs-fields.php        Définition unique de tous les champs + nettoyage des valeurs
│   ├── class-hs-cpt.php           CPT, métadonnées, menu admin, tableau de bord, automatismes
│   ├── class-hs-metabox.php       Formulaire d'édition natif (sans ACF) généré depuis les champs
│   ├── class-hs-roles.php         Rôles, capacités, accès à l'admin
│   ├── class-hs-install.php       Activation, création et mise à jour des tables
│   ├── class-hs-templates.php     Chargement des gabarits surchargeables, format des prix
│   ├── class-hs-shortcodes.php    Tous les shortcodes front
│   ├── class-hs-single.php        Page d'un bien, archive /biens/ filtrée
│   ├── class-hs-donnees.php       Calculs : occupation, finances, impayés, CRM, espaces
│   ├── class-hs-tableau.php       Tableau de bord du back-office, export CSV, reversements
│   ├── class-hs-espace.php        « Mon espace » : espaces clients, aperçu, documents, actions
│   ├── class-hs-demo.php          Outil « Données de test »
│   ├── class-hs-demo-tableaux.php Données de test des tableaux de bord
│   └── class-hs-settings.php      Réglages (WhatsApp, téléphone, email…)
└── templates/                     Gabarits HTML (surchargeables par le thème)
    ├── recherche.php
    ├── admin/                     Vues du tableau de bord (entete, gestion, finances, commercial)
    ├── espace/                    Espaces clients (proprietaire, locataire, voyageur, investisseur, technicien, connexion, apercu, document…)
    ├── barre-biens.php            Barre de filtres et de tri de [hs_biens]
    ├── liste-biens.php
    ├── carte-bien.php
    ├── fiche-bien.php             Fiche courte (shortcode [hs_bien_fiche])
    ├── single-bien.php            Page complète d'un bien (en-tête + pied du thème)
    ├── single-bien-contenu.php    Contenu de la page d'un bien
    └── liste-projets.php
```

---

## 4. Types de contenu (CPT)

Tous les CPT sont regroupés sous le menu **HomyStay Capital**.

| CPT | Libellé | Public | URL | Rôle |
| --- | --- | --- | --- | --- |
| `hs_bien` | Biens | Oui | `/biens/`, `/biens/<slug>/` | Fiche immobilière |
| `hs_projet` | Projets | Oui | `/investir/`, `/investir/<slug>/` | Projet d'investissement |
| `hs_proprietaire` | Propriétaires | Non | — | Fiche propriétaire |
| `hs_locataire` | Locataires | Non | — | Dossier locataire |
| `hs_contrat` | Contrats | Non | — | Bail ou mandat de gestion |
| `hs_ticket` | Maintenance | Non | — | Ticket d'intervention |
| `hs_police` | Assurance | Non | — | Police d'assurance |
| `hs_reservation` | Réservations | Non | — | Séjour courte durée : bien, voyageur, dates, montant, paiement |
| `hs_contact` | CRM | Non | — | Demande commerciale : type, étape du pipeline, relance, rendez-vous |
| `hs_investisseur` | Investisseurs | Non | — | Fiche investisseur, reliée à ses souscriptions |

Les CPT non publics n'ont pas d'URL sur le site, ne sont pas dans la recherche WordPress, et leurs champs ne sont pas exposés publiquement par l'API REST.

---

## 5. Champs

Tous les champs sont définis dans **`includes/class-hs-fields.php`**. Chaque champ est enregistré en base avec la clé `hs_<nom>` (ex. `hs_ville`).

Types disponibles : `text`, `textarea`, `number`, `select`, `checkbox`, `email`, `tel`, `date`, `post` (relation vers une fiche d'un autre CPT), `gallery` (liste d'images, stockée en ID séparés par des virgules).

### Bien (`hs_bien`)

| Clé | Libellé | Type |
| --- | --- | --- |
| `reference` | Référence | texte (auto si vide) |
| `statut` | Statut | liste (voir §6) |
| `type_bien` | Type de bien | liste : studio, appartement, villa, duplex, chambre, bureau, terrain, immeuble |
| `ville`, `quartier`, `adresse` | Localisation | texte |
| `latitude`, `longitude` | Coordonnées GPS | nombre décimal |
| `surface` | Surface (m²) | nombre |
| `chambres`, `salles_bain`, `capacite` | Pièces, capacité | nombre |
| `offre_courte`, `offre_longue`, `offre_vente` | Formules proposées | case à cocher |
| `prix_courte` | Prix par nuit (FCFA) | nombre |
| `prix_longue` | Loyer mensuel (FCFA) | nombre |
| `prix_vente` | Prix de vente (FCFA) | nombre |
| `proprietaire_id` | Propriétaire | relation → `hs_proprietaire` |
| `galerie` | Galerie photos | images (ajout depuis la médiathèque, tri par glisser-déposer) |
| `equipements` | Équipements | texte, un par ligne |
| `video_url`, `visite_url` | Vidéo, visite virtuelle | URL |
| `populaire` | Mis en avant | case à cocher |

Photos : l'**image mise en avant** est la photo principale ; le champ **Galerie photos** ajoute les suivantes. `HS_Fields::photos( $id )` renvoie la liste complète des ID, dans l'ordre.

### Propriétaire (`hs_proprietaire`)

`telephone`, `email`, `adresse`, `mode_reversement` (virement, Orange Money, MTN MoMo), `user_id` (compte WordPress lié). Le nom est le titre de la fiche.

### Locataire (`hs_locataire`)

`telephone`, `email`, `profession`, `revenus`, `statut_dossier` (en étude, accepté, refusé), `user_id`.

### Contrat (`hs_contrat`)

`type_contrat` (bail, mandat), `bien_id`, `locataire_id`, `proprietaire_id`, `date_debut`, `date_fin`, `loyer`, `caution`, `charges`, `commission` (%), `statut` (brouillon, actif, terminé, résilié).

### Ticket de maintenance (`hs_ticket`)

`bien_id`, `categorie` (plomberie, électricité, climatisation, nettoyage, autre), `etape` (signalé, validé, intervention, clôturé), `bloquant`, `technicien_id`, `cout`, `imputation` (propriétaire, locataire, HomyStay). La description du problème et la photo utilisent l'éditeur et l'image mise en avant.

### Police d'assurance (`hs_police`)

`bien_id`, `type_police` (habitation, loyers impayés, multirisque immeuble), `numero`, `compagnie`, `prime`, `echeance`.

### Projet d'investissement (`hs_projet`)

`ville`, `montant_recherche`, `montant_leve`, `ticket_min`, `rendement_estime` (%/an), `duree_mois`, `statut` (à venir, ouvert, complet, clôturé).

### Lire un champ en PHP

```php
$ville  = HS_Fields::get( $post_id, 'ville' );      // équivaut à get_post_meta( $post_id, 'hs_ville', true )
$statut = HS_Fields::get( $post_id, 'statut' );
```

---

## 6. Statuts et automatismes

### Statuts d'un bien

| Statut | Clé | Visible dans les listes |
| --- | --- | --- |
| Brouillon | `brouillon` | Non |
| Disponible | `disponible` | Oui |
| Réservé | `reserve` | Oui (badge « Réservé ») |
| Occupé | `occupe` | Non |
| En maintenance | `maintenance` | Non |
| Archivé | `archive` | Non |

Ce statut HomyStay est indépendant du statut de publication WordPress : un bien doit être **publié** ET **Disponible/Réservé** pour apparaître sur le site.

### Automatismes actifs

- **Référence automatique** : à l'enregistrement d'un bien sans référence, le plugin génère `HS-<3 premières lettres de la ville>-<ID sur 4 chiffres>`, ex. `HS-DOU-0012` pour Douala. Une référence saisie à la main n'est jamais écrasée.
- **Ticket bloquant** : un ticket coché « bloquant », relié à un bien et non clôturé, passe ce bien en statut **En maintenance**. Le retour à « Disponible » se fait manuellement par le gestionnaire.

### Réglages

**HomyStay Capital › Réglages** (administrateurs) :

| Réglage | Utilisation |
| --- | --- |
| Numéro WhatsApp | Bouton « Écrire sur WhatsApp » de la page d'un bien. Format international sans « + » : `237690000000` |
| Téléphone | Bouton « Appeler » |
| Email de contact | Lien « Demander par email » (objet prérempli avec le titre et la référence) |
| Message WhatsApp prérempli | Variables `{titre}`, `{reference}`, `{lien}` |
| Biens similaires | Nombre de biens de la même ville en bas de page (0 = aucun) |
| Utiliser les polices du site | Page d'un bien : polices globales d'Elementor (ou du thème) à la place de Young Serif et Figtree |

Un bouton dont le réglage est vide n'est pas affiché. Lecture en PHP : `HS_Settings::get( 'whatsapp' )`.

### Données de test

**HomyStay Capital › Réglages › Données de test** : un bouton crée 10 biens de démonstration (`includes/demo-data.php`) avec des illustrations (`assets/demo/`), un autre les supprime avec leurs images. Les biens de test sont marqués `hs_demo = 1` et leurs images `_hs_demo` : la suppression ne touche jamais vos propres biens. À supprimer avant la mise en ligne.

### Tableau de bord

**HomyStay Capital › Tableau de bord** affiche le nombre de biens par statut. Les KPI financiers (occupation, rendement, cash-flow) arriveront avec le module Finances.

---

## 7. Rôles et droits

| Rôle | Slug | Accès admin | Droits |
| --- | --- | --- | --- |
| Administrateur | `administrator` | Oui | Tout, y compris `hs_manage_finances` |
| Gestionnaire immobilier | `hs_gestionnaire` | Oui | Créer, modifier, publier, supprimer toutes les fiches ; téléverser des fichiers |
| Comptable | `hs_comptable` | Oui | Modifier les fiches ; `hs_manage_finances` |
| Commercial | `hs_commercial` | Oui | Modifier les fiches ; téléverser des fichiers |
| Technicien | `hs_technicien` | Non | Lecture (espace dédié à venir) |
| Voyageur | `hs_voyageur` | Non | Lecture |
| Propriétaire | `hs_proprietaire` | Non | Lecture |
| Locataire | `hs_locataire` | Non | Lecture |
| Investisseur | `hs_investisseur` | Non | Lecture |

Tous les CPT partagent le même jeu de capacités, généré par `capability_type = hs_item` : `edit_hs_items`, `publish_hs_items`, `delete_hs_items`, `edit_others_hs_items`, etc.

**Rôles clients** (technicien, voyageur, propriétaire, locataire, investisseur) :

- ne voient pas la barre d'administration ;
- sont redirigés vers `/mon-espace/` s'ils tentent d'ouvrir `/wp-admin`.

> La page `/mon-espace/` n'est pas encore créée par le plugin. Créez-la (même vide) pour éviter une page 404. L'URL est modifiable avec le filtre `homystay_espace_url`.

Les rôles sont recréés à chaque mise à jour de la version de base de données (`HS_DB_VERSION`). Des capacités ajoutées à la main à ces rôles seraient alors perdues : modifiez plutôt `HS_Roles::roles()`.

---

## 8. Tables financières

Créées à l'activation via `dbDelta()`. Préfixe = préfixe de la base WordPress (par défaut `wp_`). Montants en **FCFA, entiers** (`BIGINT`), devise `XAF`.

| Table | Contenu | Clés principales |
| --- | --- | --- |
| `wp_hs_ecritures` | Revenus et dépenses, rattachés à un bien (et à un contrat ou projet) | `sens` (revenu/depense), `categorie`, `montant`, `date_operation`, `moyen_paiement`, `statut` |
| `wp_hs_echeances` | Loyers appelés et payés, un par contrat et par mois | `contrat_id` + `periode` (AAAA-MM) unique, `montant_du`, `montant_paye`, `statut`, `niveau_relance` |
| `wp_hs_investissements` | Souscriptions d'un investisseur à un projet | `projet_id`, `investisseur_id`, `montant`, `statut` |
| `wp_hs_distributions` | Dividendes versés par souscription et par période | `investissement_id`, `periode`, `montant`, `date_versement` |

Les écritures portent aussi `proprietaire_id`. Valeurs de `sens` : `revenu` (catégories `loyer`, `sejour`), `depense` (`entretien`, `menage`, `assurance`, `taxes`…), `reversement` (versement au propriétaire, `reference_paiement` = `reversement-AAAA-MM`).

> **État actuel** : les tableaux de bord lisent ces tables (§9 ter). Les écritures sont créées par la validation des reversements, la clôture d'une intervention avec coût, et les données de test. La saisie manuelle des encaissements et l'appel de loyer automatique restent à faire.

Mise à jour du schéma : modifier la requête dans `HS_Install::tables()` puis incrémenter `HS_DB_VERSION` dans `homystay-core.php`. Le plugin relance `dbDelta()` automatiquement au chargement suivant.

---

## 9. Shortcodes

### `[hs_recherche]` — Moteur de recherche

Formulaire en GET : ville (liste construite à partir des biens publiés), formule, type, budget maximum, nombre minimum de chambres.

| Attribut | Défaut | Description |
| --- | --- | --- |
| `action` | page courante | Chemin de la page de résultats, ex. `/sejours/` |
| `formule` | — | Formule présélectionnée : `courte`, `longue`, `vente` |

```
[hs_recherche action="/sejours/" formule="courte"]
```

Paramètres envoyés dans l'URL : `hs_ville`, `hs_formule`, `hs_type`, `hs_prix_max`, `hs_chambres`.

### `[hs_biens]` — Liste de biens

| Attribut | Défaut | Description |
| --- | --- | --- |
| `ville` | — | Ville exacte, ex. `Douala` |
| `quartier` | — | Quartier exact |
| `formule` | — | `courte`, `longue` ou `vente` (filtre et prix affiché) |
| `type` | — | `studio`, `appartement`, `villa`, `duplex`, `chambre`, `bureau`, `terrain`, `immeuble` |
| `barre` | `oui` | Barre de filtres (formule, type, ville, quartier) et de tri au-dessus de la grille |
| `limite` | `9` | Biens par page |
| `colonnes` | `3` | 1 à 4 (2 sur tablette, 1 sur mobile) |
| `tri` | `recent` | Tri par défaut : `recent`, `ancien`, `prix_desc`, `prix_asc`, `chambres_desc`, `chambres_asc`, `sdb_desc`, `sdb_asc`. Sans formule, le tri par prix utilise le prix par nuit |
| `filtres_url` | `oui` | `oui` : les critères de `[hs_recherche]` présents dans l'URL remplacent les attributs |
| `pagination` | `oui` | Pagination via le paramètre `hs_page` |
| `populaire` | — | `1` : seulement les biens marqués « populaire » |
| `vide` | « Aucun bien ne correspond… » | Message si aucun résultat |

```
[hs_biens formule="courte" limite="12" tri="prix_asc"]
[hs_biens ville="Yaoundé" formule="longue" colonnes="2"]
```

Le budget maximum (`hs_prix_max`) ne s'applique que si une formule est choisie, car chaque formule a son propre prix.

**Présentation des cartes** : photo avec dégradé, prix en surimpression (`45 000 FCFA /nuit`), badge « En vedette » (bien marqué populaire) ou « Réservé », titre, ligne localisation (quartier et ville cliquables, ils filtrent l'archive), ligne type / formules (`Villa / Séjour · Location`) et bouton favori. Toute la carte est cliquable.

**Barre de filtres** : quatre listes (Toutes formules, Tous types, Toutes villes, Tous quartiers), le nombre de biens trouvés et le tri. Chaque changement recharge la page avec le filtre (`?hs_ville=Douala&hs_tri=prix_asc`) ; la liste des quartiers se limite à la ville choisie. Les villes et quartiers proposés sont ceux des biens publiés. Si la page contient aussi `[hs_recherche]`, le budget et le nombre de chambres saisis sont conservés.

**Favoris** : le cœur mémorise le bien dans le navigateur du visiteur (`localStorage`). Ils ne suivent pas le visiteur d'un appareil à l'autre ; l'enregistrement dans le compte viendra avec les espaces membres.

**Avis (étoiles)** : pas encore affichés, ils arriveront avec le module d'avis clients.

### `[hs_biens_populaires]` — Biens mis en avant

| Attribut | Défaut |
| --- | --- |
| `limite` | `6` |
| `colonnes` | `3` |
| `formule` | — |

Ignore les filtres de l'URL, n'affiche ni barre de filtres ni pagination.

### `[hs_bien_fiche]` — Fiche complète d'un bien

Affiche statut, offres et prix, caractéristiques, vidéo, lien de visite virtuelle et carte OpenStreetMap (si latitude et longitude sont renseignées).

| Attribut | Défaut | Description |
| --- | --- | --- |
| `id` | bien courant | ID d'un bien précis |

Utile pour afficher un bien précis dans une page Elementor. Sur la page d'un bien elle-même, c'est la page complète (§9 bis) qui s'affiche.

### `[hs_champ]` — Un seul champ

Pour composer un modèle de page « bien » ou « projet » champ par champ dans Elementor.

| Attribut | Défaut | Description |
| --- | --- | --- |
| `cle` | — | Nom du champ sans préfixe : `ville`, `prix_courte`, `chambres`… |
| `id` | contenu courant | ID d'une fiche précise |
| `brut` | `non` | `oui` : valeur brute (ex. `villa` au lieu de `Villa`, `45000` au lieu de `45 000 FCFA`) |

```
[hs_champ cle="quartier"], [hs_champ cle="ville"]
[hs_champ cle="prix_courte"]   → 45 000 FCFA
[hs_champ cle="type_bien"]     → Villa
[hs_champ cle="proprietaire_id"] → nom du propriétaire
```

Mise en forme automatique : libellé pour les listes, « Oui/Non » pour les cases, titre de la fiche liée pour les relations, date localisée, montant en FCFA pour les champs `prix_*` et `montant*`.

### `[hs_projets]` — Projets d'investissement

Cartes avec ville, rendement estimé, durée, ticket minimum et barre de progression de la levée.

| Attribut | Défaut | Description |
| --- | --- | --- |
| `statut` | `ouvert` | Un ou plusieurs statuts séparés par des virgules : `a_venir`, `ouvert`, `complet`, `cloture`. Vide = tous |
| `limite` | `3` | Nombre de projets |
| `colonnes` | `3` | 1 à 4 |

```
[hs_projets statut="ouvert,a_venir" limite="6"]
```

---

## 9 bis. Page d'un bien

Chaque bien publié a sa page : `/biens/<slug>/`. Le plugin fournit cette page complète ; aucun shortcode n'est nécessaire.

### Contenu de la page

| Zone | Contenu | Source |
| --- | --- | --- |
| Fil d'Ariane | Accueil / Biens / Ville / Titre | Le lien « Ville » ouvre la liste filtrée |
| Mosaïque | 1 grande photo + jusqu'à 4 petites, bouton « Voir les N photos » | Image mise en avant + Galerie photos |
| Plaque | Bloc vert forêt posé sur les photos : type, titre, quartier et ville | Champs du bien |
| Statut | Pastille (Disponible, Réservé…) et référence | Champs |
| Chiffres clés | Une bande : chambres, salles de bain, surface, capacité, avec icônes | Champs (masqués si vides) |
| Le logement | Contenu de l'éditeur WordPress (ou d'Elementor), 68 caractères par ligne au plus | Éditeur |
| Équipements | Deux colonnes | Champ Équipements |
| Visiter à distance | Vidéo intégrée, lien visite virtuelle | `video_url`, `visite_url` |
| Où se trouve le logement | Carte OpenStreetMap + itinéraire Google Maps | Latitude, longitude |
| Encart (fixe au défilement) | Prix par formule, alerte si non disponible, boutons WhatsApp / Appeler / Email | Champs + Réglages |
| Dans le même secteur | Biens disponibles de la même ville | Réglage « Biens similaires » |

Chaque bloc disparaît si ses données sont vides.

**Lightbox** : un clic sur une photo ouvre la visionneuse plein écran, une photo à la fois, avec compteur (`2 / 6`), légende (celle de la médiathèque), flèches, bande de vignettes, clavier (← → pour naviguer, Début / Fin, Échap pour fermer) et glissement du doigt sur mobile. Les photos voisines sont préchargées ; à la fermeture, le focus revient sur la photo cliquée. Le texte alternatif de chaque photo vient de la médiathèque (à défaut, le titre du bien).

**Mobile** (moins de 720 px) : la mosaïque devient une bande de photos à faire défiler au doigt, la plaque passe sous les photos, l'encart prix/contact remonte juste après les chiffres clés.

**Design** : vert forêt `#17392E`, surface feuille `#EEF3EF`, ocre `#C8901F` en touches, texte ardoise `#33413C`, filets pierre `#D3DCD6`. Titres en **Young Serif**, texte en **Figtree** (Google Fonts, chargées uniquement sur cette page). Styles dans `assets/css/bien.css`.

Personnaliser sans toucher au plugin (Elementor › Code personnalisé ou CSS additionnel) :

```css
.hs-bien {
	--hs-foret: #0f3d5c;                         /* couleur principale (plaque, titres, prix) */
	--hs-ocre: #d4a017;                          /* touches d'accent */
	--hs-font-titre: "Votre police", serif;      /* titres */
	--hs-font-texte: "Votre police", sans-serif; /* texte */
	--hs-largeur: 1240px;                        /* largeur max */
	--hs-sticky-top: 100px;                      /* si l'en-tête du site est fixe */
}
```

Pour garder les polices du site : **HomyStay Capital › Réglages › cocher « Utiliser les polices du site »**. En code, l'équivalent est `add_filter( 'homystay_polices', '__return_false' );`, à placer dans le `functions.php` du thème **actif** (si vous avez un thème enfant, vérifiez qu'il est bien activé dans Apparence › Thèmes) ou dans l'extension Code Snippets, jamais dans Elementor › Code personnalisé. La page prend alors :

- les **polices globales d'Elementor** (Réglages du site › Typographie globale) : « Principale » pour le titre, les intertitres et les prix, « Texte » pour le reste ;
- à défaut, la police du thème pour tout.

Videz le cache (extension de cache, navigateur) après le changement.

### Compatibilité avec le thème

- **Thème classique** (Hello Elementor, Astra…) : la page utilise l'en-tête et le pied du thème (`get_header()` / `get_footer()`), donc aussi l'en-tête et le pied construits avec Elementor Pro.
- **Thème bloc** (Twenty Twenty-Five…) : la page utilise les parties de modèle `header` et `footer` du thème.


### Ordre de priorité

1. **Modèle Elementor Pro** (Theme Builder › Single, condition « Biens ») : s'il existe, c'est lui qui s'affiche et la page du plugin est ignorée.
2. `<thème>/homystay/single-bien.php` : gabarit complet fourni par le thème.
3. `<thème>/homystay/single-bien-contenu.php` : seul le contenu est remplacé, en-tête et pied du thème conservés (**recommandé** pour personnaliser).
4. Gabarits du plugin.

Pour désactiver complètement la page du plugin et laisser le thème afficher le bien : `add_filter( 'homystay_single_template', '__return_false' );` (la fiche courte est alors ajoutée sous la description, voir `homystay_auto_fiche`).

### Page de liste `/biens/` (archive ou page Elementor)

Deux cas, détectés automatiquement :

| Situation | Ce qu'affiche `/biens/` |
| --- | --- |
| Aucune page WordPress avec l'adresse `biens` | L'**archive automatique** du plugin, avec le gabarit d'archive du thème. Elle n'est **pas modifiable avec Elementor** (Elementor affiche « la zone de contenu n'a pas été trouvée »). |
| Une page publiée avec l'adresse (slug) `biens` | **Votre page**, modifiable avec Elementor. Placez-y `[hs_biens]` (et éventuellement `[hs_recherche]`). L'archive automatique est désactivée. |

Dans les deux cas, les fiches restent à `/biens/<slug>/`, et les liens « Biens » / ville / quartier des cartes et du fil d'Ariane pointent vers `/biens/`. Même logique pour `/investir/` et les projets.

Les règles d'URL sont régénérées automatiquement quand la page apparaît ou disparaît. Si `/biens/` affiche encore l'archive juste après la création de la page, rechargez une fois, ou allez dans Réglages › Permaliens › Enregistrer.

L'archive automatique n'affiche que les biens **Disponibles** ou **Réservés** et accepte les filtres d'URL de `[hs_recherche]` : `/biens/?hs_ville=Douala&hs_formule=courte`.

---

## 9 ter. Tableaux de bord et espaces clients

### Back-office : HomyStay Capital › Tableau de bord

Trois vues, selon le rôle. L'administrateur voit les trois en onglets.

| Vue | Rôles | Contenu |
| --- | --- | --- |
| Gestion | Administrateur, gestionnaire | Occupation du mois et évolution, encaissé, impayés, tickets ; agenda du jour (arrivées, départs, rendez-vous) ; à traiter (loyers impayés, assurances à renouveler sous 30 jours, tickets à valider) ; occupation par ville ; biens par statut ; prochaines réservations ; compteurs CRM |
| Finances | Administrateur, comptable | Encaissé, dépenses, commissions, net à reverser ; histogramme 6 mois ; impayés avec palier de relance et lien WhatsApp ; reversements par propriétaire avec bouton **Valider** ; **export CSV** des écritures du mois (séparateur « ; », ouvrable dans Excel) |
| Commercial | Administrateur, commercial | Nouvelles demandes, rendez-vous de la semaine, taux de transformation sur 3 mois, relances en retard ; pipeline en 5 colonnes (filtre par type) ; relances du jour avec WhatsApp, appel, email |

Calculs :

- **Occupation** = nuits réservées (réservations non annulées) ÷ nuits disponibles des biens proposés à la nuit.
- **Commission** = taux du mandat de gestion actif du bien, sinon 15 %. **Net à reverser** = encaissé − commission − dépenses, par propriétaire.
- **Valider les reversements** enregistre une écriture `reversement` par propriétaire ; elle apparaît dans son espace et son relevé.

### Site : la page « Mon espace »

Créée automatiquement à l'adresse `/mon-espace/` avec le shortcode `[hs_espace_membre]`. Un visiteur non connecté voit un formulaire de connexion. Un compte connecté voit l'espace qui le concerne :

| Espace | Relié par | Contenu |
| --- | --- | --- |
| Propriétaire | Fiche Propriétaire (champ « Compte utilisateur » ou même email) | Prochain et dernier versement, nuits réservées ; par bien : calendrier du mois, encaissé, frais, net ; relevés mensuels imprimables |
| Locataire | Fiche Locataire | Loyer en retard ou à venir, bouton « Payer mon loyer » ; contrat ; suivi de la demande en cours en 4 étapes ; formulaire **Signaler une panne** (crée un ticket « Signalé » et prévient l'équipe par email) ; quittances imprimables |
| Voyageur | Réservations au même compte ou au même email | Séjour en cours ou à venir (dates, consignes d'arrivée, itinéraire, contact) ; séjours passés ; factures imprimables ; favoris (cœur des cartes) |
| Investisseur | Fiche Investisseur | Montant investi, dividendes et rendement sur 12 mois, prochain dividende ; histogramme par trimestre ; projets ; relevé des dividendes |
| Technicien | Tickets attribués au compte (champ « Technicien (ID utilisateur) ») | Interventions du jour et à venir ; **Démarrer** puis **Terminer** avec coût et compte rendu (le coût devient une dépense du bien, l'équipe est prévenue par email) |

Une personne qui a plusieurs rôles (propriétaire et investisseur) voit un onglet par espace.

**Relier un client** : créez son compte (Comptes › Ajouter, rôle Propriétaire, Locataire…) avec le **même email** que sa fiche, ou indiquez l'ID du compte dans la fiche. Rien d'autre à faire.

**Aperçu équipe** : connecté avec un compte de l'équipe, la page affiche une barre jaune pour choisir un espace et une personne, et voir exactement ce qu'elle voit. Les boutons d'action y sont désactivés.

**Contact** : les boutons « Payer mon loyer », « Écrire à mon gestionnaire »… ouvrent WhatsApp si un numéro est renseigné dans les Réglages, sinon un email à l'adresse de contact. Le paiement en ligne Orange Money / MTN MoMo n'est pas encore branché : le client écrit à l'équipe, qui lui envoie les coordonnées de paiement.

**Documents** (quittance, relevé, facture, dividendes) : pages imprimables, avec un bouton « Imprimer ou enregistrer en PDF ». Accès limité à la personne concernée et à l'équipe.

---

## 10. Intégration Elementor

### Placer un shortcode

Dans l'éditeur Elementor : glisser un widget **Shortcode** et y coller le shortcode. Le rendu réel s'affiche dans l'aperçu.

### Exemple de pages

| Page | Contenu Elementor | Shortcodes |
| --- | --- | --- |
| Accueil | Héro, présentation, avis, appel à l'action | `[hs_recherche action="/sejours/"]` · `[hs_biens_populaires]` · `[hs_projets limite="3"]` |
| Séjours (`/sejours/`) | Titre, texte | `[hs_recherche formule="courte"]` puis `[hs_biens formule="courte" limite="12"]` |
| Location longue durée | Titre, texte | `[hs_recherche formule="longue"]` puis `[hs_biens formule="longue"]` |
| Investir (`/investir-avec-nous/`) | Argumentaire, documents | `[hs_projets statut="ouvert,a_venir"]` |
| Propriétaires, À propos, Contact | 100 % Elementor | — (formulaires reliés au CRM : prochaine étape) |

> Pour une page « Biens » faite avec Elementor à l'adresse `/biens/`, créez une page WordPress dont le slug est exactement `biens` : elle remplace l'archive automatique (§9 bis). Même chose avec `investir` pour les projets.

### Page d'un bien avec Elementor

Deux possibilités :

1. **Page du plugin** (par défaut, §9 bis) : rien à faire, elle reprend l'en-tête et le pied du site.
2. **Modèle Elementor Pro** (Theme Builder › Single › condition « Biens ») : il remplace automatiquement la page du plugin.
   - composer la page avec les widgets Elementor (titre, image mise en avant, contenu) ;
   - ajouter `[hs_bien_fiche]` ou des `[hs_champ cle="..."]` là où vous voulez les données ;
   - les **balises dynamiques** d'Elementor Pro peuvent aussi lire les champs : *Champ personnalisé* → clé `hs_ville`, `hs_prix_courte`, etc. ;
   - si vous utilisez le widget « Contenu de l'article », désactiver l'ajout automatique de la fiche pour éviter un doublon : `add_filter( 'homystay_auto_fiche', '__return_false' );`.

### Éditer un bien avec Elementor

Possible pour enrichir la description d'un bien (blocs, colonnes, vidéos…) :

1. **Elementor › Réglages › Général › Types de publication** : cocher **Biens**.
2. Ouvrir le bien › **Modifier avec Elementor**.

Pendant l'édition, le plugin s'efface et laisse le gabarit du thème, pour qu'Elementor trouve sa zone de contenu. Sur le site, la page complète du plugin s'affiche, avec votre contenu Elementor à la place de la description.

### Message « la zone de contenu n'a pas été trouvée »

Elementor ne peut éditer que ce qui affiche `the_content()` : pages, articles, biens et projets. Il ne peut pas éditer une **archive** (liste automatique d'un type de contenu), qui n'est pas une page.

| Adresse ouverte avec Elementor | Que faire |
| --- | --- |
| `/biens/`, `/investir/` | Créer une page avec le slug `biens` / `investir` (§9 bis) : elle remplace l'archive |
| Une fiche `/biens/<slug>/` | Activer « Biens » dans les types de publication Elementor (ci-dessus) |
| Toute autre page WordPress (`/sejours/`…) | Fonctionne normalement |
| Une archive de catégorie, d'auteur, de date | Pas une page : utiliser un modèle Theme Builder d'Elementor Pro (Archive) |

### Harmoniser les couleurs

Dans **Elementor › Code personnalisé** (Pro) ou **Apparence › Personnaliser › CSS additionnel** :

```css
:root {
	--hs-accent: #c8102e;      /* couleur principale : prix, boutons */
	--hs-rayon: 4px;           /* arrondi des cartes */
	--hs-texte-doux: #555;     /* textes secondaires */
	--hs-bordure: #ddd;
	--hs-fond-carte: #fff;
	--hs-espace: 24px;         /* espace entre les cartes */
	--hs-largeur: 1200px;      /* largeur max de la page d'un bien */
	--hs-sticky-top: 24px;     /* décalage de l'encart prix si l'en-tête est fixe */
}
```

---

## 11. Personnaliser l'affichage

### Surcharger un gabarit

Le plugin cherche chaque gabarit d'abord dans le thème actif, puis dans le plugin :

```
wp-content/themes/<thème-actif>/homystay/carte-bien.php   ← prioritaire
wp-content/plugins/homystay-core/templates/carte-bien.php ← par défaut
```

Pour modifier le HTML d'une carte : copier `templates/carte-bien.php` dans `<thème>/homystay/carte-bien.php` et l'éditer. Les mises à jour du plugin n'écraseront pas votre version. Utilisez un **thème enfant** pour que les mises à jour du thème ne l'écrasent pas non plus.

| Gabarit | Variables disponibles |
| --- | --- |
| `recherche.php` | `$action`, `$villes`, `$types`, `$formules`, `$valeurs` |
| `barre-biens.php` | `$formules`, `$types`, `$villes`, `$quartiers`, `$tris`, `$valeurs`, `$total`, `$caches` |
| `liste-biens.php` | `$query` (WP_Query), `$colonnes`, `$formule`, `$vide`, `$pagination` |
| `carte-bien.php` | `$bien_id`, `$formule` |
| `fiche-bien.php` | `$bien_id` |
| `single-bien.php` | aucune (boucle WordPress) ; utilise `HS_Single::header()`, `HS_Single::data()`, `HS_Single::footer()` |
| `single-bien-contenu.php` | `$bien_id`, `$statut`, `$statut_label`, `$type`, `$reference`, `$lieu`, `$ville`, `$photos`, `$offres`, `$chiffres`, `$equipements`, `$video`, `$visite`, `$lat`, `$lng`, `$reservable`, `$whatsapp`, `$telephone`, `$email`, `$similaires` |
| `liste-projets.php` | `$query`, `$colonnes` |

### Classes CSS

| Classe | Élément |
| --- | --- |
| `.hs-grille`, `.hs-grille--1…4` | Grille de cartes |
| `.hs-carte`, `.hs-carte__media`, `__corps`, `__lieu`, `__titre`, `__infos`, `__prix` | Carte d'un bien ou d'un projet |
| `.hs-annonce`, `__media`, `__prix`, `__badge`, `__badge--reserve`, `__corps`, `__titre`, `__ligne`, `__favori`, `.est-favori` | Carte d'un bien (style annonce) |
| `.hs-barre`, `__filtres`, `__tri`, `__total`, `__select` | Barre de filtres et de tri |
| `.hs-recherche`, `.hs-recherche__champ` | Formulaire de recherche |
| `.hs-bouton`, `.hs-bouton--secondaire` | Boutons |
| `.hs-fiche`, `__statut`, `__offres`, `__caracs`, `__video`, `__carte` | Fiche d'un bien |
| `.hs-statut--<statut>` | Pastille de statut |
| `.hs-bien`, `.hs-bien__fil`, `__hero`, `__statut`, `__colonnes`, `__principal`, `__lateral`, `__similaires` ; `.hs-plaque`, `.hs-chiffres`, `.hs-section`, `.hs-equipements`, `.hs-encart`, `.hs-btn`, `.hs-pastille` | Page d'un bien (`bien.css`) |
| `.hs-mosaique`, `.hs-mosaique--1…5`, `.hs-mosaique__item`, `.hs-lightbox`, `__scene`, `__nav`, `__vignettes` | Mosaïque et lightbox |
| `.hs-progression`, `.hs-projet__levee` | Levée d'un projet |
| `.hs-pagination`, `.hs-vide` | Pagination, message vide |

---

## 12. Filtres et hooks

À placer dans le `functions.php` du thème enfant ou dans un petit plugin.

| Filtre | Défaut | Effet |
| --- | --- | --- |
| `homystay_load_css` | `true` | `false` : ne charge pas `homystay.css` |
| `homystay_polices` | `true` | `false` : ne charge pas Young Serif et Figtree ; la page d'un bien prend les polices globales d'Elementor, sinon celle du thème |
| `homystay_single_template` | `true` | `false` : désactive la page d'un bien du plugin (le thème l'affiche) |
| `homystay_auto_fiche` | `true` | `false` : n'ajoute plus la fiche courte sous la description quand la page du plugin n'est pas utilisée |
| `homystay_native_metabox` | `true` | `false` : retire le formulaire d'édition natif (si ACF le remplace) |
| `homystay_espace_url` | `/mon-espace/` | Page où sont redirigés les rôles clients |
| `homystay_biens_query_args` | — | Modifier la requête de `[hs_biens]` (`$args`, `$atts`) |

```php
// Tout le design dans Elementor / le thème
add_filter( 'homystay_load_css', '__return_false' );

// Laisser le thème afficher la page d'un bien
add_filter( 'homystay_single_template', '__return_false' );

// Afficher aussi les biens « Occupé » dans les listes
add_filter( 'homystay_biens_query_args', function ( $args ) {
	$args['meta_query'][0]['value'][] = 'occupe';
	return $args;
} );

// Shortcodes : surcharge des attributs par défaut (filtre natif WordPress)
add_filter( 'shortcode_atts_hs_biens', function ( $out ) {
	$out['colonnes'] = 4;
	return $out;
} );
```

---

## 13. API REST

Les CPT sont exposés par l'API REST WordPress (base de l'application mobile en phase 2) :

```
GET /wp-json/wp/v2/hs_bien
GET /wp-json/wp/v2/hs_bien/<id>
GET /wp-json/wp/v2/hs_projet
```

Pour `hs_bien` et `hs_projet`, les champs sont renvoyés dans l'objet `meta` (`hs_ville`, `hs_prix_courte`…). Les CPT privés (propriétaires, locataires, contrats, tickets, polices) ne sont **pas** exposés par l'API ; des routes authentifiées dédiées seront ajoutées pour l'application mobile. Conséquence : ces fiches s'éditent avec l'éditeur classique, pas Gutenberg.

La modification des champs par l'API exige la capacité `edit_hs_items`.

---

## 14. Utiliser ACF (optionnel)

Le plugin fonctionne sans ACF. Pour utiliser l'interface d'ACF à la place du formulaire natif :

1. Créer les groupes de champs ACF avec **exactement les mêmes noms** que les clés en base : `hs_ville`, `hs_statut`, `hs_prix_courte`… (le « nom du champ » ACF, pas son libellé).
2. Utiliser les mêmes valeurs pour les listes (ex. `disponible`, pas `Disponible`) et `1/0` pour les cases.
3. Enregistrer les groupes en JSON local dans le plugin (`homystay-core/acf-json/`) pour les versionner avec le code.
4. Désactiver le formulaire natif : `add_filter( 'homystay_native_metabox', '__return_false' );`

Le front, les shortcodes et l'API lisent les données avec `get_post_meta()` : si ACF est désactivé un jour, le site continue de fonctionner.

---

## 15. Ajouter un champ ou un CPT

### Ajouter un champ

Dans `includes/class-hs-fields.php`, ajouter une ligne dans le tableau du CPT concerné :

```php
'hs_bien' => array(
	// …
	'climatisation' => array( 'label' => 'Climatisation', 'type' => 'checkbox' ),
	'standing'      => array( 'label' => 'Standing', 'type' => 'select', 'options' => array(
		'standard' => 'Standard', 'haut' => 'Haut standing', 'luxe' => 'Luxe',
	), 'default' => 'standard' ),
),
```

Le champ apparaît immédiatement dans le formulaire d'admin, est enregistré sous `hs_climatisation`, exposé à l'API (si le CPT est public) et utilisable avec `[hs_champ cle="climatisation"]`. Pour l'afficher dans les cartes ou la fiche, modifier le gabarit correspondant.

Options d'un champ : `label`, `type`, `options` (listes), `default`, `step` (`'any'` pour un nombre décimal), `post_type` (relations), `help` (texte d'aide).

### Ajouter un CPT

1. Le déclarer dans `HS_CPT::types()` : `'hs_xxx' => array( 'Singulier', 'Pluriel', public?, 'slug-url', supports )`.
2. Déclarer ses champs dans `HS_Fields::all()`.
3. Réenregistrer les permaliens s'il est public.

---

## 16. Sécurité et données sensibles

- Toutes les valeurs saisies sont nettoyées selon leur type (`HS_Fields::sanitize()`), et l'enregistrement est protégé par un nonce et un contrôle de droits.
- Toutes les sorties HTML des gabarits sont échappées (`esc_html`, `esc_attr`, `esc_url`).
- **Pièces d'identité, CNI et coordonnées bancaires** : volontairement **absentes** de cette version. Elles ne doivent pas être téléversées dans la médiathèque WordPress, dont les fichiers sont accessibles publiquement par leur URL. Un stockage sécurisé (dossier protégé hors racine web, accès réservé aux rôles Administrateur et Comptable) est prévu dans une étape suivante.
- Recommandé en production : HTTPS, double authentification pour les rôles internes, sauvegarde quotidienne de la base (y compris les tables `wp_hs_*`).

---

## 17. Feuille de route

| Étape | Contenu | État |
| --- | --- | --- |
| Socle | CPT, champs, rôles, tables, shortcodes biens et projets | Fait (0.1.0) |
| Page d'un bien | Galerie, lightbox, équipements, carte, contact WhatsApp/appel/email, biens similaires, réglages | Fait (0.2.0) |
| Liste des biens | Cartes style annonce, barre de filtres et de tri, favoris locaux | Fait (0.3.0) |
| Refonte page d'un bien | Plaque, mosaïque, lightbox, polices, mobile | Fait (0.4.0) |
| Tableaux de bord | Vues Gestion, Finances, Commercial ; espaces propriétaire, locataire, voyageur, investisseur, technicien ; réservations, CRM, investisseurs | Fait (0.5.0) |
| Réservations en ligne | Calendrier public, blocage des dates, réservation depuis le site, synchro iCal Airbnb/Booking | À faire |
| Saisie financière | Encaissements et dépenses depuis l'admin, appel de loyer mensuel automatique, relances automatiques | À faire |
| Paiement | Orange Money, MTN MoMo, carte (via passerelle locale) | À faire |
| CRM | Formulaires du site → prospects, pipeline, relances | À faire |
| Gestion locative | Appels de loyer, quittances, impayés, relances, états des lieux | À faire |
| Finances | Saisie des écritures, KPI (occupation, rendement brut, cash-flow), export comptable | À faire |
| Espaces membres | `[hs_espace_membre]` : séjours, biens, logement, portefeuille | Fait (0.5.0) |
| Documents sécurisés | CNI, contrats signés, relevés | À faire |
| Phase 2 | Espace investisseur complet, signature électronique, application mobile (API REST) | À faire |

---

## 18. Dépannage

| Problème | Cause probable | Solution |
| --- | --- | --- |
| `/biens/` ou une fiche de bien renvoie une 404 | Règles de réécriture non régénérées | Réglages › Permaliens › Enregistrer |
| Elementor : « la zone de contenu n'a pas été trouvée » sur une fiche de bien | « Biens » non activé dans Elementor | Elementor › Réglages › Types de publication › cocher Biens |
| Elementor : « la zone de contenu n'a pas été trouvée » sur `/biens/` | `/biens/` affiche l'archive automatique, pas une page | Créer (ou publier) une page dont le slug est exactement `biens`, puis l'éditer avec Elementor depuis Pages › Biens |
| La page `biens` a reçu le slug `biens-2` | Une ancienne page ou un élément à la corbeille utilise déjà `biens` | Vider la corbeille / renommer l'ancienne page, puis remettre le slug `biens` |
| `[hs_biens]` affiche « Aucun bien… » | Bien non publié, statut HomyStay différent de Disponible/Réservé, ou formule non cochée | Vérifier ces trois points sur la fiche |
| Le filtre de budget ne fait rien | Aucune formule choisie | Choisir une formule dans la recherche ou ajouter `formule="..."` au shortcode |
| La ville n'apparaît pas dans la recherche | Aucun bien publié dans cette ville | Publier au moins un bien avec cette ville |
| Deux villes identiques dans la liste (« Douala », « douala ») | Saisie différente | Harmoniser l'orthographe des fiches |
| La fiche apparaît deux fois sur la page d'un bien | Modèle Elementor + ajout automatique | `add_filter( 'homystay_auto_fiche', '__return_false' );` |
| Pas de bouton WhatsApp ou Appeler | Réglage vide | HomyStay Capital › Réglages |
| L'encart prix passe sous l'en-tête fixe du site | Décalage du « sticky » | `--hs-sticky-top: 100px;` |
| La page d'un bien n'a pas l'en-tête du site | Thème sans `header.php` ni partie de modèle `header` | Utiliser Hello Elementor ou un modèle Elementor Pro |
| `homystay_polices` à `false` ne change rien | Code placé dans le `functions.php` d'un thème enfant **non activé** (classe `wp-theme-phlox` sans `wp-child-theme-…` dans la page), dans Elementor › Code personnalisé, ou cache | Utiliser plutôt la case « Utiliser les polices du site » dans HomyStay › Réglages, puis vider le cache |
| Les dates « aujourd'hui » sont décalées d'un jour | Fuseau horaire du site en UTC | Réglages › Général › Fuseau horaire : Douala (UTC+1) |
| Pas de bouton WhatsApp dans les espaces | Numéro WhatsApp vide dans les Réglages | Le renseigner ; à défaut, les boutons ouvrent un email |
| Un client connecté voit « compte relié à aucun bien » | Email du compte différent de celui de sa fiche | Mettre le même email, ou l'ID du compte dans la fiche |
| La carte n'apparaît pas | Latitude ou longitude vide | Renseigner les deux (clic droit dans Google Maps › copier les coordonnées) |
| Un locataire est renvoyé vers une page 404 en se connectant | Page `/mon-espace/` absente | Créer la page ou changer `homystay_espace_url` |
| Le menu HomyStay est invisible pour un gestionnaire | Rôle créé avant l'activation | Désactiver puis réactiver le plugin |
| Le style ne correspond pas au thème | CSS du plugin | Variables `--hs-*` (§10) ou `homystay_load_css` à `false` |
