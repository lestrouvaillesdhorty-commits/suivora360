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
