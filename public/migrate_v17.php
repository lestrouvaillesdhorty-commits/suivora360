<?php

/**
 * Migration V17 — Suivora360
 * ---------------------------------------------------------------
 * Report dans le vrai code de l'étape 2 du découpage Dossiers : scission
 * de l'onglet "Achats et offres" en 3 sous-onglets (Consultations / Offres
 * reçues / Comparaison), conforme à la maquette (AchatsConsultations.dc.html,
 * AchatsOffres.dc.html, AchatsComparaison.dc.html). Demandé par Marie Laure
 * le 06/10 ("tu peux continuer").
 *
 * Ajoute 2 colonnes, strictement additives, pour la seule vraie nouveauté
 * fonctionnelle de cette étape : le versionnage d'une offre (un fournisseur
 * renvoie une offre révisée sur la même consultation — confirmé par Marie
 * Laure, « une offre peut être révisée »).
 *
 * - offres.version (INT, défaut 1) — v1, v2, v3... sur une même consultation.
 * - offres.offre_precedente_id (INT, nullable) — chaîne vers la version
 *   qu'elle remplace ; l'ancienne version passe au statut "remplacee"
 *   (nouveau statut ajouté à Offre::STATUTS, aucune valeur existante
 *   modifiée).
 *
 * Les deux autres champs un temps envisagés pour cette étape ("Échéance de
 * réponse" et "Pièces jointes" par consultation) n'ont PAS donné lieu à de
 * nouvelles colonnes : en creusant le code existant, ils étaient déjà
 * couverts par le système de partage sécurisé "Demander une offre"
 * (table consultation_partages, déjà en place, jusqu'ici non documentée
 * dans le suivi des modules) — réutilisé tel quel plutôt que dupliqué.
 *
 * Ne supprime et ne modifie aucune donnée existante. Idempotente (vérifie
 * l'existence de chaque colonne avant de l'ajouter), peut être relancée
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
    ['offres', 'version', "ALTER TABLE offres ADD COLUMN version INT NOT NULL DEFAULT 1"],
    ['offres', 'offre_precedente_id', "ALTER TABLE offres ADD COLUMN offre_precedente_id INT"],
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
<title>Migration V17 — Suivora360</title>
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
<h1>Migration V17 — Achats et offres : versionnage des offres (2 colonnes)</h1>
<p style="font-size:14px;color:#555">
Ajoute <code>offres.version</code> et <code>offres.offre_precedente_id</code>, pour permettre à un fournisseur
de renvoyer une offre révisée sur la même consultation. Ne supprime et ne modifie <strong>aucune donnée existante</strong>,
et peut être relancée sans risque.
</p>

<?php if ($ran): ?>
<ul>
<?php foreach ($log as $line): ?>
  <li class="<?= str_starts_with($line, 'ERREUR') ? 'err' : '' ?>"><?= htmlspecialchars($line) ?></li>
<?php endforeach; ?>
</ul>
<p><strong>Migration terminée.</strong> <a href="/index.php">Retour à l'application →</a></p>
<div class="note">Pour votre sécurité, supprimez maintenant ce fichier <code>public/migrate_v17.php</code> de votre hébergement.</div>
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
