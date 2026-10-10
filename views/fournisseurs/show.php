<?php
use App\Core\View;
use App\Models\Fournisseur;

/**
 * Fiche fournisseur à 7 onglets (07/10) : Vue générale, Contacts, Offres, Commandes,
 * Documents, Évaluation, Finances (selon les droits).
 */
$actif = (int) $fournisseur['is_active'] === 1;
$qual = Fournisseur::qualificationDe($fournisseur);
$base = '/index.php?r=fournisseurs/' . (int) $fournisseur['id'];
$onglets = [
    'vue' => 'Vue générale',
    'contacts' => 'Contacts',
    'offres' => 'Offres',
    'commandes' => 'Commandes',
    'documents' => 'Documents',
    'evaluation' => 'Évaluation',
];
if ($financesAutorisees) {
    $onglets['finances'] = 'Finances';
}
$nbAlertes = count($alertesDocuments);
?>
<a href="<?= View::e($retour) ?>" style="font-size:13px;color:#666">&larr; Retour aux fournisseurs</a>

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-top:8px">
  <div>
    <h1 style="margin-bottom:4px"><?= View::e($fournisseur['nom']) ?></h1>
    <?php if (!empty($fournisseur['nom_commercial'])): ?><div style="color:#666;font-size:14px;margin-bottom:4px">Nom commercial : <?= View::e($fournisseur['nom_commercial']) ?></div><?php endif; ?>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <span style="color:#666;font-size:14px"><?= View::e($fournisseur['code'] ?? '') ?></span>
      <?php foreach ($types as $t): ?><span class="badge <?= Fournisseur::TYPES_BADGES[$t] ?>"><?= View::e(Fournisseur::TYPES[$t]) ?></span><?php endforeach; ?>
      <span class="badge <?= Fournisseur::QUALIFICATION_BADGES[$qual] ?>" title="Niveau de qualification"><?= View::e(Fournisseur::QUALIFICATIONS[$qual]) ?></span>
      <span class="badge <?= $actif ? 'badge-green' : 'badge-gray' ?>"><?= $actif ? 'Actif' : 'Inactif' ?></span>
    </div>
  </div>
  <?php if ($peutEcrire): ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start">
    <a href="<?= $base ?>/modifier" class="btn btn-secondary">Modifier</a>
    <?php if ($actif): ?>
      <details style="position:relative">
        <summary class="btn" style="list-style:none;cursor:pointer">Créer une consultation</summary>
        <div class="card" style="position:absolute;right:0;z-index:5;min-width:280px;margin-top:6px">
          <?php if (empty($dossiersActifs)): ?>
            <div class="empty-state" style="padding:8px">Aucun dossier actif. Une consultation s’adresse toujours à un dossier : créez d’abord le dossier.</div>
          <?php else: ?>
          <form method="get" action="/index.php" onsubmit="if(!this.dossier.value){return false;} this.action='/index.php'; this.r.value='dossiers/'+this.dossier.value+'/consultations/nouvelle'; this.dossier.disabled=true;">
            <input type="hidden" name="r" value="">
            <input type="hidden" name="fournisseur_id" value="<?= (int) $fournisseur['id'] ?>">
            <div class="form-group"><label for="dossierConsult">Pour quel dossier ?</label>
              <select id="dossierConsult" name="dossier"><option value="">— Choisir —</option>
                <?php foreach ($dossiersActifs as $d): ?><option value="<?= (int) $d['id'] ?>"><?= View::e($d['reference']) ?> — <?= View::e(mb_strimwidth((string) $d['objet'], 0, 40, '…')) ?></option><?php endforeach; ?>
              </select></div>
            <button type="submit" class="btn btn-sm">Continuer</button>
          </form>
          <?php endif; ?>
        </div>
      </details>
      <a href="<?= $base ?>&onglet=contacts" class="btn btn-secondary">Ajouter un contact</a>
      <form method="post" action="<?= $base ?>/desactiver" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <input type="hidden" name="retour" value="fiche">
        <button type="submit" class="btn btn-secondary">Désactiver</button>
      </form>
    <?php else: ?>
      <form method="post" action="<?= $base ?>/activer" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <input type="hidden" name="retour" value="fiche">
        <button type="submit" class="btn">Réactiver</button>
      </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php if (!$actif): ?>
  <div class="alert" style="background:#f1f2f5;color:#4b5563;margin-top:12px">Ce fournisseur est inactif : ses consultations, offres, commandes et documents restent consultables. Réactivez-le pour lui adresser de nouvelles consultations.</div>
<?php endif; ?>
<?php if (!$schemaPret): ?>
  <div class="alert" style="background:#fef3c7;color:#92400e;margin-top:12px">Mise à jour de la base à finaliser (migration V23) : contacts multiples, qualification, évaluations et échéances de documents seront disponibles ensuite.</div>
<?php endif; ?>

<div class="tabs" style="margin-top:16px;overflow-x:auto;white-space:nowrap">
  <?php foreach ($onglets as $code => $lib): ?>
    <a href="<?= $base . ($code === 'vue' ? '' : '&onglet=' . $code) ?>" class="<?= $onglet === $code ? 'active' : '' ?>"><?= View::e($lib) ?><?php
      if ($code === 'offres' && count($consultations) > 0): ?> (<?= count($consultations) ?>)<?php endif;
      if ($code === 'commandes' && count($commandes) > 0): ?> (<?= count($commandes) ?>)<?php endif;
      if ($code === 'documents' && count($pieces) > 0): ?> (<?= count($pieces) ?>)<?php endif;
      if ($code === 'documents' && $nbAlertes > 0): ?> <span class="badge badge-red" title="Documents à renouveler"><?= $nbAlertes ?></span><?php endif; ?></a>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/_tab_' . $onglet . '.php'; ?>
