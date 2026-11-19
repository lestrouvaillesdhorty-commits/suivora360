<?php
use App\Core\View;
/** Lien fournisseur — liste des demandes. Variables : $lien, $fournisseur, $demandes, $emetteur, $base, $csrfToken, $flashPortail. */
$aRepondre = array_filter($demandes, fn($d) => in_array($d['statut'], ['envoyee', 'relance'], true) && empty($d['reponse_fournisseur']));
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Demandes de prix — <?= View::e($fournisseur['nom']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<?php include __DIR__ . '/../portail/_style.php'; ?></head><body>
<?php include __DIR__ . '/_entete.php'; ?>
<div class="w">
  <div class="hero"><h1>Bonjour <?= View::e($fournisseur['nom']) ?></h1>
  <div class="sub"><?= count($aRepondre) > 0 ? count($aRepondre) . ' demande' . (count($aRepondre) > 1 ? 's attendent' : ' attend') . ' votre réponse.' : 'Aucune demande en attente de votre réponse.' ?></div></div>
  <?php if (!empty($flashPortail)): ?><div class="alert <?= $flashPortail['type'] === 'succes' ? 'al-s' : 'al-e' ?>"><?= View::e($flashPortail['message']) ?></div><?php endif; ?>
  <?php $onglet = 'demandes'; $nbCommandes = \App\Models\BonSuivi::aConfirmer((int) $fournisseur['id']); include __DIR__ . '/_onglets.php'; ?>
  <?php if (empty($demandes)): ?><div class="card"><div class="mut">Aucune demande de prix pour le moment.</div></div><?php endif; ?>
  <?php foreach ($demandes as $d):
    $ouverte = in_array($d['statut'], ['envoyee', 'relance'], true);
    $repondu = !empty($d['reponse_fournisseur']);
    $echeance = !empty($d['echeance_reponse']) ? $d['echeance_reponse'] : null;
    $retard = $echeance && $echeance < date('Y-m-d') && $ouverte && !$repondu;
  ?>
  <div class="card<?= ($ouverte && !$repondu) ? ' cota' : '' ?>">
    <?php if ($ouverte && !$repondu): ?><span class="badge ye">À répondre</span>
    <?php elseif ($d['statut'] === 'reponse_recue'): ?><span class="badge gr">Offre envoyée ✔</span>
    <?php elseif (($d['reponse_fournisseur'] ?? '') === 'delai'): ?><span class="badge bl">Délai demandé</span>
    <?php else: ?><span class="badge gy">Vous ne pouvez pas répondre</span><?php endif; ?>
    <h2 style="margin-top:8px">Demande <?= View::e($d['reference'] ?? ('#' . $d['id'])) ?></h2>
    <div class="mut"><?= $echeance ? 'À répondre avant le ' . date('d/m/Y', strtotime($echeance)) . ($retard ? ' · date dépassée' : '') : 'Sans date limite' ?></div>
    <div class="act"><a class="btn <?= ($ouverte && !$repondu) ? 'bp' : 'bs' ?>" href="<?= $base ?>/demandes/<?= (int) $d['id'] ?>"><?= ($ouverte && !$repondu) ? 'Répondre' : 'Voir' ?></a></div>
  </div>
  <?php endforeach; ?>
  <div class="priv">Ce lien est personnel : ne le partagez pas. Vous ne voyez ici que les demandes qui vous concernent.</div>
</div></body></html>
