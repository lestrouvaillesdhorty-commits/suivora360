<?php use App\Core\View; ?>
<h1>Lectures par l'IA</h1>
<div class="subtitle">Chaque lecture de message, de fichier d'offre ou de recherche fournisseur est comptée par filiale et par mois. Un contenu déjà lu n'est ni relu ni recompté. <a href="/index.php?r=parametres">← Paramètres de calcul</a></div>

<?php if (!$disponible): ?>
  <div class="card"><div class="empty-state">Cette fonction demande la dernière mise à jour de la base : lancez <strong>migrate_v39.php</strong>. En attendant, aucune limite n'est appliquée.</div></div>
<?php else: ?>
<div class="card">
  <h2>Ce mois-ci</h2>
  <div style="font-size:13px;color:#666;margin-bottom:8px">Clé IA : <?= $cleIa ? 'renseignée' : '<strong>absente</strong> (ANTHROPIC_API_KEY dans .env)' ?> · Modèle : <?= $modele !== '' ? View::e($modele) : 'par défaut' ?> (réglable avec ANTHROPIC_MODEL_EXTRACTION dans .env).</div>
  <?php foreach ($lignes as $l): $pct = $l['plafond'] > 0 ? min(100, (int) round(100 * $l['utilises'] / $l['plafond'])) : 100; ?>
  <div style="padding:12px 0;border-top:1px solid #eee">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center">
      <strong><?= View::e($l['filiale']['nom']) ?></strong>
      <span style="font-size:14px;color:<?= $l['atteint'] ? '#b42318' : ($l['alerte'] ? '#b45309' : '#333') ?>"><?= $l['utilises'] ?> / <?= $l['plafond'] ?> lectures<?= $l['atteint'] ? ' — plafond atteint' : ($l['alerte'] ? ' — plus de 80 %' : '') ?></span>
    </div>
    <div style="height:8px;background:#e5e7eb;border-radius:4px;margin:8px 0"><div style="height:8px;border-radius:4px;width:<?= $pct ?>%;background:<?= $l['atteint'] ? '#b42318' : ($l['alerte'] ? '#d97706' : '#0B7C8A') ?>"></div></div>
    <form method="post" action="/index.php?r=parametres/ia" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <input type="hidden" name="filiale_id" value="<?= (int) $l['filiale']['id'] ?>">
      <label style="font-weight:400;font-size:13px">Plafond mensuel</label>
      <input type="number" name="plafond" min="0" max="100000" value="<?= (int) $l['plafond'] ?>" style="width:100px">
      <button class="btn btn-sm btn-secondary" type="submit">Enregistrer</button>
    </form>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
