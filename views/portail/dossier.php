<?php
use App\Core\View;
use App\Models\Dossier;
/** Espace client — un dossier : avancement, suivi (notes de l'équipe), messages. Variables : $lien, $client, $dossier, $vue, $notes, $messages, $nonLus, $emetteur, $base, $csrfToken, $flashPortail. */
$etapesClient = ['qualifie' => 'Pris en charge', 'sourcing' => 'Recherche en cours', 'cotation' => 'Cotation', 'commande' => 'En commande', 'livraison' => 'En livraison', 'cloture' => 'Terminé'];
$idx = array_search($dossier['etape'], Dossier::ETAPES, true); $idx = $idx === false ? 0 : $idx;
$url = $base . '/dossiers/' . (int) $dossier['id'];
$devis = $devis ?? []; $factures = $factures ?? []; $documents = $documents ?? [];
$nbNouveaux = 0; foreach ($documents as $dc) { if (strtotime((string) $dc['created_at']) > time() - 7 * 86400) { $nbNouveaux++; } }
$onglets = ['avancement' => 'Avancement', 'devis' => 'Devis et factures', 'documents' => 'Documents', 'suivi' => 'Suivi (' . count($notes) . ')', 'messages' => 'Messages'];
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
      <a href="<?= $url ?>&vue=<?= $code ?>" class="<?= $vue === $code ? 'on' : '' ?>"><?= View::e($lib) ?><?= ($code === 'messages' && $nonLus > 0) ? ' <span class="badge rd" style="padding:1px 7px">' . (int) $nonLus . '</span>' : '' ?><?= ($code === 'documents' && $nbNouveaux > 0) ? ' <span class="badge rd" style="padding:1px 7px">' . (int) $nbNouveaux . '</span>' : '' ?></a>
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
<?php elseif ($vue === 'devis'): ?>
  <div class="card"><h2>Devis</h2>
    <?php $badgesDevis = ['envoyee' => ['À valider', 'ye'], 'acceptee' => ['Acceptée', 'gr'], 'refusee' => ['Refusée', 'rd']]; ?>
    <?php if (empty($devis)): ?><div class="mut">Aucun devis pour ce dossier pour le moment.</div><?php endif; ?>
    <?php foreach ($devis as $c): $bg = $badgesDevis[$c['statut']] ?? ['', '']; ?>
      <div class="dl"><div class="nm"><b><?= View::e($c['reference']) ?></b><span class="mut">émis le <?= date('d/m/Y', strtotime((string) $c['created_at'])) ?> · <?= number_format((float) $c['montant_total'], in_array($c['devise'], ['XAF', 'XOF'], true) ? 0 : 2, ',', ' ') ?> <?= View::dev($c['devise']) ?></span></div>
        <span class="badge <?= $bg[1] ?>"><?= $bg[0] ?></span>
        <a class="btn bs" style="padding:6px 12px" href="<?= $base ?>/cotations/<?= (int) $c['id'] ?>">Voir</a>
        <a class="btn bp" style="padding:6px 12px" href="<?= $base ?>/cotations/<?= (int) $c['id'] ?>/pdf">PDF</a></div>
    <?php endforeach; ?>
  </div>
  <div class="card"><h2>Factures</h2>
    <?php if (empty($factures)): ?><div class="mut">Aucune facture pour ce dossier pour le moment.</div><?php endif; ?>
    <?php foreach ($factures as $f): ?>
      <div class="dl"><div class="nm"><b><?= View::e($f['reference']) ?></b><span class="mut"><?= !empty($f['date_emission']) ? 'émise le ' . date('d/m/Y', strtotime((string) $f['date_emission'])) : '' ?><?= ($f['statut'] === 'emise' && !empty($f['date_echeance'])) ? ' · échéance ' . date('d/m/Y', strtotime((string) $f['date_echeance'])) : '' ?> · <?= number_format((float) $f['montant'], in_array($f['devise'], ['XAF', 'XOF'], true) ? 0 : 2, ',', ' ') ?> <?= View::dev($f['devise']) ?></span></div>
        <span class="badge <?= $f['statut'] === 'payee' ? 'gr' : 'ye' ?>"><?= $f['statut'] === 'payee' ? 'Payée' : 'À régler' ?></span>
        <a class="btn bs" style="padding:6px 12px" href="<?= $base ?>/factures/<?= (int) $f['id'] ?>">Voir</a><?php if (!empty($f['fichier'])): ?> <a class="btn bp" style="padding:6px 12px" href="<?= $base ?>/factures/<?= (int) $f['id'] ?>/fichier">PDF</a><?php endif; ?></div>
    <?php endforeach; ?>
  </div>
