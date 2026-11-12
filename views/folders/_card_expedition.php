<?php
use App\Core\View;
use App\Core\Auth;
use App\Models\Expedition;
// Carte « Expédition » d'un dossier (transport, sourcing…) : réservations de ce dossier + ajout dans une expédition ouverte.
if (!class_exists(Expedition::class) || !Expedition::disponible()) { return; }
$resDos = Expedition::reservationsDuDossier((int) $dossier['id']);
$ouvertesExp = Auth::canWrite() ? Expedition::ouvertes((int) $dossier['filiale_id']) : [];
$expPick = (int) ($_GET['exp'] ?? 0);
$expChoisie = null;
foreach ($ouvertesExp as $oe) { if ((int) $oe['id'] === $expPick) { $expChoisie = $oe; } }
?>
<div class="card">
  <h2>Expédition</h2>
  <?php if ($resDos): foreach ($resDos as $r): ?>
    <div class="kv-row"><span class="label"><a href="/index.php?r=expeditions/<?= (int) $r['expedition_id'] ?>"><?= View::e($r['expedition_reference'] ?? 'Expédition') ?></a></span>
      <span class="value"><?= View::e(($r['origine'] ?? '') . ' → ' . ($r['destination'] ?? '')) ?> · <?= View::e(Expedition::STATUTS[$r['expedition_statut'] ?? ''] ?? '') ?> · <?= number_format((float) $r['prix_vente'], 0, ',', ' ') ?> <?= View::e($r['devise'] ?? '') ?></span></div>
  <?php endforeach; else: ?>
    <div class="empty-state">Ce dossier n'est rattaché à aucune expédition.</div>
  <?php endif; ?>
  <?php if ($ouvertesExp): ?>
    <form method="get" action="/index.php" style="margin-top:10px">
      <input type="hidden" name="r" value="dossiers/<?= (int) $dossier['id'] ?>"><input type="hidden" name="onglet" value="execution">
      <div class="form-group"><label>Réserver une place dans</label>
        <select name="exp" onchange="this.form.submit()"><option value="">— Choisir une expédition ouverte —</option>
          <?php foreach ($ouvertesExp as $oe): ?><option value="<?= (int) $oe['id'] ?>" <?= $expChoisie && (int) $oe['id'] === (int) $expChoisie['id'] ? 'selected' : '' ?>><?= View::e($oe['reference'] . ' · ' . $oe['origine'] . ' → ' . $oe['destination']) ?></option><?php endforeach; ?></select></div>
    </form>
    <?php if ($expChoisie): $exp = $expChoisie; $dossiers = []; $dossierFixe = (int) $dossier['id']; $retourDossier = (int) $dossier['id']; include __DIR__ . '/../expeditions/_form_reservation.php'; endif; ?>
  <?php endif; ?>
</div>
