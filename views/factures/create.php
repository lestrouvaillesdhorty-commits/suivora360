<?php use App\Core\View; use App\Models\Facture; ?>
<a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au dossier <?= View::e($dossier['reference']) ?></a>

<h1 style="margin-top:8px">Nouvelle facture</h1>
<div class="subtitle">Dossier <?= View::e($dossier['reference']) ?></div>

<div class="card" style="max-width:600px">
  <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/factures" data-brouillon="facture-<?= (int) $dossier['id'] ?>" enctype="multipart/form-data">
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
    <?php if (\App\Core\Schema::colonne('factures', 'numero_officiel')): ?>
    <div class="form-group"><label>N° de la facture officielle</label><input type="text" name="numero_officiel" maxlength="60" placeholder="Numéro donné par votre outil de facturation ou votre comptable">
      <div class="hint" style="font-size:12px;color:#667">Suivora360 suit le paiement ; la facture officielle est établie dans votre outil comptable.</div></div>
    <div class="form-group"><label>Pièce jointe (PDF, JPG ou PNG, 10 Mo maximum)</label><input type="file" name="fichier" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"></div>
    <?php endif; ?>
    <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"></textarea></div>

    <button type="submit" class="btn">Enregistrer la facture</button>
    <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" class="btn btn-secondary">Annuler</a>
  </form>
</div>
