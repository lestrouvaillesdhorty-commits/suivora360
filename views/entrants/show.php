<?php use App\Core\View; use App\Models\EntrantDemande; ?>
<a href="/index.php?r=a-valider" style="font-size:13px;color:#666">&larr; À valider</a>
<h1 style="margin-top:8px">Vérifier la demande</h1>
<div class="subtitle">Reçu <?= View::e(date('d/m/Y H:i', strtotime((string) $e['created_at']))) ?> · <?= View::e(EntrantDemande::libelleSource((string) $e['source'])) ?>. Corrigez si besoin, puis créez la demande.</div>
<?php if ($infoIa !== ''): ?><div class="alert" style="background:#eef0f4;color:#333;font-size:13px"><?= View::e($infoIa) ?></div><?php endif; ?>

<form method="post" action="/index.php?r=a-valider/<?= (int) $e['id'] ?>/valider">
<input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
<div class="card">
  <div class="form-row">
    <div class="form-group"><label>Client</label>
      <select name="client_id"><option value="">— Non précisé —</option>
        <?php foreach ($clients as $c): ?><option value="<?= (int) $c['id'] ?>"><?= View::e($c['nom']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="form-group"><label>Activité <span style="color:#C0392B">*</span></label>
      <select name="activite" required><?php foreach ($activites as $a): ?><option value="<?= View::e($a) ?>"><?= View::e($a) ?></option><?php endforeach; ?></select></div>
  </div>
  <div class="form-group"><label>Objet <span style="color:#C0392B">*</span></label><input type="text" name="objet" value="<?= View::e($objet) ?>" required maxlength="200"></div>
  <div class="form-group"><label>Message d'origine</label><textarea name="message" rows="5"><?= View::e((string) $e['texte']) ?></textarea></div>
  <?php if ($e['nom_fichier']): ?>
    <div class="form-group"><label>Fichier joint</label>
      <a href="/index.php?r=a-valider/<?= (int) $e['id'] ?>/fichier" target="_blank" rel="noopener"><?= View::e((string) $e['nom_original']) ?></a>
      <?php if (preg_match('/^image\//', (string) $e['type_mime'])): ?><div style="margin-top:8px"><img src="/index.php?r=a-valider/<?= (int) $e['id'] ?>/fichier" alt="" style="max-width:100%;max-height:320px;border-radius:8px;border:1px solid #DDE4EC"></div><?php endif; ?>
      <div class="hint" style="font-size:12px;color:#888;margin-top:4px">Il sera joint à la demande.</div></div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:14px">
  <h2 style="margin-top:0">Articles lus</h2>
  <table class="responsive-cards" id="avArt">
    <thead><tr><th>Désignation</th><th>Quantité</th><th>Unité</th><th>Conditionnement</th><th>Référence</th><th>Marque</th></tr></thead>
    <tbody>
    <?php $lignes = $articles; $lignes[] = []; $lignes[] = []; foreach ($lignes as $i => $a): ?>
      <tr<?= !empty($a['ambigu']) ? ' style="background:#fffbeb"' : '' ?>>
        <td data-label="Désignation"><input type="text" name="art_designation[<?= $i ?>]" value="<?= View::e((string) ($a['designation'] ?? '')) ?>" style="width:100%">
          <?php if (!empty($a['ambigu'])): ?><div style="margin-top:4px"><span class="badge badge-yellow">À vérifier</span> <small><?= View::e((string) ($a['note_ambiguite'] ?? '')) ?></small></div><?php endif; ?></td>
        <td data-label="Quantité"><input type="number" step="any" min="0" name="art_quantite[<?= $i ?>]" value="<?= View::e((string) ($a['quantite'] ?? '')) ?>" style="width:100%"></td>
        <td data-label="Unité"><input type="text" name="art_unite[<?= $i ?>]" value="<?= View::e((string) ($a['unite'] ?? '')) ?>" style="width:100%"></td>
        <td data-label="Conditionnement"><input type="text" name="art_conditionnement[<?= $i ?>]" value="<?= View::e((string) ($a['conditionnement'] ?? '')) ?>" style="width:100%"></td>
        <td data-label="Référence"><input type="text" name="art_reference[<?= $i ?>]" value="<?= View::e((string) ($a['reference'] ?? '')) ?>" style="width:100%"></td>
        <td data-label="Marque"><input type="text" name="art_marque[<?= $i ?>]" value="<?= View::e((string) ($a['marque'] ?? '')) ?>" style="width:100%"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="hint" style="font-size:12.5px;color:#888;margin-top:8px">Les lignes vides sont ignorées. Rien n'est inventé : complétez ce que le message ne précise pas.</div>
</div>

<div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap">
  <button type="submit" class="btn">Créer la demande</button>
</div>
</form>
<form method="post" action="/index.php?r=a-valider/<?= (int) $e['id'] ?>/ignorer" style="margin-top:10px" onsubmit="return confirm('Mettre de côté sans créer de demande ?')">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
  <button type="submit" class="btn btn-secondary btn-sm">Ignorer</button>
</form>
