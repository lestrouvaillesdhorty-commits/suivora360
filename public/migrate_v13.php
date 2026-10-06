<?php

/**
 * Migration V13 — Suivora360
 * ---------------------------------------------------------------
 * Ferme la dernière fenêtre de course sur la génération des références
 * métier (DEM-/DOS-/OFF-/COT-/CMD-/FAC-AAAA-00000) : ajoute une contrainte
 * UNIQUE (organisation_id, type, annee) sur la table `compteurs`.
 *
 * Contexte (trouvé et corrigé le 03/10 dans Compteur::next()) : avant ce
 * correctif, deux créations arrivant presque au même instant (deux
 * utilisateurs qui créent chacun une demande/un dossier/etc. en même
 * temps) pouvaient obtenir la même référence métier, sans aucune erreur
 * visible. Compteur::next() est désormais protégée par une transaction
 * avec verrou de ligne pour le cas courant (compteur déjà existant), mais
 * la toute première utilisation d'un compteur donné (nouvelle
 * organisation, ou premier document d'un type sur une nouvelle année)
 * passe par un INSERT qu'aucun verrou de ligne ne peut protéger puisque la
 * ligne n'existe pas encore — seule une contrainte UNIQUE au niveau de la
 * base ferme ce cas précis (voir le commentaire de tête de
 * `Compteur::next()`).
 *
 * Avant d'ajouter la contrainte, cette migration vérifie qu'aucun doublon
 * n'existe déjà sur (organisation_id, type, annee) — ce qui empêcherait
 * techniquement l'ajout de la contrainte. Si des doublons sont trouvés,
 * ils sont listés et la contrainte n'est PAS ajoutée automatiquement :
 * mieux vaut les regarder au cas par cas avant de forcer une fusion.
 *
 * 100% additif par ailleurs : ne supprime et ne modifie aucune donnée
 * existante. Idempotente, peut être relancée sans risque.
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

function uniqueIndexExists(\PDO $pdo, string $driver, string $table, string $indexName): bool
{
    if ($driver === 'sqlite') {
        $stmt = $pdo->query("PRAGMA index_list($table)");
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $idx) {
            if ($idx['name'] === $indexName) {
                return true;
            }
        }
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1');
    $stmt->execute([$table, $indexName]);
    return (bool) $stmt->fetch();
}

function trouverDoublonsCompteurs(\PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT organisation_id, type, annee, COUNT(*) AS c
         FROM compteurs GROUP BY organisation_id, type, annee HAVING c > 1'
    );
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

$log = [];
$ran = false;
$doublons = [];

if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($_POST['action'] ?? '') === 'migrer') {
    try {
        $doublons = trouverDoublonsCompteurs($pdo);
        if (!empty($doublons)) {
            foreach ($doublons as $d) {
                $log[] = "ERREUR : doublon trouvé sur organisation_id={$d['organisation_id']}, type={$d['type']}, annee={$d['annee']} ({$d['c']} lignes) — contrainte NON ajoutée, à régler manuellement d'abord.";
            }
        } elseif (uniqueIndexExists($pdo, $driver, 'compteurs', 'uniq_compteurs_org_type_annee')) {
            $log[] = 'OK (déjà présente) — compteurs.uniq_compteurs_org_type_annee';
            $ran = true;
        } else {
            $pdo->exec('CREATE UNIQUE INDEX uniq_compteurs_org_type_annee ON compteurs (organisation_id, type, annee)');
            $log[] = 'AJOUTÉE — contrainte UNIQUE compteurs(organisation_id, type, annee)';
            $ran = true;
        }
    } catch (\Throwable $e) {
        $log[] = 'ERREUR : ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Migration V13 — Suivora360</title>
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
<h1>Migration V13 — Contrainte d'unicité sur les compteurs de référence</h1>
<p style="font-size:14px;color:#555">
Ajoute une contrainte UNIQUE (organisation_id, type, annee) sur <code>compteurs</code>, pour empêcher
qu'une toute première création simultanée sur un compteur encore inexistant ne produise deux références
identiques. Ne supprime et ne modifie <strong>aucune donnée existante</strong>, et peut être relancée sans risque.
Si des doublons existent déjà dans <code>compteurs</code>, ils sont listés et la contrainte n'est pas ajoutée
automatiquement.
</p>

<?php if ($ran || !empty($doublons)): ?>
<ul>
<?php foreach ($log as $line): ?>
  <li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li>
<?php endforeach; ?>
</ul>
<?php if ($ran): ?>
<p><strong>Migration terminée.</strong> <a href="/index.php">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v13.php</code> de votre hébergement.</div>
<?php else: ?>
<p><strong>Contrainte non ajoutée</strong> — réglez les doublons listés ci-dessus puis relancez cette page.</p>
<?php endif; ?>
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
