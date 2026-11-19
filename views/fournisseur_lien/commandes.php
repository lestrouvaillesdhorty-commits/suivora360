<?php
use App\Core\View;
use App\Models\BonSuivi;
/** Lien fournisseur — commandes. Variables : $lien, $fournisseur, $bons, $onglet, $emetteur, $base, $nbCommandes, $csrfToken, $flashPortail. */
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Commandes — <?= View::e($fournisseur['nom']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<?php include __DIR__ . '/../portail/_style.php'; ?></head><body>
<?php include __DIR__ . '/_entete.php'; ?>
<div class="w">
  <div class="hero"><h1>Vos commandes</h1><div class="sub"><?= $nbCommandes > 0 ? $nbCommandes . ' commande' . ($nbCommandes > 1 ? 's attendent' : ' attend') . ' votre confirmation.' : 'Les bons de commande qui vous sont adressés apparaissent ici.' ?></div></div>
  <?php if (!empty($flashPortail)): ?><div class="alert <?= $flashPortail['type'] === 'succes' ? 'al-s' : 'al-e' ?>"><?= View::e($flashPortail['message']) ?></div><?php endif; ?>
  <?php include __DIR__ . '/_onglets.php'; ?>
  <?php if (empty($bons)): ?><div class="card"><div class="mut">Aucune commande pour le moment.</div></div><?php endif; ?>
  <?php foreach ($bons as $b): $etape = BonSuivi::etape($b); $aConf = $b['statut'] === 'envoye'; ?>
  <div class="card<?= $aConf ? ' cota' : '' ?>">
    <?php if ($aConf): ?><span class="badge ye">À confirmer</span>
    <?php elseif ($etape === 5): ?><span class="badge gy">Livrée</span>
    <?php elseif ($etape === 4): ?><span class="badge e-livraison">Expédiée</span>
    <?php else: ?><span class="badge e-commande">Confirmée</span><?php endif; ?>
    <h2 style="margin-top:8px">Bon <?= View::e($b['reference']) ?></h2>
    <div class="mut"><?= !empty($b['dossier_reference']) ? 'Dossier ' . View::e($b['dossier_reference']) . (!empty($b['dossier_objet']) ? ' · ' . View::e($b['dossier_objet']) : '') : 'Commande directe' ?></div>
    <div style="margin-top:6px"><?= (int) $b['nb_lignes'] ?> article<?= (int) $b['nb_lignes'] > 1 ? 's' : '' ?> · <b><?= number_format((float) $b['montant_total'], 2, ',', ' ') ?> <?= View::e($b['devise']) ?></b></div>
    <?php include __DIR__ . '/_etapes.php'; ?>
    <div class="act"><a class="btn <?= $aConf ? 'bp' : 'bs' ?>" href="<?= $base ?>/commandes/<?= (int) $b['id'] ?>"><?= $aConf ? 'Voir et confirmer' : 'Voir' ?></a></div>
  </div>
  <?php endforeach; ?>
  <div class="priv">Ce lien est personnel : ne le partagez pas. Vous ne voyez ici que les commandes qui vous concernent.</div>
</div></body></html>
