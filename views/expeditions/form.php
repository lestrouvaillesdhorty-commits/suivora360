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
  <?php $transitaires = $transitaires ?? []; $tarifsTr = $tarifsTransitaires ?? []; ?>
  <?php if ($transitaires): ?>
  <div class="form-group"><label>Transitaire</label>
    <select name="fournisseur_id" id="sel-transitaire">
      <option value="0" data-filiale="">Autre (saisir un nom)</option>
      <?php foreach ($transitaires as $t): ?><option value="<?= (int) $t['id'] ?>" data-filiale="<?= (int) $t['filiale_id'] ?>" <?= (int) ($e['fournisseur_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= View::e($t['nom']) ?></option><?php endforeach; ?>
    </select>
    <div class="hint">Vos fournisseurs de catégorie Transitaire. Leurs tarifs, enregistrés sur leur fiche, se reprennent ci-dessous. <a href="/index.php?r=fournisseurs/nouveau">Créer un transitaire</a></div></div>
  <div class="form-group" id="bloc-partenaire-libre"><label>Nom du transitaire (si autre)</label><input type="text" name="partenaire" value="<?= $v('partenaire') ?>"></div>
  <?php else: ?>
  <div class="form-group"><label>Partenaire transitaire</label><input type="text" name="partenaire" value="<?= $v('partenaire') ?>">
    <div class="hint">Astuce : ajoutez vos transitaires dans Fournisseurs (catégorie Transitaire) pour les choisir dans une liste et y enregistrer leurs tarifs.</div></div>
  <?php endif; ?>
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
    <div class="hint" style="margin-bottom:8px">Votre tarif d'achat auprès du transitaire. Laissez vide ce qui ne s'applique pas.</div>
    <div class="form-row">
      <div class="form-group"><label>Non alimentaire : unité</label><select name="tarif_achat_type"><option value="">— Choisir —</option><?php foreach (Expedition::TARIFS_ACHAT as $k => $l): ?><option value="<?= $k ?>" <?= (($e['tarif_achat_type'] ?? '') === $k) ? 'selected' : '' ?>><?= View::e($l) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Non alimentaire : montant</label><input type="text" name="tarif_achat" inputmode="decimal" value="<?= $v('tarif_achat') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Alimentaire : unité</label><select name="tarif_achat_alim_type"><option value="">— Choisir —</option><?php foreach (Expedition::TARIFS_ACHAT as $k => $l): ?><option value="<?= $k ?>" <?= (($e['tarif_achat_alim_type'] ?? '') === $k) ? 'selected' : '' ?>><?= View::e($l) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label>Alimentaire : montant</label><input type="text" name="tarif_achat_alim" inputmode="decimal" value="<?= $v('tarif_achat_alim') ?>"></div>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Devise</label><input type="text" name="devise" value="<?= $v('devise', 'EUR') ?>"></div>
    <div class="form-group"><label>N° de suivi / conteneur</label><input type="text" name="numero_suivi" value="<?= $v('numero_suivi') ?>"></div>
  </div>
  <?php if (\App\Core\Schema::colonne('expeditions', 'public_affiche')): $v67 = \App\Models\DepartsPublics::v67(); $org = (string) ($e['public_organisation'] ?? 'capacite'); ?>
  <div class="card" id="page-publique" style="background:#f7fafc;margin:10px 0 14px">
    <h3 style="margin:0 0 6px;font-size:15px">Page publique des départs</h3>
    <div class="form-group"><label><input type="checkbox" name="public_affiche" value="1" <?= !empty($e['public_affiche']) ? 'checked' : '' ?>> Afficher ce départ sur la page publique</label></div>
    <?php if ($v67): ?>
    <div class="form-group"><label>Comment ce départ est organisé</label>
      <select name="public_organisation" id="pub-org"><?php foreach (\App\Models\DepartsPublics::ORGANISATIONS as $k => $l): ?><option value="<?= $k ?>" <?= $org === $k ? 'selected' : '' ?>><?= View::e($l) ?></option><?php endforeach; ?></select></div>
    <div id="pub-obj" style="display:none">
      <div class="form-row">
        <div class="form-group"><label>Objectif pour confirmer le départ</label><select name="public_objectif_type"><option value="kg" <?= ($e['public_objectif_type'] ?? 'kg') !== 'reservations' ? 'selected' : '' ?>>En kg réservés</option><option value="reservations" <?= ($e['public_objectif_type'] ?? '') === 'reservations' ? 'selected' : '' ?>>En nombre de réservations</option></select></div>
        <div class="form-group"><label>Objectif (kg ou nombre)</label><input type="text" name="public_objectif" inputmode="decimal" value="<?= $v('public_objectif') ?>" placeholder="Ex. 3000"></div>
      </div>
      <div class="form-group"><label><input type="checkbox" name="public_confirme" value="1" <?= !empty($e['public_confirme']) ? 'checked' : '' ?>> Départ confirmé à la main (même si l'objectif n'est pas atteint)</label>
        <div class="hint">Tant que le départ n'est pas confirmé, la page affiche « Départ prévu vers le … ». La date prévue se saisit dans « Départ » ci-dessus.</div></div>
    </div>
    <div class="form-group"><label><input type="checkbox" name="public_chiffres" value="1" <?= (!isset($e['public_chiffres']) || !empty($e['public_chiffres'])) ? 'checked' : '' ?>> Afficher les kg (places restantes ou déjà réservés), sans aucun nom</label></div>
    <?php endif; ?>
    <div class="form-row">
      <div class="form-group"><label>Tarif affiché alimentaire (€ par kg)</label><input type="text" name="public_tarif_alim" inputmode="decimal" value="<?= $v('public_tarif_alim') ?>"></div>
      <div class="form-group"><label>Tarif affiché non alimentaire (€ par kg)</label><input type="text" name="public_tarif_non_alim" inputmode="decimal" value="<?= $v('public_tarif_non_alim') ?>"></div>
    </div>
    <div class="hint">C'est le tarif que le client voit (votre prix de vente, pas votre coût d'achat). Laissez vide : « Tarif sur demande ». Capacité fixe : les places restantes sont la capacité en kg moins les kg réservés.</div>
  </div>
  <script>(function(){var s=document.getElementById('pub-org'),o=document.getElementById('pub-obj');function m(){if(s&&o){o.style.display=s.value==='reservations'?'':'none';}}if(s){s.addEventListener('change',m);}m();})();</script>
  <?php endif; ?>
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

<?php if ($transitaires): ?>
<script>
(function () {
  var tarifs = <?= json_encode($tarifsTr, JSON_UNESCAPED_UNICODE) ?>;
  var sel = document.getElementById('sel-transitaire'), mode = document.querySelector('select[name=mode]');
  var libre = document.getElementById('bloc-partenaire-libre'), fil = document.querySelector('select[name=filiale_id]');
  if (!sel || !mode) { return; }
  var champs = [
    ['non_alimentaire', 'tarif_achat', 'tarif_achat_type'],
    ['alimentaire', 'tarif_achat_alim', 'tarif_achat_alim_type']
  ];
  var dest = document.querySelector('input[name=destination]');
  function trouver(fam) {
    var d = dest ? dest.value.trim().toLowerCase() : '', exact = null, tous = null;
    (tarifs[sel.value] || []).forEach(function (r) {
      if (r.mo !== mode.value || r.f !== fam) { return; }
      if (r.d === '') { tous = r; } else if (r.d === d) { exact = r; }
    });
    return exact || tous;
  }
  function indice(input) {
    var s = input.parentNode.querySelector('.repris');
    if (!s) { s = document.createElement('span'); s.className = 'repris hint'; s.style.color = '#1e7a3c'; input.parentNode.appendChild(s); }
    return s;
  }
  function filtrer() {
    if (!fil) { return; }
    Array.prototype.forEach.call(sel.options, function (o) {
      var ok = !o.dataset.filiale || o.dataset.filiale === fil.value;
      o.hidden = !ok; o.disabled = !ok;
    });
    if (sel.selectedOptions[0] && sel.selectedOptions[0].disabled) { sel.value = '0'; }
  }
  function appliquer() {
    champs.forEach(function (c) {
      var m = document.querySelector('[name=' + c[1] + ']'), u = document.querySelector('[name=' + c[2] + ']');
      if (!m || !u) { return; }
      var t = trouver(c[0]), ind = indice(m);
      var vide = m.value === '' || m.dataset.auto === '1';
      if (t && vide) {
        m.value = t.m; u.value = t.u; m.dataset.auto = '1'; ind.textContent = t.d === '' ? 'repris du transitaire (toutes destinations)' : 'repris du transitaire pour cette destination';
      } else if (!t && m.dataset.auto === '1') {
        m.value = ''; u.value = ''; m.dataset.auto = ''; ind.textContent = '';
      }
      if (!m.dataset.ecoute) { m.dataset.ecoute = '1'; m.addEventListener('input', function () { m.dataset.auto = ''; ind.textContent = ''; }); }
    });
    if (libre) { libre.style.display = sel.value === '0' ? '' : 'none'; }
  }
  sel.addEventListener('change', appliquer); mode.addEventListener('change', appliquer);
  if (dest) { dest.addEventListener('input', appliquer); }
  if (fil) { fil.addEventListener('change', function () { filtrer(); appliquer(); }); }
  filtrer(); appliquer();
})();
</script>
<?php endif; ?>
