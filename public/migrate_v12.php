<?php

/**
 * Migration V12 — Suivora360
 * ---------------------------------------------------------------
 * Ajoute le paramètre par filiale `parametres.commercial_peut_modifier_marge`
 * (TINYINT(1), défaut 0 = verrouillé), qui permet au Propriétaire, à l'Admin
 * d'organisation ou à Finance d'autoriser — filiale par filiale, depuis
 * Paramètres — le rôle Commercial à saisir/modifier la marge d'une
 * cotation. Par défaut la colonne vaut 0 : le comportement actuel (Achats
 * fixe la marge, Commercial la voit sans la modifier) ne change pas tant
 * que personne n'active le toggle. 100% additif : ne supprime et ne
 * modifie jamais de données existantes. Idempotente, peut être relancée
 * sans risque.
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

function columnExists(\PDO $pdo, string $driver, string $table, string $column): bool
{
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("PRAGMA table_info($table)");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $col) {
            if ($col['name'] === $column) {
                return true;
            }
        }
        return false;
    }
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool) $stmt->fetch();
}

$log = [];

function addColumn(\PDO $pdo, string $driver, string $table, string $column, string $definition, array &$log): void
{
    if (columnExists($pdo, $driver, $table, $column)) {
        $log[] = "OK (déjà présente) — $table.$column";
        return;
    }
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN $column $definition");
    $log[] = "AJOUTÉE — $table.$column";
}

$ran = false;

if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($_POST['action'] ?? '') === 'migrer') {
    try {
        addColumn($pdo, $driver, 'parametres', 'commercial_peut_modifier_marge', 'TINYINT(1) NOT NULL DEFAULT 0', $log);
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
<title>Migration V12 — Suivora360</title>
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
<h1>Migration V12 — Verrou marge Commercial (configurable)</h1>
<p style="font-size:14px;color:#555">
Ajoute <code>parametres.commercial_peut_modifier_marge</code> (défaut 0 = verrouillé, comportement actuel inchangé).
Ne supprime et ne modifie <strong>aucune donnée existante</strong>, et peut être relancée sans risque si besoin.
</p>

<?php if ($ran): ?>
<ul>
<?php foreach ($log as $line): ?>
  <li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li>
<?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php?r=parametres">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v12.php</code> de votre hébergement.</div>
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
