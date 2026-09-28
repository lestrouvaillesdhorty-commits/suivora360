<?php
use App\Core\View;
?>
<div style="display:flex;justify-content:space-between;align-items:center">
  <h1>Notifications</h1>
  <?php if (!empty($notifications)): ?>
  <form method="post" action="/index.php?r=notifications/tout-marquer-lu">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <button type="submit" class="btn btn-sm btn-secondary">Tout marquer comme lu</button>
  </form>
  <?php endif; ?>
</div>
<div class="subtitle">Mouvements importants qui vous concernent (assignation à un dossier, retrait, etc.).</div>

<div class="card">
  <?php if (empty($notifications)): ?>
    <div class="empty-state">Aucune notification pour le moment.</div>
  <?php else: ?>
    <?php foreach ($notifications as $n): ?>
      <form method="post" action="/index.php?r=notifications/<?= $n['id'] ?>/lue" style="margin:0">
        <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
        <button type="submit" class="notif-row-btn" style="width:100%;text-align:left;background:<?= $n['lu'] ? 'transparent' : '#f5f7ff' ?>;border:none;border-bottom:1px solid #eef0f4;padding:12px 4px;cursor:pointer;display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
          <span>
            <span style="display:block">
              <?php if (!$n['lu']): ?><span class="badge badge-blue" style="margin-right:6px">Nouveau</span><?php endif; ?>
              <strong><?= View::e($n['titre']) ?></strong>
            </span>
            <?php if (!empty($n['message'])): ?><span style="display:block;color:#666;font-size:13px;margin-top:4px"><?= View::e($n['message']) ?></span><?php endif; ?>
          </span>
          <span class="label" style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></span>
        </button>
      </form>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
