<?php
use App\Core\View;
use App\Models\DepartsPublics;
use App\Models\Expedition;
/** Réglages de la page publique des départs. Variables : $disponible, $filiales, $filialeId, $reg, $url, $expeditions, $peutEcrire. */
?>
<div class="page-header"><h1>Page publique des départs</h1><a class="btn btn-secondary" href="/index.php?r=expeditions">← Expéditions</a></div>
<?php if (!$disponible): ?>
  <div class="card"><div class="empty-state">Pas encore activée : lancez migrate_v66.php une fois.</div></div>
<?php else: ?>
<div class="card">
  <p style="margin-top:0">Un lien (et un QR code) à partager à la place du tableau de dates du flyer : vos clients choisissent leur trajet et leur type de produit, voient les départs, les places et le tarif, puis vous écrivent sur WhatsApp. La page est en lecture seule : aucun client, aucune réservation ni aucun coût d'achat n'y apparaît.</p>
  <form method="post" action="/index.php?r=expeditions/page-publique">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="filiale_id" value="<?= (int) $filialeId ?>">
    <?php if (count($filiales) > 1): ?>
    <div class="form-group"><label>Entreprise</label><select onchange="location='/index.php?r=expeditions/page-publique&filiale_id='+this.value"><?php foreach ($filiales as $f): ?><option value="<?= (int) $f['id'] ?>" <?= (int) $f['id'] === (int) $filialeId ? 'selected' : '' ?>><?= View::e($f['nom']) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="form-group"><label><input type="checkbox" name="actif" value="1" <?= $reg['actif'] ? 'checked' : '' ?> <?= $peutEcrire ? '' : 'disabled' ?>> Activer la page publique</label></div>
    <div class="form-group"><label>Numéro WhatsApp de réservation</label><input type="text" name="whatsapp" inputmode="tel" value="<?= View::e($reg['whatsapp']) ?>" placeholder="Ex. +33612345678" style="max-width:280px" <?= $peutEcrire ? '' : 'disabled' ?>>
      <div class="hint">Avec l'indicatif du pays. Le bouton « Réserver » ouvre une conversation sur ce numéro, avec le trajet, la date et le type de produit déjà écrits.</div></div>
    <div class="form-group"><label>Produits interdits et conditions (affichés en bas de la page)</label><textarea name="interdits" rows="4" placeholder="Ex. Produits interdits : … Produits acceptés seulement par voyageur : …" <?= $peutEcrire ? '' : 'disabled' ?>><?= View::e($reg['interdits']) ?></textarea>
      <div class="hint">Évite les questions « est-ce que je peux envoyer ça ? ». Laissez vide pour ne rien afficher.</div></div>
    <?php if ($peutEcrire): ?><button class="btn btn-primary" type="submit">Enregistrer</button><?php endif; ?>
  </form>
</div>
<?php if ($url !== ''): ?>
<div class="card">
  <h2 style="margin-top:0;font-size:16px">Votre lien à partager</h2>
  <?php if (!$reg['actif']): ?><div class="hint" style="color:#b45309;margin-bottom:8px">La page est désactivée : le lien affiche « page non disponible » tant que vous ne l'activez pas.</div><?php endif; ?>
  <input type="text" id="lien" readonly value="<?= View::e($url) ?>" onclick="this.select()" style="width:100%;font-weight:600">
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0">
    <button class="btn btn-primary" type="button" id="copier">Copier le lien</button>
    <a class="btn btn-secondary" href="<?= View::e($url) ?>" target="_blank" rel="noopener">Voir la page</a>
    <a class="btn btn-secondary" id="dl" download="qr-departs.png" href="#">Télécharger le QR code</a>
  </div>
  <canvas id="qr" width="260" height="260" style="border:1px solid #dde4ec;border-radius:8px;max-width:100%"></canvas>
  <div class="hint">Sur votre flyer, remplacez le tableau des dates par ce QR code : il reste valable, la page se met à jour toute seule.</div>
  <?php if ($peutEcrire): ?>
  <form method="post" action="/index.php?r=expeditions/page-publique" style="margin-top:12px" onsubmit="return confirm('Créer un nouveau lien ? L\'ancien lien et l\'ancien QR code ne fonctionneront plus.')">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="filiale_id" value="<?= (int) $filialeId ?>">
    <input type="hidden" name="nouveau_lien" value="1"><input type="hidden" name="whatsapp" value="<?= View::e($reg['whatsapp']) ?>"><?php if ($reg['actif']): ?><input type="hidden" name="actif" value="1"><?php endif; ?>
    <button class="btn btn-secondary" type="submit" style="color:#b3261e;border-color:#e3b5b1">Créer un nouveau lien</button>
    <span class="hint">À faire seulement si le lien a été diffusé par erreur.</span></form>
  <?php endif; ?>
