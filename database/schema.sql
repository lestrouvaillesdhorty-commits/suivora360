-- Schéma de référence Suivora360 (MySQL / MariaDB)
-- À utiliser uniquement si vous préférez créer les tables manuellement via phpMyAdmin.
-- Normalement, public/install.php crée ces tables automatiquement — ce fichier
-- n'est là qu'en secours ou pour consultation.

CREATE TABLE IF NOT EXISTS organisations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
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
    created_at DATETIME NOT NULL
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
    responsable_id INT,
    priorite VARCHAR(20),
    echeance DATE,
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS compteurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT NOT NULL,
    type VARCHAR(20) NOT NULL,
    annee INT NOT NULL,
    valeur INT NOT NULL DEFAULT 0
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
