<?php
use App\Core\View;
use App\Models\Expedition;
/** Formulaire expédition. Variables : $exp (null = création), $filiales. */
$e = $exp ?? [];
$v = fn($k, $d = '') => View::e((string) ($e[$k] ?? $d));
$action = $exp ? '/index.php?r=expeditions/' . (int) $exp['id'] . '/modifier' : '/index.php?r=expeditions';
?>
<div class="page-header"><h1><?= $exp ? 'Modifier ' . View::e($exp['reference']) : 'Nouvelle expédition' ?></h1></div>
<form method="post" action="<?= $action ?>" class="card">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
  <?php if (!$exp): ?>
  <div class="form-group"><label>Filiale</label>
    <select name="filiale_id" required><?php foreach ($filiales as $f): ?><option value="<?= (int) $f['id'] ?>"><?= View::e($f['nom']) ?></option><?php endforeach; ?></select></div>
  <?php endif; ?>
  <?php if (Expedition::v60()): ?>
  <div class="form-group"><label>Achat du transport</label>
    <select name="mode_achat" id="mode-achat"><?php foreach (Expedition::MODES_ACHAT as $k => $l): ?><option value="<?= $k ?>" <?= (($e['mode_achat'] ?? 'capacite') === $k) ? 'selected' : '' ?>><?= View::e($l) ?></option><?php endforeach; ?></select>
    <div class="hint">« À la demande » : vous n'achetez pas de place d'avance. Les clients s'ajoutent, chaque réservation est valorisée au tarif de votre transitaire, et vous décidez du départ.</div></div>
  <?php endif; ?>
  <div class="form-row">
    <div class="form-group"><label>Type</label><select name="type"><?php foreach (Expedition::TYPES as $k => $l): ?><option value="<?= $k ?>" <?= ($e['type'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Mode</label><select name="mode"><?php foreach (Expedition::MODES as $k => $l): ?><option value="<?= $k ?>" <?= ($e['mode'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Origine</label><input type="text" name="origine" required value="<?= $v('origine') ?>" placeholder="Ex. Douala"></div>
    <div class="form-group"><label>Destination</label><input type="text" name="destination" required value="<?= $v('destination') ?>" placeholder="Ex. Le Havre"></div>
  </div>
  <div class="form-group"><label>Partenaire transitaire</label><input type="text" name="partenaire" value="<?= $v('partenaire') ?>"></div>
  <div class="form-row">
    <div class="form-group"><label>Clôture des réservations</label><input type="date" name="cloture_reservations" value="<?= $v('cloture_reservations') ?>"></div>
    <div class="form-group"><label>Départ (à renseigner quand le transitaire le confirme)</label><input type="date" name="date_depart" value="<?= $v('date_depart') ?>"></div>
    <div class="form-group"><label>Arrivée prévue</label><input type="date" name="date_arrivee" value="<?= $v('date_arrivee') ?>"></div>
  </div>
  <div id="bloc-capacite">
  <div class="form-row">
    <div class="form-group"><label>Capacité (m³)</label><input type="text" name="capacite_volume" inputmode="decimal" value="<?= $v('capacite_volume') ?>"></div>
    <div class="form-group"><label>Capacité (kg)</label><input type="text" name="capacite_poids" inputmode="decimal" value="<?= $v('capacite_poids') ?>"></div>
  </div>
  <div class="form-group"><label>Coût d'achat du transport</label><input type="text" name="cout_achat" inputmode="decimal" value="<?= $v('cout_achat', '0') ?>"></div>
  </div>
  <div id="bloc-tarif" style="display:none">
    <div class="form-row">
      <div class="form-group"><label>Tarif d'achat du transitaire</label><select name="tarif_achat_type"><option value="">— Choisir —</option><?php foreach (Expedition::TARIFS_ACHAT as $k => $l): ?><option value="<?= $k ?>" <?= (($e['tarif_achat_type'] ?? '') === $k) ? 'selected' : '' ?>><?= View::e($l) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Montant (non alimentaire)</label><input type="text" name="tarif_achat" inputmode="decimal" value="<?= $v('tarif_achat') ?>"></div>
      <div class="form-group"><label>Montant alimentaire (si différent)</label><input type="text" name="tarif_achat_alim" inputmode="decimal" value="<?= $v('tarif_achat_alim') ?>"></div>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Devise</label><input type="text" name="devise" value="<?= $v('devise', 'EUR') ?>"></div>
    <div class="form-group"><label>N° de suivi / conteneur</label><input type="text" name="numero_suivi" value="<?= $v('numero_suivi') ?>"></div>
  </div>
  <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"><?= $v('notes') ?></textarea></div>
  <button class="btn btn-primary" type="submit">Enregistrer</button>
  <a class="btn" href="/index.php?r=expeditions<?= $exp ? '/' . (int) $exp['id'] : '' ?>">Annuler</a>
</form>
<script>
(function () {
  var sel = document.getElementById('mode-achat'), cap = document.getElementById('bloc-capacite'), tar = document.getElementById('bloc-tarif');
  function maj() { var t = sel && sel.value === 'tarif'; if (cap) cap.style.display = t ? 'none' : ''; if (tar) tar.style.display = t ? '' : 'none'; }
  if (sel) { sel.addEventListener('change', maj); } maj();
})();
</script>
