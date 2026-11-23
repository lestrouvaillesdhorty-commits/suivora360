<?php
use App\Core\View;
use App\Models\Dossier;
/** Espace client — un dossier : avancement, suivi (notes de l'équipe), messages. Variables : $lien, $client, $dossier, $vue, $notes, $messages, $nonLus, $emetteur, $base, $csrfToken, $flashPortail. */
$etapesClient = ['qualifie' => 'Pris en charge', 'sourcing' => 'Recherche en cours', 'cotation' => 'Cotation', 'commande' => 'En commande', 'livraison' => 'En livraison', 'cloture' => 'Terminé'];
$idx = array_search($dossier['etape'], Dossier::ETAPES, true); $idx = $idx === false ? 0 : $idx;
$url = $base . '/dossiers/' . (int) $dossier['id'];
$onglets = ['avancement' => 'Avancement', 'suivi' => 'Suivi (' . count($notes) . ')', 'messages' => 'Messages'];
$nomEquipe = $emetteur !== '' ? $emetteur : 'Équipe';
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Dossier <?= View::e($dossier['reference']) ?> — <?= View::e($client['nom']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<?php include __DIR__ . '/_style.php'; ?></head><body>
<?php include __DIR__ . '/_entete.php'; ?>
<div class="w">
  <div class="hero"><h1><?= View::e($dossier['reference']) ?> — <?= View::e($dossier['objet']) ?></h1>
  <div class="sub"><span class="badge big e-<?= View::e($dossier['etape']) ?>"><?= View::e($etapesClient[$dossier['etape']] ?? $dossier['etape']) ?></span></div></div>
  <?php if (!empty($flashPortail)): ?><div class="alert <?= $flashPortail['type'] === 'succes' ? 'al-s' : 'al-e' ?>"><?= View::e($flashPortail['message']) ?></div><?php endif; ?>
  <div class="tabs">
    <?php foreach ($onglets as $code => $lib): ?>
      <a href="<?= $url ?>&vue=<?= $code ?>" class="<?= $vue === $code ? 'on' : '' ?>"><?= View::e($lib) ?><?= ($code === 'messages' && $nonLus > 0) ? ' <span class="badge rd" style="padding:1px 7px">' . (int) $nonLus . '</span>' : '' ?></a>
    <?php endforeach; ?>
  </div>

<?php if ($vue === 'avancement'): ?>
  <div class="card"><h2>Avancement</h2>
    <div class="steps s-<?= View::e($dossier['etape']) ?>" style="width:100%"><?php for ($i = 0; $i < 6; $i++): ?><i class="<?= $i <= $idx ? 'd' : '' ?>"></i><?php endfor; ?></div>
    <div class="mut" style="margin-top:8px"><?= !empty($dossier['echeance']) && $dossier['statut'] === 'actif' ? 'Échéance prévue ' . date('d/m/Y', strtotime($dossier['echeance'])) : ($dossier['statut'] === 'actif' ? 'Dossier en cours.' : 'Dossier terminé.') ?></div>
    <?php if (!empty($notes)): $n = $notes[0]; ?>
      <div style="border-left:3px solid #0B7C8A;padding:2px 0 2px 10px;margin-top:14px"><div class="mut"><?= date('d/m/Y H:i', strtotime((string) $n['created_at'])) ?> · dernière information</div><div style="white-space:pre-line"><?= View::e($n['texte']) ?></div></div>
    <?php endif; ?>
  </div>
<?php elseif ($vue === 'suivi'): ?>
  <div class="card"><h2>Suivi du dossier</h2>
    <?php if (empty($notes)): ?><div class="mut">Pas encore d’information. Votre interlocuteur y indique l’avancement de votre dossier.</div><?php endif; ?>
    <?php foreach ($notes as $n): ?>
      <div style="border-left:3px solid #0B7C8A;padding:2px 0 2px 10px;margin-bottom:12px"><div class="mut"><?= date('d/m/Y H:i', strtotime((string) $n['created_at'])) ?></div><div style="white-space:pre-line"><?= View::e($n['texte']) ?></div></div>
    <?php endforeach; ?>
    <div class="mut">Mis à jour par votre interlocuteur<?= $emetteur !== '' ? ' chez ' . View::e($emetteur) : '' ?>. Une question ? Utilisez l’onglet Messages.</div>
  </div>
<?php else: ?>
  <div class="card"><h2>Messages</h2>
    <?php $actionUrl = $url . '/messages'; $fichierBase = $base . '/messages'; include __DIR__ . '/_fil_externe.php'; ?>
  </div>
<?php endif; ?>
  <div class="act"><a href="<?= $base ?>">← Tous mes dossiers</a></div>
  <div class="priv">Ce lien est personnel : ne le partagez pas. Vous ne voyez ici que vos propres informations.</div>
</div></body></html>
