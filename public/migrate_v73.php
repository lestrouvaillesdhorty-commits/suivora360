<?php

/**
 * Migration V73 — Suivora360
 * ---------------------------------------------------------------
 * Étape « Chez le transitaire » dans le suivi des commandes (colonnes + étape « Sans transitaire » sur les commandes existantes).
 * Strictement additive et idempotente. Protégée par INSTALL_TOKEN (.env). Après usage, supprimez ce fichier.
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
        // 1. Colonnes de l'étape « Chez le transitaire » (qui, reçue le, repartie le).
        $ajouts = [
            ['commande_steps', 'transitaire_id', 'INT NULL'],
            ['commande_steps', 'transitaire_nom', 'VARCHAR(190) NULL'],
            ['commande_steps', 'date_arrivee', 'VARCHAR(10) NULL'],
            ['commande_steps', 'date_depart', 'VARCHAR(10) NULL'],
        ];
        foreach ($ajouts as [$t, $c, $def]) {
            if (!tableExiste($pdo, $driver, $t)) { $log[] = "IGNORÉE (table absente) — $t"; continue; }
            if (!colonneExiste($pdo, $driver, $t, $c)) {
                $pdo->exec("ALTER TABLE $t ADD COLUMN $c $def");
                $log[] = "AJOUTÉE — $t.$c";
            } else {
                $log[] = "OK (déjà présente) — $t.$c";
            }
        }
        // 2. Étape « transitaire » ajoutée, en « Sans transitaire », sur les commandes existantes (entre Expédition et Dédouanement).
        //    L'avancement ne bouge pas : une étape « inclus » ne compte ni dans le total ni dans le pourcentage.
        if (tableExiste($pdo, $driver, 'commande_steps')) {
            $now = date('Y-m-d H:i:s');
            $cmds = $pdo->query("SELECT DISTINCT commande_id FROM commande_steps")->fetchAll(\PDO::FETCH_COLUMN);
            $nb = 0;
            foreach ($cmds as $cid) {
                $q = $pdo->prepare("SELECT 1 FROM commande_steps WHERE commande_id = ? AND libelle = 'transitaire'");
                $q->execute([$cid]);
                if ($q->fetchColumn()) { continue; }
                $q = $pdo->prepare("SELECT ordre FROM commande_steps WHERE commande_id = ? AND libelle = 'expedition'");
                $q->execute([$cid]);
                $ordreExp = $q->fetchColumn();
                if ($ordreExp === false) { continue; }
                $pdo->prepare("UPDATE commande_steps SET ordre = ordre + 1 WHERE commande_id = ? AND ordre > ?")->execute([$cid, $ordreExp]);
                $pdo->prepare("INSERT INTO commande_steps (commande_id, libelle, statut, date_prevue, date_reelle, notes, ordre, created_at, updated_at) VALUES (?, 'transitaire', 'inclus', NULL, NULL, '', ?, ?, ?)")
                    ->execute([$cid, $ordreExp + 1, $now, $now]);
                $nb++;
            }
            $log[] = $nb > 0 ? "AJOUTÉE — étape « Chez le transitaire » (Sans transitaire) sur $nb commande(s)" : 'OK (déjà présente) — étape transitaire sur toutes les commandes';
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
<title>Migration V73 — Suivora360</title>
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
<h1>Migration V73 — Étape transitaire</h1>
<p style="font-size:14px;color:#555">Ajoute l'étape « Chez le transitaire » au suivi des commandes. Les commandes existantes la reçoivent en « Sans transitaire » : leur avancement ne change pas.</p>
<?php if ($ran): ?>
<ul style="font-size:13px;line-height:1.6">
<?php foreach ($log as $line): ?><li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li><?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v73.php</code> de votre hébergement.</div>
<?php else: ?>
<?php foreach ($log as $line): ?><p class="err"><?= htmlspecialchars($line) ?></p><?php endforeach; ?>
<form method="post"><input type="hidden" name="action" value="migrer"><input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"><button type="submit">Lancer la migration</button></form>
<?php endif; ?>
</div>
</body>
</html>
