<?php
use App\Core\View;
use App\Models\DepartsPublics;
/** Champs d'un départ hebdomadaire. Variable : $h. */
$dis = $peutEcrire ? '' : 'disabled';
?>
<div class="form-row">
  <div class="form-group"><label>Origine</label><input type="text" name="origine" value="<?= View::e((string) $h['origine']) ?>" placeholder="Ex. Cameroun" <?= $dis ?>></div>
  <div class="form-group"><label>Destination</label><input type="text" name="destination" value="<?= View::e((string) $h['destination']) ?>" placeholder="Ex. France" <?= $dis ?>></div>
  <div class="form-group"><label>Transport</label><select name="transport" <?= $dis ?>><?php foreach (DepartsPublics::TRANSPORTS as $k => $l): ?><option value="<?= $k ?>" <?= $h['transport'] === $k ? 'selected' : '' ?>><?= View::e($l) ?></option><?php endforeach; ?></select></div>
</div>
<div class="form-row">
  <div class="form-group"><label>Jour de départ</label><select name="jour_semaine" <?= $dis ?>><?php foreach (DepartsPublics::JOURS as $k => $l): ?><option value="<?= $k ?>" <?= (int) $h['jour_semaine'] === $k ? 'selected' : '' ?>><?= View::e(ucfirst($l)) ?></option><?php endforeach; ?></select></div>
  <div class="form-group"><label>Type de produit</label><select name="produit" <?= $dis ?>><?php foreach (DepartsPublics::PRODUITS as $k => $l): ?><option value="<?= $k ?>" <?= $h['produit'] === $k ? 'selected' : '' ?>><?= View::e($l) ?></option><?php endforeach; ?></select></div>
  <div class="form-group"><label>Dépôt du colis</label><select name="depot_jours_avant" <?= $dis ?>><option value="0" <?= (int) $h['depot_jours_avant'] === 0 ? 'selected' : '' ?>>Le jour même</option><?php foreach ([1, 2, 3, 4, 5] as $n): ?><option value="<?= $n ?>" <?= (int) $h['depot_jours_avant'] === $n ? 'selected' : '' ?>>Au moins <?= $n ?> jour<?= $n > 1 ? 's' : '' ?> avant</option><?php endforeach; ?></select></div>
</div>
<div class="form-row">
  <div class="form-group"><label>Heure limite (facultatif)</label><input type="text" name="heure_limite" value="<?= View::e((string) $h['heure_limite']) ?>" placeholder="Ex. 14:00" style="max-width:140px" <?= $dis ?>></div>
  <div class="form-group"><label>Tarif affiché (€ par kg)</label><input type="text" name="tarif_kg" inputmode="decimal" value="<?= View::e((string) $h['tarif_kg']) ?>" style="max-width:160px" <?= $dis ?>></div>
  <div class="form-group"><label><input type="checkbox" name="actif" value="1" <?= !empty($h['actif']) ? 'checked' : '' ?> <?= $dis ?>> Afficher sur la page</label></div>
</div>
<div class="form-group"><label>Lieu(x) de dépôt, adresse et horaires</label><textarea name="lieu_depot" rows="3" placeholder="Ex. Pontoise : adresse, horaires&#10;Brétigny-sur-Orge : adresse, horaires" <?= $dis ?>><?= View::e((string) $h['lieu_depot']) ?></textarea></div>
