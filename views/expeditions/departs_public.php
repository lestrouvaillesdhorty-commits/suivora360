<?php
use App\Core\View;
use App\Models\DepartsPublics;
use App\Models\EntrepriseInfo;
/** Page publique des départs. Variables : $emetteur, $logoUri, $departs, $whatsapp, $interdits, $aspect, $infos, $lieux, $token. Aucune donnée client. */
$asp = $aspect ?? ['titre' => '', 'soustitre' => '', 'ambiance' => 'menthe', 'photo' => '', 'tel' => '', 'email' => '', 'etapes' => 1, 'conv' => 1, 'apercu' => 1];
$amb = DepartsPublics::AMBIANCES[$asp['ambiance']] ?? DepartsPublics::AMBIANCES['menthe'];
$tel = preg_replace('/\D/', '', (string) $whatsapp);
$mode = EntrepriseInfo::enteteMode($emetteur, !empty($logoUri));
$trajets = []; $transports = [];
foreach ($departs as $d) { $trajets[$d['origine'] . ' → ' . $d['destination']] = true; $transports[$d['transport_lib']] = true; }
$donnees = array_map(fn($d) => $d + ['trajet' => $d['origine'] . ' → ' . $d['destination']], $departs);
$interdits = trim((string) ($interdits ?? ''));
$infos = $infos ?? []; $lieux = $lieux ?? [];
$titre = trim((string) $asp['titre']) !== '' ? trim((string) $asp['titre']) : (string) $emetteur['nom'];
$sousTitre = trim((string) $asp['soustitre']) !== '' ? trim((string) $asp['soustitre']) : 'Choisissez votre trajet et votre type de produit, puis réservez sur WhatsApp.';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$resume = $asp['apercu'] ? DepartsPublics::resumeApercu($departs) : '';
$prochain = null;
foreach ($departs as $d) { if (!empty($d['depart']) && ($prochain === null || $d['depart'] < $prochain['depart'])) { $prochain = $d; } }
$photoUrl = !empty($asp['photo']) ? '/index.php?r=departs/' . $token . '/photo' : '';
$contact = $tel !== '' || trim((string) $asp['tel']) !== '' || trim((string) $asp['email']) !== '';
$mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Prochains départs — <?= View::e($titre) ?></title>
<meta property="og:type" content="website"><meta property="og:title" content="<?= View::e('Prochains départs · ' . $titre) ?>">
<meta property="og:description" content="<?= View::e($resume !== '' ? $resume : $sousTitre) ?>">
<?php if ($photoUrl): ?><meta property="og:image" content="<?= View::e($base . $photoUrl) ?>"><?php endif; ?>
<style>
:root{--fond1:<?= $amb[3] ?>;--txt:<?= $amb[1] ?>;--acc:<?= $amb[2] ?>;--bord:<?= $amb[4] ?>;--hero:<?= $amb[0] ?>}
*{box-sizing:border-box}body{margin:0;background:#FBFCFC;font:15px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;color:#1d2a2c}
.in{max-width:880px;margin:0 auto;padding:0 16px}
.hero{background:var(--hero);color:var(--txt);padding:26px 0 22px;border-bottom:1px solid var(--bord);position:relative;overflow:hidden}
.hero.photo{padding:0}.hero.photo .fond{height:200px;background:#cfd8dc center/cover no-repeat}.hero.photo .in{position:relative}
.hero .marque{display:flex;align-items:center;gap:14px;flex-wrap:wrap}.hero img.logo{max-height:52px;max-width:170px;background:#fff;border-radius:10px;padding:5px}
.hero.photo .bloc{background:#fff;border:1px solid var(--bord);border-radius:16px;margin-top:-46px;padding:16px 18px;position:relative;color:var(--txt)}
.hero h1{margin:0;font:700 26px/1.2 Georgia,'Times New Roman',serif;color:var(--txt)}.hero p{margin:6px 0 0;font-size:14.5px;opacity:.9}
.proch{display:inline-block;margin-top:12px;background:#fff;border:1px solid var(--bord);border-radius:999px;padding:6px 14px;font-size:13.5px;color:var(--txt)}.proch b{color:var(--acc)}
.hero.photo{padding-bottom:20px}
h2.t{font:700 19px Georgia,serif;margin:30px 0 12px;color:var(--txt)}
.info{border-radius:12px;padding:12px 14px;margin:10px 0;background:var(--fond1);border:1px solid var(--bord);color:var(--txt);font-size:14.5px}.info.alerte{background:#FFF6E5;border-color:#F0DCB0;color:#5B4210}
.etapes{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:6px}.et{background:#fff;border:1px solid var(--bord);border-radius:12px;padding:12px;font-size:13.5px}.et b{display:block;color:var(--acc);margin-bottom:2px;font-size:14px}
.filtres{display:flex;gap:10px;flex-wrap:wrap;margin:6px 0 14px}.filtres>div{flex:1;min-width:170px}
label{display:block;font-size:12px;color:#5b6b6e;margin-bottom:3px}
select{width:100%;padding:11px 10px;border:1px solid var(--bord);border-radius:10px;background:#fff;font-size:15px;color:#1d2a2c}
.dep{display:flex;gap:0;background:#fff;border:1px solid var(--bord);border-radius:16px;margin-bottom:12px;overflow:hidden}
.date{flex:0 0 96px;background:var(--fond1);color:var(--txt);text-align:center;padding:12px 6px;display:flex;flex-direction:column;justify-content:center}
.date small{font-size:10.5px;line-height:1.2;opacity:.8}.date .j{font-size:13px;margin-top:4px}.date b{font:700 34px/1 Georgia,serif;color:var(--acc)}.date .m{font-size:13px}
.corps{flex:1;padding:12px 16px;min-width:0}
.route{display:flex;align-items:center;gap:10px;font-size:17px;flex-wrap:wrap}.ligne{flex:0 1 70px;min-width:36px;height:2px;background:var(--bord);position:relative;display:block}.ligne u{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);background:#fff;padding:0 6px;text-decoration:none;color:var(--acc);font-size:15px;line-height:1}
.sous{font-size:12.5px;color:#5b6b6e;margin-top:2px}.regle{font-size:12.5px;margin-top:4px;color:#3c4d50}
.bas{display:flex;gap:18px;flex-wrap:wrap;align-items:flex-start;margin-top:10px}.pl{font-size:12.5px;min-width:140px}.tarif{font-size:13.5px}
.pill{display:inline-block;border-radius:999px;padding:2px 10px;font-weight:600;font-size:12px}
.ok{background:#E4F4EA;color:#1e6b3a}.peu{background:#FFF2DD;color:#9a5a0b}.complet{background:#FCE8E6;color:#a02a20}.sd{background:#EEF1F2;color:#445}
.btn{display:inline-block;margin-top:12px;background:#1e9e55;color:#fff;text-decoration:none;border-radius:10px;padding:11px 16px;font-weight:600;font-size:14px;text-align:center;border:0;cursor:pointer}
.btn.gris{background:#fff;color:#334;border:1px solid var(--bord)}.btn.sec{background:#fff;color:var(--txt);border:1px solid var(--bord);margin-top:0}
.vide{background:#fff;border:1px dashed var(--bord);border-radius:12px;padding:24px;text-align:center;color:#556}
.depot{margin-top:10px;font-size:12.5px;background:#F4F8F6;border-radius:10px;padding:8px 10px;white-space:pre-line}.prog{margin-top:10px;font-size:11.5px;color:#556}.barre{height:8px;background:#EAF0F0;border-radius:99px;overflow:hidden;margin-bottom:3px}.barre i{display:block;height:8px;background:var(--acc)}
.note{font-size:12.5px;color:#667;margin:14px 0}
.lieux{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:10px}.lieu{background:#fff;border:1px solid var(--bord);border-radius:14px;padding:14px;font-size:14px}.lieu b{display:block;font-size:15.5px;color:var(--txt);margin-bottom:3px}.lieu .h{color:#5b6b6e;font-size:13px;margin-top:4px;white-space:pre-line}.lieu a{color:var(--acc);font-weight:600;font-size:13px;text-decoration:none}
.contact{background:var(--fond1);border:1px solid var(--bord);border-radius:16px;padding:16px;margin:30px 0 14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}.contact h2{flex-basis:100%;margin:0 0 2px;font:700 18px Georgia,serif;color:var(--txt)}.contact .btn{margin-top:0}
.interdits{background:#fff;border:1px solid var(--bord);border-left:4px solid var(--acc);border-radius:10px;padding:12px 14px;margin:20px 0;font-size:13.5px}.interdits b{display:block;margin-bottom:4px}
.pied{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 40px}
@media(max-width:600px){.etapes{grid-template-columns:1fr}.date{flex-basis:78px}.date b{font-size:28px}.btn{width:100%}.hero h1{font-size:22px}.contact .btn{flex:1 1 100%}}
</style>
</head>
<body>
<header class="hero<?= $photoUrl ? ' photo' : '' ?>">
<?php if ($photoUrl): ?><div class="fond" style="background-image:url('<?= View::e($photoUrl) ?>')"></div><?php endif; ?>
<div class="in"><div class="<?= $photoUrl ? 'bloc' : '' ?>">
  <div class="marque">
    <?php if ($mode !== 'nom' && !empty($logoUri)): ?><img class="logo" src="<?= $logoUri ?>" alt="<?= View::e($emetteur['nom']) ?>"><?php endif; ?>
    <div><h1><?= View::e($titre) ?></h1><p><?= View::e($sousTitre) ?></p></div>
  </div>
  <?php if ($prochain): $t = strtotime($prochain['depart']); ?><span class="proch">Prochain départ : <b><?= (int) date('j', $t) . ' ' . $mois[(int) date('n', $t) - 1] ?></b> · <?= View::e($prochain['origine'] . ' → ' . $prochain['destination']) ?></span><?php endif; ?>
</div></div>
</header>
<main class="in">
<?php foreach ($infos as $i): ?><div class="info<?= $i['niveau'] === 'alerte' ? ' alerte' : '' ?>"><?= nl2br(View::e($i['texte'])) ?></div><?php endforeach; ?>
<?php if ($asp['etapes']): ?>
<h2 class="t">Comment ça marche</h2>
<div class="etapes"><div class="et"><b>1. Choisissez</b>Votre trajet et votre type de produit.</div><div class="et"><b>2. Écrivez-nous</b>Le bouton ouvre WhatsApp avec le message déjà prêt.</div><div class="et"><b>3. Déposez votre colis</b>Nous confirmons le prix final et le lieu de dépôt.</div></div>
<?php endif; ?>
<h2 class="t">Les départs</h2>
<?php if (!$departs): ?>
  <div class="vide">Aucun départ n'est ouvert aux réservations pour le moment.<?php if ($tel !== ''): ?><br><br><a class="btn" href="https://wa.me/<?= $tel ?>?text=<?= rawurlencode('Bonjour, quand est le prochain départ ?') ?>">Demander la prochaine date sur WhatsApp</a><?php endif; ?></div>
<?php else: ?>
  <div class="filtres">
    <div><label for="f-trajet">Trajet</label><select id="f-trajet"><option value="">Tous les trajets</option><?php foreach (array_keys($trajets) as $t): ?><option><?= View::e($t) ?></option><?php endforeach; ?></select></div>
    <div><label for="f-produit">Type de produit</label><select id="f-produit"><option value="alimentaire">Alimentaire</option><option value="non_alimentaire">Non alimentaire</option></select></div>
    <?php if (count($transports) > 1): ?><div><label for="f-mode">Transport</label><select id="f-mode"><option value="">Tous</option><?php foreach (array_keys($transports) as $l): ?><option><?= View::e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
  </div>
  <div id="liste"></div>
  <div class="note">Les dates, les places et les tarifs sont mis à jour automatiquement. Tous les tarifs sont au kilo et indicatifs<?= $asp['conv'] ? ' : les montants convertis à côté sont donnés à titre indicatif' : '' ?> ; le prix final est confirmé avec vous. Un cas particulier ? Précisez-le dans votre message.</div>
<?php endif; ?>
<?php if ($lieux): ?>
<h2 class="t">Où déposer votre colis</h2>
<div class="lieux"><?php foreach ($lieux as $l): ?><div class="lieu"><b><?= View::e($l['nom']) ?></b><?= nl2br(View::e((string) $l['adresse'])) ?><?php if (!empty($l['horaires'])): ?><div class="h"><?= View::e($l['horaires']) ?></div><?php endif; ?><?php if (!empty($l['carte_url'])): ?><div style="margin-top:6px"><a href="<?= View::e($l['carte_url']) ?>" target="_blank" rel="noopener">Voir sur la carte</a></div><?php endif; ?></div><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($interdits !== ''): ?><div class="interdits"><b>Produits interdits et conditions</b><div><?= nl2br(View::e($interdits)) ?></div></div><?php endif; ?>
<?php if ($contact): ?>
<section class="contact"><h2>Nous contacter</h2>
  <?php if ($tel !== ''): ?><a class="btn" href="https://wa.me/<?= $tel ?>">WhatsApp</a><?php endif; ?>
  <?php if (trim((string) $asp['tel']) !== ''): ?><a class="btn sec" href="tel:<?= View::e(preg_replace('/[^0-9+]/', '', $asp['tel'])) ?>">Appeler <?= View::e($asp['tel']) ?></a><?php endif; ?>
  <?php if (trim((string) $asp['email']) !== ''): ?><a class="btn sec" href="mailto:<?= View::e($asp['email']) ?>"><?= View::e($asp['email']) ?></a><?php endif; ?>
</section>
<?php endif; ?>
<?php if ($departs): ?><div class="pied"><button class="btn sec" type="button" id="img-dl">Télécharger l'image des départs</button></div><?php endif; ?>
</main>
<script src="/assets/js/qr.js"></script>
<script>
(function () {
  var D = <?= json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var TEL = <?= json_encode($tel) ?>;
  var TAUX = <?= json_encode((float) ($tauxEurFcfa ?? 0)) ?>;
  var CONV = <?= $asp['conv'] ? 'true' : 'false' ?>;
  var liste = document.getElementById('liste'); if (!liste) { return; }
  var fT = document.getElementById('f-trajet'), fP = document.getElementById('f-produit'), fM = document.getElementById('f-mode');
  var JOURS = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'], MOIS = ['janv.','févr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
  function e(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
  function dt(iso) { var p = iso.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function dateCourte(iso) { if (!iso) { return null; } var d = dt(iso); return d.getDate() + ' ' + MOIS[d.getMonth()]; }
  function dateJour(iso) { var d = dt(iso); return JOURS[d.getDay()] + ' ' + d.getDate() + ' ' + MOIS[d.getMonth()]; }
  function dateLongue(iso) { var p = iso.split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }
  function nb(n) { return String(n).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
  function fcfa(dv) { return dv === 'XAF' || dv === 'XOF'; }
  function sym(dv) { return dv === 'EUR' ? '€' : (fcfa(dv) ? 'FCFA' : e(dv)); }
  function fmt(v, dv) { return nb(fcfa(dv) ? Math.round(v) : Math.round(v * 100) / 100) + ' ' + sym(dv); }
  function conv(v, dv) {
    if (!CONV || !(TAUX > 0)) { return ''; }
    if (dv === 'EUR') { return fmt(v * TAUX, 'XAF'); }
    if (fcfa(dv)) { return fmt(v / TAUX, 'EUR'); }
    return '';
  }
  function tarif(d, alim) {
    var v = d.produit ? (d.produit === 'alimentaire' ? d.tarif_alim : d.tarif_non_alim) : (alim ? d.tarif_alim : d.tarif_non_alim);
    var lib = d.produit ? (d.produit === 'alimentaire' ? 'Alimentaire' : 'Non alimentaire') : (alim ? 'Alimentaire' : 'Non alimentaire');
    if (v === null || v === undefined) { return '<span style="color:#667">Tarif sur demande</span>'; }
    var c = conv(v, d.devise);
    return lib + ' : <b>' + fmt(v, d.devise) + ' / kg</b>' + (c ? '<div style="color:#667;font-size:12px">≈ ' + c + ' / kg</div>' : '');
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
      var dt0 = d.depart ? dt(d.depart) : null;
      var jour = dt0 ? JOURS[dt0.getDay()] : '';
      var grand = dt0 ? dt0.getDate() : '?', mois = dt0 ? MOIS[dt0.getMonth()] : 'à confirmer';
      var btn = '';
      if (TEL) {
        btn = d.etat === 'complet' ? '<a class="btn gris" href="' + e(whats(d, alim)) + '">Complet : demander la prochaine date</a>'
          : '<a class="btn" href="' + e(whats(d, alim)) + '">' + (hebdo ? "J'ai un colis à déposer" : 'Réserver sur WhatsApp') + '</a>';
      }
      var sous = e(d.transport_lib) + (d.cloture ? ' · réservations jusqu\'au ' + dateCourte(d.cloture) : '');
      var regle = hebdo && d.regle ? '<div class="regle">' + e(d.regle) + '</div>' : '';
      var depot = hebdo && d.depot ? '<div class="depot"><b>Où déposer</b>\n' + e(d.depot) + '</div>' : '';
      var barre = d.etat === 'remplissage' && d.progres ? '<div class="prog"><div class="barre"><i style="width:' + d.progres.pct + '%"></i></div><div>Ce départ est confirmé dès que ' + (d.progres.type === 'kg' ? nb(d.progres.objectif) + ' kg' : nb(d.progres.objectif) + ' réservations') + ' sont atteints. Plus vous réservez tôt, plus vite on part.</div></div>' : '';
      var ic = d.transport === 'aerien' ? '✈' : (d.transport === 'maritime' ? '⚓' : '▶');
      html += '<article class="dep"><div class="date"><small>' + petit + '</small><span class="j">' + jour + '</span><b>' + grand + '</b><span class="m">' + mois + '</span></div>'
        + '<div class="corps"><div class="route"><b>' + e(d.origine) + '</b><i class="ligne"><u>' + ic + '</u></i><b>' + e(d.destination) + '</b></div>'
        + '<div class="sous">' + sous + '</div>' + regle
        + '<div class="bas"><div class="pl">' + etat(d) + '</div><div class="tarif">' + tarif(d, alim) + '</div></div>' + depot + barre + btn + '</div></article>';
    });
    liste.innerHTML = n ? html : '<div class="vide">Aucun départ pour ce choix. Essayez « Tous les trajets ».</div>';
  }
  [fT, fP, fM].forEach(function (f) { if (f) { f.addEventListener('change', maj); } });
  maj();
  // Image à partager (1080 x 1350) dessinée dans le navigateur, avec le QR code de la page.
  var bi = document.getElementById('img-dl');
  if (bi) { bi.addEventListener('click', function () {
    var W = 1080, H = 1350, c = document.createElement('canvas'); c.width = W; c.height = H; var x = c.getContext('2d');
    var st = getComputedStyle(document.documentElement), fond = st.getPropertyValue('--fond1').trim() || '#E3F2F1', txt = st.getPropertyValue('--txt').trim() || '#0F3F46', acc = st.getPropertyValue('--acc').trim() || '#0B7C8A';
    x.fillStyle = '#FFFFFF'; x.fillRect(0, 0, W, H); x.fillStyle = fond; x.fillRect(0, 0, W, 330);
    x.fillStyle = txt; x.font = '700 58px Georgia, serif'; x.textBaseline = 'alphabetic';
    var titre = <?= json_encode($titre) ?>; x.fillText(titre.length > 30 ? titre.slice(0, 29) + '…' : titre, 60, 150);
    x.font = '30px sans-serif'; x.fillStyle = acc; x.fillText('Prochains départs', 60, 205);
    var liste2 = D.filter(function (d) { return d.depart; }).sort(function (a, b) { return a.depart < b.depart ? -1 : 1; }).slice(0, 6), y = 400;
    liste2.forEach(function (d) {
      x.fillStyle = fond; x.fillRect(60, y - 60, 960, 130);
      x.fillStyle = acc; x.font = '700 44px sans-serif'; x.fillText(dateCourte(d.depart), 90, y + 10);
      x.fillStyle = txt; x.font = '600 34px sans-serif'; x.fillText(d.trajet.length > 34 ? d.trajet.slice(0, 33) + '…' : d.trajet, 330, y - 8);
      var v = d.tarif_alim !== null ? d.tarif_alim : d.tarif_non_alim;
      x.font = '28px sans-serif'; x.fillText(d.transport_lib + (v !== null && v !== undefined ? ' · ' + fmt(v, d.devise) + ' / kg' : ''), 330, y + 38);
      y += 150;
    });
    if (!liste2.length) { x.fillStyle = txt; x.font = '34px sans-serif'; x.fillText('Dates à confirmer : écrivez-nous sur WhatsApp.', 60, 430); }
    var q = document.createElement('canvas');
    try { SuivoraQR.dessiner(q, location.href.split('#')[0], 260); x.fillStyle = '#fff'; x.fillRect(60, 1020, 280, 280); x.drawImage(q, 70, 1030, 260, 260); } catch (er) {}
    x.fillStyle = txt; x.font = '600 34px sans-serif'; x.fillText('Scannez pour voir tous les départs', 380, 1100);
    x.font = '28px sans-serif'; x.fillText('et réserver sur WhatsApp', 380, 1145);
    var a = document.createElement('a'); a.download = 'departs.png'; a.href = c.toDataURL('image/png'); document.body.appendChild(a); a.click(); a.remove();
  }); }
})();
</script>
</body>
</html>
