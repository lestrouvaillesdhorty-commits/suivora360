<?php
use App\Core\View;
use App\Models\Echange;
/** Onglet Suivi du dossier : notes de l'équipe, privées par défaut, visibles du client et/ou du fournisseur si cochées. */
if (!Echange::pret()): ?>
  <div class="card"><div class="empty-state">Migration V72 requise pour activer le suivi du dossier.</div></div>
<?php return; endif;
$id = (int) $dossier['id'];
$peutEcrire = \App\Core\Auth::canWrite();
$csrf = \App\Core\Auth::csrfToken();
$notes = Echange::notes($id);
$base = '/index.php?r=dossiers/' . $id;
?>
<?php if ($peutEcrire): ?>
<div class="card">
  <h2>Ajouter une note</h2>
  <form method="post" action="<?= $base ?>/suivi">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrf) ?>">
    <textarea name="texte" rows="3" maxlength="3000" placeholder="Écrire une note sur l’avancement du dossier…" style="width:100%"></textarea>
    <div style="margin-top:6px">
      <label style="display:flex;gap:7px;align-items:center;font-size:13.5px;margin:4px 0"><input type="checkbox" name="visible_client" value="1"> Visible par le client</label>
      <label style="display:flex;gap:7px;align-items:center;font-size:13.5px;margin:4px 0"><input type="checkbox" name="visible_fournisseur" value="1"> Visible par le fournisseur (sur ses commandes de ce dossier)</label>
      <div style="font-size:12px;color:#888;margin:2px 0 8px">Par défaut, une note reste privée : cochez pour la montrer. Cocher « client » prévient aussi le client par e-mail.</div>
    </div>
    <button type="submit" class="btn btn-sm">Ajouter</button>
  </form>
</div>
<?php endif; ?>
<div class="card">
  <h2>Notes du dossier</h2>
  <?php if (empty($notes)): ?><div class="empty-state">Aucune note pour le moment.</div><?php endif; ?>
  <?php foreach ($notes as $n): $vc = (int) $n['visible_client'] === 1; $vf = (int) $n['visible_fournisseur'] === 1; ?>
    <div style="border:1px solid #E6E8EF;border-radius:10px;padding:9px 12px;margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap">
        <span style="font-size:12px;color:#667"><?= date('d/m/Y H:i', strtotime((string) $n['created_at'])) ?> · <?= View::e(\App\Models\Utilisateur::nameOf($n['auteur_id'] ? (int) $n['auteur_id'] : null)) ?></span>
        <span>
          <?php if ($vc): ?><span class="badge badge-green">Visible client</span><?php endif; ?>
          <?php if ($vf): ?><span class="badge badge-blue">Visible fournisseur</span><?php endif; ?>
          <?php if (!$vc && !$vf): ?><span class="badge badge-gray">Privée</span><?php endif; ?>
        </span>
      </div>
      <div style="white-space:pre-line;font-size:14px;margin-top:3px"><?= View::e($n['texte']) ?></div>
      <?php if ($peutEcrire): ?>
      <details style="margin-top:6px"><summary style="font-size:12.5px;color:#0B7C8A;cursor:pointer">Modifier ou retirer</summary>
        <form method="post" action="<?= $base ?>/suivi/<?= (int) $n['id'] ?>" style="margin-top:6px">
          <input type="hidden" name="csrf_token" value="<?= View::e($csrf) ?>">
          <textarea name="texte" rows="3" maxlength="3000" style="width:100%"><?= View::e($n['texte']) ?></textarea>
          <label style="display:flex;gap:7px;align-items:center;font-size:13px;margin:4px 0"><input type="checkbox" name="visible_client" value="1" <?= $vc ? 'checked' : '' ?>> Visible par le client</label>
          <label style="display:flex;gap:7px;align-items:center;font-size:13px;margin:4px 0"><input type="checkbox" name="visible_fournisseur" value="1" <?= $vf ? 'checked' : '' ?>> Visible par le fournisseur</label>
          <button type="submit" class="btn btn-sm">Enregistrer</button>
        </form>
        <form method="post" action="<?= $base ?>/suivi/<?= (int) $n['id'] ?>/retirer" style="margin-top:6px" onsubmit="return confirm('Retirer cette note ? Elle disparaît aussi du lien.')">
          <input type="hidden" name="csrf_token" value="<?= View::e($csrf) ?>">
          <button type="submit" class="btn btn-sm btn-secondary">Retirer la note</button>
        </form>
      </details>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
