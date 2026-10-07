<?php

use App\Core\Database;
use App\Core\Env;

require __DIR__ . '/../app/autoload.php';
Env::load(__DIR__ . '/../.env');

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$expected = Env::get('INSTALL_TOKEN', '');

if ($expected === '' || $expected === 'change-moi-avant-mise-en-ligne') {
    http_response_code(403);
    die("Sécurité : définissez d'abord INSTALL_TOKEN dans le fichier .env avant de lancer l'installation.");
}

if (!hash_equals($expected, (string) $token)) {
    http_response_code(403);
    die("Jeton d'installation incorrect. Vérifiez l'URL utilisée.");
}

$pdo = Database::connection();
$driver = Database::driver();
$id = Database::idColumnType();

function tableExists(\PDO $pdo, string $driver, string $table): bool
{
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
    } else {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    }
    $stmt->execute([$table]);
    return (bool) $stmt->fetch();
}

$errors = [];
$success = null;

function createSchema(\PDO $pdo, string $driver, string $id): void
{
    $engine = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

    $pdo->exec("CREATE TABLE IF NOT EXISTS organisations (
        id $id,
        nom VARCHAR(255) NOT NULL,
        actif TINYINT(1) NOT NULL DEFAULT 1,
        abonnement_offre VARCHAR(20) NULL,
        abonnement_prix DECIMAL(12,2) NULL,
        abonnement_devise VARCHAR(3) NULL,
        abonnement_echeance DATE NULL,
        abonnement_notes VARCHAR(255) NULL,
        created_at DATETIME NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tentatives_connexion (
        id $id,
        email VARCHAR(255) NOT NULL,
        ip VARCHAR(45) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS filiales (
        id $id,
        organisation_id INT NOT NULL,
        nom VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS utilisateurs (
        id $id,
        organisation_id INT NOT NULL,
        nom VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        mot_de_passe_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'lecture_seule',
        actif TINYINT(1) NOT NULL DEFAULT 1,
        is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
        doit_changer_mdp TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS utilisateur_filiales (
        id $id,
        utilisateur_id INT NOT NULL,
        filiale_id INT NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS demandes (
        id $id,
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
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS demande_articles (
        id $id,
        demande_id INT NOT NULL,
        designation VARCHAR(255) NOT NULL,
        quantite DECIMAL(12,2),
        unite VARCHAR(20),
        reference VARCHAR(100),
        marque VARCHAR(100),
        created_at DATETIME NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS demande_pieces_jointes (
        id $id,
        demande_id INT NOT NULL,
        nom_original VARCHAR(255) NOT NULL,
        nom_fichier VARCHAR(255) NOT NULL,
        taille INT NOT NULL DEFAULT 0,
        type_mime VARCHAR(100),
        uploaded_by INT,
        created_at DATETIME NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS dossier_pieces_jointes (
        id $id,
        dossier_id INT NOT NULL,
        nom_original VARCHAR(255) NOT NULL,
        nom_fichier VARCHAR(255) NOT NULL,
        taille INT NOT NULL DEFAULT 0,
        type_mime VARCHAR(100),
        uploaded_by INT,
        created_at DATETIME NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS clients (
        id $id,
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
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS fournisseurs (
        id $id,
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
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS fournisseur_pieces_jointes (
        id $id,
        fournisseur_id INT NOT NULL,
        categorie VARCHAR(30) NOT NULL DEFAULT 'autre',
        nom_original VARCHAR(255) NOT NULL,
        nom_fichier VARCHAR(255) NOT NULL,
        taille INT NOT NULL DEFAULT 0,
        type_mime VARCHAR(100),
        uploaded_by INT,
        created_at DATETIME NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS parametres (
        id $id,
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
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id $id,
        filiale_id INT NOT NULL,
        utilisateur_id INT,
        action VARCHAR(50) NOT NULL,
        entite_type VARCHAR(50) NOT NULL,
        entite_id INT,
        details TEXT,
        created_at DATETIME NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS dossiers (
        id $id,
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
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS compteurs (
        id $id,
        organisation_id INT NOT NULL,
        type VARCHAR(20) NOT NULL,
        annee INT NOT NULL,
        valeur INT NOT NULL DEFAULT 0
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS consultations_fournisseur (
        id $id,
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
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS offres (
        id $id,
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
        created_by INT,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS offre_items (
        id $id,
        offre_id INT NOT NULL,
        designation VARCHAR(255) NOT NULL,
        quantite DECIMAL(12,2),
        unite VARCHAR(20),
        prix_unitaire DECIMAL(14,2),
        montant DECIMAL(14,2),
        created_at DATETIME NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cotations (
        id $id,
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
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS cotation_items (
        id $id,
        cotation_id INT NOT NULL,
        designation VARCHAR(255) NOT NULL,
        quantite DECIMAL(12,2),
        unite VARCHAR(20),
        prix_unitaire DECIMAL(14,2),
        montant DECIMAL(14,2),
        created_at DATETIME NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS commandes (
        id $id,
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
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS commande_steps (
        id $id,
        commande_id INT NOT NULL,
        libelle VARCHAR(100) NOT NULL,
        statut VARCHAR(20) NOT NULL DEFAULT 'a_faire',
        date_prevue DATE,
        date_reelle DATE,
        notes TEXT,
        ordre INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS consultation_partages (
        id $id,
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
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS factures (
        id $id,
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
    )$engine");

    // Collaborateurs de dossier (sourcing multi-collaborateurs, dédiés à un
    // fournisseur précis) + notifications internes (cloche).
    $pdo->exec("CREATE TABLE IF NOT EXISTS dossier_collaborateurs (
        id $id,
        dossier_id INT NOT NULL,
        filiale_id INT NOT NULL,
        fournisseur_id INT NOT NULL,
        utilisateur_id INT NOT NULL,
        assigned_by INT,
        created_at DATETIME NOT NULL
    )$engine");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id $id,
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
    )$engine");
}

$alreadyInstalled = tableExists($pdo, $driver, 'organisations');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'installer') {
    $orgNom = trim($_POST['organisation_nom'] ?? '');
    $filialeNom = trim($_POST['filiale_nom'] ?? '');
    $dirNom = trim($_POST['dirigeant_nom'] ?? '');
    $dirEmail = trim($_POST['dirigeant_email'] ?? '');
    $dirPass = $_POST['dirigeant_mot_de_passe'] ?? '';

    if ($orgNom === '' || $filialeNom === '' || $dirNom === '' || $dirEmail === '' || strlen($dirPass) < 6) {
        $errors[] = 'Tous les champs sont obligatoires (mot de passe : 6 caractères minimum).';
    } else {
        try {
            createSchema($pdo, $driver, $id);

            $stmt = $pdo->prepare('INSERT INTO organisations (nom, created_at) VALUES (?, ?)');
            $stmt->execute([$orgNom, date('Y-m-d H:i:s')]);
            $orgId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO filiales (organisation_id, nom, created_at) VALUES (?, ?, ?)');
            $stmt->execute([$orgId, $filialeNom, date('Y-m-d H:i:s')]);

            $stmt = $pdo->prepare(
                'INSERT INTO utilisateurs (organisation_id, nom, email, mot_de_passe_hash, role, actif, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, ?)'
            );
            $stmt->execute([$orgId, $dirNom, $dirEmail, password_hash($dirPass, PASSWORD_DEFAULT), 'proprietaire', date('Y-m-d H:i:s')]);

            $success = "Installation terminée ! Vous pouvez maintenant vous connecter avec l'email $dirEmail.";
            $alreadyInstalled = true;
        } catch (\Throwable $e) {
            $errors[] = 'Erreur pendant l\'installation : ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Installation — Suivora360</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #f4f5f7; margin: 0; padding: 40px 20px; }
.card { max-width: 520px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
h1 { font-size: 20px; margin-top: 0; }
label { display: block; font-size: 13px; font-weight: 600; margin: 16px 0 4px; color: #333; }
input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
button { margin-top: 24px; width: 100%; padding: 12px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; }
.alert { padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
.alert-error { background: #fde8e8; color: #9b1c1c; }
.alert-success { background: #def7ec; color: #03543f; }
.note { font-size: 13px; color: #666; margin-top: 20px; line-height: 1.5; }
</style>
</head>
<body>
<div class="card">
<h1>Installation de Suivora360</h1>

<?php foreach ($errors as $err): ?>
<div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
<?php endforeach; ?>

<?php if ($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<p><a href="/index.php?r=login">Aller à la page de connexion →</a></p>
<div class="note">
Pour votre sécurité, supprimez maintenant le fichier <code>public/install.php</code> de votre hébergement (via le gestionnaire de fichiers ou FTP).
</div>
<?php elseif ($alreadyInstalled): ?>
<div class="alert alert-error">
La base de données est déjà installée. Si vous voulez repartir de zéro, videz les tables manuellement via phpMyAdmin, puis rechargez cette page.
</div>
<p><a href="/index.php?r=login">Aller à la page de connexion →</a></p>
<?php else: ?>
<p style="font-size:14px;color:#555">Ceci configure la base de données et crée votre première organisation, votre première filiale et votre compte dirigeant.</p>
<form method="post">
<input type="hidden" name="action" value="installer">
<input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

<label>Nom de l'organisation (groupe)</label>
<input type="text" name="organisation_nom" placeholder="Ex : LTDH SAS IMPORT-EXPORT" required>

<label>Nom de la première filiale</label>
<input type="text" name="filiale_nom" placeholder="Ex : Optimum BNB" required>

<label>Votre nom (compte dirigeant)</label>
<input type="text" name="dirigeant_nom" required>

<label>Votre email</label>
<input type="email" name="dirigeant_email" required>

<label>Mot de passe (6 caractères min.)</label>
<input type="password" name="dirigeant_mot_de_passe" required minlength="6">

<button type="submit">Installer</button>
</form>
<?php endif; ?>
</div>
</body>
</html>
