<?php
use App\Core\View;

// [réécrit 06/10, report fidèle de la maquette Dossiers Besoin.dc.html,
// demandé explicitement par Marie Laure après avoir constaté que la
// première version de cet onglet (déplacement brut de l'ancien contenu
// pré-maquette) ne suivait pas le visuel validé.
//
// "Lieux concernés" réutilise demandes.lieu_livraison + destination_pays
// (déjà utilisés ailleurs sur Synthèse/anciennement Dossier) plutôt qu'une
// nouvelle colonne. "Contraintes" réutilise demandes.qualification_notes
// (notes composées à la qualification, même esprit minimal déjà en place
// sur ce champ) — à confirmer si Marie Laure veut un champ dédié distinct.
?>
<div class="card">
  <h2>Message d'origine du client</h2>
  <?php if (!$demande): ?>
    <div class="empty-state">Aucune demande d'origine liée à ce dossier.</div>
  <?php else: ?>
    <div class="quote-block">« <?= nl2br(View::e($demande['message'])) ?> »</div>
    <div class="hint">Message reçu via la demande <?= View::e($demande['reference']) ?><?= !empty($demande['recue_le']) ? ' du ' . date('d/m/Y', strtotime($demande['recue_le'])) : '' ?> — conservé tel quel, non modifiable.</div>
  <?php endif; ?>
</div>

<?php if ($demande): ?>
<?php
  $lieuxConcernes = trim(($demande['lieu_livraison'] ?? '') . ((($demande['lieu_livraison'] ?? '') !== '' && ($demande['destination_pays'] ?? '') !== '') ? ', ' : '') . ($demande['destination_pays'] ?? ''));
?>
<div class="card">
  <h2>Besoin structuré</h2>
  <div class="form-row">
    <div class="form-group"><label>Lieux concernés</label><input type="text" value="<?= View::e($lieuxConcernes !== '' ? $lieuxConcernes : 'À préciser') ?>" disabled></div>
    <div class="form-group"><label>Date souhaitée par le client</label><input type="text" value="<?= !empty($demande['date_souhaitee_client']) ? date('d/m/Y', strtotime($demande['date_souhaitee_client'])) : 'Non renseignée' ?>" disabled></div>
    <div class="form-group"><label>Échéance interne</label><input type="text" value="<?= $dossier['echeance'] ? date('d/m/Y', strtotime($dossier['echeance'])) : 'Non renseignée' ?>" disabled></div>
  </div>
  <div class="form-group"><label>Contraintes</label><textarea rows="2" disabled><?= View::e($demande['qualification_notes'] ?? '') ?: 'Aucune contrainte particulière signalée à la qualification.' ?></textarea></div>
  <div class="hint">Champs issus de la demande d'origine — modifiables uniquement depuis sa fiche.</div>
</div>
<?php endif; ?>

<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:20px 22px 0"><h2 style="margin:0">Articles</h2></div>
  <div style="padding:16px 22px">
  <?php if (empty($articles)): ?>
    <div class="empty-state">Aucun article pour le moment.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Désignation</th><th>Référence</th><th>Marque</th><th>Qté</th><th>Unité</th><th>Conditionnement</th><th>Caractéristiques</th></tr></thead>
      <tbody>
      <?php foreach ($articles as $a): ?>
        <tr>
          <td><?= View::e($a['designation']) ?></td>
          <td><?= !empty($a['reference']) ? View::e($a['reference']) : '<span class="a-preciser">À préciser</span>' ?></td>
          <td><?= !empty($a['marque']) ? View::e($a['marque']) : '<span class="a-preciser">À préciser</span>' ?></td>
          <td><?= View::e((string) $a['quantite']) ?></td>
          <td><?= View::e($a['unite']) ?></td>
          <td><?= View::e($a['conditionnement'] ?? '') ?: '—' ?></td>
          <td><?= View::e($a['caracteristiques'] ?? '') ?: '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  </div>

  <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/articles" style="padding:16px 22px 20px;border-top:1px solid var(--border)">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-row">
      <div class="form-group"><label>Désignation</label><input type="text" name="designation" required></div>
      <div class="form-group"><label>Quantité</label><input type="number" step="0.01" name="quantite"></div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Unité</label>
        <select name="unite">
          <option value="">—</option>
          <?php foreach (['Pièce', 'Carton', 'Kg', 'Tonne', 'Litre', 'm³', 'Sac', 'Palette', "Conteneur 20'", "Conteneur 40'"] as $u): ?>
            <option><?= $u ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Conditionnement</label><input type="text" name="conditionnement" placeholder="ex. sacs de 50 kg"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Référence</label><input type="text" name="reference"></div>
      <div class="form-group"><label>Marque</label><input type="text" name="marque"></div>
    </div>
    <div class="form-group"><label>Caractéristiques</label><input type="text" name="caracteristiques" placeholder="précisions techniques, compatibilité..."></div>
    <button type="submit" class="btn">+ Ajouter l'article</button>
    <div class="hint">Une donnée inconnue reste « À préciser » — ne jamais inventer une référence, une quantité ou une caractéristique.</div>
  </form>
</div>
