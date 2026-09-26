<?php use App\Core\View; use App\Models\ConsultationPartage; ?>
<a href="/index.php?r=consultations/<?= $consultation['id'] ?>" style="font-size:13px;color:#666">&larr; Retour à la consultation <?= View::e($consultation['reference']) ?></a>

<h1 style="margin-top:8px">Demander une offre — récapitulatif &amp; WhatsApp</h1>
<div class="subtitle">Fournisseur : <?= View::e($consultation['fournisseur_nom']) ?> — Dossier <?= View::e($consultation['dossier_reference']) ?></div>

<div class="card" style="max-width:760px">
  <form method="post" action="/index.php?r=consultations/<?= $consultation['id'] ?>/partages">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="masquer_client" value="1" checked style="width:auto">
        Masquer les informations du client final (nom, coordonnées) sur le récapitulatif
      </label>
      <div style="font-size:12px;color:#888;margin-top:4px">Recommandé : le fournisseur voit l'objet et les articles demandés, pas l'identité de votre client.</div>
    </div>

    <div class="form-row">
      <div class="form-group"><label>Échéance de réponse souhaitée</label><input type="date" name="echeance_reponse"></div>
      <div class="form-group">
        <label>Durée de validité du lien</label>
        <select name="duree_jours">
          <?php foreach (ConsultationPartage::DUREES_JOURS as $j): ?>
            <option value="<?= $j ?>" <?= $j === 7 ? 'selected' : '' ?>><?= $j ?> jours</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>Pièces jointes du dossier à partager (optionnel)</label>
      <?php if (empty($pieces)): ?>
        <div class="empty-state">Aucune pièce jointe sur ce dossier.</div>
      <?php else: ?>
        <?php foreach ($pieces as $p): ?>
          <label style="display:flex;align-items:center;gap:8px;padding:4px 0">
            <input type="checkbox" name="pieces_ids[]" value="<?= $p['id'] ?>" style="width:auto">
            <?= View::e($p['nom_original']) ?> <span style="color:#888;font-size:12px">(<?= number_format($p['taille'] / 1024, 0) ?> Ko)</span>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn">Générer le lien et le message WhatsApp</button>
  </form>
</div>

<?php if (!empty($partages)): ?>
<div class="card" style="max-width:760px">
  <h2>Liens déjà générés</h2>
  <table>
    <thead><tr><th>Créé le</th><th>Expire le</th><th>Envoyé</th><th>Consultations</th><th>Statut</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($partages as $p): ?>
      <tr>
        <td><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
        <td><?= date('d/m/Y H:i', strtotime($p['expire_le'])) ?></td>
        <td><?= $p['marque_envoye_le'] ? '✓ ' . date('d/m/Y', strtotime($p['marque_envoye_le'])) : '—' ?></td>
        <td><?= (int) $p['nb_consultations'] ?><?= $p['dernier_acces'] ? ' (dernière le ' . date('d/m/Y', strtotime($p['dernier_acces'])) . ')' : '' ?></td>
        <td>
          <?php if (!empty($p['revoque_le'])): ?>
            <span class="badge badge-red">Révoqué</span>
          <?php elseif (ConsultationPartage::estValide($p)): ?>
            <span class="badge badge-green">Actif</span>
          <?php else: ?>
            <span class="badge badge-gray">Expiré</span>
          <?php endif; ?>
        </td>
        <td><a href="/index.php?r=consultations/<?= $consultation['id'] ?>/partages/<?= $p['id'] ?>" class="btn btn-sm btn-secondary">Voir le lien</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
