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
        role VARCHAR(20) NOT NULL DEFAULT 'employe',
        actif TINYINT(1) NOT NULL DEFAULT 1,
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
            $stmt->execute([$orgId, $dirNom, $dirEmail, password_hash($dirPass, PASSWORD_DEFAULT), 'dirigeant', date('Y-m-d H:i:s')]);

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
