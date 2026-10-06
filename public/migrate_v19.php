<?php

/**
 * Migration V19 — Suivora360
 * ---------------------------------------------------------------
 * Report dans le vrai code de l'étape 4 du découpage Dossiers : onglet
 * "Documents" rangé par catégorie, avec visibilité Interne / Visible client
 * (maquette Documents.dc.html). Choix confirmé par Marie Laure le 06/10
 * (« Catégorie + visibilité »).
 *
 * Ajoute 2 colonnes, strictement additives, à dossier_pieces_jointes :
 * - categorie (VARCHAR 30, défaut 'autre') — voir DossierPieceJointe::CATEGORIES.
 * - visibilite (VARCHAR 10, défaut 'interne') — 'interne' ou 'client'.
 *   Étiquette uniquement pour l'instant : aucun espace client ne la lit
 *   encore, "interne" par défaut garantit qu'aucun document n'est exposé
 *   par erreur le jour où il en existera un.
 *
 * Étape 4 également (onglet "Équipe et historique", maquette Equipe.dc.html) :
 * - dossier_collaborateurs.role (VARCHAR 100, nullable) — "Rôle dans le
 *   dossier" saisi à l'assignation (ex. « Appui achats »).
 *
 * Les pièces jointes déjà en base prennent les valeurs par défaut
 * (catégorie "Autre", visibilité "Interne") et se reclassent depuis l'onglet.
 * Ne supprime et ne modifie aucune donnée existante. Idempotente.
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

function colonneExiste(\PDO $pdo, string $driver, string $table, string $colonne): bool
{
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("PRAGMA table_info($table)");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $col) {
            if ($col['name'] === $colonne) {
                return true;
            }
        }
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
    );
    $stmt->execute([$table, $colonne]);
    return (bool) $stmt->fetch();
}

$colonnesAAjouter = [
    ['dossier_pieces_jointes', 'categorie', "ALTER TABLE dossier_pieces_jointes ADD COLUMN categorie VARCHAR(30) NOT NULL DEFAULT 'autre'"],
    ['dossier_pieces_jointes', 'visibilite', "ALTER TABLE dossier_pieces_jointes ADD COLUMN visibilite VARCHAR(10) NOT NULL DEFAULT 'interne'"],
    ['dossier_collaborateurs', 'role', "ALTER TABLE dossier_collaborateurs ADD COLUMN role VARCHAR(100)"],
];

$log = [];
$ran = false;

if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($_POST['action'] ?? '') === 'migrer') {
    try {
        foreach ($colonnesAAjouter as [$table, $colonne, $sql]) {
            if (colonneExiste($pdo, $driver, $table, $colonne)) {
                $log[] = "OK (déjà présente) — $table.$colonne";
                continue;
            }
            $pdo->exec($sql);
            $log[] = "AJOUTÉE — $table.$colonne";
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
<title>Migration V19 — Suivora360</title>
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
<h1>Migration V19 — Documents et Équipe (3 colonnes)</h1>
<p style="font-size:14px;color:#555">
Ajoute <code>categorie</code> et <code>visibilite</code> à <code>dossier_pieces_jointes</code>, pour ranger les documents d'un dossier par catégorie et les marquer Interne / Visible client, et <code>role</code> à <code>dossier_collaborateurs</code> (rôle d'un collaborateur dans le dossier). Ne supprime et ne modifie <strong>aucune donnée existante</strong>,
et peut être relancée sans risque.
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
