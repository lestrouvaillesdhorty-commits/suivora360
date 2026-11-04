<?php

/**
 * Migration V37 — Suivora360
 * ---------------------------------------------------------------
 * Sous-activités configurables par filiale, catégories de fournisseur personnalisables, et colonnes sous_activite / famille_produit sur les demandes. Crée deux tables ; strictement additive et idempotente : aucune donnée
 * existante n'est modifiée. Protégée par INSTALL_TOKEN (.env). Après usage, supprimez ce fichier.
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
$log = [];
$ran = false;


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

if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($_POST['action'] ?? '') === 'migrer') {
    try {
        $id = Database::idColumnType();
        $engine = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
        if (!tableExiste($pdo, $driver, 'sous_activites')) {
            $pdo->exec("CREATE TABLE sous_activites (
                id $id,
                filiale_id INT NOT NULL,
                activite VARCHAR(100) NOT NULL,
                nom VARCHAR(120) NOT NULL,
                ordre INT NOT NULL DEFAULT 0,
                actif TINYINT NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
            )$engine");
            $log[] = 'AJOUTÉE — table sous_activites';
        } else {
            $log[] = 'OK (déjà présente) — table sous_activites';
        }
        if (!tableExiste($pdo, $driver, 'categories_fournisseur')) {
            $pdo->exec("CREATE TABLE categories_fournisseur (
                id $id,
                filiale_id INT NOT NULL,
                libelle VARCHAR(120) NOT NULL,
                actif TINYINT NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
            )$engine");
            $log[] = 'AJOUTÉE — table categories_fournisseur';
        } else {
            $log[] = 'OK (déjà présente) — table categories_fournisseur';
        }
        foreach (['sous_activite' => 'VARCHAR(120)', 'famille_produit' => 'VARCHAR(120)'] as $col => $type) {
            if (!colonneExiste($pdo, $driver, 'demandes', $col)) {
                $pdo->exec("ALTER TABLE demandes ADD COLUMN $col $type");
                $log[] = "AJOUTÉE — demandes.$col";
            } else {
                $log[] = "OK (déjà présente) — demandes.$col";
            }
        }
        // Valeurs de départ : seulement pour une filiale qui n'a encore aucune sous-activité.
        $deja = $pdo->query('SELECT DISTINCT filiale_id FROM sous_activites')->fetchAll(\PDO::FETCH_COLUMN);
        $ins = $pdo->prepare('INSERT INTO sous_activites (filiale_id, activite, nom, ordre, actif, created_at) VALUES (?,?,?,?,1,?)');
        $n = 0;
        foreach ($pdo->query('SELECT id FROM filiales')->fetchAll(\PDO::FETCH_COLUMN) as $fid) {
            if (in_array($fid, $deja)) { continue; }
            foreach (\App\Models\SousActivite::DEFAUTS as $act => $noms) {
                foreach ($noms as $i => $nom) { $ins->execute([$fid, $act, $nom, $i + 1, date('Y-m-d H:i:s')]); $n++; }
            }
        }
        $log[] = "Sous-activités de départ créées : $n";
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
<title>Migration V37 — Suivora360</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #f4f5f7; margin: 0; padding: 40px 20px; }
.card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
h1 { font-size: 20px; margin-top: 0; }
button { margin-top: 18px; padding: 12px 20px; background: #2d18fa; color: #fff; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; }
.note { font-size: 13px; color: #666; margin-top: 20px; line-height: 1.5; }
li.err { color: #9b1c1c; }
</style>
</head>
<body>
<div class="card">
<h1>Migration V37 — Sous-activités et catégories</h1>
<p style="font-size:14px;color:#555">Crée les listes de sous-activités et de catégories de fournisseur, et ajoute deux colonnes aux demandes. Aucune donnée existante n'est modifiée. Peut être relancée sans risque.</p>
<?php if ($ran): ?>
<ul style="font-size:13px;line-height:1.6">
<?php foreach ($log as $line): ?><li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li><?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v37.php</code> de votre hébergement.</div>
<?php else: ?>
<?php foreach ($log as $line): ?><p class="err"><?= htmlspecialchars($line) ?></p><?php endforeach; ?>
<form method="post"><input type="hidden" name="action" value="migrer"><input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"><button type="submit">Lancer la migration</button></form>
<?php endif; ?>
</div>
</body>
</html>
