<?php
use App\Core\View;
$a = $activiteLogistique ?? ['lignes' => [], 'couts' => [], 'ca' => [], 'marges' => [], 'delai_reception' => null, 'delai_livraison' => null];
$m = fn($t) => implode(' · ', array_map(fn($d, $v) => number_format($v, 2, ',', ' ') . ' ' . View::e($d), array_keys($t), $t)) ?: '—';
$nb = count($a['lignes']);
$nbLivres = count(array_filter($a['lignes'], fn($l) => $l['etat'] === 'Livré'));
$nbCours = count(array_filter($a['lignes'], fn($l) => in_array($l['etat'], ['Chez le transitaire', 'En route', 'En attente'], true)));
$j = fn($v) => $v === null ? '—' : number_format($v, 1, ',', ' ') . ' j';
?>
<style>
.al-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:14px}
.al-k{background:#fff;border:1px solid #E1EAEE;border-radius:12px;padding:12px 14px}
.al-k span{font-size:12px;color:#5B6B78;display:block}.al-k b{font-size:17px;display:block;margin-top:2px}.al-k small{font-size:12px;color:#7A8894}
</style>
<div class="al-kpis">
  <div class="al-k"><span>Dossiers confiés</span><b><?= $nb ?></b><small><?= $nbCours ?> en cours · <?= $nbLivres ?> livrés</small></div>
  <div class="al-k"><span>Ce qu'il te coûte</span><b><?= $m($a['couts']) ?></b><small>par devise, jamais additionnées</small></div>
  <div class="al-k"><span>Ton CA sur ces dossiers</span><b><?= $m($a['ca']) ?></b><small>dossiers de transport (cotation client)</small></div>
  <div class="al-k"><span>Ta marge</span><b><?= $m($a['marges']) ?></b><small>dossiers de transport</small></div>
  <div class="al-k"><span>Délais moyens</span><b>Reçu → parti : <?= $j($a['delai_reception']) ?></b><small>Parti → livré : <?= $j($a['delai_livraison']) ?></small></div>
</div>
<div class="card">
  <h2>Dossiers où il intervient</h2>
  <?php if (!$nb): ?>
    <div class="empty-state">Aucun dossier pour l'instant. Un dossier apparaît ici dès que ce transitaire est choisi dans « Suivi du colis », dans la carte transport d'un dossier d'achat, ou comme offre retenue.</div>
  <?php else: ?>
  <table class="responsive-cards" style="margin-top:6px">
    <thead><tr><th>Dossier</th><th>Type</th><th>État du colis</th><th class="num">Coût transitaire</th><th class="num">CA</th></tr></thead>
    <tbody>
    <?php foreach ($a['lignes'] as $l): ?>
      <tr>
        <td data-label="Dossier"><a href="/index.php?r=dossiers/<?= (int) $l['id'] ?>"><?= View::e($l['reference']) ?></a><div style="font-size:12px;color:#888"><?= View::e((string) $l['objet']) ?></div></td>
        <td data-label="Type"><?= $l['transport'] ? 'Transport' : 'Achat avec transport' ?></td>
        <td data-label="État du colis"><?= View::e($l['etat']) ?></td>
        <td data-label="Coût transitaire" class="num" style="white-space:nowrap"><?= $l['cout'] !== null ? number_format($l['cout'], 2, ',', ' ') . ' ' . View::e($l['dev_cout']) : '—' ?></td>
        <td data-label="CA" class="num" style="white-space:nowrap"><?= $l['ca'] !== null ? number_format($l['ca'], 2, ',', ' ') . ' ' . View::e($l['dev_ca']) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div style="font-size:12px;color:#888;margin-top:8px">Pour un dossier d'achat, seule la partie transport confiée au transitaire est comptée dans son coût, et le CA n'est pas affiché (il mélange la marchandise et le transport). Les délais viennent des dates de la carte « Suivi du colis ».</div>
  <?php endif; ?>
</div>
