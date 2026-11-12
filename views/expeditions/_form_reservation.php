<?php
use App\Core\View;
use App\Models\Expedition;
/** Formulaire de réservation. Variables : $exp, $dossiers (liste ou []), $dossierFixe (id ou 0), $retourDossier (id ou 0), $csrfToken. */
$dossierFixe = $dossierFixe ?? 0;
$retourDossier = $retourDossier ?? 0;
?>
<form method="post" action="/index.php?r=expeditions/<?= (int) $exp['id'] ?>/reserver">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
  <?php if ($retourDossier): ?><input type="hidden" name="retour_dossier" value="<?= (int) $retourDossier ?>"><?php endif; ?>
  <?php if ($dossierFixe): ?>
    <input type="hidden" name="dossier_id" value="<?= (int) $dossierFixe ?>">
  <?php else: ?>
  <div class="form-row">
    <div class="form-group"><label>Dossier client (facultatif)</label>
      <select name="dossier_id"><option value="">— Aucun —</option>
        <?php foreach ($dossiers as $d): ?><option value="<?= (int) $d['id'] ?>"><?= View::e($d['reference'] . ' · ' . $d['objet']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Nom du client (si pas de dossier)</label><input type="text" name="client_nom"></div>
  </div>
  <?php endif; ?>
  <div class="form-row">
    <div class="form-group"><label>Marchandise</label><select name="type_marchandise"><?php foreach (Expedition::MARCHANDISES as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Description</label><input type="text" name="description"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Volume (m³)</label><input type="text" name="volume" inputmode="decimal"></div>
    <div class="form-group"><label>Poids (kg)</label><input type="text" name="poids" inputmode="decimal"></div>
    <div class="form-group"><label>Colis</label><input type="text" name="colis" inputmode="numeric"></div>
    <div class="form-group"><label>Palettes</label><input type="text" name="palettes" inputmode="numeric"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Tarification</label><select name="tarification"><?php foreach (Expedition::TARIFICATIONS as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Tarif (forfait total ou prix unitaire)</label><input type="text" name="tarif_unitaire" inputmode="decimal"></div>
  </div>
  <button class="btn btn-primary" type="submit">Réserver</button>
</form>
