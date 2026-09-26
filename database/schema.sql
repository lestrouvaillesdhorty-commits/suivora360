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
    role VARCHAR(20) NOT NULL DEFAULT 'employe',
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
    nom VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    telephone VARCHAR(50),
    pays VARCHAR(100),
    ville VARCHAR(100),
    adresse VARCHAR(255),
    secteur VARCHAR(100),
    notes TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fournisseurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filiale_id INT NOT NULL,
    nom VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    telephone VARCHAR(50),
    pays VARCHAR(100),
    ville VARCHAR(100),
    adresse VARCHAR(255),
    devise VARCHAR(10),
    secteur VARCHAR(100),
    site_web VARCHAR(255),
    notes TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
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
