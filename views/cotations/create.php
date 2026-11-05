<?php use App\Core\Auth; use App\Core\View; use App\Models\Demande; ?>
<?php
// [ajouté 06/10, étape 3] Nouvelle version d'une cotation : mêmes champs,
// pré-remplis depuis $cotationPrecedente (null = création normale).
$v = fn(string $champ) => $cotationPrecedente[$champ] ?? '';
$vNum = fn(string $champ) => ($cotationPrecedente[$champ] ?? null) !== null ? rtrim(rtrim(number_format((float) $cotationPrecedente[$champ], 2, '.', ''), '0'), '.') : '';
?>
<a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au dossier <?= View::e($dossier['reference']) ?></a>

<?php
// [07/10, module Clients] Devise préférée du client de la demande : simple préremplissage d'une NOUVELLE cotation (jamais appliquée aux cotations existantes).
$deviseClientDefaut = '';
if (!$cotationPrecedente && !empty($demande['client_id']) && \App\Models\Client::schemaPret()) {
    $clientDevise = \App\Models\Client::find((int) $demande['client_id']);
    $deviseClientDefaut = (string) ($clientDevise['devise_preferee'] ?? '');
}
?>
<h1 style="margin-top:8px"><?= $cotationPrecedente ? 'Nouvelle version de la cotation ' . View::e($cotationPrecedente['reference']) . ' (v' . ((int) $cotationPrecedente['version'] + 1) . ')' : 'Nouvelle cotation client' ?></h1>
<div class="subtitle">Dossier <?= View::e($dossier['reference']) ?> — <?= View::e($dossier['objet']) ?></div>

<?php if ($offreRetenue && !empty($modeGroupe)):
  $dvG = $calcAchat['devise'];
  $fG = fn($x) => number_format((float) $x, 2, ',', ' ') . ' ' . $dvG;
