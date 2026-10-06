<?php use App\Core\Auth; use App\Core\View; use App\Models\Demande; ?>
<?php
// [ajouté 06/10, étape 3] Nouvelle version d'une cotation : mêmes champs,
// pré-remplis depuis $cotationPrecedente (null = création normale).
$v = fn(string $champ) => $cotationPrecedente[$champ] ?? '';
$vNum = fn(string $champ) => ($cotationPrecedente[$champ] ?? null) !== null ? rtrim(rtrim(number_format((float) $cotationPrecedente[$champ], 2, '.', ''), '0'), '.') : '';
?>
<a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au dossier <?= View::e($dossier['reference']) ?></a>

<h1 style="margin-top:8px"><?= $cotationPrecedente ? 'Nouvelle version de la cotation ' . View::e($cotationPrecedente['reference']) . ' (v' . ((int) $cotationPrecedente['version'] + 1) . ')' : 'Nouvelle cotation client' ?></h1>
<div class="subtitle">Dossier <?= View::e($dossier['reference']) ?> — <?= View::e($dossier['objet']) ?></div>

<?php if ($offreRetenue): ?>
  <div class="alert alert-succes">Offre retenue : <?= View::e($offreRetenue['fournisseur_nom']) ?> — <?= number_format((float) $offreRetenue['montant_total'], 2, ',', ' ') ?> <?= View::e($offreRetenue['devise']) ?> (incoterm négocié : <?= View::e($offreRetenue['incoterm_negocie']) ?: '—' ?>)</div>
<?php else: ?>
  <div class="alert alert-erreur">Aucune offre n'a encore été retenue pour ce dossier (voir le Comparateur). Vous pouvez tout de même créer une cotation manuellement.</div>
<?php endif; ?>

