<?php
use App\Core\View;
use App\Models\BonCommandeFournisseur as Bon;

/** Liste des bons de commande fournisseur. Variables : $bons, $statut, $q, $schemaPret, $peutGerer. */
$fmt = fn($m) => number_format((float) $m, 2, ',', ' ');
?>
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
  <div><h1 style="margin-bottom:2px">Bons de commande fournisseurs</h1><div class="subtitle"><?= count($bons) ?> résultat<?= count($bons) > 1 ? 's' : '' ?></div></div>
  <a href="/index.php?r=fournisseurs" class="btn btn-secondary">Fournisseurs</a>
</div>
<?php if (!$schemaPret): ?>
  <div class="alert" style="background:#fef3c7;color:#92400e;margin-top:12px">Mise à jour de la base à finaliser (migration V24) pour utiliser les bons de commande.</div>
<?php endif; ?>
<form method="get" action="/index.php" class="filter-bar" style="margin-top:14px">
  <input type="hidden" name="r" value="bons-commande">
  <div class="f-group"><label for="q">Recherche</label><input type="text" id="q" name="q" value="<?= View::e($q) ?>" placeholder="Référence, fournisseur, dossier"></div>
  <div class="f-group"><label for="statut">Statut</label>
    <select id="statut" name="statut"><option value="">Tous</option>
      <?php foreach (Bon::STATUTS as $k => $lib): ?><option value="<?= $k ?>" <?= $statut === $k ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?></select></div>
  <div class="f-group"><button type="submit" class="btn">Filtrer</button> <a href="/index.php?r=bons-commande" class="btn btn-secondary">Réinitialiser</a></div>
</form>
<div class="card">
<?php if (empty($bons)): ?>
  <div class="empty-state">Aucun bon de commande. Créez-en un depuis l’offre retenue d’un dossier, ou depuis la fiche d’un fournisseur (onglet Commandes).</div>
<?php else: ?>
  <table class="responsive-cards liste-cartes">
    <thead><tr><th>Bon</th><th>Fournisseur</th><th>Dossier</th><th>Date</th><th>Livraison souhaitée</th><th class="num">Montant</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($bons as $b): ?>
      <tr>
        <td data-label="Bon"><a href="/index.php?r=bons-commande/<?= (int) $b['id'] ?>"><?= View::e($b['reference']) ?></a></td>
        <td data-label="Fournisseur"><a href="/index.php?r=fournisseurs/<?= (int) $b['fournisseur_id'] ?>"><?= View::e($b['fournisseur_nom']) ?></a></td>
        <td data-label="Dossier"><?= !empty($b['dossier_id']) ? '<a href="/index.php?r=dossiers/' . (int) $b['dossier_id'] . '">' . View::e($b['dossier_reference']) . '</a>' : '—' ?></td>
        <td data-label="Date"><?= date('d/m/Y', strtotime($b['date_emission'])) ?></td>
        <td data-label="Livraison souhaitée"><?= $b['date_livraison_souhaitee'] ? date('d/m/Y', strtotime($b['date_livraison_souhaitee'])) : '—' ?></td>
        <td data-label="Montant" class="num" style="white-space:nowrap"><?= $fmt($b['montant_total']) ?> <?= View::e($b['devise']) ?></td>
        <td data-label="Statut"><span class="badge <?= Bon::BADGES[$b['statut']] ?>"><?= View::e(Bon::STATUTS[$b['statut']]) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div style="font-size:12px;color:#888;margin-top:8px">Les montants sont dans la devise de chaque bon et ne sont jamais additionnés entre devises.</div>
<?php endif; ?>
</div>
