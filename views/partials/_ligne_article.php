<?php
// Ligne d'article commune (offre fournisseur et cotation client), en grille de saisie (voir grille-articles.js).
// Variables : $item (array, vide pour une ligne neuve), $avecRemise, $articlesDemande, $avecNotes (ajoute item_type[]),
// $forceType ('article' ou 'note') : impose le type (modèles de ligne) ; sinon il vient de $item['est_note'].
use App\Core\View;
$item = $item ?? [];
$ligneType = $forceType ?? (!empty($item['est_note']) ? 'note' : 'article');
$avecNotes = !empty($avecNotes);
$num = function ($x) { return ($x !== null && $x !== '') ? View::e(rtrim(rtrim(number_format((float) $x, 2, '.', ''), '0'), '.')) : ''; };
$unites = \App\Models\Demande::UNITES;
if (($item['unite'] ?? '') !== '' && !in_array($item['unite'], $unites, true)) { $unites[] = $item['unite']; }
$avecDa = !empty($articlesDemande);
$nbCol = 9 + ($avecDa ? 1 : 0) + (!empty($avecRemise) ? 1 : 0);
$typeHidden = $avecNotes ? '<input type="hidden" name="item_type[]" value="' . $ligneType . '">' : '';
if ($ligneType === 'note'): ?>
<tr data-type="note" class="art-ligne">
  <td class="no" data-gr-no>&#9998;</td>
  <td colspan="<?= $nbCol ?>" class="c-note"><input type="text" name="item_designation[]" value="<?= View::e((string) ($item['designation'] ?? '')) ?>" placeholder="Note libre : remarque, condition, précision…" autocomplete="off"><?= $typeHidden ?>
    <span data-gr-hidden hidden>
      <?php if ($avecDa): ?><input type="hidden" name="item_demande_article[]" value="0"><?php endif; ?>
      <input type="hidden" name="item_quantite[]" value=""><input type="hidden" name="item_unite[]" value=""><input type="hidden" name="item_prix_unitaire[]" value="">
      <?php if (!empty($avecRemise)): ?><input type="hidden" name="item_remise[]" value=""><?php endif; ?>
      <input type="hidden" name="item_conditionnement[]" value=""><input type="hidden" name="item_reference[]" value=""><input type="hidden" name="item_marque[]" value=""><input type="hidden" name="item_caracteristiques[]" value="">
    </span></td>
  <td class="ac"><button type="button" data-gr-del title="Retirer">&times;</button></td>
</tr>
<?php else: ?>
<tr data-type="article" class="art-ligne">
  <td class="no" data-gr-no></td>
  <?php if ($avecDa): ?>
  <td data-label="Article de la demande" class="c-da"><select name="item_demande_article[]">
      <option value="0">— Choisir —</option>
      <?php foreach ($articlesDemande as $ad): ?><option value="<?= (int) $ad['id'] ?>" <?= (int) ($item['demande_article_id'] ?? 0) === (int) $ad['id'] ? 'selected' : '' ?>><?= View::e($ad['designation']) ?></option><?php endforeach; ?>
      <option value="-1" <?= (int) ($item['demande_article_id'] ?? 0) === -1 ? 'selected' : '' ?>>Autre article (hors demande)</option>
    </select></td>
  <?php endif; ?>
  <td class="c-des" data-label="Désignation"><input type="text" name="item_designation[]" value="<?= View::e((string) ($item['designation'] ?? '')) ?>" autocomplete="off"><?= $typeHidden ?></td>
  <td data-label="Qté"><input type="number" step="0.01" name="item_quantite[]" value="<?= $num($item['quantite'] ?? null) ?>" data-gr-qte></td>
  <td data-label="Unité"><select name="item_unite[]"><option value="">—</option>
      <?php foreach ($unites as $u): ?><option <?= ($item['unite'] ?? '') === $u ? 'selected' : '' ?>><?= View::e($u) ?></option><?php endforeach; ?>
    </select></td>
  <td data-label="Prix unitaire"><input type="number" step="0.01" name="item_prix_unitaire[]" <?= ($item['cout'] ?? null) !== null && $item['cout'] !== '' ? 'data-cout="' . View::e((string) $item['cout']) . '"' : '' ?> value="<?= $num($item['prix_unitaire'] ?? null) ?>"></td>
  <?php if (!empty($avecRemise)): ?><td data-label="Remise %"><input type="number" step="0.01" min="0" max="100" name="item_remise[]" value="<?= $num($item['remise_pct'] ?? null) ?>"></td><?php endif; ?>
  <td data-label="Montant"><input type="text" class="art-montant" readonly tabindex="-1"></td>
  <td data-label="Référence"><input type="text" name="item_reference[]" value="<?= View::e((string) ($item['reference'] ?? '')) ?>" autocomplete="off"></td>
  <td data-label="Marque"><input type="text" name="item_marque[]" value="<?= View::e((string) ($item['marque'] ?? '')) ?>" autocomplete="off"></td>
  <td data-label="Conditionnement"><input type="text" name="item_conditionnement[]" value="<?= View::e((string) ($item['conditionnement'] ?? '')) ?>" placeholder="ex. sacs de 50 kg" autocomplete="off"></td>
  <td data-label="Caractéristiques"><input type="text" name="item_caracteristiques[]" value="<?= View::e((string) ($item['caracteristiques'] ?? '')) ?>" autocomplete="off"></td>
  <td class="ac"><button type="button" data-gr-dup title="Dupliquer">&#10697;</button><button type="button" data-gr-del title="Retirer">&times;</button></td>
</tr>
<?php endif; ?>
