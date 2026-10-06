<?php use App\Core\View; ?>
<a href="/index.php?r=demandes/<?= $demande['id'] ?>" style="font-size:13px;color:#666">&larr; Retour à la demande</a>

<h1 style="margin-top:8px">Articles suggérés par l'IA</h1>
<div class="subtitle">Vérifiez et corrigez avant d'enregistrer — décochez ce qui ne correspond pas. Les informations non précisées par le client n'ont pas été inventées : complétez-les vous-même si vous les connaissez.</div>

<div class="card">
<form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/extraction-ia/confirmer">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
  <table class="responsive-cards">
    <thead>
      <tr>
        <th></th>
        <th>Désignation</th>
        <th>Quantité</th>
        <th>Unité</th>
        <th>Conditionnement</th>
        <th>Référence</th>
        <th>Marque</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($suggestions as $i => $s): ?>
      <?php
        $ambigu = !empty($s['ambigu']);
        $dejaExistant = !empty($s['deja_existant']);
        $cocheParDefaut = !$dejaExistant;
      ?>
      <tr<?= ($ambigu || $dejaExistant) ? ' style="background:#fffbeb"' : '' ?>>
        <td data-label=""><input type="checkbox" name="inclure[]" value="<?= $i ?>" <?= $cocheParDefaut ? 'checked' : '' ?>></td>
        <td data-label="Désignation">
          <input type="text" name="designation[<?= $i ?>]" value="<?= View::e($s['designation']) ?>" style="width:100%">
          <?php if ($dejaExistant): ?>
            <div style="margin-top:4px"><span class="badge badge-gray">Déjà présent dans cette demande</span></div>
          <?php endif; ?>
          <?php if ($ambigu): ?>
            <div style="margin-top:4px">
              <span class="badge badge-yellow">À vérifier</span>
              <?php if (!empty($s['note_ambiguite'])): ?>
                <div style="font-size:12px;color:#92400e;margin-top:4px"><?= View::e($s['note_ambiguite']) ?></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </td>
        <td data-label="Quantité"><input type="number" step="0.01" name="quantite[<?= $i ?>]" value="<?= View::e($s['quantite'] !== null ? (string) $s['quantite'] : '') ?>" style="width:90px"></td>
        <td data-label="Unité"><input type="text" name="unite[<?= $i ?>]" value="<?= View::e($s['unite']) ?>" style="width:110px"></td>
        <td data-label="Conditionnement"><input type="text" name="conditionnement[<?= $i ?>]" value="<?= View::e($s['conditionnement'] ?? '') ?>" style="width:140px" placeholder="Ex : sacs de 50 kg"></td>
        <td data-label="Référence"><input type="text" name="reference[<?= $i ?>]" value="<?= View::e($s['reference'] ?? '') ?>" style="width:110px"></td>
        <td data-label="Marque"><input type="text" name="marque[<?= $i ?>]" value="<?= View::e($s['marque'] ?? '') ?>" style="width:110px"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div style="font-size:12px;color:#888;margin-top:10px">Les lignes surlignées correspondent soit à un article déjà présent sur cette demande (décoché par défaut, pour éviter un doublon), soit à une information que l'IA n'a pas pu déduire avec certitude.</div>
  <div style="margin-top:16px">
    <button type="submit" class="btn">Enregistrer les articles cochés</button>
    <a href="/index.php?r=demandes/<?= $demande['id'] ?>" class="btn btn-secondary" style="margin-left:8px">Annuler</a>
  </div>
</form>
</div>
