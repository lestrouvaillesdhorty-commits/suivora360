-- Schéma de référence Suivora360 (MySQL / MariaDB)
-- À utiliser uniquement si vous préférez créer les tables manuellement via phpMyAdmin.
-- Normalement, public/install.php crée ces tables automatiquement — ce fichier
-- n'est là qu'en secours ou pour consultation.

CREATE TABLE IF NOT EXISTS organisations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    abonnement_offre VARCHAR(20) NULL,
    abonnement_prix DECIMAL(12,2) NULL,
    abonnement_devise VARCHAR(3) NULL,
    abonnement_echeance DATE NULL,
    abonnement_notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS filiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT NOT NULL,
    nom VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT NOT NULL,
    nom VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    mot_de_passe_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'lecture_seule',
    actif TINYINT(1) NOT NULL DEFAULT 1,
    is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
    doit_changer_mdp TINYINT(1) NOT NULL DEFAULT 0,
    filiale_active_id INT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS utilisateur_filiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    filiale_id INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS demandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filiale_id INT NOT NULL,
    reference VARCHAR(30) NOT NULL,
    objet VARCHAR(255) NOT NULL,
    message TEXT,
    canal VARCHAR(50),
    expediteur_nom VARCHAR(255),
    expediteur_entreprise VARCHAR(255),
    expediteur_email VARCHAR(255),
    expediteur_telephone VARCHAR(50),
    recue_le DATE,
    activite VARCHAR(255),
    responsable_id INT,
    priorite VARCHAR(20) DEFAULT 'normale',
    echeance DATE,
    statut VARCHAR(20) NOT NULL DEFAULT 'a_qualifier',
    client_id INT,
    destination_pays VARCHAR(100),
    lieu_livraison VARCHAR(255),
    incoterm_souhaite VARCHAR(10),
    mode_paiement_souhaite VARCHAR(30),
    qualification_type VARCHAR(30),
    qualification_status VARCHAR(30),
    qualification_notes TEXT,
    qualified_at DATETIME,
    qualified_by INT,
    linked_request_id INT,
    linked_dossier_id INT,
    original_started_at DATE,
    registered_in_suivora_at DATE,
    external_reference VARCHAR(100),
    external_source VARCHAR(100),
    takeover_stage VARCHAR(50),
    historical_takeover TINYINT(1) NOT NULL DEFAULT 0,
    -- [ajouté v14, module Demandes 04/10] Date souhaitée PAR LE CLIENT,
    -- distincte de `echeance` (échéance interne de traitement).
    date_souhaitee_client DATE,
    -- [ajouté v14] Notes internes d'affectation, utilisables dès la
    -- création/modification — distinctes de `qualification_notes`.
    notes_internes TEXT,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filiale_id INT NOT NULL,
    code VARCHAR(20),
    nom VARCHAR(255) NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'actif',
    type VARCHAR(30),
    email VARCHAR(255),
    telephone VARCHAR(50),
    pays VARCHAR(100),
    ville VARCHAR(100),
    code_postal VARCHAR(20),
    adresse VARCHAR(255),
    adresse_livraison VARCHAR(255),
    secteur VARCHAR(100),
    siret VARCHAR(50),
    tva VARCHAR(50),
    incoterm_habituel VARCHAR(10),
    mode_transport_habituel VARCHAR(30),
    conditions_paiement VARCHAR(30),
    fonction_contact VARCHAR(100),
    notes TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    relation VARCHAR(10) NOT NULL DEFAULT 'client', -- migrate_v21 : prospect / client
    responsable_id INT NULL,
    devise_preferee VARCHAR(10) NULL,
    contact_prenom VARCHAR(100) NULL,
    contact_nom VARCHAR(100) NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- migrate_v21 : contacts supplémentaires (le contact principal reste sur la ligne clients)
CREATE TABLE IF NOT EXISTS client_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    prenom VARCHAR(100) NOT NULL DEFAULT '',
    nom VARCHAR(100) NOT NULL DEFAULT '',
    fonction VARCHAR(100) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL DEFAULT '',
    telephone VARCHAR(50) NOT NULL DEFAULT '',
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- migrate_v21 : adresses supplémentaires (facturation / livraison)
CREATE TABLE IF NOT EXISTS client_adresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    type VARCHAR(15) NOT NULL DEFAULT 'livraison',
    libelle VARCHAR(100) NOT NULL DEFAULT '',
    adresse VARCHAR(255) NOT NULL DEFAULT '',
    code_postal VARCHAR(20) NOT NULL DEFAULT '',
    ville VARCHAR(100) NOT NULL DEFAULT '',
    pays VARCHAR(100) NOT NULL DEFAULT '',
    par_defaut TINYINT(1) NOT NULL DEFAULT 0,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS client_pieces_jointes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    categorie VARCHAR(30) NOT NULL DEFAULT 'autre',
    nom_original VARCHAR(255) NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    taille INT NOT NULL DEFAULT 0,
    type_mime VARCHAR(100) NOT NULL DEFAULT '',
    uploaded_by INT,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- migrate_v22 : liens privés de l'espace client externe
