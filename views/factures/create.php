<?php use App\Core\View; use App\Models\Facture; ?>
<a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au dossier <?= View::e($dossier['reference']) ?></a>

<h1 style="margin-top:8px">Nouvelle facture</h1>
<div class="subtitle">Dossier <?= View::e($dossier['reference']) ?></div>

<div class="card" style="max-width:600px">
  <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/factures">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <?php if ($commande): ?><input type="hidden" name="commande_id" value="<?= $commande['id'] ?>"><?php endif; ?>

    <?php if (!empty($cotations)): ?>
    <div class="form-group">
      <label>Cotation associée (optionnel)</label>
      <select name="cotation_id">
        <option value="">—</option>
        <?php foreach ($cotations as $c): ?>
          <option value="<?= $c['id'] ?>"><?= View::e($c['reference']) ?> — <?= View::e($c['client_nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <div class="form-row">
      <div class="form-group">
        <label>Type</label>
        <select name="type">
          <?php foreach (Facture::TYPES as $code => $label): ?>
            <option value="<?= $code ?>"><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Devise</label>
        <select name="devise">
          <?php foreach (['EUR', 'USD', 'XOF', 'XAF', 'GBP', 'CNY'] as $d): ?><option><?= $d ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group"><label>Montant</label><input type="number" step="0.01" name="montant" required></div>
    <div class="form-row">
      <div class="form-group"><label>Date d'émission</label><input type="date" name="date_emission" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-group"><label>Échéance</label><input type="date" name="date_echeance"></div>
    </div>
    <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"></textarea></div>

    <button type="submit" class="btn">Enregistrer la facture</button>
    <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" class="btn btn-secondary">Annuler</a>
  </form>
</div>