</div>
<script src="/assets/js/qr.js"></script>
<script>
(function () {
  var url = <?= json_encode($url) ?>, cv = document.getElementById('qr');
  try { SuivoraQR.dessiner(cv, url, 520); cv.style.width = '260px'; document.getElementById('dl').href = cv.toDataURL('image/png'); } catch (e) {}
  document.getElementById('copier').addEventListener('click', function () {
    var b = this, i = document.getElementById('lien'); i.select();
    (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).catch(function () { document.execCommand('copy'); }).then(function () { b.textContent = 'Lien copié'; });
  });
})();
</script>
<?php endif; ?>
<?php if (!$v70): ?>
<div class="card"><div class="empty-state">Apparence, informations importantes et lieux de dépôt : lancez migrate_v70.php une fois.</div></div>
<?php else: $w = $peutEcrire ? '' : 'disabled'; ?>
<div class="card" id="apparence">
  <h2 style="margin-top:0;font-size:16px">Apparence de la page</h2>
  <form method="post" action="/index.php?r=expeditions/page-publique/apparence" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="filiale_id" value="<?= (int) $filialeId ?>">
    <div class="form-group"><label>Titre</label><input type="text" name="titre" maxlength="160" value="<?= View::e($aspect['titre']) ?>" placeholder="<?= View::e($raisonSociale) ?>" <?= $w ?>></div>
    <div class="form-group"><label>Phrase sous le titre</label><input type="text" name="soustitre" maxlength="255" value="<?= View::e($aspect['soustitre']) ?>" placeholder="Choisissez votre trajet et votre type de produit, puis réservez sur WhatsApp." <?= $w ?>></div>
    <div class="form-group"><label>Ambiance</label><select name="ambiance" <?= $w ?>><?php foreach (DepartsPublics::AMBIANCES as $k => $a): ?><option value="<?= $k ?>" <?= $aspect['ambiance'] === $k ? 'selected' : '' ?>><?= View::e($a[5]) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Photo du bandeau (facultatif)</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" <?= $w ?>>
      <?php if ($aspect['photo'] !== ''): ?><div class="hint"><label style="display:inline"><input type="checkbox" name="photo_retirer" value="1"> Retirer la photo actuelle</label></div><?php endif; ?></div>
    <div class="form-group"><label>Téléphone affiché</label><input type="text" name="tel" inputmode="tel" value="<?= View::e($aspect['tel']) ?>" style="max-width:280px" <?= $w ?>></div>
    <div class="form-group"><label>E-mail affiché</label><input type="email" name="email" value="<?= View::e($aspect['email']) ?>" style="max-width:340px" <?= $w ?>>
      <div class="hint">Avec le WhatsApp, ils forment le bloc « Nous contacter ». Laissez vide ce que vous ne voulez pas montrer.</div></div>
    <div class="form-group"><label><input type="checkbox" name="etapes" value="1" <?= $aspect['etapes'] ? 'checked' : '' ?> <?= $w ?>> Afficher « Comment ça marche » (3 étapes)</label></div>
    <div class="form-group"><label><input type="checkbox" name="conv" value="1" <?= $aspect['conv'] ? 'checked' : '' ?> <?= $w ?>> Afficher la conversion EUR ↔ FCFA à côté du tarif</label></div>
    <div class="form-group"><label><input type="checkbox" name="apercu" value="1" <?= $aspect['apercu'] ? 'checked' : '' ?> <?= $w ?>> Montrer les prochaines dates dans l'aperçu du lien (WhatsApp, Facebook)</label></div>
    <?php if ($peutEcrire): ?><button class="btn btn-primary" type="submit" name="action" value="aspect">Enregistrer l'apparence</button><?php endif; ?>
  </form>