CREATE TABLE IF NOT EXISTS client_portail_liens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expire_le DATETIME NOT NULL,
    revoque_le DATETIME NULL,
    nb_acces INT NOT NULL DEFAULT 0,
    dernier_acces DATETIME NULL,
    cree_par INT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fournisseurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filiale_id INT NOT NULL,
    code VARCHAR(20),
    nom VARCHAR(255) NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'a_qualifier',
    email VARCHAR(255),
    telephone VARCHAR(50),
    pays VARCHAR(100),
    ville VARCHAR(100),
    adresse VARCHAR(255),
    devise VARCHAR(10),
    secteur VARCHAR(100),
    site_web VARCHAR(255),
    categories_produits VARCHAR(255),
    marques VARCHAR(255),
    pays_desservis VARCHAR(255),
    incoterms_pratiques VARCHAR(255),
    quantite_min VARCHAR(100),
    fonction_contact VARCHAR(100),
    note_prix TINYINT,
    note_qualite TINYINT,
    note_delai TINYINT,
    note_reactivite TINYINT,
    note_conformite TINYINT,
    note_engagements TINYINT,
    notes TEXT,
    nom_commercial VARCHAR(255),
    types_partenaire VARCHAR(255),
    specialites VARCHAR(500),
    activites VARCHAR(255),
    code_postal VARCHAR(20),
    siret VARCHAR(60),
    tva VARCHAR(60),
    contact_prenom VARCHAR(100),
    contact_nom VARCHAR(100),
    devises_proposees VARCHAR(100),
    conditions_paiement VARCHAR(30),
    delai_indicatif VARCHAR(100),
    conditions_livraison VARCHAR(255),
    responsable_id INT,
    origine_contact VARCHAR(100),
    qualification VARCHAR(15) NOT NULL DEFAULT 'a_qualifier',
    reexamen_le DATE,
    client_id INT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fournisseur_pieces_jointes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fournisseur_id INT NOT NULL,
    categorie VARCHAR(30) NOT NULL DEFAULT 'autre',
    nom_original VARCHAR(255) NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    taille INT NOT NULL DEFAULT 0,
    type_mime VARCHAR(100),
    uploaded_by INT,
    expire_le DATE, -- migrate_v23 : échéance facultative
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fournisseur_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fournisseur_id INT NOT NULL,
    prenom VARCHAR(100) NOT NULL DEFAULT '',
    nom VARCHAR(100) NOT NULL DEFAULT '',
    fonction VARCHAR(100) NOT NULL DEFAULT '',
    email VARCHAR(255) NOT NULL DEFAULT '',
    telephone VARCHAR(50) NOT NULL DEFAULT '',
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    INDEX idx_fournisseur_contacts_fou (fournisseur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fournisseur_adresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fournisseur_id INT NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'autre',
    libelle VARCHAR(100) NOT NULL DEFAULT '',
    adresse VARCHAR(255) NOT NULL DEFAULT '',
    code_postal VARCHAR(20) NOT NULL DEFAULT '',
    ville VARCHAR(100) NOT NULL DEFAULT '',
    pays VARCHAR(100) NOT NULL DEFAULT '',
    par_defaut TINYINT(1) NOT NULL DEFAULT 0,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    INDEX idx_fournisseur_adresses_fou (fournisseur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fournisseur_qualifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fournisseur_id INT NOT NULL,
    decision VARCHAR(15) NOT NULL,
    criteres TEXT,
    commentaire TEXT,
    justificatifs VARCHAR(255),
    evaluateur_id INT,
    date_decision DATE NOT NULL,
    date_reexamen DATE,
    created_at DATETIME NOT NULL,
    INDEX idx_fournisseur_qualifications_fou (fournisseur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fournisseur_evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fournisseur_id INT NOT NULL,
    dossier_id INT,
    critere VARCHAR(60) NOT NULL,
    resultat VARCHAR(15) NOT NULL,
    commentaire TEXT,
    piece_id INT,
    evaluateur_id INT,
    date_evaluation DATE NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_fournisseur_evaluations_fou (fournisseur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parametres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filiale_id INT NOT NULL UNIQUE,
    taux_eur_fcfa DECIMAL(10,3) NOT NULL DEFAULT 655.957,
    marge_defaut_pourcentage DECIMAL(6,2) NOT NULL DEFAULT 20,
    tva_defaut_pourcentage DECIMAL(5,2) NOT NULL DEFAULT 20,
    assurance_defaut DECIMAL(10,2) NOT NULL DEFAULT 0,
    dedouanement_defaut DECIMAL(10,2) NOT NULL DEFAULT 0,
    taux_date_maj DATE,
    taux_source VARCHAR(100),
    diviseur_volumetrique_aerien DECIMAL(10,2) NOT NULL DEFAULT 6000,
    diviseur_volumetrique_maritime DECIMAL(10,2) NOT NULL DEFAULT 1000,
    commercial_peut_modifier_marge TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filiale_id INT NOT NULL,
    utilisateur_id INT,
    action VARCHAR(50) NOT NULL,
    entite_type VARCHAR(50) NOT NULL,
    entite_id INT,
    details TEXT,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS demande_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    demande_id INT NOT NULL,
    designation VARCHAR(255) NOT NULL,
    quantite DECIMAL(12,2),
    unite VARCHAR(20),
    reference VARCHAR(100),
    marque VARCHAR(100),
    -- [ajouté v14, module Demandes 04/10] Conditionnement / précision de la
    -- ligne (ex. "sacs de 50 kg", "carton de 12"), distinct de reference/marque.
    conditionnement VARCHAR(100),
    -- [ajouté 06/10, migrate_v16.php pour les bases existantes] Colonne
    -- "Caractéristiques" du tableau Articles de la maquette Dossiers
    -- (Besoin.dc.html), reportée fidèlement sur demande de Marie Laure.
    caracteristiques VARCHAR(255),
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS demande_pieces_jointes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    demande_id INT NOT NULL,
    nom_original VARCHAR(255) NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    taille INT NOT NULL DEFAULT 0,
    type_mime VARCHAR(100),
    uploaded_by INT,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dossier_pieces_jointes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dossier_id INT NOT NULL,
    nom_original VARCHAR(255) NOT NULL,
    nom_fichier VARCHAR(255) NOT NULL,
    taille INT NOT NULL DEFAULT 0,
    type_mime VARCHAR(100),
    uploaded_by INT,
    categorie VARCHAR(30) NOT NULL DEFAULT 'autre', -- migrate_v19.php
    visibilite VARCHAR(10) NOT NULL DEFAULT 'interne', -- migrate_v19.php : 'interne' | 'client'
    offre_id INT NULL, -- migrate_v28.php : fichier rattaché à une offre
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dossiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    demande_id INT NOT NULL UNIQUE,
    filiale_id INT NOT NULL,
    reference VARCHAR(30) NOT NULL,
    objet VARCHAR(255) NOT NULL,
    etape VARCHAR(20) NOT NULL DEFAULT 'qualifie',
    statut VARCHAR(20) NOT NULL DEFAULT 'actif',
    type_dossier VARCHAR(30) NOT NULL DEFAULT 'autre',
    responsable_id INT,
    priorite VARCHAR(20),
    echeance DATE,
    notes TEXT,
    -- [ajouté 06/10, migrate_v15.php pour les bases existantes] Onglet
    -- Exécution, déclinaison Prestation entreprise (cahier des charges
    -- section 9) — voir Dossier::TYPES_PRESTATION / TYPES_PRESTATION_CHAMPS.
    type_prestation VARCHAR(30),
    visite_terrain_necessaire TINYINT(1) DEFAULT 1,
    prestation_mesure_1 VARCHAR(255),
    prestation_mesure_2 VARCHAR(255),
    -- [ajouté 06/10, migrate_v16.php pour les bases existantes] Carte
    -- "Évaluation du besoin — visite terrain" de la maquette Dossiers
    -- (ExecutionPrestation.dc.html), reportée fidèlement sur demande de
    -- Marie Laure — voir Dossier::updatePrestation().
    prestation_site VARCHAR(255),
    prestation_technicien VARCHAR(255),
    prestation_delai_estime VARCHAR(100),
    prestation_constat TEXT,
    prestation_contraintes VARCHAR(255),
    annule_motif VARCHAR(255) NULL,
    annule_le DATETIME NULL,
    annule_par INT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS compteurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT NOT NULL,
    type VARCHAR(20) NOT NULL,
    annee INT NOT NULL,
    valeur INT NOT NULL DEFAULT 0,
    -- [ajouté 03/10, migrate_v13.php pour les bases existantes] Empêche
    -- deux lignes de compteur pour le même (organisation, type, année) —
    -- voir Compteur::next() pour la fenêtre de course que cette contrainte
    -- referme (toute première utilisation d'un compteur donné).
    UNIQUE KEY uniq_compteurs_org_type_annee (organisation_id, type, annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================================================
-- Pipeline Phase 2 (sourcing) et Phase 3 (cotation/commande/facturation)
-- ==========================================================================

CREATE TABLE IF NOT EXISTS consultations_fournisseur (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dossier_id INT NOT NULL,
    filiale_id INT NOT NULL,
    fournisseur_id INT NOT NULL,
    reference VARCHAR(30) NOT NULL,
    articles_demandes TEXT,
    statut VARCHAR(20) NOT NULL DEFAULT 'envoyee',
    date_envoi DATE,
    date_relance DATE,
    notes TEXT,
    created_by INT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS offres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT NOT NULL,
    dossier_id INT NOT NULL,
    filiale_id INT NOT NULL,
    fournisseur_id INT NOT NULL,
    reference VARCHAR(30) NOT NULL,
    montant_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    devise VARCHAR(10),
    incoterm_negocie VARCHAR(10),
    delai_livraison VARCHAR(100),
    validite_offre DATE,
    statut VARCHAR(20) NOT NULL DEFAULT 'recue',
    notes TEXT,
    pays_origine VARCHAR(100),
    lieu_depart VARCHAR(150),
    quantite_min VARCHAR(100),
    disponibilite VARCHAR(150),
    poids_kg DECIMAL(10,2),
    nombre_colis INT,
    volume_m3 DECIMAL(10,3),
    conformite_technique VARCHAR(20),
    conditions_paiement VARCHAR(30),
    garantie VARCHAR(150),
    transport_montant DECIMAL(12,2),
    assurance_montant DECIMAL(12,2),
    emballage_montant DECIMAL(12,2),
    douane_montant DECIMAL(12,2),
    dedouanement_montant DECIMAL(12,2),
    autres_frais_montant DECIMAL(12,2),
    motif_decision TEXT,
    mode_transport VARCHAR(100),
    perimetre_mission TEXT,
    source_type VARCHAR(20), -- migrate_v27.php : offre saisie manuellement (web, catalogue, telephone, autre)
    source_url VARCHAR(500),
    source_date DATE,
    version INT NOT NULL DEFAULT 1, -- migrate_v17.php : fournisseur renvoie une offre révisée
    offre_precedente_id INT, -- migrate_v17.php : chaîne vers la version remplacée
    created_by INT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS offre_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offre_id INT NOT NULL,
    designation VARCHAR(255) NOT NULL,
    quantite DECIMAL(12,2),
    unite VARCHAR(20),
    prix_unitaire DECIMAL(14,2),
    montant DECIMAL(14,2),
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dossier_id INT NOT NULL,
    filiale_id INT NOT NULL,
    offre_id INT,
    client_id INT NOT NULL,
    reference VARCHAR(30) NOT NULL,
    montant_achat DECIMAL(14,2),
    marge_pourcentage DECIMAL(6,2),
    marge_montant DECIMAL(14,2),
    montant_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    devise VARCHAR(10),
    mode_paiement_negocie VARCHAR(30),
    incoterm_client VARCHAR(10),
    validite_devis DATE,
    statut VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    notes TEXT,
    version INT NOT NULL DEFAULT 1, -- migrate_v18.php : v1, v2... d'une cotation sur un même dossier
    cotation_precedente_id INT, -- migrate_v18.php : chaîne vers la version remplacée
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cotation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cotation_id INT NOT NULL,
    designation VARCHAR(255) NOT NULL,
    quantite DECIMAL(12,2),
    unite VARCHAR(20),
    prix_unitaire DECIMAL(14,2),
    montant DECIMAL(14,2),
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dossier_id INT NOT NULL UNIQUE,
    filiale_id INT NOT NULL,
    cotation_id INT NOT NULL,
    reference VARCHAR(30) NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'en_cours',
    etape VARCHAR(30) NOT NULL DEFAULT 'paiement',
    notes TEXT,
    prochaine_action VARCHAR(255),
    date_relance DATE,
    tracking_numero VARCHAR(100),
    date_transit_debut DATE,
    date_transit_fin DATE,
    livrables TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commande_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    libelle VARCHAR(100) NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'a_faire',
    date_prevue DATE,
    date_reelle DATE,
    notes TEXT,
    ordre INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS consultation_partages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    masquer_client TINYINT(1) NOT NULL DEFAULT 1,
    pieces_ids TEXT,
    echeance_reponse DATE,
    expire_le DATETIME NOT NULL,
    revoque_le DATETIME,
    marque_envoye_le DATETIME,
    nb_consultations INT NOT NULL DEFAULT 0,
    dernier_acces DATETIME,
    cree_par INT,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS factures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dossier_id INT NOT NULL,
    commande_id INT,
    cotation_id INT,
    filiale_id INT NOT NULL,
    reference VARCHAR(30) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'unique',
    montant DECIMAL(14,2) NOT NULL DEFAULT 0,
    devise VARCHAR(10),
    date_emission DATE,
    date_echeance DATE,
    statut VARCHAR(20) NOT NULL DEFAULT 'emise',
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================================================
-- Collaborateurs de dossier (sourcing multi-collaborateurs) + notifications
-- ==========================================================================

-- Un collaborateur assigné à un dossier, dédié à un fournisseur précis (ex :
-- une personne de confiance côté partenaire local qui suit ce fournisseur
-- pour accélérer la collecte des devis). Retiré automatiquement quand
-- l'offre du fournisseur associé n'est pas retenue (voir Offre::retenir()).
CREATE TABLE IF NOT EXISTS dossier_collaborateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dossier_id INT NOT NULL,
    filiale_id INT NOT NULL,
    fournisseur_id INT NOT NULL,
    utilisateur_id INT NOT NULL,
    assigned_by INT,
    role VARCHAR(100), -- migrate_v19.php : rôle dans le dossier (texte libre)
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications internes (cloche) pour tout mouvement important — la
-- première utilisation est l'assignation/retrait d'un collaborateur de
-- dossier ; le type reste générique pour être réutilisé plus tard.
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT NOT NULL,
    filiale_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    titre VARCHAR(255) NOT NULL,
    message TEXT,
    lien VARCHAR(255),
    entite_type VARCHAR(50),
    entite_id INT,
    lu TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Budget prévisionnel vs réalisé de l'onglet Exécution (migrate_v20.php) :
-- une ligne par (dossier, catégorie), 6 catégories fixes (DossierBudget::CATEGORIES).
CREATE TABLE IF NOT EXISTS dossier_budget_lignes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dossier_id INT NOT NULL,
    filiale_id INT NOT NULL,
    categorie VARCHAR(30) NOT NULL,
    montant_previsionnel DECIMAL(14,2) NOT NULL DEFAULT 0,
    montant_realise DECIMAL(14,2),
    devise VARCHAR(10) NOT NULL DEFAULT 'FCFA',
    detail VARCHAR(255),
    updated_by INT,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_budget_dossier_cat (dossier_id, categorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tentatives_connexion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    INDEX idx_tentatives_email (email, created_at),
    INDEX idx_tentatives_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bons_commande_fournisseur (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filiale_id INT NOT NULL,
    fournisseur_id INT NOT NULL,
    dossier_id INT,
    offre_id INT,
    reference VARCHAR(30) NOT NULL,
    statut VARCHAR(15) NOT NULL DEFAULT 'brouillon',
    date_emission DATE NOT NULL,
    date_livraison_souhaitee DATE,
    devise VARCHAR(10) NOT NULL DEFAULT '',
    incoterm VARCHAR(10) NOT NULL DEFAULT '',
    lieu_livraison VARCHAR(255) NOT NULL DEFAULT '',
    conditions_paiement VARCHAR(255) NOT NULL DEFAULT '',
    reference_offre VARCHAR(60) NOT NULL DEFAULT '',
    notes TEXT,
    notes_internes TEXT,
    fournisseur_nom VARCHAR(255) NOT NULL DEFAULT '',
    fournisseur_adresse VARCHAR(500) NOT NULL DEFAULT '',
    destinataire_nom VARCHAR(200) NOT NULL DEFAULT '',
    destinataire_email VARCHAR(255) NOT NULL DEFAULT '',
    montant_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    envoye_le DATETIME,
    confirme_le DATETIME,
    annule_le DATETIME,
    annule_motif VARCHAR(255),
    created_by INT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_bcf_fournisseur (fournisseur_id),
    INDEX idx_bcf_dossier (dossier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bon_commande_fournisseur_lignes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bon_id INT NOT NULL,
    designation VARCHAR(255) NOT NULL,
    quantite DECIMAL(12,2),
    unite VARCHAR(20) NOT NULL DEFAULT '',
    prix_unitaire DECIMAL(14,2),
    montant DECIMAL(14,2),
    ordre INT NOT NULL DEFAULT 0,
    INDEX idx_bcfl_bon (bon_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
