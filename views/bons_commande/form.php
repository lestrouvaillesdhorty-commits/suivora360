<?php
use App\Core\View;

/**
 * Bon de commande fournisseur — création / modification (brouillon).
 * Variables : $fournisseur, $bon (null en création), $old, $erreurs, $offre, $devises, $incoterms, $dossiers, $contacts.
 */
$estModif = $bon !== null;
$actionUrl = $estModif ? '/index.php?r=bons-commande/' . (int) $bon['id'] . '/modifier' : '/index.php?r=fournisseurs/' . (int) $fournisseur['id'] . '/bons-commande';
$v = fn(string $k) => (string) ($old[$k] ?? '');
$err = fn(string $k) => $erreurs[$k] ?? null;
$lignes = array_values((array) ($old['lignes'] ?? []));
if (empty($lignes)) { $lignes = [['designation' => '', 'quantite' => '', 'unite' => '', 'prix_unitaire' => '']]; }
$retourUrl = $estModif ? '/index.php?r=bons-commande/' . (int) $bon['id'] : '/index.php?r=fournisseurs/' . (int) $fournisseur['id'] . '&onglet=commandes';
$messageErreur = fn(?string $m) => $m ? '<div style="color:#991b1b;font-size:12px;margin-top:3px">' . View::e($m) . '</div>' : '';
?>
<a href="<?= View::e($retourUrl) ?>" style="font-size:13px;color:#666">&larr; Retour</a>
<h1 style="margin-top:8px"><?= $estModif ? 'Modifier le bon ' . View::e($bon['reference']) : 'Nouveau bon de commande fournisseur' ?></h1>
<div class="subtitle">Fournisseur : <strong><?= View::e($fournisseur['nom']) ?></strong>
  <?php if (!$estModif && !empty($offre)): ?> — repris de l’offre retenue <?= View::e($offre['reference']) ?> (v<?= (int) $offre['version'] ?>)<?php endif; ?></div>

