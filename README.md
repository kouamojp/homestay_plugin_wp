# HomyStay Core

Plugin métier de la plateforme **HomyStay Capital** : biens immobiliers, réservations en ligne avec paiement Orange Money / MTN MoMo, gestion locative (propriétaires, locataires, contrats, loyers, reversements), maintenance, assurance, CRM, investisseurs et projets, tableaux de bord par rôle et espaces clients. Les données s'affichent sur le site par des shortcodes, dans des pages construites avec Elementor.

> Ce README est la documentation **technique** (développeurs, intégration). Le **guide d'utilisation** pour l'équipe, sans code, est un document à part.

| | |
| --- | --- |
| Version | 0.6.1 |
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
9 quater. [Réservation en ligne](#9-quater-réservation-en-ligne)
9 quinquies. [Synchronisation entre sites](#9-quinquies-synchronisation-entre-sites)
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
- crée la page **Mon espace** (`/mon-espace/`, shortcode `[hs_espace_membre]`) si elle n'existe pas ;
- enregistre les types de contenu et régénère les règles de réécriture ;
- planifie la tâche horaire `hs_expirer_demandes` (expiration des réservations non payées).

À la **désactivation**, aucune donnée n'est supprimée (fiches, tables et rôles sont conservés) ; seule la tâche planifiée est retirée.

Une mise à jour du plugin qui change `HS_DB_VERSION` relance automatiquement la création des tables, des rôles et de la page Mon espace au chargement suivant : pas besoin de désactiver / réactiver.

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
│   ├── css/reservation.css        Formulaire de réservation, calendrier, page de paiement
│   └── js/
│       ├── bien.js                Lightbox photos de la page d'un bien
│       ├── liste.js               Barre de filtres (envoi auto) et favoris
│       ├── reservation.js         Calendrier des disponibilités, prix, étapes, boutons « Copier »
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
│   ├── class-hs-reservation.php   Réservation en ligne : disponibilités, prix, paiement Mobile Money, confirmation, compte voyageur
│   ├── class-hs-sync.php          Synchronisation entre sites : export .zip, import avec recâblage des numéros
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
    ├── single-bien-contenu.php    Contenu de la page d'un bien (avec le formulaire de réservation)
    ├── page-reservation.php       Page de paiement / suivi d'une réservation (en-tête + pied du thème)
    ├── reservation-suivi.php      Contenu de cette page : instructions Orange Money / MTN MoMo, état, consignes
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
| `nuits_min` | Nuits minimum (réservation en ligne) | nombre (1 si vide) |
| `frais_menage` | Frais de ménage, une fois par séjour (FCFA) | nombre |
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

`bien_id`, `categorie` (plomberie, électricité, climatisation, nettoyage, autre), `etape` (signalé, validé, intervention, clôturé), `bloquant`, `technicien_id` (compte du technicien : il voit le ticket dans son espace), `technicien_nom`, `date_intervention`, `creneau`, `urgent`, `signale_par`, `cout`, `imputation` (propriétaire, locataire, HomyStay). La description du problème et la photo utilisent l'éditeur et l'image mise en avant.

### Police d'assurance (`hs_police`)

`bien_id`, `type_police` (habitation, loyers impayés, multirisque immeuble), `numero`, `compagnie`, `prime`, `echeance`.

### Projet d'investissement (`hs_projet`)

`ville`, `montant_recherche`, `montant_leve`, `ticket_min`, `rendement_estime` (%/an), `duree_mois`, `avancement` (texte libre : « Gros œuvre terminé »), `statut` (à venir, ouvert, complet, clôturé).

### Réservation (`hs_reservation`)

| Clé | Libellé | Valeurs |
| --- | --- | --- |
| `bien_id` | Bien | relation → `hs_bien` |
| `voyageur`, `telephone`, `email` | Voyageur | texte ; l'email relie la réservation au compte du voyageur |
| `user_id` | Compte utilisateur | ID, rempli à la confirmation |
| `arrivee`, `depart`, `personnes` | Séjour | dates `Y-m-d` (départ = matin du dernier jour, non compté comme nuit) |
| `montant` | Montant total (FCFA) | nuits × prix + frais de ménage |
| `a_payer` | À payer pour confirmer | acompte (Réglages) |
| `paiement` | Paiement | `attente`, `verification` (ID saisi), `acompte`, `paye`, `rembourse` |
| `operateur`, `numero_paiement`, `transaction` | Paiement déclaré | `orange`, `mtn`, `especes`, `virement` ; numéro ; ID de transaction |
| `montant_paye` | Montant reçu | cumulé à chaque paiement enregistré |
| `statut` | Statut | `demande`, `confirmee`, `terminee`, `annulee`, `expiree` |
| `source` | Source | `site`, `whatsapp`, `telephone`, `airbnb`, `booking`, `autre` |
| `consignes`, `message` | Consignes d'arrivée, message du voyageur | texte |

Métadonnées internes (hors formulaire) : `hs_cle` (jeton du lien de paiement), `hs_expire_le` (UTC), `hs_menage` (frais de ménage au moment de la réservation), `hs_paiement_declare_le`, `hs_confirmation_envoyee`.

Les réservations `annulee` et `expiree` sont ignorées partout (calendrier, occupation, espaces) : `HS_Donnees::reservations_actives()`.

### Contact CRM (`hs_contact`)

`type_contact` (propriétaire, investisseur, voyageur, locataire), `etape` (nouveau, contacté, rendez-vous, proposition, contrat signé, perdu), `telephone`, `email`, `source`, `besoin`, `rdv` + `rdv_heure`, `relance` (date de prochaine relance), `action` (prochaine action), `date_signature`.

### Investisseur (`hs_investisseur`)

`telephone`, `email`, `user_id`, `mode_versement` (virement, Orange Money, MTN MoMo). Ses souscriptions et dividendes sont dans les tables `hs_investissements` et `hs_distributions` (§8).

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
| Utiliser les polices du site | Pages du plugin (bien, paiement, Mon espace) : polices globales d'Elementor (ou du thème) à la place de Young Serif et Figtree |
| Réservation en ligne | Activation, numéros Orange Money / MTN MoMo, acompte, délai de paiement, heures, consignes : voir §9 quater |

Un bouton dont le réglage est vide n'est pas affiché. Lecture en PHP : `HS_Settings::get( 'whatsapp' )`.

### Données de test

**HomyStay Capital › Réglages › Données de test** : un bouton crée 10 biens de démonstration (`includes/demo-data.php`) avec des illustrations (`assets/demo/`), un autre les supprime avec leurs images. Les biens de test sont marqués `hs_demo = 1` et leurs images `_hs_demo` : la suppression ne touche jamais vos propres biens. À supprimer avant la mise en ligne.

### Tableau de bord

**HomyStay Capital › Tableau de bord** : vues Gestion, Finances et Commercial selon le rôle (§9 ter).

### Autres automatismes

- **Réservation en ligne** : blocage des dates, expiration des demandes non payées, confirmation avec écriture de revenu et création du compte voyageur (§9 quater).
- **Panne signalée par un locataire** : crée un ticket « Signalé » et prévient l'équipe ; **intervention terminée** par le technicien : le coût devient une dépense du bien (sauf imputation au locataire).
- **Validation des reversements** : écrit une ligne `reversement` par propriétaire et rend le relevé visible dans son espace.

---

## 7. Rôles et droits

| Rôle | Slug | Accès admin | Droits |
| --- | --- | --- | --- |
| Administrateur | `administrator` | Oui | Tout, y compris `hs_manage_finances` |
| Gestionnaire immobilier | `hs_gestionnaire` | Oui | Créer, modifier, publier, supprimer toutes les fiches ; téléverser des fichiers |
| Comptable | `hs_comptable` | Oui | Modifier les fiches ; `hs_manage_finances` |
| Commercial | `hs_commercial` | Oui | Modifier les fiches ; téléverser des fichiers |
| Technicien | `hs_technicien` | Non | Espace « Mes interventions » : démarrer, terminer un ticket attribué |
| Voyageur | `hs_voyageur` | Non | Lecture |
| Propriétaire | `hs_proprietaire` | Non | Lecture |
| Locataire | `hs_locataire` | Non | Lecture |
| Investisseur | `hs_investisseur` | Non | Lecture |

Tous les CPT partagent le même jeu de capacités, généré par `capability_type = hs_item` : `edit_hs_items`, `publish_hs_items`, `delete_hs_items`, `edit_others_hs_items`, etc.

**Rôles clients** (technicien, voyageur, propriétaire, locataire, investisseur) :

- ne voient pas la barre d'administration ;
- sont redirigés vers `/mon-espace/` s'ils tentent d'ouvrir `/wp-admin`.

> La page `/mon-espace/` est créée automatiquement (shortcode `[hs_espace_membre]`). Elle peut être retravaillée avec Elementor tant que le shortcode y reste. L'URL de redirection est modifiable avec le filtre `homystay_espace_url`. Les formulaires des espaces passent par `admin-post.php`, exclu de la redirection.

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

> **État actuel** : les tableaux de bord lisent ces tables (§9 ter). Les écritures sont créées par la confirmation d'un paiement de séjour (`revenu` / `sejour`, avec l'ID de transaction en `reference_paiement`), la validation des reversements, la clôture d'une intervention avec coût, et les données de test. La saisie manuelle des encaissements, l'appel de loyer automatique et la saisie des souscriptions restent à faire.

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

### `[hs_espace_membre]` — Espace client

Sans attribut. Affiche le formulaire de connexion, ou l'espace du compte connecté (propriétaire, locataire, voyageur, investisseur, technicien), avec des onglets si le compte a plusieurs espaces. Placé automatiquement sur la page `/mon-espace/` ; détails au §9 ter.

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
| Encart (fixe au défilement) | Bien réservable en ligne : prix par nuit, calendrier, voyageurs, prix calculé, formulaire de réservation (§9 quater), puis les autres formules et un contact discret. Sinon : prix par formule, alerte si non disponible, boutons WhatsApp / Appeler / Email | Champs + Réglages |
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
| Gestion | Administrateur, gestionnaire | Occupation du mois et évolution, encaissé, impayés, tickets ; agenda du jour (arrivées, départs, rendez-vous) ; à traiter (paiements de séjour à vérifier, réservations en attente de paiement, loyers impayés, assurances à renouveler sous 30 jours, tickets à valider) ; occupation par ville ; biens par statut ; prochaines réservations ; compteurs CRM |
| Finances | Administrateur, comptable | Encaissé, dépenses, commissions, net à reverser ; histogramme 6 mois ; impayés avec palier de relance et lien WhatsApp ; reversements par propriétaire avec bouton **Valider** ; **export CSV** des écritures du mois (séparateur « ; », ouvrable dans Excel) |
| Commercial | Administrateur, commercial | Nouvelles demandes, rendez-vous de la semaine, taux de transformation sur 3 mois, relances en retard ; pipeline en 5 colonnes (filtre par type) ; relances du jour avec WhatsApp, appel, email |

Calculs :

- **Occupation** = nuits réservées (réservations ni annulées ni expirées, demandes en cours comprises) ÷ nuits disponibles des biens proposés à la nuit, sur le mois en cours.
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

**Comptes voyageurs** : créés automatiquement quand une réservation passe à « Confirmée » (voir §9 quater).

**Relier un client** : créez son compte (Comptes › Ajouter, rôle Propriétaire, Locataire…) avec le **même email** que sa fiche, ou indiquez l'ID du compte dans la fiche. Rien d'autre à faire.

**Aperçu équipe** : connecté avec un compte de l'équipe, la page affiche une barre jaune pour choisir un espace et une personne, et voir exactement ce qu'elle voit. Les boutons d'action y sont désactivés.

**Contact** : les boutons « Payer mon loyer », « Écrire à mon gestionnaire »… ouvrent WhatsApp si un numéro est renseigné dans les Réglages, sinon un email à l'adresse de contact. Pour le loyer, le paiement passe encore par l'équipe (elle envoie les coordonnées de paiement). Les séjours se paient en ligne : voir §9 quater.

**Documents** (quittance, relevé, facture, dividendes) : pages imprimables, avec un bouton « Imprimer ou enregistrer en PDF ». Accès limité à la personne concernée et à l'équipe.

---

## 9 quater. Réservation en ligne

Les biens **loués à la nuit** se réservent depuis leur page, sans intervention de l'équipe jusqu'au paiement.

### Conditions pour qu'un bien soit réservable

- **Location courte durée** cochée et **Prix par nuit** renseigné ;
- statut **Disponible** ;
- Réglages › Réservation en ligne › **Activer le calendrier** coché (par défaut).

Sinon, la page garde l'encart de contact habituel (WhatsApp, appel, email).

### Parcours du voyageur

1. **Page du bien** : calendrier des disponibilités (nuits déjà prises barrées, 12 mois), nombre de voyageurs limité à la capacité, prix calculé en direct (nuits × prix + frais de ménage, acompte). Clic sur **Réserver** → nom, téléphone, email (facultatif mais conseillé), message → **Envoyer ma demande**.
2. La réservation est créée : statut **Demande**, paiement **En attente**, source **Site**. Les dates sont **bloquées pendant le délai de paiement** (24 h par défaut). L'équipe et le voyageur reçoivent un email.
3. **Page de paiement** (`/?hs_resa=ID&cle=…`, lien unique envoyé par email) : montant à envoyer, numéros **Orange Money** et **MTN MoMo** avec boutons « Copier », codes `#150#` et `*126#`. Après le transfert, le voyageur choisit l'opérateur, saisit le numéro qui a payé et l'**ID de transaction** du SMS → paiement **À vérifier**, l'équipe est prévenue.
4. Sans paiement déclaré dans le délai, la demande passe à **Expirée** et les dates se libèrent (tâche planifiée toutes les heures, et vérification à chaque affichage du calendrier). Un paiement déclaré après expiration relance la demande si les dates sont encore libres.

### Côté équipe

- **Tableau de bord › À traiter** : « Paiement à vérifier » en tête, puis les réservations en attente de paiement.
- **Réservations** : colonnes Bien, Séjour, Montant, Paiement, Statut. Dans une réservation, l'encadré **Suivi de la réservation** affiche le paiement déclaré (opérateur, numéro, ID de transaction, montant attendu) et les boutons :
  - **Paiement reçu, confirmer** : à cliquer après avoir retrouvé la transaction dans votre compte Orange Money ou MTN MoMo. Enregistre le montant en **revenu** (table `hs_ecritures`, catégorie `sejour`, avec la référence de transaction), passe la réservation à **Confirmée**, crée le compte voyageur et envoie la confirmation ;
  - **Paiement introuvable** : remet le paiement en attente, garde les dates 6 h et demande au voyageur de ressaisir l'ID ;
  - **Annuler la réservation** : libère les dates et prévient le voyageur ;
  - **Écrire au voyageur sur WhatsApp** : message prérempli (lien de paiement ou confirmation).
- Passer à la main le statut à **Confirmée** (réservation saisie depuis WhatsApp, Airbnb…) déclenche aussi la confirmation et le compte voyageur, une seule fois. Ce passage n'enregistre pas de revenu : saisissez-le à part.

### Compte voyageur

À la confirmation, si la réservation a un email :
- un compte existe déjà avec cet email → il est relié à la réservation ;
- sinon un compte **Voyageur** est créé (identifiant tiré de l'email) et l'email de confirmation contient le **lien pour choisir le mot de passe**.

Le voyageur retrouve ensuite ses séjours, consignes et factures sur `/mon-espace/`. Une demande non payée y apparaît avec le bouton **Payer**.

### Réglages (HomyStay Capital › Réglages › Réservation en ligne)

| Réglage | Défaut | Rôle |
| --- | --- | --- |
| Réservation sur le site | coché | Affiche le calendrier et le bouton « Réserver » |
| Orange Money : numéro, titulaire | vide | Laissé vide = moyen non proposé |
| MTN MoMo : numéro, titulaire | vide | Idem |
| Acompte pour confirmer | 50 % | 100 = paiement complet ; arrondi à la centaine supérieure |
| Délai pour payer | 24 h | Durée de blocage des dates sans paiement |
| Heures d'arrivée et de départ | 14 h / 11 h | Affichées au voyageur |
| Consignes d'arrivée par défaut | vide | Copiées dans chaque réservation confirmée sans consignes propres |

Sans aucun numéro Mobile Money, la page de paiement indique « Nous vous contactons » et l'équipe gère le paiement par WhatsApp.

### Sécurité

- Le prix est **recalculé côté serveur** ; la disponibilité est revérifiée juste avant l'enregistrement (deux voyageurs ne peuvent pas prendre les mêmes nuits).
- Jeton anti-falsification (nonce) et champ piège invisible contre les robots.
- La page de paiement n'est accessible qu'avec la clé du lien, le compte du voyageur ou un compte de l'équipe.
- Le plugin ne vérifie pas lui-même les transactions Mobile Money : c'est l'équipe qui confirme, d'après son compte opérateur.

### Paiement automatique (plus tard)

Pour brancher une passerelle (CinetPay, NotchPay, Campay…), son webhook n'a qu'à appeler :

```php
do_action( 'homystay_paiement_recu', $reservation_id, $montant, $reference );
```

Cela enregistre le revenu, confirme la réservation, crée le compte et envoie la confirmation, exactement comme le bouton de l'équipe.

### Gabarits

`templates/reservation-suivi.php` (contenu de la page de paiement) et `templates/page-reservation.php` (en-tête et pied du thème autour) sont surchargeables dans `<thème>/homystay/`. Le formulaire de réservation est dans `single-bien-contenu.php`.

---

## 9 quinquies. Synchronisation entre sites

**HomyStay Capital › Synchronisation** (administrateurs) copie **uniquement les données HomyStay** d'un site à un autre, par exemple du site local vers le staging. Les pages, Elementor, le thème, les autres extensions et les comptes administrateurs ne sont pas touchés.

### Ce qui est copié

| Données | Détail |
| --- | --- |
| Fiches | Tous les types `hs_*` (publiés, brouillons, en attente, privés, planifiés) avec leurs champs `hs_…` et l'image mise en avant |
| Photos | Image mise en avant et galerie de chaque fiche (case « Inclure les photos ») |
| Comptes | Comptes ayant un rôle HomyStay, et comptes auxquels une fiche ou une ligne renvoie. **Sans mot de passe** |
| Tables | `wp_hs_ecritures`, `wp_hs_echeances`, `wp_hs_investissements`, `wp_hs_distributions` |
| Réglages | Option `hs_reglages`, importée seulement si la case est cochée à l'import |

Non copiés : le contenu Elementor d'une fiche de bien (méta `_elementor_*`), les images insérées dans le texte de la description, les pages du site.

### Mode d'emploi (local → staging)

1. Les deux sites ont la **même version du plugin** (l'import refuse sinon : `HS_DB_VERSION` différent).
2. Site local : **Exporter** → un fichier `homystay-sync-<site>-<date>.zip`. Cocher « Inclure les données de test » pour emporter aussi les biens de démonstration.
3. Staging : **sauvegarde de la base**, puis **Importer** ce fichier.
4. Lire le compte rendu : fiches créées / mises à jour, photos, comptes, lignes de tables.

### Comment les liens sont préservés

- Chaque fiche et chaque photo reçoit au premier export un identifiant unique (méta `_hs_uid`, UUID). À l'import, une fiche portant le même identifiant est **mise à jour**, sinon elle est **créée** : réimporter ne crée jamais de doublon.
- Premier import sur un site qui a déjà des fiches HomyStay : une fiche **sans identifiant**, de même type et de même slug (ex. `villa-contemporaine-a-makepe`), est reprise et mise à jour au lieu d'être dupliquée (0.6.1).
- Les numéros changent d'un site à l'autre. L'import les recâble partout : champs de type relation (`hs_bien_id`, `hs_proprietaire_id`…), `_thumbnail_id`, `hs_galerie`, comptes (`hs_user_id`, `hs_technicien_id`), colonnes des tables (`bien_id`, `contrat_id`, `projet_id`, `proprietaire_id`, `investisseur_id`, `user_id`, `created_by`), `ecriture_id` des échéances et `investissement_id` des dividendes.
- Comptes : retrouvés **par email** ; sinon créés avec leurs rôles HomyStay et un mot de passe aléatoire, sans email envoyé. Le client choisit son mot de passe avec « Mot de passe oublié » sur `/mon-espace/`. Un rôle administrateur n'est jamais transmis.
- Un lien vers une fiche absente du fichier (par exemple un bien de test non exporté) devient 0. Les lignes de tables qui renvoient à une fiche non exportée sont écartées à l'export.

### Options d'import

| Option | Défaut | Effet |
| --- | --- | --- |
| Remplacer les tables financières | coché | Vide les 4 tables du site puis insère celles du fichier. Les numéros de lignes changent ; les lignes de test restent reconnues par « Supprimer les données de test » |
| Importer aussi les réglages | décoché | Remplace les réglages du site (WhatsApp, numéros Mobile Money, email…) |
| Supprimer les fiches absentes | décoché | Met à la corbeille les fiches **déjà importées** (avec `_hs_uid`) qui ne sont plus dans le fichier. Les fiches créées directement sur le site ne sont jamais touchées |

### Limites

- **Sens unique** : le site qui exporte fait référence. Une fiche modifiée des deux côtés garde la version du fichier ; avec « Remplacer les tables », les écritures saisies sur le site d'arrivée sont perdues.
- Taille : l'archive passe par un envoi de fichier ; la taille maximale du serveur d'arrivée (`upload_max_filesize`, `post_max_size`) est affichée sur l'écran. Au-delà, exporter sans les photos.
- Extension PHP `zip` requise des deux côtés.

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
| `single-bien-contenu.php` | `$bien_id`, `$statut`, `$statut_label`, `$type`, `$reference`, `$lieu`, `$ville`, `$photos`, `$offres`, `$chiffres`, `$equipements`, `$video`, `$visite`, `$lat`, `$lng`, `$reservable`, `$whatsapp`, `$telephone`, `$email`, `$similaires`, `$resa` (calendrier et prix, ou `null`), `$capacite` |
| `liste-projets.php` | `$query`, `$colonnes` |
| `page-reservation.php` | aucune ; en-tête et pied du thème autour de `reservation-suivi.php` |
| `reservation-suivi.php` | `$resa_id`, `$cle` |
| `espace/<espace>.php` | `$ctx` (espace, aperçu, fiche ou `user_id` / `email`) |
| `espace/document.php` | `$doc` (titre, période, qui, bien, lignes, total, texte) |
| `admin/<vue>.php` | `$mois` (`Y-m`) |

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
| `.hs-resa`, `__champ`, `__choix`, `__recap`, `__total`, `__acompte`, `__coord`, `__note` ; `.hs-cal`, `__grille`, `__jour` (`.est-pris`, `.est-debut`, `.est-fin`, `.est-dedans`) | Formulaire de réservation et calendrier (`reservation.css`) |
| `.hs-suivi`, `__tete`, `__colonnes`, `__fil` ; `.hs-moyen`, `.hs-moyen--orange`, `--mtn` ; `.hs-paiement`, `.hs-recap` | Page de paiement / suivi |
| `.es`, `.es-carte`, `.es-bande`, `.es-btn`… | Espaces clients (`espace.css`) |
| `.tb-*` | Tableau de bord du back-office (`tableau.css`) |
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
| `homystay_reservation_active` | `true` | `false` : pas de réservation en ligne pour un bien (`$bien_id`) |
| `homystay_devis` | — | Modifier le prix d'un séjour (`$devis`, `$bien_id`, `$arrivee`, `$depart`, `$personnes`) : tarif week-end, réduction à la semaine… |
| `homystay_creer_compte_voyageur` | `true` | `false` : ne crée pas de compte à la confirmation (`$reservation_id`) |

| Action | Quand |
| --- | --- |
| `homystay_reservation_demandee` | Demande envoyée depuis le site (`$reservation_id`) |
| `homystay_paiement_declare` | Le voyageur a saisi son ID de transaction |
| `homystay_paiement_recu` | À appeler par une passerelle de paiement (`$id`, `$montant`, `$reference`) |
| `homystay_reservation_confirmee` | Réservation confirmée, compte créé, email envoyé |

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

Pour `hs_bien` et `hs_projet`, les champs sont renvoyés dans l'objet `meta` (`hs_ville`, `hs_prix_courte`…). Les CPT privés (propriétaires, locataires, contrats, tickets, polices, réservations, CRM, investisseurs) ne sont **pas** exposés par l'API ; des routes authentifiées dédiées seront ajoutées pour l'application mobile. Conséquence : ces fiches s'éditent avec l'éditeur classique, pas Gutenberg.

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
- Formulaires publics (réservation, paiement) : nonce, champ piège anti-robot, prix et disponibilité recalculés côté serveur. La page de paiement exige le jeton du lien (`hash_equals`), le compte du voyageur ou un rôle de l'équipe.
- Formulaires des espaces (signaler une panne, démarrer / terminer une intervention) : nonce + contrôle que la fiche appartient bien au compte connecté. Documents imprimables : accès limité à la personne concernée et à l'équipe.
- Le plugin **ne vérifie pas** les transactions Mobile Money : l'ID saisi par le voyageur est une déclaration ; seule la confirmation de l'équipe (ou d'une passerelle) enregistre un revenu.
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
| Réservations en ligne | Calendrier public, blocage des dates, réservation depuis le site, paiement Orange Money / MTN MoMo déclaré et vérifié par l'équipe, compte voyageur automatique | Fait (0.6.0) |
| Synchronisation entre sites | Export / import des données HomyStay (local → staging) avec recâblage des numéros | Fait (0.6.0) |
| Synchro des calendriers | iCal Airbnb / Booking (import et export) | À faire |
| Saisie financière | Encaissements et dépenses depuis l'admin, appel de loyer mensuel automatique, relances automatiques | À faire |
| Paiement automatique | Passerelle (CinetPay, NotchPay, Campay) branchée sur `homystay_paiement_recu`, carte bancaire, loyers | À faire |
| CRM | Pipeline, relances, rendez-vous dans le tableau de bord | Fait (0.5.0) |
| CRM : formulaires | Formulaires du site (« Déposer un bien », « Investir ») → fiches CRM automatiques | À faire |
| Gestion locative | Impayés, quittances, reversements et relevés | Fait (0.5.0) |
| Gestion locative : suite | Appel de loyer automatique, relances automatiques, états des lieux | À faire |
| Investissement : saisie | Souscriptions et dividendes depuis l'admin | À faire |
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
| Un locataire est renvoyé vers une page 404 en se connectant | Page `/mon-espace/` supprimée ou renommée | Remettre une page `mon-espace` avec `[hs_espace_membre]` (elle est recréée à la prochaine mise à jour de base) ou changer `homystay_espace_url` |
| Pas de calendrier ni de bouton « Réserver » sur un bien | Location courte durée non cochée, prix par nuit vide, statut autre que Disponible, ou réservation en ligne désactivée | Vérifier la fiche et HomyStay › Réglages |
| La page de paiement dit « Nous vous contactons » | Aucun numéro Orange Money ni MTN MoMo | Les renseigner dans les Réglages |
| Le voyageur ne reçoit ni lien de paiement ni confirmation | Emails WordPress non envoyés (WAMP local, hébergement sans SMTP) | Extension SMTP ; en attendant, bouton « Écrire au voyageur sur WhatsApp » de la réservation |
| Des demandes non payées bloquent encore les dates | WP-Cron ne tourne pas (site sans visites) | L'expiration est aussi vérifiée à chaque affichage d'un calendrier ; en production, un vrai cron qui appelle `wp-cron.php` |
| Synchronisation : « Versions différentes » | Le plugin n'a pas la même version sur les deux sites | Mettre à jour le plugin là où il est en retard |
| Synchronisation : « Le fichier dépasse la taille maximale » | Limite d'envoi du serveur d'arrivée | Exporter sans les photos, ou augmenter `upload_max_filesize` et `post_max_size` |
| Synchronisation : un client importé ne peut pas se connecter | Les mots de passe ne sont pas copiés | « Mot de passe oublié » sur `/mon-espace/`, ou nouveau mot de passe dans Comptes |
| « Ce lien de réservation n'est pas valide » | Lien tronqué (clé manquante) ou réservation supprimée | Renvoyer le lien depuis l'encadré « Suivi de la réservation » |
| Le menu HomyStay est invisible pour un gestionnaire | Rôle créé avant l'activation | Désactiver puis réactiver le plugin |
| Le style ne correspond pas au thème | CSS du plugin | Variables `--hs-*` (§10) ou `homystay_load_css` à `false` |