<?php if ($demande): ?>
<div class="card" style="max-width:760px">
  <h2 style="font-size:14px;color:#666">Souhaits initiaux du client (à la qualification)</h2>
  <div class="info-row"><span class="label">Destination</span><span><?= View::e($demande['destination_pays']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Incoterm souhaité</span><span><?= View::e($demande['incoterm_souhaite']) ?: '—' ?></span></div>
  <div class="info-row"><span class="label">Mode de paiement souhaité</span><span><?= View::e($demande['mode_paiement_souhaite']) ?: '—' ?></span></div>
</div>
<?php endif; ?>

<div class="card" style="max-width:760px">
  <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/cotations">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="offre_id" value="<?= ($cotationPrecedente['offre_id'] ?? null) ?: ($offreRetenue['id'] ?? '') ?>">
    <?php if ($cotationPrecedente): ?><input type="hidden" name="cotation_precedente_id" value="<?= (int) $cotationPrecedente['id'] ?>"><?php endif; ?>

    <div class="form-group">
      <label>Client</label>
      <select name="client_id" required>
        <option value="">— Sélectionner —</option>
        <?php foreach ($clients as $c): ?>
          <?php $clientDefaut = $cotationPrecedente ? (int) $cotationPrecedente['client_id'] : (int) ($demande['client_id'] ?? 0); ?>
          <option value="<?= $c['id'] ?>" <?= ($clientDefaut === (int) $c['id']) ? 'selected' : '' ?>><?= View::e($c['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php if (Auth::canModifierMarge((int) $dossier['filiale_id'])): ?>
    <div class="form-row">
      <div class="form-group"><label>Montant d'achat (coût fournisseur)</label><input type="number" step="0.01" name="montant_achat" value="<?= $cotationPrecedente ? $vNum('montant_achat') : ($offreRetenue['montant_total'] ?? '') ?>"></div>
      <div class="form-group"><label>Marge (%)</label><input type="number" step="0.01" name="marge_pourcentage" placeholder="ex: 15" value="<?= $vNum('marge_pourcentage') ?>"></div>
    </div>
    <?php elseif (Auth::canSeeMarges()): ?>
    <div class="form-row">
      <div class="form-group">
        <label>Montant d'achat (coût fournisseur)</label>
        <input type="number" step="0.01" value="<?= $cotationPrecedente ? $vNum('montant_achat') : ($offreRetenue['montant_total'] ?? '') ?>" disabled>
        <input type="hidden" name="montant_achat" value="<?= $cotationPrecedente ? $vNum('montant_achat') : ($offreRetenue['montant_total'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Marge (%)</label>
        <input type="number" step="0.01" value="" disabled placeholder="Fixée par les Achats">
        <div style="font-size:11px;color:#888;margin-top:2px">Visible une fois saisie par les Achats — non modifiable depuis ce rôle.</div>
      </div>
    </div>
    <?php else: ?>
      <input type="hidden" name="montant_achat" value="<?= $cotationPrecedente ? $vNum('montant_achat') : ($offreRetenue['montant_total'] ?? '') ?>">
    <?php endif; ?>
    <div class="form-row">
      <div class="form-group"><label>Montant total facturé au client</label><input type="number" step="0.01" name="montant_total" required value="<?= $vNum('montant_total') ?>"></div>
      <div class="form-group">
        <label>Devise</label>
        <select name="devise">
          <?php foreach (['EUR', 'USD', 'XOF', 'XAF', 'GBP', 'CNY'] as $d): ?>
            <option <?= (($cotationPrecedente['devise'] ?? ($offreRetenue['devise'] ?? '')) === $d) ? 'selected' : '' ?>><?= $d ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Mode de paiement négocié</label>
        <select name="mode_paiement_negocie">
          <option value="">— Non précisé —</option>
          <?php foreach (Demande::MODES_PAIEMENT as $code => $label): ?>
            <option value="<?= $code ?>" <?= (($cotationPrecedente['mode_paiement_negocie'] ?? ($demande['mode_paiement_souhaite'] ?? '')) === $code) ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Incoterm client</label>
        <select name="incoterm_client">
          <option value="">— Non précisé —</option>
          <?php foreach (Demande::INCOTERMS as $code => $label): ?>
            <option value="<?= $code ?>" <?= (($cotationPrecedente['incoterm_client'] ?? ($demande['incoterm_souhaite'] ?? '')) === $code) ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group"><label>Validité du devis</label><input type="date" name="validite_devis" value="<?= View::e($v('validite_devis')) ?>"></div>

    <div class="form-group">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <label style="margin:0">Détail des articles (optionnel)</label>
        <button type="button" class="btn btn-sm btn-secondary" id="add-item">+ Ligne</button>
      </div>
      <table style="margin-top:10px" id="items-table">
        <thead><tr><th>Désignation</th><th>Qté</th><th>Unité</th><th>Prix unitaire</th><th></th></tr></thead>
        <tbody id="items-body">
        <?php foreach ($itemsPrecedents ?? [] as $it): ?>
          <tr>
            <td><input type="text" name="item_designation[]" value="<?= View::e($it['designation']) ?>"></td>
            <td><input type="number" step="0.01" name="item_quantite[]" style="width:90px" value="<?= $it['quantite'] !== null ? View::e(rtrim(rtrim(number_format((float) $it['quantite'], 2, '.', ''), '0'), '.')) : '' ?>"></td>
            <td>
              <select name="item_unite[]">
                <option value="">—</option>
                <?php $unites = ['Pièce', 'Carton', 'Kg', 'Tonne', 'Litre', 'm³', 'Sac', 'Palette', "Conteneur 20'", "Conteneur 40'"]; if ($it['unite'] !== '' && !in_array($it['unite'], $unites, true)) { $unites[] = $it['unite']; } ?>
                <?php foreach ($unites as $u): ?>
                  <option <?= $it['unite'] === $u ? 'selected' : '' ?>><?= View::e($u) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="number" step="0.01" name="item_prix_unitaire[]" style="width:110px" value="<?= $it['prix_unitaire'] !== null ? View::e(rtrim(rtrim(number_format((float) $it['prix_unitaire'], 2, '.', ''), '0'), '.')) : '' ?>"></td>
            <td><button type="button" class="btn btn-sm btn-secondary remove-item">&times;</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"><?= View::e($v('notes')) ?></textarea></div>

    <button type="submit" class="btn"><?= $cotationPrecedente ? 'Créer la nouvelle version' : 'Créer la cotation' ?></button>
    <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" class="btn btn-secondary">Annuler</a>
  </form>
</div>

<template id="item-row-template">
  <tr>
    <td><input type="text" name="item_designation[]"></td>
    <td><input type="number" step="0.01" name="item_quantite[]" style="width:90px"></td>
    <td>
      <select name="item_unite[]">
        <option value="">—</option>
        <?php foreach (['Pièce', 'Carton', 'Kg', 'Tonne', 'Litre', 'm³', 'Sac', 'Palette', "Conteneur 20'", "Conteneur 40'"] as $u): ?>
          <option><?= $u ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input type="number" step="0.01" name="item_prix_unitaire[]" style="width:110px"></td>
    <td><button type="button" class="btn btn-sm btn-secondary remove-item">&times;</button></td>
  </tr>
</template>

<script>
document.getElementById('add-item').addEventListener('click', function () {
  var tpl = document.getElementById('item-row-template');
  var clone = tpl.content.cloneNode(true);
  document.getElementById('items-body').appendChild(clone);
});
document.getElementById('items-body').addEventListener('click', function (e) {
  if (e.target.classList.contains('remove-item')) {
    e.target.closest('tr').remove();
  }
});
</script>
