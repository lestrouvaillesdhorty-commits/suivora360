<?php
use App\Core\View;
use App\Core\Auth;
use App\Models\DossierTransport;
use App\Models\Expedition;
use App\Models\TransitaireTarif;
// Transport confié à un transitaire : une seule saisie, reprise dans l'offre retenue et dans la cotation.
$trListe = TransitaireTarif::transitaires(Auth::user());
$trTarifs = TransitaireTarif::pourFormulaire(array_column($trListe, 'id'));
$trOk = !empty($dossier['tr_montant']);
$trChoisi = (int) ($dossier['tr_transitaire_id'] ?? 0) ?: (int) ($offreRetenue['transitaire_id'] ?? 0);
$trDest = (string) ($dossier['tr_destination'] ?? '') !== '' ? (string) $dossier['tr_destination'] : trim((string) ($demande['lieu_livraison'] ?? ''));
$trUnite = (string) ($dossier['tr_unite'] ?? '');
$trQte = $dossier['tr_quantite'] ?? null;
if ($trQte === null && $offreRetenue) { foreach (['m3', 'kg', 'colis'] as $u) { $q = DossierTransport::quantiteOffre($offreRetenue, $u); if ($q !== null) { $trQte = $q; break; } } }
$trModes = Expedition::MODES;
$trQteTxt = $trQte !== null && $trQte !== '' ? rtrim(rtrim(number_format((float) $trQte, 3, '.', ''), '0'), '.') : '';
?>
<div class="ex-transport">
  <div class="ex-tr-titre"><b>Transport par le transitaire</b><?php if ($trOk): ?><span class="badge badge-green">Enregistré</span><?php endif; ?></div>
  <?php if (!$trListe): ?>
    <div class="empty-state">Aucun transitaire avec des tarifs n'est enregistré. Ajoutez-en un dans la fiche d'un partenaire de type « Transitaire ».</div>
  <?php else: ?>
  <form method="post" action="/index.php?r=dossiers/<?= (int) $dossier['id'] ?>/transport-transitaire" id="formTransport">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="ex-tr-grid">
      <div><label>Transitaire</label><select name="tr_transitaire_id" id="trT" <?= Auth::canWrite() ? '' : 'disabled' ?>><option value="">Choisir…</option>
        <?php foreach ($trListe as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $trChoisi === (int) $t['id'] ? 'selected' : '' ?>><?= View::e($t['nom']) ?></option><?php endforeach; ?></select></div>
      <div><label>Mode</label><select name="tr_mode" id="trM"><?php foreach ($trModes as $k => $lb): ?><option value="<?= $k ?>" <?= ($dossier['tr_mode'] ?? '') === $k ? 'selected' : '' ?>><?= View::e($lb) ?></option><?php endforeach; ?></select></div>
      <div><label>Marchandise</label><select name="tr_famille" id="trF"><?php foreach (Expedition::MARCHANDISES as $k => $lb): ?><option value="<?= $k ?>" <?= ($dossier['tr_famille'] ?? 'alimentaire') === $k ? 'selected' : '' ?>><?= View::e($lb) ?></option><?php endforeach; ?></select></div>
      <div><label>Destination</label><input type="text" name="tr_destination" id="trD" value="<?= View::e($trDest) ?>" maxlength="120"></div>
    </div>
    <div class="ex-tr-grid" style="margin-top:10px">
      <div><label>Tarif de sa fiche</label><input type="text" id="trTarif" readonly tabindex="-1" placeholder="Choisissez le transitaire"></div>
      <div><label id="trQLib">Quantité</label><input type="text" inputmode="decimal" name="tr_quantite" id="trQ" value="<?= View::e($trQteTxt) ?>"></div>
      <div><label>Remise du transitaire (%)</label><input type="text" inputmode="decimal" name="tr_remise_pct" id="trR" value="<?= View::e(($dossier['tr_remise_pct'] ?? '') !== '' && $dossier['tr_remise_pct'] !== null ? rtrim(rtrim(number_format((float) $dossier['tr_remise_pct'], 2, '.', ''), '0'), '.') : '') ?>" placeholder="0"></div>
    </div>
    <div class="ex-tr-total"><span id="trDetail" class="sy-s"></span><b id="trFinal"><?= $trOk ? number_format((float) $dossier['tr_montant'], 2, ',', ' ') . ' ' . View::e((string) ($dossier['tr_devise'] ?? '')) : '—' ?></b></div>
    <div class="sy-s" style="margin-top:6px"><?= $offreRetenue ? 'Le montant final est repris dans le transport de l\'offre retenue, puis réparti sur chaque article à la cotation.' : 'Aucune offre retenue pour l\'instant : le montant sera repris dans l\'offre dès qu\'elle sera retenue, en enregistrant à nouveau.' ?></div>
    <?php if (Auth::canWrite()): ?><div style="margin-top:10px"><button type="submit" class="btn btn-sm">Enregistrer le transport</button></div><?php endif; ?>
  </form>
  <script>
  (function () {
    var T = <?= json_encode($trTarifs) ?>, D = <?= json_encode(array_column($trListe, 'devise', 'id')) ?>, U = {m3: 'm³', kg: 'kg', colis: 'colis', palette: 'palettes'}, UP = {m3: 'au m³', kg: 'au kilo', colis: 'par colis', palette: 'par palette'};
    var $ = function (i) { return document.getElementById(i); };
    var num = function (v) { var n = parseFloat(String(v).replace(/\s/g, '').replace(',', '.')); return isNaN(n) ? 0 : n; };
    var fmt = function (n) { return n.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };
    function calc() {
      var liste = T[$('trT').value] || [], d = $('trD').value.trim().toLowerCase(), t = null, tous = null;
      liste.forEach(function (r) { if (r.mo === $('trM').value && r.f === $('trF').value) { if (r.d === d) { t = r; } else if (r.d === '') { tous = r; } } });
      t = t || tous;
      if (!t) { $('trTarif').value = $('trT').value ? 'Aucun tarif pour ce choix' : ''; $('trDetail').textContent = ''; $('trFinal').textContent = '—'; return; }
      $('trTarif').value = fmt(t.m) + ' ' + UP[t.u]; $('trQLib').textContent = 'Quantité (' + U[t.u] + ')';
      var brut = Math.round(t.m * num($('trQ').value) * 100) / 100, pct = Math.min(100, Math.max(0, num($('trR').value))), rem = Math.round(brut * pct) / 100;
      $('trDetail').textContent = fmt(brut) + (rem ? ' − remise ' + fmt(rem) : '');
      $('trFinal').textContent = 'Montant final : ' + fmt(brut - rem) + ' ' + (D[$('trT').value] || '');
    }
    ['trT', 'trM', 'trF', 'trD', 'trQ', 'trR'].forEach(function (i) { $(i).addEventListener('input', calc); $(i).addEventListener('change', calc); });
    calc();
  })();
  </script>
  <?php endif; ?>
</div>
