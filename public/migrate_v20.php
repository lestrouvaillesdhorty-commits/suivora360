<?php

/**
 * Migration V20 — Suivora360
 * ---------------------------------------------------------------
 * Report dans le vrai code de l'étape 5 du découpage Dossiers : carte
 * "Budget — prévisionnel vs réalisé" de l'onglet Exécution (maquette
 * ExecutionPrestation.dc.html). Confirmé par Marie Laure le 06/10 (« Code »).
 *
 * Crée une seule table, strictement additive : dossier_budget_lignes — une
 * ligne par (dossier, catégorie), les 6 catégories étant fixes (voir
 * DossierBudget::CATEGORIES). Aucune donnée existante n'est modifiée.
 * Idempotente.
 *
 * Protégée par le même jeton que l'installation (INSTALL_TOKEN dans .env).
 * Après usage, supprimez ce fichier de l'hébergement.
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
$id = Database::idColumnType();
$engine = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

function tableExiste(\PDO $pdo, string $driver, string $table): bool
{
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name = ?");
        $stmt->execute([$table]);
        return (bool) $stmt->fetch();
    }
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    $stmt->execute([$table]);
    return (bool) $stmt->fetch();
}

$log = [];
$ran = false;

if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($_POST['action'] ?? '') === 'migrer') {
    try {
        if (!tableExiste($pdo, $driver, 'dossier_budget_lignes')) {
            $pdo->exec("CREATE TABLE dossier_budget_lignes (
                id $id,
                dossier_id INT NOT NULL,
                filiale_id INT NOT NULL,
                categorie VARCHAR(30) NOT NULL,
                montant_previsionnel DECIMAL(14,2) NOT NULL DEFAULT 0,
                montant_realise DECIMAL(14,2),
                devise VARCHAR(10) NOT NULL DEFAULT 'FCFA',
                detail VARCHAR(255),
                updated_by INT,
                updated_at DATETIME NOT NULL,
                UNIQUE (dossier_id, categorie)
            )$engine");
            $log[] = 'CRÉÉE — table dossier_budget_lignes';
        } else {
            $log[] = 'OK (déjà présente) — table dossier_budget_lignes';
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
<title>Migration V20 — Suivora360</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #f4f5f7; margin: 0; padding: 40px 20px; }
.card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
h1 { font-size: 20px; margin-top: 0; }
button { margin-top: 16px; padding: 12px 20px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; }
.note { font-size: 13px; color: #666; margin-top: 20px; line-height: 1.5; }
ul { font-size: 13px; line-height: 1.6; padding-left: 20px; }
li.err { color: #9b1c1c; }
</style>
</head>
<body>
<div class="card">
<h1>Migration V20 — Budget de l'onglet Exécution (1 table)</h1>
<p style="font-size:14px;color:#555">
Crée la table <code>dossier_budget_lignes</code> (budget prévisionnel vs réalisé par catégorie, par dossier). Ne supprime et ne modifie <strong>aucune donnée existante</strong>, et peut être relancée sans risque.
</p>

<?php if ($ran): ?>
<ul>
<?php foreach ($log as $line): ?>
  <li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li>
<?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v19.php</code> de votre hébergement.</div>
<?php else: ?>
<form method="post">
<input type="hidden" name="action" value="migrer">
<input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
<button type="submit">Lancer la migration</button>
</form>
<?php endif; ?>
</div>
</body>
</html>