</div>
<div class="card" id="infos">
  <h2 style="margin-top:0;font-size:16px">Informations importantes</h2>
  <p class="hint" style="margin-top:0">Un message libre, affiché en haut de la page (jour férié, changement de lieu, date limite…). Il disparaît tout seul après la date de fin.</p>
  <?php foreach ($infos as $i): ?>
  <form method="post" action="/index.php?r=expeditions/page-publique/apparence" style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;border:1px solid #dde4ec;border-radius:10px;padding:10px;margin-bottom:8px">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="filiale_id" value="<?= (int) $filialeId ?>"><input type="hidden" name="id" value="<?= (int) $i['id'] ?>">
    <div style="flex:1;min-width:200px"><?= nl2br(View::e($i['texte'])) ?><div class="hint"><?= $i['niveau'] === 'alerte' ? 'À ne pas rater' : 'Information' ?><?= $i['fin'] ? ' · jusqu\'au ' . View::e(date('d/m/Y', strtotime($i['fin']))) : ' · sans date de fin' ?></div></div>
    <?php if ($peutEcrire): ?><button class="btn btn-secondary" type="submit" name="action" value="info_supprimer" style="color:#b3261e">Retirer</button><?php endif; ?>
  </form>
  <?php endforeach; ?>
  <?php if ($peutEcrire): ?>
  <form method="post" action="/index.php?r=expeditions/page-publique/apparence">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="filiale_id" value="<?= (int) $filialeId ?>">
    <div class="form-group"><label>Nouveau message</label><textarea name="texte" rows="2" maxlength="600" placeholder="Ex. Pas de départ le lundi 11 novembre (jour férié)."></textarea></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap"><div class="form-group"><label>Type</label><select name="niveau"><option value="info">Information</option><option value="alerte">À ne pas rater</option></select></div>
    <div class="form-group"><label>Affiché jusqu'au (facultatif)</label><input type="date" name="fin"></div></div>
    <button class="btn btn-primary" type="submit" name="action" value="info_ajouter">Ajouter sur la page</button>
  </form><?php endif; ?>
</div>
<div class="card" id="lieux">
  <h2 style="margin-top:0;font-size:16px">Lieux de dépôt</h2>
  <p class="hint" style="margin-top:0">Affichés dans un bloc « Où déposer votre colis ». Écrivez vos vraies adresses et horaires.</p>
  <?php foreach ($lieux as $l): ?>
  <form method="post" action="/index.php?r=expeditions/page-publique/apparence" style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;border:1px solid #dde4ec;border-radius:10px;padding:10px;margin-bottom:8px">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="filiale_id" value="<?= (int) $filialeId ?>"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
    <div style="flex:1;min-width:200px"><strong><?= View::e($l['nom']) ?></strong><br><?= nl2br(View::e((string) $l['adresse'])) ?><?php if (!empty($l['horaires'])): ?><div class="hint"><?= View::e($l['horaires']) ?></div><?php endif; ?></div>
    <?php if ($peutEcrire): ?><button class="btn btn-secondary" type="submit" name="action" value="lieu_supprimer" style="color:#b3261e">Retirer</button><?php endif; ?>
  </form>
  <?php endforeach; ?>
  <?php if ($peutEcrire): ?>
  <form method="post" action="/index.php?r=expeditions/page-publique/apparence">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="filiale_id" value="<?= (int) $filialeId ?>">
    <div class="form-group"><label>Nom du lieu</label><input type="text" name="nom" maxlength="120"></div>
    <div class="form-group"><label>Adresse</label><textarea name="adresse" rows="2"></textarea></div>
    <div class="form-group"><label>Jours et horaires</label><textarea name="horaires" rows="2" placeholder="Ex. Lundi au vendredi, 18h–20h"></textarea></div>
    <div class="form-group"><label>Lien de la carte (facultatif)</label><input type="url" name="carte_url" placeholder="https://maps.google.com/…"></div>
    <button class="btn btn-primary" type="submit" name="action" value="lieu_ajouter">Ajouter ce lieu</button>
  </form><?php endif; ?>
