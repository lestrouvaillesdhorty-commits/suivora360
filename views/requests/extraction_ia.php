<?php use App\Core\View; ?>
<a href="/index.php?r=demandes/<?= $demande['id'] ?>" style="font-size:13px;color:#666">&larr; Retour à la demande</a>

<h1 style="margin-top:8px">Articles suggérés par l'IA</h1>
<div class="subtitle">Vérifiez et corrigez avant d'enregistrer — décochez ce qui ne correspond pas.</div>

<div class="card">
<form method="post" action="/index.php?r=demandes/<?= $demande['id'] ?>/extraction-ia/confirmer">
  <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
  <table>
    <thead><tr><th></th><th>Désignation</th><th>Quantité</th><th>Unité</th></tr></thead>
    <tbody>
    <?php foreach ($suggestions as $i => $s): ?>
      <tr>
        <td><input type="checkbox" name="inclure[]" value="<?= $i ?>" checked></td>
        <td><input type="text" name="designation[<?= $i ?>]" value="<?= View::e($s['designation']) ?>" style="width:100%"></td>
        <td><input type="number" step="0.01" name="quantite[<?= $i ?>]" value="<?= View::e($s['quantite'] !== null ? (string) $s['quantite'] : '') ?>" style="width:90px"></td>
        <td><input type="text" name="unite[<?= $i ?>]" value="<?= View::e($s['unite']) ?>" style="width:110px"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div style="margin-top:16px">
    <button type="submit" class="btn">Enregistrer les articles cochés</button>
    <a href="/index.php?r=demandes/<?= $demande['id'] ?>" class="btn btn-secondary" style="margin-left:8px">Annuler</a>
  </div>
</form>
</div>
