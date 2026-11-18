<?php
use App\Core\View;
use App\Models\EntrepriseInfo;
use App\Models\Expedition;
/** Page publique des départs. Variables : $emetteur, $logoUri, $departs, $whatsapp. Aucune donnée client. */
$col = EntrepriseInfo::couleurEffective($emetteur);
$tel = preg_replace('/\D/', '', (string) $whatsapp);
$mode = EntrepriseInfo::enteteMode($emetteur, !empty($logoUri));
$trajets = []; $transports = [];
foreach ($departs as $d) { $trajets[$d['origine'] . ' → ' . $d['destination']] = true; $transports[$d['transport_lib']] = true; }
$donnees = array_map(fn($d) => $d + ['trajet' => $d['origine'] . ' → ' . $d['destination']], $departs);
$tel = preg_replace('/\D/', '', (string) $whatsapp);
$interdits = trim((string) ($interdits ?? ''));
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
.interdits{background:#fff;border:1px solid #f0d9b5;border-left:4px solid var(--c);border-radius:10px;padding:12px 14px;margin:0 0 30px;font-size:13.5px}.interdits b{display:block;margin-bottom:4px}
.depot{min-width:200px;flex:1;font-size:12.5px;background:#f4f8f6;border-radius:8px;padding:8px 10px;white-space:pre-line}.barre{height:8px;background:#eef1f5;border-radius:99px;overflow:hidden;margin-top:4px}.barre i{display:block;height:8px;background:var(--c)}
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
    <?php if (count($transports) > 1): ?><div><label for="f-mode">Transport</label><select id="f-mode"><option value="">Tous</option><?php foreach (array_keys($transports) as $l): ?><option><?= View::e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  </div>
  <div id="liste"></div>
  <div class="note">Les dates, les places et les tarifs sont mis à jour automatiquement. Tous les tarifs sont au kilo et indicatifs : le prix final est confirmé avec vous. Un cas particulier ? Précisez-le dans votre message.</div>
<?php endif; ?>
<?php if ($interdits !== ''): ?><div class="interdits"><b>Produits interdits et conditions</b><div><?= nl2br(View::e($interdits)) ?></div></div><?php endif; ?>
</div>
<script>
(function () {
  var D = <?= json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var TEL = <?= json_encode($tel) ?>;
  var liste = document.getElementById('liste'); if (!liste) { return; }
  var fT = document.getElementById('f-trajet'), fP = document.getElementById('f-produit'), fM = document.getElementById('f-mode');
  var JOURS = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'], MOIS = ['janv.','févr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
  function e(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function dt(iso) { var p = iso.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function dateCourte(iso) { if (!iso) { return null; } var d = dt(iso); return d.getDate() + ' ' + MOIS[d.getMonth()]; }
  function dateJour(iso) { var d = dt(iso); return JOURS[d.getDay()] + ' ' + d.getDate() + ' ' + MOIS[d.getMonth()]; }
  function dateLongue(iso) { var p = iso.split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }
  function nb(n) { return String(n).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
  function tarif(d, alim) {
    var v = d.produit ? (d.produit === 'alimentaire' ? d.tarif_alim : d.tarif_non_alim) : (alim ? d.tarif_alim : d.tarif_non_alim);
    var lib = d.produit ? (d.produit === 'alimentaire' ? 'Alimentaire' : 'Non alimentaire') : (alim ? 'Alimentaire' : 'Non alimentaire');
    if (v === null || v === undefined) { return '<span style="color:#667">Tarif sur demande</span>'; }
    return lib + ' : <b>' + nb(v) + ' ' + (d.devise === 'EUR' ? '€' : e(d.devise)) + ' / kg</b>';
  }
  function whats(d, alim) {
    var prod = d.produit ? (d.produit === 'alimentaire' ? 'Alimentaire' : 'Non alimentaire') : (alim ? 'Alimentaire' : 'Non alimentaire');
    var quand = d.depart ? dateLongue(d.depart) : 'date à confirmer', msg;
    if (d.etat === 'complet') { msg = 'Bonjour, le départ du ' + quand + ' (' + d.trajet + ") est complet. Quand est le prochain départ ?\nType de produit : " + prod; }
    else if (d.genre === 'hebdo') { msg = "Bonjour, j'ai un colis à déposer pour le départ du " + quand + ' : ' + d.trajet + ' (' + d.transport_lib.toLowerCase() + ").\nType de produit : " + prod + "\nPoids estimé : \nMon nom : "; }
    else { msg = 'Bonjour, je voudrais réserver sur le départ ' + (d.prevu ? 'prévu vers le ' : 'du ') + quand + ' : ' + d.trajet + ' (' + d.transport_lib.toLowerCase() + ").\nType de produit : " + prod + "\nQuantité estimée : \nMon nom : "; }
    return 'https://wa.me/' + TEL + '?text=' + encodeURIComponent(msg);
  }
  function etat(d) {
    switch (d.etat) {
      case 'complet': return '<span class="pill complet">Complet</span>';
      case 'peu': return '<span class="pill peu">Dernières places</span>' + (d.places_kg !== null ? '<br>Environ ' + nb(d.places_kg) + ' kg libres' : '');
      case 'ok': return '<span class="pill ok">Places disponibles</span>' + (d.places_kg !== null ? '<br>Il reste environ ' + nb(d.places_kg) + ' kg' : '');
      case 'confirme': return '<span class="pill ok">Départ confirmé</span>';
      case 'remplissage':
        var p = d.progres, t = '<span class="pill peu">En cours de remplissage</span>';
        if (p && p.chiffres) { t += '<br>' + (p.type === 'kg' ? nb(p.actuel) + ' kg sur ' + nb(p.objectif) + ' kg' : nb(p.actuel) + ' réservations sur ' + nb(p.objectif)); }
        return t;
      case 'depot': return '<span class="pill ok">Départ chaque semaine</span>';
      default: return '<span class="pill sd">Sur demande</span>';
    }
  }
  function maj() {
    var alim = fP.value === 'alimentaire', html = '', n = 0;
    D.forEach(function (d) {
      if (d.produit && d.produit !== fP.value) { return; }
      if (fT.value && d.trajet !== fT.value) { return; }
      if (fM && fM.value && d.transport_lib !== fM.value) { return; }
      n++;
      var hebdo = d.genre === 'hebdo';
      var petit = hebdo ? 'Prochain départ' : (d.prevu ? 'Vers le' : 'Départ');
      var dateTxt = d.depart ? (hebdo ? dateJour(d.depart) : dateCourte(d.depart)) : 'À confirmer';
      var btn = '';
      if (TEL) {
        btn = d.etat === 'complet' ? '<a class="btn gris" href="' + e(whats(d, alim)) + '">Complet : demander la prochaine date</a>'
          : '<a class="btn" href="' + e(whats(d, alim)) + '">' + (hebdo ? "J'ai un colis à déposer" : 'Réserver sur WhatsApp') + '</a>';
      }
      var sous = e(d.transport_lib) + (d.cloture ? ' · réservations jusqu\'au ' + dateCourte(d.cloture) : '');
      var regle = hebdo ? '<div style="font-size:12.5px;margin-top:4px">' + e(d.regle) + '</div>' : '';
      var depot = hebdo && d.depot ? '<div class="depot"><b>Où déposer</b>\n' + e(d.depot) + '</div>' : '';
      var barre = d.etat === 'remplissage' && d.progres ? '<div style="flex-basis:100%;padding-left:2px"><div class="barre"><i style="width:' + d.progres.pct + '%"></i></div><div style="font-size:11.5px;color:#556;margin-top:3px">Ce départ est confirmé dès que ' + (d.progres.type === 'kg' ? nb(d.progres.objectif) + ' kg' : nb(d.progres.objectif) + ' réservations') + ' sont atteints. Plus vous réservez tôt, plus vite on part.</div></div>' : '';
      html += '<div class="dep"><div class="date"><small>' + petit + '</small><b>' + dateTxt + '</b></div><div class="mid"><b>' + e(d.trajet) + '</b><span>' + sous + '</span>' + regle + '</div>' + depot + '<div class="pl">' + etat(d) + '</div><div class="tarif">' + tarif(d, alim) + '</div>' + btn + barre + '</div>';
    });
    liste.innerHTML = n ? html : '<div class="vide">Aucun départ pour ce choix. Essayez « Tous les trajets ».</div>';
  }
  [fT, fP, fM].forEach(function (f) { if (f) { f.addEventListener('change', maj); } });
  maj();
})();
</script>
</body>
</html>
