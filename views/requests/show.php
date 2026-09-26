<?php use App\Core\View; use App\Models\Utilisateur; ?>
<a href="/index.php?r=demandes" style="font-size:13px;color:#666">&larr; Retour aux demandes</a>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px">
  <div>
    <h1>
      <?= View::e($demande['reference']) ?>
      <?php if ($demande['statut'] === 'a_qualifier'): ?>
        <span class="badge badge-yellow">À qualifier</span>
      <?php else: ?>
        <span class="badge badge-blue">Qualifiée</span>
      <?php endif; ?>
      <?php if ($demande['priorite'] === 'haute'): ?><span class="badge badge-red">Haute</span><?php endif; ?>
    </h1>
    <div class="subtitle"><?= View::e($demande['objet']) ?> — <?= View::e($filiale['nom'] ?? '') ?></div>
  </div>
  <div>
    <?php if ($demande['statut'] === 'a_qualifier'): ?>
      <button class="btn" onclick="document.getElementById('qualifier-form').style.display='block'">Qualifier la demande</button>
    <?php elseif ($dossier): ?>
      <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" class="btn">Voir le dossier <?= View::e($dossier['reference']) ?></a>
    <?php endif; ?>
  </div>
</div>

<?php if ($demande['statut'] === 'a_qualifier'): ?>
<div class="card" id="qualifier-form" style="display:none">
  <h2>Qualifier la demande</h2>
  <form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/qualifier">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Responsable</label>
        <input type="text" name="responsable_id" placeholder="ID utilisateur (optionnel)" value="<?= View::e((string) $demande['responsable_id']) ?>">
      </div>
      <div class="form-group">
        <label>Priorité</label>
        <select name="priorite">
          <option value="normale" <?= $demande['priorite'] === 'normale' ? 'selected' : '' ?>>Normale</option>
          <option value="haute" <?= $demande['priorite'] === 'haute' ? 'selected' : '' ?>>Haute</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label>Échéance</label>
      <input type="date" name="echeance" value="<?= View::e($demande['echeance']) ?>">
    </div>
    <button type="submit" class="btn">Qualifier et créer le dossier</button>
    <button type="button" class="btn btn-secondary" onclick="document.getElementById('qualifier-form').style.display='none'">Annuler</button>
  </form>
</div>
<?php endif; ?>

<div class="detail-grid">
  <div>
    <div class="card">
      <h2>Demande et message original</h2>
      <div class="info-row"><span class="label">Objet</span><span><?= View::e($demande['objet']) ?></span></div>
      <div class="info-row"><span class="label">Message</span><span><?= nl2br(View::e($demande['message'])) ?></span></div>
      <div class="info-row"><span class="label">Canal</span><span><?= View::e($demande['canal']) ?></span></div>
      <div class="info-row"><span class="label">Reçue le</span><span><?= $demande['recue_le'] ? date('d/m/Y', strtotime($demande['recue_le'])) : '—' ?></span></div>
    </div>

    <div class="card">
      <h2>Expéditeur</h2>
      <div class="info-row"><span class="label">Nom</span><span><?= View::e($demande['expediteur_nom']) ?: '—' ?></span></div>
      <div class="info-row"><span class="label">Entreprise</span><span><?= View::e($demande['expediteur_entreprise']) ?: '—' ?></span></div>
      <div class="info-row"><span class="label">E-mail</span><span><?= View::e($demande['expediteur_email']) ?: '—' ?></span></div>
      <div class="info-row"><span class="label">Téléphone</span><span><?= View::e($demande['expediteur_telephone']) ?: '—' ?></span></div>
    </div>

    <div class="card">
      <h2>Articles</h2>
      <?php if (empty($articles)): ?>
        <div class="empty-state">Aucun article pour le moment.</div>
      <?php else: ?>
        <table>
          <thead><tr><th>Désignation</th><th>Qté</th><th>Unité</th><th>Référence</th><th>Marque</th></tr></thead>
          <tbody>
          <?php foreach ($articles as $a): ?>
            <tr>
              <td><?= View::e($a['designation']) ?></td>
              <td><?= View::e((string) $a['quantite']) ?></td>
              <td><?= View::e($a['unite']) ?></td>
              <td><?= View::e($a['reference']) ?></td>
              <td><?= View::e($a['marque']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="card">
      <h2>Suivi</h2>
      <div class="info-row"><span class="label">Activité</span><span><?= View::e($demande['activite']) ?: '—' ?></span></div>
      <div class="info-row"><span class="label">Responsable</span><span><?= View::e(Utilisateur::nameOf($demande['responsable_id'])) ?></span></div>
      <div class="info-row"><span class="label">Priorité</span><span><?= $demande['priorite'] === 'haute' ? 'Haute' : 'Normale' ?></span></div>
      <div class="info-row"><span class="label">Échéance</span><span><?= $demande['echeance'] ? date('d/m/Y', strtotime($demande['echeance'])) : '—' ?></span></div>
    </div>
  </div>
</div>
