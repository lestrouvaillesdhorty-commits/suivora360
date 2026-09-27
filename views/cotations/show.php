<?php use App\Core\Auth; use App\Core\View; use App\Models\Cotation; use App\Models\Commande; ?>
<?php
$badges = [
    'brouillon' => 'badge-gray',
    'envoyee' => 'badge-blue',
    'acceptee' => 'badge-green',
    'refusee' => 'badge-red',
];
$commandeExistante = Commande::findByDossier((int) $cotation['dossier_id']);
?>
<a href="/index.php?r=dossiers/<?= $cotation['dossier_id'] ?>" style="font-size:13px;color:#666">&larr; Retour au dossier <?= View::e($cotation['dossier_reference']) ?></a>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px">
  <div>
    <h1><?= View::e($cotation['reference']) ?></h1>
    <div class="subtitle">Cotation pour <?= View::e($cotation['client_nom']) ?></div>
  </div>
  <?php if ($cotation['statut'] === 'acceptee' && !$commandeExistante): ?>
    <form method="post" action="/index.php?r=dossiers/<?= $cotation['dossier_id'] ?>/commande">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <input type="hidden" name="cotation_id" value="<?= $cotation['id'] ?>">
      <button type="submit" class="btn">Créer la commande</button>
    </form>
  <?php elseif ($commandeExistante): ?>
    <a href="/index.php?r=dossiers/<?= $cotation['dossier_id'] ?>/commande" class="btn btn-secondary">Voir la commande</a>
  <?php endif; ?>
</div>

<div class="detail-grid">
  <div>
    <div class="card">
      <h2>Articles</h2>
      <?php if (empty($items)): ?>
        <div class="empty-state">Aucun article détaillé.</div>
      <?php else: ?>
        <table>
          <thead><tr><th>Désignation</th><th>Qté</th><th>Unité</th><th>Prix unitaire</th><th>Montant</th></tr></thead>
          <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td><?= View::e($item['designation']) ?></td>
              <td><?= View::e((string) $item['quantite']) ?></td>
              <td><?= View::e($item['unite']) ?></td>
              <td><?= $item['prix_unitaire'] !== null ? number_format((float) $item['prix_unitaire'], 2, ',', ' ') : '—' ?></td>
              <td><?= $item['montant'] !== null ? number_format((float) $item['montant'], 2, ',', ' ') : '—' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Notes</h2>
      <div style="white-space:pre-wrap;font-size:14px"><?= View::e($cotation['notes']) ?: '—' ?></div>
    </div>
  </div>

  <div>
    <div class="card">
      <h2>Détails financiers</h2>
      <?php if (Auth::canSeeMarges()): ?>
      <div class="info-row"><span class="label">Montant d'achat</span><span><?= $cotation['montant_achat'] !== null ? number_format((float) $cotation['montant_achat'], 2, ',', ' ') . ' ' . View::e($cotation['devise']) : '—' ?></span></div>
      <div class="info-row"><span class="label">Marge</span><span><?= $cotation['marge_montant'] !== null ? number_format((float) $cotation['marge_montant'], 2, ',', ' ') . ' ' . View::e($cotation['devise']) : '—' ?><?= $cotation['marge_pourcentage'] !== null ? ' (' . rtrim(rtrim(number_format((float) $cotation['marge_pourcentage'], 2), '0'), '.') . '%)' : '' ?></span></div>
      <?php endif; ?>
      <div class="info-row"><span class="label">Montant total client</span><span><strong><?= number_format((float) $cotation['montant_total'], 2, ',', ' ') ?> <?= View::e($cotation['devise']) ?></strong></span></div>
      <div class="info-row"><span class="label">Mode de paiement négocié</span><span><?= View::e($cotation['mode_paiement_negocie']) ?: '—' ?></span></div>
      <div class="info-row"><span class="label">Incoterm client</span><span><?= View::e($cotation['incoterm_client']) ?: '—' ?></span></div>
      <div class="info-row"><span class="label">Validité</span><span><?= $cotation['validite_devis'] ? date('d/m/Y', strtotime($cotation['validite_devis'])) : '—' ?></span></div>
    </div>

    <div class="card">
      <h2>Statut</h2>
      <div class="info-row"><span class="label">Statut actuel</span><span><span class="badge <?= $badges[$cotation['statut']] ?? 'badge-gray' ?>"><?= Cotation::STATUTS[$cotation['statut']] ?? $cotation['statut'] ?></span></span></div>
      <form method="post" action="/index.php?r=cotations/<?= $cotation['id'] ?>/statut" style="margin-top:16px;padding-top:16px;border-top:1px solid #eef0f4">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <label>Changer le statut</label>
        <select name="statut" onchange="this.form.submit()">
          <?php foreach (Cotation::STATUTS as $code => $label): ?>
            <option value="<?= $code ?>" <?= $cotation['statut'] === $code ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>
</div>
