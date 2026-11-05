<?php

/**
 * Migration V44 — Suivora360
 * ---------------------------------------------------------------
 * Comparaison article par article : chaque ligne d'offre est rattachée à l'article de la demande qu'elle concerne (demande_article_id) et peut être marquée « retenue » (retenu). Deux colonnes ajoutées à offre_items et rattachement automatique des lignes existantes par désignation identique ; strictement additive et idempotente. Protégée par INSTALL_TOKEN (.env). Après usage, supprimez ce fichier.
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
        if (!colonneExiste($pdo, $driver, 'offre_items', 'demande_article_id')) {
            $pdo->exec('ALTER TABLE offre_items ADD COLUMN demande_article_id INT NULL');
            $log[] = 'AJOUTÉE — colonne offre_items.demande_article_id';
        } else {
            $log[] = 'OK (déjà présente) — colonne offre_items.demande_article_id';
        }
        if (!colonneExiste($pdo, $driver, 'offre_items', 'retenu')) {
            $pdo->exec('ALTER TABLE offre_items ADD COLUMN retenu INT NOT NULL DEFAULT 0');
            $log[] = 'AJOUTÉE — colonne offre_items.retenu';
        } else {
            $log[] = 'OK (déjà présente) — colonne offre_items.retenu';
        }
        // Rattachement automatique des lignes déjà saisies : désignation identique (casse et espaces ignorés).
        $n = 0;
        $lignes = $pdo->query("SELECT oi.id, oi.designation, d.demande_id FROM offre_items oi
            INNER JOIN offres o ON o.id = oi.offre_id INNER JOIN dossiers d ON d.id = o.dossier_id
            WHERE oi.demande_article_id IS NULL")->fetchAll(\PDO::FETCH_ASSOC);
        $cache = [];
        foreach ($lignes as $l) {
            $did = (int) $l['demande_id'];
            if (!isset($cache[$did])) {
                $st = $pdo->prepare('SELECT id, designation FROM demande_articles WHERE demande_id = ?');
                $st->execute([$did]);
                $cache[$did] = [];
                foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $a) {
                    $cache[$did][mb_strtolower(trim(preg_replace('/\s+/', ' ', $a['designation'])))] = (int) $a['id'];
                }
            }
            $cle = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $l['designation'])));
            if (isset($cache[$did][$cle])) {
                $pdo->prepare('UPDATE offre_items SET demande_article_id = ? WHERE id = ?')->execute([$cache[$did][$cle], $l['id']]);
                $n++;
            }
        }
        $log[] = "Lignes d'offres rattachées automatiquement à un article de la demande : $n";
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
<title>Migration V44 — Suivora360</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #f4f5f7; margin: 0; padding: 40px 20px; }
.card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
h1 { font-size: 20px; margin-top: 0; }
button { margin-top: 18px; padding: 12px 20px; background: #0B7C8A; color: #fff; border: none; border-radius: 6px; font-size: 15px; cursor: pointer; }
.note { font-size: 13px; color: #666; margin-top: 20px; line-height: 1.5; }
li.err { color: #9b1c1c; }
</style>
</head>
<body>
<div class="card">
<h1>Migration V44 — Comparaison par article</h1>
<p style="font-size:14px;color:#555">Rattache les lignes d'offres aux articles de la demande pour comparer article par article. Aucune donnée existante n'est supprimée. Peut être relancée sans risque.</p>
<?php if ($ran): ?>
<ul style="font-size:13px;line-height:1.6">
<?php foreach ($log as $line): ?><li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li><?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v39.php</code> de votre hébergement.</div>
<?php else: ?>
<?php foreach ($log as $line): ?><p class="err"><?= htmlspecialchars($line) ?></p><?php endforeach; ?>
<form method="post"><input type="hidden" name="action" value="migrer"><input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"><button type="submit">Lancer la migration</button></form>
<?php endif; ?>
</div>
</body>
</html>