<form method="post" action="<?= $actionUrl ?>">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
  <?php if (!$estModif): ?>
    <input type="hidden" name="offre_id" value="<?= (int) ($old['offre_id'] ?? 0) ?>">
    <input type="hidden" name="reference_offre" value="<?= View::e($v('reference_offre')) ?>">
  <?php endif; ?>
  <?= $messageErreur($err('offre_id')) ?>

  <div class="card">
    <h2>Informations du bon</h2>
    <div class="form-row">
      <div class="form-group"><label for="date_emission">Date du bon *</label>
        <input type="date" id="date_emission" name="date_emission" value="<?= View::e($v('date_emission')) ?>" required><?= $messageErreur($err('date_emission')) ?></div>
      <div class="form-group"><label for="date_livraison_souhaitee">Livraison souhaitée</label>
        <input type="date" id="date_livraison_souhaitee" name="date_livraison_souhaitee" value="<?= View::e($v('date_livraison_souhaitee')) ?>"><?= $messageErreur($err('date_livraison_souhaitee')) ?></div>
      <div class="form-group"><label for="devise">Devise (une seule par bon) *</label>
        <select id="devise" name="devise" required><option value="">— Choisir —</option>
          <?php $listeDevises = $devises; if ($v('devise') !== '' && !in_array($v('devise'), $listeDevises, true)) { $listeDevises[] = $v('devise'); } foreach ($listeDevises as $d): ?><option <?= $v('devise') === $d ? 'selected' : '' ?>><?= View::e($d) ?></option><?php endforeach; ?></select><?= $messageErreur($err('devise')) ?></div>
    </div>
    <?php if (!$estModif): ?>
    <div class="form-group"><label for="dossier_id">Dossier lié (facultatif)</label>
      <select id="dossier_id" name="dossier_id" <?= !empty($old['offre_id']) ? 'style="pointer-events:none;background:#f3f4f6"' : '' ?>>
        <option value="">— Aucun dossier —</option>
        <?php foreach ($dossiers as $d): ?><option value="<?= (int) $d['id'] ?>" <?= (int) ($old['dossier_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= View::e($d['reference']) ?> — <?= View::e(mb_strimwidth((string) $d['objet'], 0, 50, '…')) ?></option><?php endforeach; ?>
      </select><?= $messageErreur($err('dossier_id')) ?>
      <div style="font-size:12px;color:#888;margin-top:3px">Un bon lié à un dossier ne peut être marqué « envoyé » que si le client a accepté la cotation.</div></div>
    <?php else: ?>
      <div class="info-row"><span class="label">Dossier</span><span><?= !empty($bon['dossier_reference']) ? View::e($bon['dossier_reference']) : '—' ?></span></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Lignes commandées</h2>
    <?= $messageErreur($err('lignes')) ?>
    <table class="responsive-cards" id="tableLignes">
      <thead><tr><th>Désignation</th><th style="width:100px">Quantité</th><th style="width:90px">Unité</th><th style="width:130px">Prix unitaire</th><th style="width:120px" class="num">Montant</th><th style="width:40px"></th></tr></thead>
      <tbody id="lignesCorps">
      <?php foreach ($lignes as $i => $l): ?>
        <tr class="ligne">
          <td data-label="Désignation"><input type="text" name="lignes[<?= $i ?>][designation]" value="<?= View::e((string) ($l['designation'] ?? '')) ?>" maxlength="255"></td>
          <td data-label="Quantité"><input type="text" inputmode="decimal" name="lignes[<?= $i ?>][quantite]" value="<?= View::e((string) ($l['quantite'] ?? '')) ?>" class="q"></td>
          <td data-label="Unité"><input type="text" name="lignes[<?= $i ?>][unite]" value="<?= View::e((string) ($l['unite'] ?? '')) ?>" maxlength="20" placeholder="kg, pièce…"></td>
          <td data-label="Prix unitaire"><input type="text" inputmode="decimal" name="lignes[<?= $i ?>][prix_unitaire]" value="<?= View::e((string) ($l['prix_unitaire'] ?? '')) ?>" class="pu"></td>
          <td data-label="Montant" class="num montant" style="white-space:nowrap">—</td>
          <td><button type="button" class="btn btn-sm btn-secondary suppr" title="Retirer la ligne" aria-label="Retirer la ligne">×</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div style="margin-top:10px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <button type="button" class="btn btn-sm btn-secondary" id="ajouterLigne">+ Ajouter une ligne</button>
      <div style="font-weight:700">Total : <span id="totalBon">—</span> <span id="deviseTotal"></span></div>
    </div>
    <div style="font-size:12px;color:#888;margin-top:6px">Quantités et unités sont conservées ligne par ligne. Le total additionne uniquement des montants dans la devise du bon ; les lignes sans prix ne sont pas comptées. Le total définitif est recalculé à l’enregistrement.</div>
  </div>

  <div class="card">
    <h2>Conditions de la commande</h2>
    <div class="form-row">
      <div class="form-group"><label for="incoterm">Incoterm</label>
        <select id="incoterm" name="incoterm"><option value="">—</option>
          <?php foreach ($incoterms as $code => $lib): ?><option value="<?= $code ?>" <?= $v('incoterm') === $code ? 'selected' : '' ?>><?= View::e($lib) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label for="conditions_paiement">Conditions de paiement</label>
        <input type="text" id="conditions_paiement" name="conditions_paiement" maxlength="255" value="<?= View::e($v('conditions_paiement')) ?>"></div>
    </div>
    <div class="form-group"><label for="lieu_livraison">Lieu de livraison / d’enlèvement</label>
      <input type="text" id="lieu_livraison" name="lieu_livraison" maxlength="255" value="<?= View::e($v('lieu_livraison')) ?>"></div>
    <div class="form-group"><label for="notes">Instructions pour le fournisseur (visibles sur le bon)</label>
      <textarea id="notes" name="notes" rows="3"><?= View::e($v('notes')) ?></textarea></div>
  </div>

  <div class="card">
    <h2>Destinataire</h2>
    <div class="form-row">
      <div class="form-group"><label for="destinataire_nom">Nom du contact</label>
        <input type="text" id="destinataire_nom" name="destinataire_nom" maxlength="200" value="<?= View::e($v('destinataire_nom')) ?>"></div>
      <div class="form-group"><label for="destinataire_email">E-mail</label>
        <input type="email" id="destinataire_email" name="destinataire_email" value="<?= View::e($v('destinataire_email')) ?>"><?= $messageErreur($err('destinataire_email')) ?></div>
    </div>
    <?php if (!empty($contacts)): ?>
      <div style="font-size:12px;color:#888">Autres interlocuteurs :
        <?php foreach ($contacts as $c): if ((int) $c['actif'] !== 1) { continue; } ?>
          <a href="#" class="choisirContact" data-nom="<?= View::e(trim($c['prenom'] . ' ' . $c['nom'])) ?>" data-email="<?= View::e($c['email']) ?>"><?= View::e(trim($c['prenom'] . ' ' . $c['nom'])) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="form-group" style="margin-top:12px"><label for="notes_internes">Notes internes (jamais imprimées)</label>
      <textarea id="notes_internes" name="notes_internes" rows="2"><?= View::e($v('notes_internes')) ?></textarea></div>
  </div>

  <button type="submit" class="btn"><?= $estModif ? 'Enregistrer les modifications' : 'Créer le bon (brouillon)' ?></button>
  <a href="<?= View::e($retourUrl) ?>" class="btn btn-secondary">Annuler</a>
</form>

<script>
(function () {
  var corps = document.getElementById('lignesCorps');
  var index = corps.querySelectorAll('tr.ligne').length;
  function num(s) { s = (s || '').replace(/\s/g, '').replace(',', '.'); var n = parseFloat(s); return isNaN(n) ? null : n; }
  function fmt(n) { return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function recalcul() {
    var total = 0, any = false;
    corps.querySelectorAll('tr.ligne').forEach(function (tr) {
      var q = num(tr.querySelector('.q').value), p = num(tr.querySelector('.pu').value), cell = tr.querySelector('.montant');
      if (q !== null && p !== null) { var m = Math.round(q * p * 100) / 100; total += m; any = true; cell.textContent = fmt(m); } else { cell.textContent = '—'; }
    });
    document.getElementById('totalBon').textContent = any ? fmt(total) : '—';
    document.getElementById('deviseTotal').textContent = document.getElementById('devise').value;
  }
  corps.addEventListener('input', recalcul);
  document.getElementById('devise').addEventListener('change', recalcul);
  corps.addEventListener('click', function (e) {
    if (e.target.classList.contains('suppr')) {
      var tr = e.target.closest('tr');
      if (corps.querySelectorAll('tr.ligne').length > 1) { tr.remove(); } else { tr.querySelectorAll('input').forEach(function (i) { i.value = ''; }); }
      recalcul();
    }
  });
  document.getElementById('ajouterLigne').addEventListener('click', function () {
    var modele = corps.querySelector('tr.ligne').cloneNode(true);
    modele.querySelectorAll('input').forEach(function (i) { i.value = ''; i.name = i.name.replace(/lignes\[\d+\]/, 'lignes[' + index + ']'); });
    index++; corps.appendChild(modele); recalcul(); modele.querySelector('input').focus();
  });
  document.querySelectorAll('.choisirContact').forEach(function (a) {
    a.addEventListener('click', function (e) { e.preventDefault(); document.getElementById('destinataire_nom').value = a.dataset.nom; document.getElementById('destinataire_email').value = a.dataset.email; });
  });
  recalcul();
})();
</script>
