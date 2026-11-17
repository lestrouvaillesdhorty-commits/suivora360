<?php use App\Core\View; ?>
<a href="/index.php?r=consultations/<?= (int) $consultation['id'] ?>" style="font-size:13px;color:#666">&larr; Retour à la consultation <?= View::e($consultation['reference']) ?></a>

<h1 style="margin-top:8px">Préparer la consultation</h1>
<div class="subtitle">Dossier <?= View::e($dossier['reference']) ?> — <?= View::e($dossier['objet']) ?> · pas encore envoyée</div>

<div class="card" style="max-width:640px">
  <form method="post" action="/index.php?r=consultations/<?= (int) $consultation['id'] ?>/modifier">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-group">
      <label for="selFournisseur">Fournisseur consulté</label>
      <select name="fournisseur_id" id="selFournisseur">
        <?php foreach ($fournisseurs as $f): ?>
          <option value="<?= (int) $f['id'] ?>" <?= (int) $consultation['fournisseur_id'] === (int) $f['id'] ? 'selected' : '' ?>><?= View::e($f['nom']) ?><?= $f['pays'] ? ' (' . View::e($f['pays']) . ')' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php if (!empty($articlesDemande)): ?>
    <div class="form-group">
      <input type="hidden" name="articles_choix" value="1">
      <label>Articles à envoyer à ce fournisseur (<?= count($articlesDemande) ?> dans la demande)</label>
      <?php foreach ($articlesDemande as $a): ?>
        <?php $coche = $articlesIds === null || in_array((int) $a['id'], $articlesIds, true); ?>
        <label style="display:flex;gap:8px;align-items:flex-start;font-weight:400;padding:3px 0">
          <input type="checkbox" name="articles[]" value="<?= (int) $a['id'] ?>" <?= $coche ? 'checked' : '' ?> style="width:auto;margin-top:3px">
          <span><?= View::e(trim(($a['quantite'] !== null && $a['quantite'] !== '' ? rtrim(rtrim(number_format((float) $a['quantite'], 2, ',', ''), '0'), ',') . ' ' : '') . ($a['unite'] ?? '') . ' ' . $a['designation'])) ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (\App\Models\ConsultationFournisseur::prepaDisponible()):
      $liv = \App\Models\ConsultationFournisseur::livraison($consultation, $demande ?? null);
      $infosAct = \App\Models\ConsultationFournisseur::infos($consultation);
      $propo = array_merge(\App\Models\ConsultationFournisseur::INFOS_DEFAUT, \App\Models\ConsultationFournisseur::INFOS_PROPOSEES);
      $autres = array_values(array_diff($infosAct, $propo)); ?>
    <div class="card" style="background:#f8f9fb;box-shadow:none;border:1px solid #e3e6ec">
      <h2 style="font-size:15px">Livraison demandée au fournisseur</h2>
      <div class="form-group"><label for="livMode">Mode de livraison</label>
        <select name="livraison_mode" id="livMode">
          <option value="directe" <?= !$liv['etape'] ? 'selected' : '' ?>>Directe : un seul lieu de livraison</option>
          <option value="etape" <?= $liv['etape'] ? 'selected' : '' ?>>Avec étape : livraison dans un premier pays, puis destination finale</option>
        </select></div>
      <div id="blocEtape" style="<?= $liv['etape'] ? '' : 'display:none' ?>">
        <div class="form-row">
          <div class="form-group"><label>Livrer à (étape)</label><input type="text" name="livraison_etape_pays" value="<?= View::e($liv['etape_pays']) ?>" placeholder="Ex. France"></div>
          <div class="form-group"><label>Destination finale</label><input type="text" name="livraison_finale_pays" value="<?= View::e($liv['finale']) ?>" placeholder="Ex. Cameroun"></div>
        </div>
        <div class="form-group"><label>Adresse ou entrepôt de réception (étape)</label><textarea name="livraison_etape_adresse" rows="2"><?= View::e($liv['etape_adresse']) ?></textarea></div>
      </div>
      <div class="form-group"><label>Incoterm demandé</label><input type="text" name="incoterm_demande" value="<?= View::e($liv['incoterm']) ?>" placeholder="Ex. DAP, EXW, FOB" style="max-width:260px">
        <div class="hint" style="font-size:12px;color:#888">Avec étape, il s'applique à la livraison dans le premier pays. Par défaut : celui de la demande.</div></div>
      <script>(function(){var m=document.getElementById('livMode');m.addEventListener('change',function(){document.getElementById('blocEtape').style.display=m.value==='etape'?'':'none';});})();</script>
    </div>
    <div class="card" style="background:#f8f9fb;box-shadow:none;border:1px solid #e3e6ec">
      <h2 style="font-size:15px">Informations à nous donner (listées dans la demande)</h2>
      <?php foreach ($propo as $lib): ?>
        <label style="display:flex;gap:8px;align-items:center;font-weight:400;padding:3px 0"><input type="checkbox" name="infos[]" value="<?= View::e($lib) ?>" <?= in_array($lib, $infosAct, true) ? 'checked' : '' ?> style="width:auto"> <?= View::e($lib) ?></label>
      <?php endforeach; ?>
      <div class="form-group" style="margin-top:8px"><label>Autres informations (une par ligne)</label><textarea name="infos_autres" rows="2" placeholder="Ex. Photos du produit"><?= View::e(implode("\n", $autres)) ?></textarea></div>
    </div>
    <?php endif; ?>

    <div class="form-group">
      <label>Précisions complémentaires pour le fournisseur</label>
      <textarea name="articles_demandes" rows="12" style="min-height:240px;resize:vertical;line-height:1.5" class="autogrow"><?= View::e($consultation['articles_demandes']) ?></textarea>
    </div>
    <div class="form-group">
      <label>Notes internes</label>
      <textarea name="notes" rows="5" style="resize:vertical" class="autogrow"><?= View::e($consultation['notes']) ?></textarea>
    </div>
    <button type="submit" class="btn">Enregistrer les modifications</button>
    <a href="/index.php?r=consultations/<?= (int) $consultation['id'] ?>" class="btn btn-secondary">Annuler</a>
  </form>
</div>
<script>
// Les grands champs de texte s'agrandissent automatiquement pour afficher tout le message.
document.querySelectorAll('textarea.autogrow').forEach(function (t) {
  var min = t.offsetHeight;
  function g() { t.style.height = 'auto'; t.style.height = Math.max(min, t.scrollHeight + 4) + 'px'; }
  t.addEventListener('input', g); g();
});
</script>