<?php elseif ($vue === 'documents'): ?>
  <?php
  $catsClient = \App\Models\DossierPieceJointe::CATEGORIES; unset($catsClient['offres_fournisseurs']);
  $parCat = []; foreach ($documents as $dc) { $parCat[$dc['categorie']][] = $dc; }
  $urlDoc = fn($dc, $apercu = false) => $url . '/documents/' . (int) $dc['id'] . ($apercu ? '&apercu=1' : '');
  $estImage = fn($dc) => in_array($dc['type_mime'], ['image/jpeg', 'image/png'], true);
  $taille = fn($o) => $o >= 1048576 ? number_format($o / 1048576, 1, ',', ' ') . ' Mo' : number_format(max(1, $o / 1024), 0, ',', ' ') . ' Ko';
  $nouveau = fn($dc) => strtotime((string) $dc['created_at']) > time() - 7 * 86400;
  ?>
  <style>.gal{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:8px;margin:6px 0 10px}.gal a{display:block;aspect-ratio:1;border-radius:10px;overflow:hidden;border:1px solid #DDE4EC;position:relative;background:#EEF2F6}.gal img{width:100%;height:100%;object-fit:cover;display:block}.gal .nw{position:absolute;top:4px;left:4px}.gh{font-size:13px;font-weight:700;color:#0B7C8A;margin:16px 0 4px;border-left:4px solid #0B7C8A;padding-left:8px}.nw{background:#FFF1E0;color:#B4570F;font-size:10.5px;font-weight:700;border-radius:99px;padding:1px 7px}</style>
  <div class="card"><h2>Documents du dossier</h2>
    <?php if (empty($documents)): ?><div class="mut">Aucun document partagé pour le moment. Votre interlocuteur y ajoute les photos, fiches techniques et certificats de votre dossier.</div><?php endif; ?>
    <?php foreach ($catsClient as $code => $lib): if (empty($parCat[$code])) { continue; } ?>
      <div class="gh"><?= View::e($lib) ?></div>
      <?php $imgs = array_filter($parCat[$code], $estImage); $autres = array_filter($parCat[$code], fn($dc) => !$estImage($dc)); ?>
      <?php if ($imgs): ?><div class="gal"><?php foreach ($imgs as $dc): ?><a href="<?= $urlDoc($dc, true) ?>" target="_blank" rel="noopener" title="<?= View::e($dc['nom_original']) ?>"><img loading="lazy" src="<?= $urlDoc($dc, true) ?>" alt="<?= View::e($dc['nom_original']) ?>"><?php if ($nouveau($dc)): ?><span class="nw">nouveau</span><?php endif; ?></a><?php endforeach; ?></div><?php endif; ?>
      <?php foreach ($autres as $dc): ?>
        <div class="dl"><div class="nm"><b style="word-break:break-word"><?= View::e($dc['nom_original']) ?></b> <?php if ($nouveau($dc)): ?><span class="nw">nouveau</span><?php endif; ?><span class="mut"><?= date('d/m/Y', strtotime((string) $dc['created_at'])) ?> · <?= $taille((int) $dc['taille']) ?></span></div>
          <a class="btn bs" style="padding:6px 12px" href="<?= $urlDoc($dc) ?>">Télécharger</a></div>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if (!empty($documents)): ?><div class="mut" style="margin-top:12px">Une question sur un document ? Utilisez l’onglet Messages.</div><?php endif; ?>
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
