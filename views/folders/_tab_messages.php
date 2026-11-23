<?php
use App\Core\View;
use App\Models\Echange;
use App\Models\BonCommandeFournisseur;
/** Onglet Messages du dossier. Variables : $dossier, $echangeNonLus (avant lecture). */
if (!Echange::pret()): ?>
  <div class="card"><div class="empty-state">Migration V72 requise pour activer la messagerie du dossier.</div></div>
<?php return; endif;
$id = (int) $dossier['id'];
$peutEcrire = \App\Core\Auth::canWrite();
$csrfToken = \App\Core\Auth::csrfToken();
$client = \App\Models\Client::find(Echange::clientIdDuDossier($dossier));
try { $bons = BonCommandeFournisseur::pourDossier($id); } catch (\Throwable $e) { $bons = []; }
?>
<div class="card">
  <?php $nonLusFil = fn(array $ms) => count(array_filter($ms, fn($m) => $m['emetteur'] === 'externe' && empty($m['lu_interne']))); ?>
  <?php $msgClient = $client ? Echange::messages(Echange::CLIENT, $id) : []; $nl = $nonLusFil($msgClient); ?>
  <h2>Client<?= $client ? ' · ' . View::e($client['nom']) : '' ?> <?php if ($nl > 0): ?><span class="badge badge-red"><?= $nl ?> non lu<?= $nl > 1 ? 's' : '' ?></span><?php endif; ?></h2>
  <?php if (!$client): ?>
    <div class="empty-state">Aucun client n’est rattaché à ce dossier.</div>
  <?php else:
    $messages = $msgClient;
    $actionUrl = '/index.php?r=dossiers/' . $id . '/messages'; $fichierBase = '/index.php?r=dossiers/' . $id . '/messages'; $canal = Echange::CLIENT; $bonId = null; $placeholder = 'Répondre au client…';
    include __DIR__ . '/../echanges/_fil.php'; ?>
    <div class="hint" style="font-size:12px;color:#888;margin-top:6px">Le client voit ce fil sur son lien, dans le dossier. Il est prévenu par e-mail (contact choisi sur le lien) quand vous répondez.</div>
  <?php endif; ?>
</div>
<?php foreach ($bons as $b):
  $f = \App\Models\Fournisseur::find((int) $b['fournisseur_id']);
  $messages = Echange::messages(Echange::FOURNISSEUR, $id, (int) $b['id']);
  $actionUrl = '/index.php?r=dossiers/' . $id . '/messages'; $fichierBase = '/index.php?r=dossiers/' . $id . '/messages'; $canal = Echange::FOURNISSEUR; $bonId = (int) $b['id']; $placeholder = 'Répondre au fournisseur…'; ?>
<div class="card">
  <h2>Fournisseur · <?= View::e($f['nom'] ?? '') ?> <?php $nlf = $nonLusFil($messages); if ($nlf > 0): ?><span class="badge badge-red"><?= $nlf ?> non lu<?= $nlf > 1 ? 's' : '' ?></span><?php endif; ?> <span style="font-weight:400;font-size:13px;color:#666">— bon <a href="/index.php?r=bons-commande/<?= (int) $b['id'] ?>"><?= View::e($b['reference']) ?></a></span></h2>
  <?php include __DIR__ . '/../echanges/_fil.php'; ?>
</div>
<?php endforeach; ?>
<?php if (!$bons): ?><div class="card"><h2>Fournisseur</h2><div class="empty-state">Aucun bon de commande envoyé à un fournisseur sur ce dossier : sa messagerie apparaît dès l’envoi du bon.</div></div><?php endif; ?>
<?php Echange::marquerLusInterne($id); ?>
