<?php
use App\Core\View;
use App\Models\EntrepriseInfo;
use App\Models\Expedition;
/** Page publique des départs. Variables : $emetteur, $logoUri, $departs, $whatsapp. Aucune donnée client. */
$col = EntrepriseInfo::couleurEffective($emetteur);
$tel = preg_replace('/\D/', '', (string) $whatsapp);
$mode = EntrepriseInfo::enteteMode($emetteur, !empty($logoUri));
$trajets = [];
foreach ($departs as $d) { $trajets[$d['origine'] . ' → ' . $d['destination']] = true; }
$modes = [];
foreach ($departs as $d) { $modes[$d['mode']] = Expedition::MODES[$d['mode']] ?? $d['mode']; }
$donnees = array_map(fn($d) => $d + ['trajet' => $d['origine'] . ' → ' . $d['destination'], 'mode_lib' => Expedition::MODES[$d['mode']] ?? $d['mode'], 'type_lib' => Expedition::TYPES[$d['type']] ?? ''], $departs);
$unites = ['m3' => 'm³', 'kg' => 'kg', 'colis' => 'colis', 'palette' => 'palette'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Prochains départs — <?= View::e($emetteur['nom']) ?></title>
<style>
:root{--c:<?= $col ?>}
*{box-sizing:border-box}body{margin:0;background:#f3f5f8;font:15px/1.45 -apple-system,Segoe UI,Roboto,sans-serif;color:#1c2030}
.top{background:var(--c);color:#fff;padding:18px 16px}.in{max-width:860px;margin:0 auto}
.top .brand{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.top img{max-height:48px;max-width:160px;background:#fff;border-radius:6px;padding:4px}
.top h1{margin:0;font-size:20px}.top p{margin:4px 0 0;font-size:13.5px;opacity:.92}
.filtres{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}.filtres>div{flex:1;min-width:170px}
label{display:block;font-size:12px;color:#556;margin-bottom:3px}
select{width:100%;padding:11px 10px;border:1px solid #cfd6df;border-radius:10px;background:#fff;font-size:15px}
.dep{display:flex;gap:14px;align-items:center;flex-wrap:wrap;background:#fff;border:1px solid #dde4ec;border-radius:12px;padding:12px 14px;margin-bottom:10px}
.date{min-width:84px;text-align:center;border-right:1px solid #eef1f5;padding-right:12px}.date small{display:block;font-size:11px;color:#667}.date b{font-size:20px;color:var(--c);line-height:1.2}
.mid{flex:1;min-width:190px}.mid b{font-size:16px}.mid span{display:block;font-size:12.5px;color:#556}
.pl{min-width:130px;font-size:12.5px}.pill{display:inline-block;border-radius:999px;padding:2px 10px;font-weight:600;font-size:12px}
.ok{background:#e8f5ec;color:#1e7b3a}.peu{background:#fff4e0;color:#b45309}.complet{background:#fdeaea;color:#b42318}.sd{background:#eef1f5;color:#445}
.tarif{min-width:140px;font-size:13px}
.btn{display:inline-block;background:#1e9e55;color:#fff;text-decoration:none;border-radius:10px;padding:11px 14px;font-weight:600;font-size:14px;text-align:center}
.btn.gris{background:#fff;color:#334;border:1px solid #cfd6df}
.vide{background:#fff;border:1px dashed #cfd6df;border-radius:12px;padding:24px;text-align:center;color:#556}
.note{font-size:12.5px;color:#667;margin:14px 0 30px}
@media(max-width:560px){.date{border-right:0;padding-right:0;text-align:left;min-width:0}.btn{width:100%}}
</style>
</head>
<body>
<div class="top"><div class="in"><div class="brand">
  <?php if ($mode !== 'nom' && !empty($logoUri)): ?><img src="<?= $logoUri ?>" alt="<?= View::e($emetteur['nom']) ?>"><?php endif; ?>
  <div><h1><?= View::e($emetteur['nom']) ?></h1><p>Prochains départs. Choisissez votre trajet et votre type de produit.</p></div>
</div></div></div>
<div class="in" style="padding:0 16px">
<?php if (!$departs): ?>
  <div class="vide" style="margin-top:18px">Aucun départ n'est ouvert aux réservations pour le moment.<?php if ($tel !== ''): ?><br><br><a class="btn" href="https://wa.me/<?= $tel ?>?text=<?= rawurlencode('Bonjour, quand est le prochain départ ?') ?>">Demander la prochaine date sur WhatsApp</a><?php endif; ?></div>
<?php else: ?>
  <div class="filtres">
    <div><label for="f-trajet">Trajet</label><select id="f-trajet"><option value="">Tous les trajets</option><?php foreach (array_keys($trajets) as $t): ?><option><?= View::e($t) ?></option><?php endforeach; ?></select></div>
    <div><label for="f-produit">Type de produit</label><select id="f-produit"><option value="alimentaire">Alimentaire</option><option value="non_alimentaire">Non alimentaire</option></select></div>
    <?php if (count($modes) > 1): ?><div><label for="f-mode">Mode</label><select id="f-mode"><option value="">Tous</option><?php foreach ($modes as $k => $l): ?><option value="<?= View::e($k) ?>"><?= View::e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  </div>
  <div id="liste"></div>
  <div class="note">Les dates, les places et les tarifs sont mis à jour automatiquement. Tarifs indicatifs : le prix final est confirmé avec vous. Un cas particulier (produit hors format, alcool, produit dangereux) ? Précisez-le dans votre message.</div>
<?php endif; ?>
</div>
<script>
(function () {
  var D = <?= json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var TEL = <?= json_encode($tel) ?>, UN = <?= json_encode($unites) ?>;
  var liste = document.getElementById('liste'); if (!liste) { return; }
  var fT = document.getElementById('f-trajet'), fP = document.getElementById('f-produit'), fM = document.getElementById('f-mode');
  function e(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function dateFr(iso) { if (!iso) { return null; } var p = iso.split('-'); var m = ['janv.','févr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.']; return parseInt(p[2], 10) + ' ' + m[parseInt(p[1], 10) - 1]; }
  function dateLongue(iso) { var p = iso.split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }
  function nombre(n) { return String(n).replace('.', ','); }
  function tarif(d, alim) {
    var v = alim ? d.tarif_alim : d.tarif_non_alim;
    if (v === null || v === undefined) { return '<span style="color:#667">Tarif sur demande</span>'; }
    return (alim ? 'Alimentaire' : 'Non alimentaire') + ' : <b>' + nombre(v) + ' ' + e(d.devise === 'EUR' ? '€' : d.devise) + (d.unite ? ' / ' + e(UN[d.unite] || d.unite) : '') + '</b>';
  }
  function whats(d, alim) {
    var prod = alim ? 'Alimentaire' : 'Non alimentaire';
    if (d.etat === 'complet') { return 'https://wa.me/' + TEL + '?text=' + encodeURIComponent('Bonjour, le départ du ' + (d.depart ? dateLongue(d.depart) : '') + ' (' + d.trajet + ") est complet. Quand est le prochain départ ?\nType de produit : " + prod); }
    var msg = 'Bonjour, je voudrais réserver sur le départ du ' + (d.depart ? dateLongue(d.depart) : 'date à confirmer') + ' : ' + d.trajet + ' (' + d.mode_lib.toLowerCase() + ").\nType de produit : " + prod + "\nQuantité estimée : \nMon nom : ";
    return 'https://wa.me/' + TEL + '?text=' + encodeURIComponent(msg);
  }
  function maj() {
    var alim = fP.value === 'alimentaire', html = '', n = 0;
    D.forEach(function (d) {
      if (fT.value && d.trajet !== fT.value) { return; }
      if (fM && fM.value && d.mode !== fM.value) { return; }
      n++;
      var pl = d.etat === 'complet' ? '<span class="pill complet">Complet</span>' : d.etat === 'peu' ? '<span class="pill peu">Dernières places</span><br>Environ ' + nombre(d.places) + ' m³ libres'
        : d.etat === 'ok' ? '<span class="pill ok">Places disponibles</span><br>Environ ' + nombre(d.places) + ' m³ libres' : '<span class="pill sd">Sur demande</span>';
      var dt = dateFr(d.depart);
      var btn = TEL ? (d.etat === 'complet' ? '<a class="btn gris" href="' + e(whats(d, alim)) + '">Complet : demander la prochaine date</a>' : '<a class="btn" href="' + e(whats(d, alim)) + '">Réserver sur WhatsApp</a>') : '';
      html += '<div class="dep"><div class="date"><small>Départ</small><b>' + (dt || 'À confirmer') + '</b></div><div class="mid"><b>' + e(d.trajet) + '</b><span>' + e(d.mode_lib) + (d.type_lib ? ' · ' + e(d.type_lib.toLowerCase()) : '') + (d.cloture ? ' · réservations jusqu\'au ' + dateFr(d.cloture) : '') + '</span></div><div class="pl">' + pl + '</div><div class="tarif">' + tarif(d, alim) + '</div>' + btn + '</div>';
    });
    liste.innerHTML = n ? html : '<div class="vide">Aucun départ pour ce choix. Essayez « Tous les trajets ».</div>';
  }
  [fT, fP, fM].forEach(function (f) { if (f) { f.addEventListener('change', maj); } });
  maj();
})();
</script>
</body>
</html>