</div>
<?php endif; ?>
<div class="card" id="hebdo">
  <h2 style="margin-top:0;font-size:16px">Départs fixes chaque semaine (dépôt du colis)</h2>
  <p class="hint" style="margin-top:0">Par exemple : tous les lundis, alimentaire, Cameroun → France ; tous les mercredis, non alimentaire, par voyageur, dans les deux sens. La page calcule toute seule la prochaine date. Tous les tarifs sont au kilo. Plusieurs lieux de dépôt ? Écrivez-les l'un sous l'autre.</p>
  <?php if (!\App\Models\DepartsPublics::hebdoDisponible()): ?>
    <div class="empty-state">Pas encore activé : lancez migrate_v67.php une fois.</div>
  <?php else: ?>
  <?php foreach ($hebdo as $h): $prochaine = \App\Models\DepartsPublics::prochaineDate($h); ?>
  <form method="post" action="/index.php?r=expeditions/page-publique/hebdo" class="card" style="background:#f7fafc;margin-bottom:10px">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="filiale_id" value="<?= (int) $filialeId ?>"><input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
    <div style="margin-bottom:6px"><strong><?= View::e(ucfirst(\App\Models\DepartsPublics::JOURS[(int) $h['jour_semaine']] ?? '')) ?> · <?= View::e($h['origine'] . ' → ' . $h['destination']) ?> · <?= View::e(\App\Models\DepartsPublics::PRODUITS[$h['produit']] ?? '') ?></strong>
      <span class="hint"> · <?= !(int) $h['actif'] ? 'désactivé' : ($prochaine ? 'prochain départ : ' . View::e(\App\Models\DepartsPublics::jourDate($prochaine)) : 'aucune date') ?><?= !empty($h['suspendu_jusqua']) ? ' · suspendu' : '' ?></span></div>
    <?php include __DIR__ . '/_hebdo_champs.php'; ?>
    <?php if ($peutEcrire): ?><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
      <button class="btn btn-primary" type="submit" name="action" value="enregistrer">Enregistrer</button>
      <?php if (!empty($h['suspendu_jusqua'])): ?><button class="btn btn-secondary" type="submit" name="action" value="reprendre">Réactiver</button>
      <?php else: ?><button class="btn btn-secondary" type="submit" name="action" value="suspendre" title="Jour férié, pause : le prochain départ disparaît de la page">Suspendre le prochain départ</button><?php endif; ?>
      <button class="btn btn-secondary" type="submit" name="action" value="supprimer" style="color:#b3261e;border-color:#e3b5b1" onclick="return confirm('Supprimer ce départ hebdomadaire ?')">Supprimer</button></div><?php endif; ?>
  </form>
  <?php endforeach; ?>
  <?php if ($peutEcrire): $h = ['id' => 0, 'jour_semaine' => 1, 'origine' => '', 'destination' => '', 'transport' => 'fret', 'produit' => 'alimentaire', 'depot_jours_avant' => 0, 'heure_limite' => '', 'tarif_kg' => '', 'devise' => 'EUR', 'lieu_depot' => '', 'actif' => 1]; ?>
  <details><summary class="btn btn-secondary" style="display:inline-block;cursor:pointer">Ajouter un départ chaque semaine</summary>
  <form method="post" action="/index.php?r=expeditions/page-publique/hebdo" class="card" style="background:#f7fafc;margin-top:10px">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>"><input type="hidden" name="filiale_id" value="<?= (int) $filialeId ?>"><input type="hidden" name="id" value="0">
    <?php include __DIR__ . '/_hebdo_champs.php'; ?>
    <button class="btn btn-primary" type="submit" name="action" value="enregistrer" style="margin-top:8px">Ajouter</button>
  </form></details>
  <?php endif; ?>
  <?php endif; ?>
</div>
<div class="card">
  <h2 style="margin-top:0;font-size:16px">Départs ouverts (à capacité ou selon les réservations)</h2>
  <?php if (!$expeditions): ?><div class="empty-state">Aucune expédition ouverte.</div><?php else: ?>
  <table class="table responsive-cards"><thead><tr><th>Expédition</th><th>Trajet</th><th>Départ</th><th>Sur la page</th></tr></thead><tbody>
  <?php foreach ($expeditions as $e): ?><tr>
    <td data-label="Expédition"><a href="/index.php?r=expeditions/<?= (int) $e['id'] ?>/modifier"><strong><?= View::e($e['reference']) ?></strong></a></td>
    <td data-label="Trajet"><?= View::e($e['origine'] . ' → ' . $e['destination']) ?></td>
    <td data-label="Départ"><?= $e['date_depart'] ? View::e(date('d/m/Y', strtotime($e['date_depart']))) : 'À confirmer' ?></td>
    <td data-label="Sur la page"><?= !empty($e['public_affiche']) ? 'Oui' : 'Non' ?> · <a href="/index.php?r=expeditions/<?= (int) $e['id'] ?>/modifier#page-publique">régler</a></td></tr><?php endforeach; ?>
  </tbody></table>
  <div class="hint" style="margin-top:8px">Un départ s'affiche seulement s'il est coché « Afficher sur la page publique » dans l'expédition. Il disparaît à la clôture des réservations.</div>
  <?php endif; ?>
</div>
<?php endif; ?>
