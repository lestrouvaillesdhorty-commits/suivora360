<?php
use App\Models\Cotation;
use App\Models\Demande;
use App\Models\Dossier;
use App\Core\View;

/** Espace client externe — page principale. Variables : $lien, $client, $onglet, $dossiers, $cotations, $factures, $nbAttente, $emetteur, $base, $erreurs, $old, $csrfToken, $flashPortail. */
$etapesClient = ['qualifie' => 'Pris en charge', 'sourcing' => 'Recherche en cours', 'cotation' => 'Cotation', 'commande' => 'En commande', 'livraison' => 'En livraison', 'cloture' => 'Terminé'];
$fm = fn($m, $d) => number_format((float) $m, 0, ',', ' ') . ' ' . ($d !== null && $d !== '' ? \App\Core\View::dev($d) : '');
$onglets = ['dossiers' => 'Mes dossiers (' . count($dossiers) . ')', 'cotations' => 'Cotations (' . count($cotations) . ')', 'factures' => 'Factures (' . count($factures) . ')', 'demande' => 'Nouvelle demande'];
$valeur = fn(string $k) => View::e((string) ($old[$k] ?? ''));
$contact = trim(($client['contact_prenom'] ?? '') . ' ' . ($client['contact_nom'] ?? ''));
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Espace client — <?= View::e($client['nom']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<?php include __DIR__ . '/_style.php'; ?></head><body>
<?php include __DIR__ . '/_entete.php'; ?>
<div class="w">
  <div class="hero"><h1>Bonjour<?= $contact !== '' ? ' ' . View::e($contact) : '' ?></h1>
  <div class="sub">Suivez vos dossiers, consultez vos documents et déposez une nouvelle demande.</div></div>
  <?php if (!empty($flashPortail)): ?><div class="alert <?= $flashPortail['type'] === 'succes' ? 'al-s' : 'al-e' ?>"><?= View::e($flashPortail['message']) ?></div><?php endif; ?>

<?php
$aValider = count(array_filter($cotations, fn($c) => \App\Models\PortailClient::decisionPossible($c)));
$enCours = count(array_filter($dossiers, fn($d) => $d['statut'] === 'actif'));
$nbTermines = count($dossiers) - $enCours;
$aPayer = count(array_filter($factures, fn($f) => $f['statut'] === 'emise'));
?>
  <div class="kp">
    <div class="k <?= $aValider > 0 ? 'w' : '' ?>"><span>À valider</span><b><?= $aValider ?></b></div>
    <div class="k"><span>Dossiers en cours</span><b><?= $enCours ?></b></div>
    <div class="k"><span>Factures à payer</span><b><?= $aPayer ?></b></div>
    <div class="k"><span>Terminés</span><b><?= $nbTermines ?></b></div>
  </div>
  <div class="tabs">
    <?php foreach ($onglets as $code => $lib): ?>
      <a href="<?= $base ?>&onglet=<?= $code ?>" class="<?= $onglet === $code ? 'on' : '' ?>"><?= View::e($lib) ?><?= ($code === 'cotations' && $nbAttente > 0) ? ' · ' . $nbAttente . ' à valider' : '' ?></a>
    <?php endforeach; ?>
  </div>

<?php if (!empty($entites) && in_array($onglet, ['cotations', 'factures'], true)): ?>
  <div class="noprint" id="chips-ent" style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 12px">
    <button type="button" class="btn bp" style="padding:6px 12px" data-ent="0">Toutes</button>
    <?php foreach ($entites as $eid => $enom): ?><button type="button" class="btn bs" style="padding:6px 12px" data-ent="<?= (int) $eid ?>"><?= View::e($enom) ?></button><?php endforeach; ?>
  </div>
  <script>
  (function () {
    var chips = document.querySelectorAll('#chips-ent button');
    chips.forEach(function (b) { b.addEventListener('click', function () {
      var e = b.dataset.ent;
      chips.forEach(function (x) { x.className = 'btn ' + (x === b ? 'bp' : 'bs'); x.style.padding = '6px 12px'; });
      document.querySelectorAll('.row[data-ent]').forEach(function (r) { r.style.display = (e === '0' || r.dataset.ent === e) ? '' : 'none'; });
    }); });
  })();
  </script>
<?php endif; ?>
<?php if ($onglet === 'dossiers'): ?>
  <?php foreach (array_filter($cotations, fn($c) => \App\Models\PortailClient::decisionPossible($c)) as $c): ?>
  <div class="ban">
    <div class="bt"><b><?= View::e($c['reference']) ?></b> · <?= View::e($c['dossier_objet']) ?> · <?= $fm($c['montant_total'], $c['devise']) ?> · <?= !empty($c['validite_devis']) ? 'valable jusqu\'au ' . date('d/m/Y', strtotime($c['validite_devis'])) : 'sans date limite' ?></div>
    <a class="btn bp" href="<?= $base ?>/cotations/<?= (int) $c['id'] ?>">Consulter et répondre</a>
  </div>
  <?php endforeach; ?>
  <?php if (empty($dossiers)): ?><div class="card"><div class="mut">Aucun dossier pour le moment.</div></div><?php else: ?>
  <div class="flt noprint">
    <input type="text" id="rech" placeholder="Rechercher un dossier ou une référence" autocomplete="off">
    <?php if (!empty($entites)): ?><select id="f-ent"><option value="0">Toutes les sociétés</option><?php foreach ($entites as $eid => $enom): ?><option value="<?= (int) $eid ?>"><?= View::e($enom) ?></option><?php endforeach; ?></select><?php endif; ?>
    <select id="f-st"><option value="actif">En cours</option><option value="tous">Tous les statuts</option><option value="termine">Terminés</option></select>
  </div>
  <div class="card" id="liste-dos" style="padding:6px 16px">
  <?php foreach ($dossiers as $d): $idx = array_search($d['etape'], Dossier::ETAPES, true); $idx = $idx === false ? 0 : $idx; $actif = $d['statut'] === 'actif'; ?>
    <div class="row" data-ent="<?= (int) ($d['ent_id'] ?? 0) ?>" data-st="<?= $actif ? 'actif' : 'termine' ?>" data-q="<?= View::e(mb_strtolower($d['reference'] . ' ' . $d['objet'])) ?>"><div style="flex:1;min-width:200px">
      <div class="ref"><?php if (\App\Models\Echange::pret()): ?><a href="<?= $base ?>/dossiers/<?= (int) $d['id'] ?>" style="color:inherit"><?= View::e($d['reference']) ?> — <?= View::e($d['objet']) ?></a><?php $nl = \App\Models\Echange::nonLusExterne('client', (int) $d['id']); if ($nl > 0): ?> <span class="badge rd" style="padding:1px 7px"><?= $nl ?> réponse<?= $nl > 1 ? 's' : '' ?></span><?php endif; ?><?php else: ?><?= View::e($d['reference']) ?> — <?= View::e($d['objet']) ?><?php endif; ?></div>
      <div class="mut"><?= !empty($d['echeance']) && $actif ? 'Échéance prévue ' . date('d/m/Y', strtotime($d['echeance'])) : ($actif ? 'En cours' : 'Terminé') ?><?php if (!empty($entites) && isset($entites[(int) ($d['ent_id'] ?? 0)])): ?> <span class="so"><?= View::e($entites[(int) $d['ent_id']]) ?></span><?php endif; ?></div>
    </div>
    <div class="pg"><i><u style="width:<?= (int) round(($idx + 1) / 6 * 100) ?>%"></u></i><small>Étape <?= $idx + 1 ?> sur 6</small></div>
    <span class="badge big e-<?= View::e($d['etape']) ?>"><?= View::e($etapesClient[$d['etape']] ?? $d['etape']) ?></span></div>
  <?php endforeach; ?>
  <div class="mut" id="vide-dos" style="display:none;padding:14px 0">Aucun dossier ne correspond à cette recherche.</div>
  </div>
  <?php if ($nbTermines > 0): ?><a href="#" id="voir-term" class="noprint" style="font-size:14px;font-weight:600;color:var(--p);text-decoration:none">▾ Voir les <?= $nbTermines ?> dossier<?= $nbTermines > 1 ? 's' : '' ?> terminé<?= $nbTermines > 1 ? 's' : '' ?></a><?php endif; ?>
  <script>
  (function () {
    var rech = document.getElementById('rech'), fe = document.getElementById('f-ent'), fs = document.getElementById('f-st'), v = document.getElementById('vide-dos');
    function f() {
      var q = rech.value.trim().toLowerCase(), e = fe ? fe.value : '0', st = fs.value, n = 0;
      document.querySelectorAll('#liste-dos .row').forEach(function (r) {
        var ok = (!q || r.dataset.q.indexOf(q) > -1) && (e === '0' || r.dataset.ent === e) && (st === 'tous' || r.dataset.st === st);
        r.style.display = ok ? '' : 'none'; if (ok) n++;
      });
      v.style.display = n ? 'none' : '';
    }
    rech.addEventListener('input', f); fs.addEventListener('change', f); if (fe) fe.addEventListener('change', f);
    var t = document.getElementById('voir-term');
    if (t) t.addEventListener('click', function (ev) { ev.preventDefault(); fs.value = 'tous'; f(); t.style.display = 'none'; });
    f();
  })();
  </script>
  <?php endif; ?>

<?php elseif ($onglet === 'cotations'): ?>
  <div class="card"><h2>Mes cotations</h2>
  <?php if (empty($cotations)): ?><div class="mut">Aucune cotation à consulter pour le moment.</div><?php endif; ?>
  <?php $badges = ['envoyee' => ['À valider', 'ye'], 'acceptee' => ['Acceptée', 'gr'], 'refusee' => ['Refusée', 'rd']]; foreach ($cotations as $c): $bg = $badges[$c['statut']]; ?>
    <div class="row" data-ent="<?= (int) ($c['ent_id'] ?? 0) ?>"><div><div class="ref"><?= View::e($c['reference']) ?> — <?= View::e($c['dossier_objet']) ?></div>
      <?php if (!empty($entites) && isset($entites[(int) ($c['ent_id'] ?? 0)])): ?><div class="mut">Facturé à <?= View::e($entites[(int) $c['ent_id']]) ?></div><?php endif; ?>
      <div class="mut"><?= $fm($c['montant_total'], $c['devise']) ?><?= !empty($c['validite_devis']) ? ' · valable jusqu\'au ' . date('d/m/Y', strtotime($c['validite_devis'])) : '' ?></div></div>
      <div><span class="badge <?= $bg[1] ?>"><?= $bg[0] ?></span> &nbsp;<a class="btn bs" style="padding:6px 12px" href="<?= $base ?>/cotations/<?= (int) $c['id'] ?>">Voir</a></div></div>
  <?php endforeach; ?></div>

<?php elseif ($onglet === 'factures'): ?>
  <div class="card"><h2>Mes factures</h2>
  <?php if (empty($factures)): ?><div class="mut">Aucune facture pour le moment.</div><?php endif; ?>
  <?php foreach ($factures as $f): ?>
    <div class="row" data-ent="<?= (int) ($f['ent_id'] ?? 0) ?>"><div><div class="ref"><?= View::e($f['reference']) ?></div>
      <?php if (!empty($entites) && isset($entites[(int) ($f['ent_id'] ?? 0)])): ?><div class="mut">Facturée à <?= View::e($entites[(int) $f['ent_id']]) ?></div><?php endif; ?>
      <div class="mut"><?= !empty($f['date_emission']) ? 'Émise le ' . date('d/m/Y', strtotime($f['date_emission'])) . ' · ' : '' ?><?= View::e($f['dossier_reference']) ?><?= ($f['statut'] === 'emise' && !empty($f['date_echeance'])) ? ' · à régler avant le ' . date('d/m/Y', strtotime($f['date_echeance'])) : '' ?></div></div>
      <div><b><?= $fm($f['montant'], $f['devise']) ?></b> &nbsp;<span class="badge <?= $f['statut'] === 'payee' ? 'gr' : 'ye' ?>"><?= $f['statut'] === 'payee' ? 'Payée' : 'À régler' ?></span> &nbsp;<a class="btn bs" style="padding:6px 12px" href="<?= $base ?>/factures/<?= (int) $f['id'] ?>">Voir</a><?php if (!empty($f['fichier'])): ?> <a class="btn bp" style="padding:6px 12px" href="<?= $base ?>/factures/<?= (int) $f['id'] ?>/fichier">PDF</a><?php endif; ?></div></div>
  <?php endforeach; ?></div>

<?php else: ?>
  <div class="card"><h2>Déposer une nouvelle demande</h2>
  <div class="mut">Décrivez votre besoin : votre interlocuteur le reçoit directement et vous répond.</div>
  <?php if ((int) $client['is_active'] !== 1): ?>
    <div class="alert al-e">Votre compte client est actuellement inactif. Merci de contacter directement votre interlocuteur.</div>
  <?php else: ?>
  <form method="post" action="<?= $base ?>/demande" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="hp" aria-hidden="true"><label>Site web</label><input type="text" name="site_web" tabindex="-1" autocomplete="off"></div>
    <label for="objet">Objet de la demande *</label>
    <input type="text" id="objet" name="objet" maxlength="200" value="<?= $valeur('objet') ?>" placeholder="Ex. : 20 tonnes de farine de blé, livraison Douala">
    <?php if (!empty($erreurs['objet'])): ?><div class="err"><?= View::e($erreurs['objet']) ?></div><?php endif; ?>
    <label for="activite">Type de besoin</label>
    <select id="activite" name="activite"><option value="">— Je ne sais pas —</option>
      <?php foreach (\App\Models\Activite::liste((int) (\App\Models\Filiale::find((int) $client['filiale_id'])['organisation_id'] ?? 0)) as $a): ?><option <?= ($old['activite'] ?? '') === $a ? 'selected' : '' ?>><?= View::e($a) ?></option><?php endforeach; ?></select>
    <label for="message">Votre besoin *</label>
    <textarea id="message" name="message" rows="6" maxlength="5000" placeholder="Produits, quantités, destination, contraintes…"><?= $valeur('message') ?></textarea>
    <?php if (!empty($erreurs['message'])): ?><div class="err"><?= View::e($erreurs['message']) ?></div><?php endif; ?>
    <label for="date_souhaitee">Date souhaitée (facultatif)</label>
    <input type="date" id="date_souhaitee" name="date_souhaitee" value="<?= $valeur('date_souhaitee') ?>">
    <label for="fichiers">Joindre des fichiers (facultatif)</label>
    <input type="file" id="fichiers" name="fichiers[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt">
    <div class="mut">Cahier des charges, liste de produits, photos… PDF, images, Word, Excel ou texte · 5 fichiers maximum, 10 Mo chacun.</div>
    <div class="act"><button type="submit" class="btn bp">Envoyer ma demande</button></div>
  </form>
  <?php endif; ?></div>
<?php endif; ?>
  <div class="priv">Ce lien est personnel : ne le partagez pas. Vous ne voyez ici que vos propres informations.</div>
</div></body></html>
