<?php use App\Core\View; use App\Models\Dossier; use App\Models\Utilisateur; ?>
<a href="/index.php?r=dossiers" style="font-size:13px;color:#666">&larr; Retour aux dossiers</a>

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-top:8px">
  <div>
    <h1><?= View::e($dossier['reference']) ?></h1>
    <div class="subtitle"><?= View::e($dossier['objet']) ?> — <?= View::e($filiale['nom'] ?? '') ?></div>
  </div>
  <a href="/index.php?r=demandes/<?= $demande['id'] ?>" class="btn btn-secondary">Voir la demande d'origine</a>
</div>

<div class="detail-grid">
  <div>
    <div class="card">
      <h2>Étape et suivi</h2>
      <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/etape" style="margin-bottom:16px">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <label>Étape</label>
        <select name="etape" class="stage-select" onchange="this.form.submit()">
          <?php foreach (Dossier::ETAPES as $etape): ?>
            <option value="<?= $etape ?>" <?= $dossier['etape'] === $etape ? 'selected' : '' ?>><?= Dossier::ETAPES_LABELS[$etape] ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <div class="info-row"><span class="label">Statut</span><span><?= ucfirst($dossier['statut']) ?></span></div>
      <div class="info-row"><span class="label">Responsable</span><span><?= View::e(Utilisateur::nameOf($dossier['responsable_id'])) ?></span></div>
      <div class="info-row"><span class="label">Priorité</span><span><?= $dossier['priorite'] === 'haute' ? 'Haute' : 'Normale' ?></span></div>
      <div class="info-row"><span class="label">Échéance</span><span><?= $dossier['echeance'] ? date('d/m/Y', strtotime($dossier['echeance'])) : '—' ?></span></div>
    </div>

    <div class="card">
      <h2>Demande d'origine</h2>
      <div class="info-row"><span class="label">Expéditeur</span><span><?= View::e($demande['expediteur_nom']) ?> (<?= View::e($demande['expediteur_entreprise']) ?>)</span></div>
      <div class="info-row"><span class="label">Message</span><span><?= nl2br(View::e($demande['message'])) ?></span></div>
      <div class="info-row"><span class="label">Canal</span><span><?= View::e($demande['canal']) ?></span></div>
    </div>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <h2 style="margin:0">Articles</h2>
        <button class="btn btn-sm" onclick="document.getElementById('article-form').style.display='block'">+ Ajouter</button>
      </div>

      <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/articles" id="article-form" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid #eef0f4">
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
          <div class="form-group"><label>Référence</label><input type="text" name="reference"></div>
        </div>
        <div class="form-group"><label>Marque</label><input type="text" name="marque"></div>
        <button type="submit" class="btn">Enregistrer</button>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('article-form').style.display='none'">Annuler</button>
      </form>

      <?php if (empty($articles)): ?>
        <div class="empty-state" style="margin-top:12px">Aucun article pour le moment.</div>
      <?php else: ?>
        <table style="margin-top:16px">
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

    <div class="card">
      <h2>Sourcing / Comparateur d'offres</h2>
      <div class="empty-state">Fonctionnalité à venir</div>
    </div>

    <div class="card">
      <h2>Cotation</h2>
      <div class="empty-state">Fonctionnalité à venir</div>
    </div>

    <div class="card">
      <h2>Documents</h2>
      <div class="empty-state">Fonctionnalité à venir</div>
    </div>
  </div>

  <div>
    <div class="card">
      <h2>Notes internes</h2>
      <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/notes">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <textarea name="notes" rows="6" placeholder="Notes internes..."><?= View::e($dossier['notes']) ?></textarea>
        <button type="submit" class="btn btn-sm" style="margin-top:10px">Enregistrer</button>
      </form>
      <div style="font-size:12px;color:#999;margin-top:10px">
        Dernière modification : <?= date('d/m/Y H:i', strtotime($dossier['updated_at'])) ?>
      </div>
    </div>
  </div>
</div>
