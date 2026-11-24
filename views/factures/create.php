<?php use App\Core\View; use App\Models\Facture; ?>
<a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" style="font-size:13px;color:#666">&larr; Retour au dossier <?= View::e($dossier['reference']) ?></a>

<h1 style="margin-top:8px">Nouvelle facture</h1>
<div class="subtitle">Dossier <?= View::e($dossier['reference']) ?></div>

<div class="card" style="max-width:600px">
  <form method="post" action="/index.php?r=dossiers/<?= $dossier['id'] ?>/factures" data-brouillon="facture-<?= (int) $dossier['id'] ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <?php if ($commande): ?><input type="hidden" name="commande_id" value="<?= $commande['id'] ?>"><?php endif; ?>

    <?php
    // Cotation d'avance : celle acceptée par le client ; sinon la seule du dossier.
    $preselection = 0;
    foreach ($cotations as $c) { if (($c['statut'] ?? '') === 'acceptee') { $preselection = (int) $c['id']; break; } }
    if (!$preselection && count($cotations) === 1) { $preselection = (int) $cotations[0]['id']; }
    $libStatut = ['brouillon' => 'Brouillon', 'envoyee' => 'Envoyée', 'acceptee' => 'Acceptée par le client', 'refusee' => 'Refusée'];
    $fmtC = fn($c) => number_format((float) $c['montant_total'], in_array($c['devise'], ['XAF', 'XOF'], true) ? 0 : 2, ',', ' ') . ' ' . \App\Core\View::dev($c['devise']);
    $aNumero = \App\Core\Schema::colonne('factures', 'numero_officiel');
    ?>
    <style>
      .fa-etape{display:flex;align-items:center;gap:10px;margin:22px 0 10px;font-weight:700;font-size:15px}.fa-etape:first-of-type{margin-top:0}
      .fa-etape .n{width:26px;height:26px;border-radius:50%;background:#0B7C8A;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:13px;flex:0 0 auto}
      .fa-opt{display:flex;gap:10px;align-items:flex-start;border:1px solid #cfd8dc;border-radius:10px;padding:10px 12px;margin-bottom:8px;cursor:pointer}
      .fa-opt:has(input:checked){border:2px solid #0B7C8A;background:#E6F4F6;padding:9px 11px}
      .fa-opt input{margin-top:3px}.fa-opt small{display:block;color:#667;font-size:12px}
      .fa-up{border:1.5px dashed #0B7C8A;border-radius:10px;padding:14px;background:#F1FAFB}
    </style>
    <div class="fa-etape"><span class="n">1</span>Quelle cotation ?</div>
    <?php foreach ($cotations as $c): ?>
      <label class="fa-opt"><input type="radio" name="cotation_id" value="<?= (int) $c['id'] ?>" data-montant="<?= View::e((string) (float) $c['montant_total']) ?>" data-devise="<?= View::e((string) $c['devise']) ?>" <?= $preselection === (int) $c['id'] ? 'checked' : '' ?>>
        <span><strong><?= View::e($c['reference']) ?></strong> — <?= View::e($c['client_nom']) ?><small><?= View::e($libStatut[$c['statut']] ?? $c['statut']) ?> · <?= View::e($fmtC($c)) ?></small></span></label>
    <?php endforeach; ?>
    <label class="fa-opt"><input type="radio" name="cotation_id" value="" <?= $preselection ? '' : 'checked' ?>><span>Aucune cotation</span></label>
    <?php if ($preselection): ?><div class="hint" style="font-size:12px;color:#667">Choisie d'avance : la cotation acceptée du dossier.</div><?php endif; ?>

    <div class="fa-etape"><span class="n">2</span>Montant et dates</div>
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
        <select name="devise" id="fa-devise">
          <?php foreach (['EUR', 'USD', 'XOF', 'XAF', 'GBP', 'CNY'] as $d): ?><option><?= $d ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group"><label>Montant</label><input type="number" step="0.01" name="montant" id="fa-montant" required>
      <div class="hint" style="font-size:12px;color:#667" id="fa-aide-montant"></div></div>
    <div class="form-row">
      <div class="form-group"><label>Date d'émission</label><input type="date" name="date_emission" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-group"><label>Échéance</label><input type="date" name="date_echeance"></div>
    </div>
    <?php if ($aNumero): ?>
    <div class="form-group"><label>N° de la facture officielle</label><input type="text" name="numero_officiel" maxlength="60" placeholder="Numéro donné par votre outil de facturation ou votre comptable">
      <div class="hint" style="font-size:12px;color:#667">Suivora360 suit le paiement ; la facture officielle est établie dans votre outil comptable.</div></div>
    <div class="fa-etape"><span class="n">3</span>Le PDF de votre outil de facturation</div>
    <div class="fa-up"><input type="file" name="fichier" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
      <div class="hint" style="font-size:12px;color:#667;margin-top:6px">PDF, JPG ou PNG, 10 Mo maximum. Le client verra la facture et ce fichier dans son espace.</div></div>
    <?php endif; ?>
    <script>
    (function () {
      var m = document.getElementById('fa-montant'), d = document.getElementById('fa-devise'), aide = document.getElementById('fa-aide-montant'), modifie = false;
      m.addEventListener('input', function () { modifie = true; });
      function appliquer() {
        var r = document.querySelector('input[name="cotation_id"]:checked');
        if (!r || !r.dataset.montant) { aide.textContent = ''; return; }
        if (!modifie) { m.value = r.dataset.montant; }
        for (var i = 0; i < d.options.length; i++) { if (d.options[i].text === r.dataset.devise) { d.selectedIndex = i; break; } }
        aide.textContent = 'Repris de la cotation : modifiable.';
      }
      document.querySelectorAll('input[name="cotation_id"]').forEach(function (x) { x.addEventListener('change', appliquer); });
      appliquer();
    })();
    </script>

    <div class="form-group"><label>Notes</label><textarea name="notes" rows="3"></textarea></div>

    <button type="submit" class="btn">Enregistrer la facture</button>
    <a href="/index.php?r=dossiers/<?= $dossier['id'] ?>" class="btn btn-secondary">Annuler</a>
  </form>
</div>
