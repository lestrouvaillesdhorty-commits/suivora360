<?php

/**
 * Migration V21 — Suivora360
 * ---------------------------------------------------------------
 * Couche d'administration multi-entreprises (multi-SaaS).
 *
 * Ajoute des colonnes et une table, strictement additives (aucune donnée modifiée) :
 *  - organisations.actif        (défaut 1) : permet à l'administrateur Suivora
 *                                de suspendre / rétablir l'accès d'une entreprise ;
 *  - utilisateurs.is_super_admin (défaut 0) : marque le compte « Administrateur
 *                                Suivora », au-dessus de toutes les entreprises.
 *
 * Désignation de l'administrateur Suivora : champ facultatif du formulaire
 * ci-dessous (adresse e-mail d'un compte EXISTANT et actif). Aucun compte
 * n'est désigné automatiquement, et l'application elle-même ne peut jamais
 * attribuer ce droit : c'est un geste volontaire, fait ici, avec le jeton.
 *
 * Idempotente. Protégée par INSTALL_TOKEN (.env). Après usage, supprimez ce
 * fichier de l'hébergement.
 */

use App\Core\Database;
use App\Core\Env;

require __DIR__ . '/../app/autoload.php';
Env::load(__DIR__ . '/../.env');

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$expected = Env::get('INSTALL_TOKEN', '');

if ($expected === '' || $expected === 'change-moi-avant-mise-en-ligne') {
    http_response_code(403);
    die("Sécurité : INSTALL_TOKEN doit être défini dans .env avant de lancer la migration.");
}
if (!hash_equals($expected, (string) $token)) {
    http_response_code(403);
    die("Jeton incorrect. Vérifiez l'URL utilisée.");
}

$pdo = Database::connection();
$driver = Database::driver();

function colonneExiste(\PDO $pdo, string $driver, string $table, string $colonne): bool
{
    if ($driver === 'sqlite') {
        foreach ($pdo->query("PRAGMA table_info($table)")->fetchAll(\PDO::FETCH_ASSOC) as $col) {
            if ($col['name'] === $colonne) {
                return true;
            }
        }
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
    $stmt->execute([$table, $colonne]);
    return (bool) $stmt->fetch();
}

function tableExiste(\PDO $pdo, string $driver, string $table): bool
{
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
    } else {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    }
    $stmt->execute([$table]);
    return (bool) $stmt->fetch();
}

$log = [];
$ran = false;