?>
  <div class="alert alert-succes"><strong><?= count($offresRetenues) ?> offre(s) retenue(s)</strong> — coût d'achat réparti par article :
    <div style="margin-top:6px;font-size:13px">
    <?php foreach ($calcAchat['offres'] as $l): ?>• <?= View::e($l['fournisseur']) ?> : prix <?= $fG($l['prix']) ?><?php if ($l['frais_propres'] > 0.004): ?> + ses frais <?= $fG($l['frais_propres']) ?><?php endif; ?><?php if ($l['part_communs'] > 0.004): ?> + part des frais communs <?= $fG($l['part_communs']) ?><?php endif; ?> = <strong><?= $fG($l['total']) ?></strong><br><?php endforeach; ?>
    <?php foreach ($calcAchat['frais'] as $f): ?>• Frais communs « <?= View::e($f['libelle']) ?> » : <?= $fG($f['montant']) ?> (répartis au prorata de la valeur d'achat)<br><?php endforeach; ?>
    = <strong>coût total d'achat : <?= $fG($calcAchat['total']) ?></strong> — c'est la base de la marge ci-dessous.</div>
    <?php if ($calcAchat['ignores']): ?><div style="margin-top:6px;color:#9b1c1c">Non additionné (devise différente) : <?= View::e(implode(', ', $calcAchat['ignores'])) ?>.</div><?php endif; ?>
  </div>
<?php elseif ($offreRetenue):
  $coutTotalOffre = \App\Models\Offre::coutRendu($offreRetenue);
  $fraisOffre = [
      'Expédition jusqu\'à la destination finale' => $offreRetenue['transport_montant'] ?? 0, 'Assurance' => $offreRetenue['assurance_montant'] ?? 0,
      'Emballage' => $offreRetenue['emballage_montant'] ?? 0, 'Douane / droits' => $offreRetenue['douane_montant'] ?? 0,
      'Dédouanement' => $offreRetenue['dedouanement_montant'] ?? 0, 'Autres frais' => $offreRetenue['autres_frais_montant'] ?? 0,
  ];
  $fmtM = fn($x) => number_format((float) $x, 2, ',', ' ') . ' ' . $offreRetenue['devise'];
?>
  <div class="alert alert-succes">Offre retenue : <?= View::e($offreRetenue['fournisseur_nom']) ?> (incoterm négocié : <?= View::e($offreRetenue['incoterm_negocie']) ?: '—' ?>)
    <div style="margin-top:6px;font-size:13px">Prix du fournisseur : <strong><?= $fmtM($offreRetenue['montant_total']) ?></strong>
    <?php foreach ($fraisOffre as $lib => $m): if ((float) $m > 0): ?> + <?= View::e($lib) ?> : <?= $fmtM($m) ?><?php endif; endforeach; ?>
    = <strong>coût total d'achat : <?= $fmtM($coutTotalOffre) ?></strong> — c'est la base de la marge ci-dessous.</div>
  </div>
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

    <?php
    $deviseOffre = $cotationPrecedente ? ($cotationPrecedente['devise_achat'] ?? $cotationPrecedente['devise'] ?? 'EUR') : ($offreRetenue['devise'] ?? ($deviseClientDefaut ?: 'EUR'));
    $deviseDevis = $cotationPrecedente['devise'] ?? ($deviseClientDefaut ?: $deviseOffre);
    $tauxPrec = $cotationPrecedente ? (float) ($cotationPrecedente['taux_change'] ?? 1) : 0;
    $achatOrigine = $cotationPrecedente
        ? ($cotationPrecedente['montant_achat'] !== null ? rtrim(rtrim(number_format((float) $cotationPrecedente['montant_achat'] / ($tauxPrec > 0 ? $tauxPrec : 1), 2, '.', ''), '0'), '.') : '')
        : (!empty($modeGroupe) ? rtrim(rtrim(number_format((float) $calcAchat['total'], 2, '.', ''), '0'), '.') : (isset($offreRetenue['montant_total']) ? rtrim(rtrim(number_format(\App\Models\Offre::coutRendu($offreRetenue), 2, '.', ''), '0'), '.') : ''));
    $devisesListe = ['EUR', 'USD', 'XOF', 'XAF', 'GBP', 'CNY'];
    $tvaSel = $cotationPrecedente && $cotationPrecedente['tva_taux'] !== null ? (float) $cotationPrecedente['tva_taux'] : (float) $tvaDefaut;
    $tvaOptions = \App\Models\Parametres::TVA_OPTIONS; if (!in_array($tvaSel, $tvaOptions, true)) { $tvaOptions[] = $tvaSel; }
    ?>
    <input type="hidden" name="devise_achat" id="devise_achat" value="<?= View::e($deviseOffre) ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Devise du devis (celle du client)</label>
        <select name="devise" id="devise_devis">
          <?php foreach ($devisesListe as $d): ?><option <?= $deviseDevis === $d ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" id="bloc-taux" style="display:none">
        <label>Taux de change : 1 <span id="lib-offre"><?= View::e($deviseOffre) ?></span> = ? <span id="lib-devis"><?= View::e($deviseDevis) ?></span></label>
        <input type="number" step="0.000001" name="taux_change" id="taux_change" value="<?= $tauxPrec > 0 ? View::e((string) $tauxPrec) : '' ?>">
        <div class="hint" style="font-size:11px;color:#888;margin-top:2px">Proposé d'après les paramètres de l'entreprise (EUR ↔ FCFA) ; modifiable.</div>
      </div>
    </div>
    <?php if (Auth::canModifierMarge((int) $dossier['filiale_id'])): ?>
    <div class="form-row">
      <div class="form-group"><label>Montant d'achat (coût fournisseur, en <?= View::e($deviseOffre) ?>)</label><input type="number" step="0.01" name="achat_origine" id="achat_origine" value="<?= View::e($achatOrigine) ?>"></div>
      <div class="form-group"><label>Marge (%)</label><input type="number" step="0.01" name="marge_pourcentage" placeholder="ex: 15" value="<?= $vNum('marge_pourcentage') ?>"></div>
    </div>
    <?php elseif (Auth::canSeeMarges()): ?>
    <div class="form-row">
      <div class="form-group">
        <label>Montant d'achat (coût fournisseur, en <?= View::e($deviseOffre) ?>)</label>
        <input type="number" step="0.01" value="<?= View::e($achatOrigine) ?>" disabled>
        <input type="hidden" name="achat_origine" id="achat_origine" value="<?= View::e($achatOrigine) ?>">
      </div>
      <div class="form-group">
        <label>Marge (%)</label>
        <input type="number" step="0.01" value="" disabled placeholder="Fixée par les Achats">
        <div style="font-size:11px;color:#888;margin-top:2px">Visible une fois saisie par les Achats — non modifiable depuis ce rôle.</div>
      </div>
    </div>
    <?php else: ?>
      <input type="hidden" name="achat_origine" id="achat_origine" value="<?= View::e($achatOrigine) ?>">
    <?php endif; ?>

    <div class="tot-bloc">
      <div class="form-row">
        <div class="form-group"><label>Montant HT (hors taxe)</label><input type="number" step="0.01" name="montant_ht" id="montant_ht" value="<?= $cotationPrecedente ? $vNum('montant_ht') : '' ?>"></div>
        <div class="form-group"><label>TVA (%)</label>
          <select name="tva_taux" id="tva_taux"><?php foreach ($tvaOptions as $t): ?><option value="<?= View::e((string) $t) ?>" <?= (float) $t === $tvaSel ? 'selected' : '' ?>><?= View::e(rtrim(rtrim(number_format((float) $t, 2, ',', ''), '0'), ',')) ?> %</option><?php endforeach; ?></select>
        </div>
      </div>
      <div class="tot-ligne"><span>Montant de la TVA</span><span><strong id="aff-tva">—</strong></span></div>
      <div class="form-group" style="margin:8px 0 0"><label>Montant total facturé au client (TTC)</label><input type="number" step="0.01" name="montant_total" id="montant_total" required value="<?= $cotationPrecedente ? $vNum('montant_total') : '' ?>"></div>
      <div class="hint" style="font-size:11px;color:#667;margin-top:4px">Tout se calcule tout seul (achat × taux × marge, puis TVA). Vous pouvez corriger le HT ou le TTC à la main.</div>
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
      <div style="font-size:12px;color:#666;margin-top:4px"><?php if (!$cotationPrecedente && !empty($itemsPrecedents)): ?><?= !empty($depuisOffre) ? "Articles et prix repris de l'offre retenue. " : 'Articles repris de la demande. ' ?><?php endif; ?>Les prix unitaires (HT) se calculent depuis le coût, le taux et la marge ; vous pouvez les corriger.</div>
      <table style="margin-top:10px" id="items-table"><tbody id="items-body">
        <?php foreach ($itemsPrecedents ?? [] as $item) { include __DIR__ . '/../partials/_ligne_article.php'; } ?>
      </tbody></table>
    </div>

    <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"><?= View::e($v('notes')) ?></textarea></div>

    <button type="submit" class="btn"><?= $cotationPrecedente ? 'Créer la nouvelle version' : 'Créer la cotation' ?></button>
    <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" class="btn btn-secondary">Annuler</a>
  </form>
</div>

<template id="item-row-template"><?php $item = []; include __DIR__ . '/../partials/_ligne_article.php'; ?></template>

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
<script>
// Cotation : coût d'achat (devise de l'offre) × taux de change × (1 + marge) = HT ; + TVA = TTC.
(function () {
  var f = document.getElementById('montant_total').closest('form'), body = document.getElementById('items-body');
  var $ = function (id) { return document.getElementById(id); };
  var ht = $('montant_ht'), ttc = $('montant_total'), tva = $('tva_taux'), achat = $('achat_origine'), marge = f.querySelector('[name="marge_pourcentage"]'),
      dv = $('devise_devis'), dOffre = $('devise_achat'), taux = $('taux_change'), bloc = $('bloc-taux');
  var T = <?= json_encode(['EUR' => ['XAF' => $tauxEurFcfa, 'XOF' => $tauxEurFcfa], 'XAF' => ['EUR' => 1 / max($tauxEurFcfa, 0.0001), 'XOF' => 1], 'XOF' => ['EUR' => 1 / max($tauxEurFcfa, 0.0001), 'XAF' => 1]]) ?>;
  var auto = !<?= $cotationPrecedente ? 'true' : 'false' ?>, tauxManuel = <?= $tauxPrec > 0 ? 'true' : 'false' ?>;
  var r2 = function (x) { return (Math.round(x * 100) / 100).toFixed(2); };
  var num = function (el) { var v = el ? parseFloat(el.value) : NaN; return v; };
  function memeDevise() { return dv.value === dOffre.value; }
  function majTaux() {
    $('lib-offre').textContent = dOffre.value; $('lib-devis').textContent = dv.value;
    bloc.style.display = memeDevise() ? 'none' : '';
    if (!memeDevise() && !tauxManuel) { var t = (T[dOffre.value] || {})[dv.value]; taux.value = t ? String(Math.round(t * 1000000) / 1000000) : ''; }
  }
  function tauxEff() { return memeDevise() ? 1 : num(taux); }
  function sommeLignes() {
    var s = 0, n = 0;
    body.querySelectorAll('tr').forEach(function (tr) {
      var q = parseFloat(tr.querySelector('[name="item_quantite[]"]').value), p = parseFloat(tr.querySelector('[name="item_prix_unitaire[]"]').value), m = tr.querySelector('.art-montant');
      if (!isNaN(q) && !isNaN(p)) { s += q * p; n++; if (m) m.value = r2(q * p); } else if (m) m.value = '';
    });
    return n ? s : null;
  }
  function affTva() { var h = num(ht), t = num(ttc); $('aff-tva').textContent = (!isNaN(h) && !isNaN(t)) ? r2(t - h) + ' ' + dv.value : '—'; }
  function depuisHt() { var h = num(ht), t = num(tva); if (!isNaN(h)) ttc.value = r2(h * (1 + (isNaN(t) ? 0 : t) / 100)); affTva(); }
  function recalc() {
    var tx = tauxEff(), m = marge ? (parseFloat(marge.value) || 0) : 0, a = num(achat);
    if (!isNaN(tx) && tx > 0) {
      body.querySelectorAll('[data-cout]').forEach(function (inp) { inp.value = r2(parseFloat(inp.dataset.cout) * tx * (1 + m / 100)); });
    }
    var s = sommeLignes();
    if (auto) {
      if (!isNaN(a) && !isNaN(tx) && tx > 0) ht.value = r2(a * tx * (1 + m / 100));
      else if (s !== null) ht.value = r2(s);
      depuisHt();
    }
  }
  ht.addEventListener('input', function () { auto = false; depuisHt(); });
  ttc.addEventListener('input', function () { auto = false; var t = num(tva), v = num(ttc); if (!isNaN(v)) ht.value = r2(v / (1 + (isNaN(t) ? 0 : t) / 100)); affTva(); });
  tva.addEventListener('change', depuisHt);
  [achat, marge].forEach(function (el) { if (el) el.addEventListener('input', function () { auto = true; recalc(); }); });
  taux.addEventListener('input', function () { tauxManuel = true; auto = true; recalc(); });
  dv.addEventListener('change', function () { tauxManuel = false; majTaux(); auto = true; recalc(); });
  body.addEventListener('input', function (e) {
    if (e.target.name === 'item_prix_unitaire[]' && e.target.dataset.cout) { e.target.removeAttribute('data-cout'); }
    var s = sommeLignes(); if (auto && s !== null && isNaN(num(achat))) { ht.value = r2(s); depuisHt(); }
  });
  body.addEventListener('click', function () { setTimeout(sommeLignes, 0); });
  majTaux(); recalc(); affTva();
})();
</script>
