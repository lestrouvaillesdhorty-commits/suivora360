<?php
// Décliner / Supprimer une offre (à côté de « Retenir »). Variables : $o (offre), $dossier, $csrfToken.
use App\Core\View;
$base = '/index.php?r=dossiers/' . (int) $dossier['id'] . '/comparateur/';
?>
<div class="offre-actions" style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;align-items:flex-start">
<?php if ($o['statut'] === 'recue'): ?>
  <details>
    <summary style="cursor:pointer" class="btn btn-sm btn-secondary">Décliner…</summary>
    <form method="post" action="<?= $base ?>ecarter" style="margin-top:8px;min-width:220px">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <input type="hidden" name="offre_id" value="<?= (int) $o['id'] ?>">
      <label style="font-size:12px">Motif (obligatoire)</label>
      <textarea name="motif_ecart" rows="2" required placeholder="ex : prix trop élevé, délai trop long"></textarea>
      <button type="submit" class="btn btn-sm btn-secondary" style="margin-top:6px">Confirmer — décliner cette offre</button>
    </form>
  </details>
<?php elseif ($o['statut'] === 'rejetee'): ?>
  <?php if (empty($sansBadge)): ?><span class="badge badge-gray">Déclinée</span><?php endif; ?>
  <form method="post" action="<?= $base ?>reexaminer" style="margin:0">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="offre_id" value="<?= (int) $o['id'] ?>">
    <button type="submit" class="btn btn-sm btn-secondary">Remettre en comparaison</button>
  </form>
<?php endif; ?>
<?php if ($o['statut'] !== 'retenue'): ?>
  <form method="post" action="<?= $base ?>supprimer" style="margin:0" onsubmit="return confirm('Cette offre sera retirée du dossier, vous ne pourrez pas la récupérer. Continuer ?')">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <input type="hidden" name="offre_id" value="<?= (int) $o['id'] ?>">
    <button type="submit" class="btn btn-sm btn-secondary" style="color:#B3261E">Supprimer</button>
  </form>
<?php endif; ?>
</div>
