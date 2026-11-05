<?php
// Ligne d'article commune (offre fournisseur et cotation client) : mêmes champs que les articles de la demande.
// Variables : $item (array, vide pour une ligne neuve), $placeholderUnite (optionnel).
use App\Core\View;
$item = $item ?? [];
$num = function ($x) { return ($x !== null && $x !== '') ? View::e(rtrim(rtrim(number_format((float) $x, 2, '.', ''), '0'), '.')) : ''; };
$unites = \App\Models\Demande::UNITES;
if (($item['unite'] ?? '') !== '' && !in_array($item['unite'], $unites, true)) { $unites[] = $item['unite']; }
?>
<tr class="art-ligne"><td colspan="5" style="padding:0;border:0">
  <div class="art-grid">
    <?php if (!empty($articlesDemande)): ?>
    <div class="art-da"><label>Article de la demande concerné</label>
      <select name="item_demande_article[]">
        <option value="0">— Choisir l'article —</option>
        <?php foreach ($articlesDemande as $ad): ?><option value="<?= (int) $ad['id'] ?>" <?= (int) ($item['demande_article_id'] ?? 0) === (int) $ad['id'] ? 'selected' : '' ?>><?= View::e($ad['designation']) ?></option><?php endforeach; ?>
        <option value="-1" <?= (int) ($item['demande_article_id'] ?? 0) === -1 ? 'selected' : '' ?>>Autre article (hors demande)</option>
      </select></div>
    <?php endif; ?>
    <div class="art-des"><label>Désignation</label><input type="text" name="item_designation[]" value="<?= View::e((string) ($item['designation'] ?? '')) ?>"></div>
    <div class="art-q"><label>Qté</label><input type="number" step="0.01" name="item_quantite[]" value="<?= $num($item['quantite'] ?? null) ?>"></div>
    <div class="art-u"><label>Unité</label>
      <select name="item_unite[]"><option value="">—</option>
        <?php foreach ($unites as $u): ?><option <?= ($item['unite'] ?? '') === $u ? 'selected' : '' ?>><?= View::e($u) ?></option><?php endforeach; ?>
      </select></div>
    <div class="art-p"><label>Prix unitaire</label><input type="number" step="0.01" name="item_prix_unitaire[]" <?= ($item['cout'] ?? null) !== null && $item['cout'] !== '' ? 'data-cout="' . View::e((string) $item['cout']) . '"' : '' ?> value="<?= $num($item['prix_unitaire'] ?? null) ?>"></div>
    <div class="art-m"><label>Montant</label><input type="text" class="art-montant" readonly tabindex="-1"></div>
    <div class="art-c"><label>Conditionnement</label><input type="text" name="item_conditionnement[]" value="<?= View::e((string) ($item['conditionnement'] ?? '')) ?>" placeholder="ex. sacs de 50 kg"></div>
    <div class="art-r"><label>Référence</label><input type="text" name="item_reference[]" value="<?= View::e((string) ($item['reference'] ?? '')) ?>"></div>
    <div class="art-k"><label>Marque</label><input type="text" name="item_marque[]" value="<?= View::e((string) ($item['marque'] ?? '')) ?>"></div>
    <div class="art-x"><label>Caractéristiques</label><input type="text" name="item_caracteristiques[]" value="<?= View::e((string) ($item['caracteristiques'] ?? '')) ?>"></div>
    <div class="art-del"><button type="button" class="btn btn-sm btn-secondary remove-item" title="Supprimer la ligne">&times;</button></div>
  </div>
</td></tr>