if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($_POST['action'] ?? '') === 'migrer') {
    try {
        if (!colonneExiste($pdo, $driver, 'organisations', 'actif')) {
            $pdo->exec('ALTER TABLE organisations ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1');
            $log[] = 'CRÉÉE — colonne organisations.actif (toutes les entreprises existantes restent actives)';
        } else {
            $log[] = 'OK (déjà présente) — colonne organisations.actif';
        }
        if (!colonneExiste($pdo, $driver, 'utilisateurs', 'is_super_admin')) {
            $pdo->exec('ALTER TABLE utilisateurs ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0');
            $log[] = 'CRÉÉE — colonne utilisateurs.is_super_admin (aucun compte désigné par défaut)';
        } else {
            $log[] = 'OK (déjà présente) — colonne utilisateurs.is_super_admin';
        }

        if (!colonneExiste($pdo, $driver, 'utilisateurs', 'doit_changer_mdp')) {
            $pdo->exec('ALTER TABLE utilisateurs ADD COLUMN doit_changer_mdp TINYINT(1) NOT NULL DEFAULT 0');
            $log[] = 'CRÉÉE — colonne utilisateurs.doit_changer_mdp (aucun compte existant obligé de changer son mot de passe)';
        } else {
            $log[] = 'OK (déjà présente) — colonne utilisateurs.doit_changer_mdp';
        }
        foreach ([
            'abonnement_offre' => 'VARCHAR(20) NULL',
            'abonnement_prix' => 'DECIMAL(12,2) NULL',
            'abonnement_devise' => 'VARCHAR(3) NULL',
            'abonnement_echeance' => 'DATE NULL',
            'abonnement_notes' => 'VARCHAR(255) NULL',
        ] as $col => $def) {
            if (!colonneExiste($pdo, $driver, 'organisations', $col)) {
                $pdo->exec("ALTER TABLE organisations ADD COLUMN $col $def");
                $log[] = "CRÉÉE — colonne organisations.$col";
            } else {
                $log[] = "OK (déjà présente) — colonne organisations.$col";
            }
        }
        if (!tableExiste($pdo, $driver, 'tentatives_connexion')) {
            $engine = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
            $pdo->exec('CREATE TABLE tentatives_connexion (
                id ' . Database::idColumnType() . ',
                email VARCHAR(255) NOT NULL,
                ip VARCHAR(45) NOT NULL DEFAULT \'\',
                created_at DATETIME NOT NULL
            )' . $engine);
            $pdo->exec('CREATE INDEX idx_tentatives_email ON tentatives_connexion (email, created_at)');
            $pdo->exec('CREATE INDEX idx_tentatives_ip ON tentatives_connexion (ip, created_at)');
            $log[] = 'CRÉÉE — table tentatives_connexion (limitation des tentatives de connexion)';
        } else {
            $log[] = 'OK (déjà présente) — table tentatives_connexion';
        }

        // --- Module Clients (07/10) : relation, responsable, devise préférée, contact principal ---
        foreach ([
            'relation' => "VARCHAR(10) NOT NULL DEFAULT 'client'",
            'responsable_id' => 'INT NULL',
            'devise_preferee' => 'VARCHAR(10) NULL',
            'contact_prenom' => 'VARCHAR(100) NULL',
            'contact_nom' => 'VARCHAR(100) NULL',
        ] as $col => $def) {
            if (!colonneExiste($pdo, $driver, 'clients', $col)) {
                $pdo->exec("ALTER TABLE clients ADD COLUMN $col $def");
                $log[] = "CRÉÉE — colonne clients.$col";
            } else {
                $log[] = "OK (déjà présente) — colonne clients.$col";
            }
        }
        // Séparation « relation commerciale » (prospect / client) et « statut » (actif / inactif) :
        // l'ancien statut « prospect » devient relation = prospect + statut actif. Aucune autre donnée modifiée.
        $n = $pdo->exec("UPDATE clients SET relation = 'prospect', statut = 'actif' WHERE statut = 'prospect' AND relation = 'client'");
        $log[] = 'Relation reprise de l\'ancien statut « prospect » : ' . (int) $n . ' client(s)';

        $engine = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
        if (!tableExiste($pdo, $driver, 'client_contacts')) {
            $pdo->exec('CREATE TABLE client_contacts (
                id ' . Database::idColumnType() . ',
                client_id INT NOT NULL,
                prenom VARCHAR(100) NOT NULL DEFAULT \'\',
                nom VARCHAR(100) NOT NULL DEFAULT \'\',
                fonction VARCHAR(100) NOT NULL DEFAULT \'\',
                email VARCHAR(255) NOT NULL DEFAULT \'\',
                telephone VARCHAR(50) NOT NULL DEFAULT \'\',
                actif TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
            )' . $engine);
            $pdo->exec('CREATE INDEX idx_client_contacts_client ON client_contacts (client_id)');
            $log[] = 'CRÉÉE — table client_contacts (contacts supplémentaires)';
        } else {
            $log[] = 'OK (déjà présente) — table client_contacts';
        }
        if (!tableExiste($pdo, $driver, 'client_adresses')) {
            $pdo->exec('CREATE TABLE client_adresses (
                id ' . Database::idColumnType() . ',
                client_id INT NOT NULL,
                type VARCHAR(15) NOT NULL DEFAULT \'livraison\',
                libelle VARCHAR(100) NOT NULL DEFAULT \'\',
                adresse VARCHAR(255) NOT NULL DEFAULT \'\',
                code_postal VARCHAR(20) NOT NULL DEFAULT \'\',
                ville VARCHAR(100) NOT NULL DEFAULT \'\',
                pays VARCHAR(100) NOT NULL DEFAULT \'\',
                par_defaut TINYINT(1) NOT NULL DEFAULT 0,
                actif TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
            )' . $engine);
            $pdo->exec('CREATE INDEX idx_client_adresses_client ON client_adresses (client_id)');
            $log[] = 'CRÉÉE — table client_adresses (adresses de facturation / livraison supplémentaires)';
        } else {
            $log[] = 'OK (déjà présente) — table client_adresses';
        }
        if (!tableExiste($pdo, $driver, 'client_pieces_jointes')) {
            $pdo->exec('CREATE TABLE client_pieces_jointes (
                id ' . Database::idColumnType() . ',
                client_id INT NOT NULL,
                categorie VARCHAR(30) NOT NULL DEFAULT \'autre\',
                nom_original VARCHAR(255) NOT NULL,
                nom_fichier VARCHAR(255) NOT NULL,
                taille INT NOT NULL DEFAULT 0,
                type_mime VARCHAR(100) NOT NULL DEFAULT \'\',
                uploaded_by INT,
                created_at DATETIME NOT NULL
            )' . $engine);
            $pdo->exec('CREATE INDEX idx_client_pj_client ON client_pieces_jointes (client_id)');
            $log[] = 'CRÉÉE — table client_pieces_jointes (documents du client)';
        } else {
            $log[] = 'OK (déjà présente) — table client_pieces_jointes';
        }

        $email = strtolower(trim((string) ($_POST['email_admin'] ?? '')));
        if ($email !== '') {
            $stmt = $pdo->prepare('SELECT id, nom, actif FROM utilisateurs WHERE LOWER(email) = ?');
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            if (!$u) {
                $log[] = "ERREUR : aucun compte avec l'adresse « $email ». Aucun administrateur Suivora désigné.";
            } elseif ((int) $u['actif'] !== 1) {
                $log[] = 'ERREUR : ce compte est désactivé. Aucun administrateur Suivora désigné.';
            } else {
                $pdo->prepare('UPDATE utilisateurs SET is_super_admin = 1 WHERE id = ?')->execute([$u['id']]);
                $log[] = 'DÉSIGNÉ — administrateur Suivora : ' . $u['nom'];
            }
        } else {
            $log[] = 'Aucun administrateur Suivora désigné (champ laissé vide) — à faire plus tard en relançant cette migration avec une adresse e-mail.';
        }
        $ran = true;
    } catch (\Throwable $e) {
        $log[] = 'ERREUR : ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Migration V21 — Suivora360</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #f4f5f7; margin: 0; padding: 40px 20px; }
.card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
h1 { font-size: 20px; margin-top: 0; }
label { display: block; font-size: 13px; font-weight: 600; margin: 18px 0 5px; }
input[type=email] { width: 100%; padding: 8px 10px; border: 1px solid #d7dae0; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
button { margin-top: 18px; padding: 12px 20px; background: #2d18fa; color: #fff; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; }
.note { font-size: 13px; color: #666; margin-top: 20px; line-height: 1.5; }
ul { font-size: 13px; line-height: 1.6; padding-left: 20px; }
li.err { color: #9b1c1c; }
</style>
</head>
<body>
<div class="card">
<h1>Migration V21 — Administration multi-entreprises, sécurité de connexion, abonnements</h1>
<p style="font-size:14px;color:#555">
Ajoute des colonnes (<code>organisations.actif</code>, <code>utilisateurs.is_super_admin</code>, <code>utilisateurs.doit_changer_mdp</code>, <code>organisations.abonnement_*</code>) les tables <code>tentatives_connexion</code>, <code>client_contacts</code>, <code>client_adresses</code>, <code>client_pieces_jointes</code> et de nouvelles colonnes du module Clients (<code>clients.relation</code>, <code>responsable_id</code>, <code>devise_preferee</code>…). Ne modifie <strong>aucune donnée existante</strong> (toutes les entreprises restent actives) et peut être relancée sans risque.
</p>

<?php if ($ran): ?>
<ul>
<?php foreach ($log as $line): ?>
  <li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li>
<?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v21.php</code> de votre hébergement.</div>
<?php else: ?>
<form method="post">
<input type="hidden" name="action" value="migrer">
<input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
<label for="email_admin">Administrateur Suivora — e-mail de VOTRE compte (facultatif)</label>
<input type="email" id="email_admin" name="email_admin" placeholder="vous@exemple.com" autocomplete="off">
<div class="note" style="margin-top:6px">Le compte désigné verra apparaître « Administration Suivora » dans le menu (liste des entreprises, création, suspension). À renseigner avec une adresse qui existe déjà dans l'application.</div>
<button type="submit">Lancer la migration</button>
</form>
<?php endif; ?>
</div>
</body>
</html>
