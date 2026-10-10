<?php
use App\Models\Cotation;
use App\Models\Demande;
use App\Models\Dossier;
use App\Core\View;

/** Espace client externe — page principale. Variables : $lien, $client, $onglet, $dossiers, $cotations, $factures, $nbAttente, $emetteur, $base, $erreurs, $old, $csrfToken, $flashPortail. */
$etapesClient = ['qualifie' => 'Pris en charge', 'sourcing' => 'Recherche en cours', 'cotation' => 'Cotation', 'commande' => 'En commande', 'livraison' => 'En livraison', 'cloture' => 'Terminé'];
$fm = fn($m, $d) => number_format((float) $m, 0, ',', ' ') . ' ' . ($d !== null && $d !== '' ? $d : '');
$onglets = ['dossiers' => 'Mes dossiers (' . count($dossiers) . ')', 'cotations' => 'Cotations (' . count($cotations) . ')', 'factures' => 'Factures (' . count($factures) . ')', 'demande' => 'Nouvelle demande'];
$valeur = fn(string $k) => View::e((string) ($old[$k] ?? ''));
$contact = trim(($client['contact_prenom'] ?? '') . ' ' . ($client['contact_nom'] ?? ''));
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Espace client — <?= View::e($client['nom']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<?php include __DIR__ . '/_style.php'; ?></head><body>
<?php include __DIR__ . '/_entete.php'; ?>
<div class="w">
  <div class="hero"><h1>Bonjour<?= $contact !== '' ? ' ' . View::e($contact) : '' ?></h1>
  <div class="sub">Suivez vos dossiers, consultez vos documents et déposez une nouvelle demande.</div></div>
  <?php if (!empty($flashPortail)): ?><div class="alert <?= $flashPortail['type'] === 'succes' ? 'al-s' : 'al-e' ?>"><?= View::e($flashPortail['message']) ?></div><?php endif; ?>

  <div class="tabs">
    <?php foreach ($onglets as $code => $lib): ?>
      <a href="<?= $base ?>&onglet=<?= $code ?>" class="<?= $onglet === $code ? 'on' : '' ?>"><?= View::e($lib) ?><?= ($code === 'cotations' && $nbAttente > 0) ? ' · ' . $nbAttente . ' à valider' : '' ?></a>
    <?php endforeach; ?>
  </div>

<?php if ($onglet === 'dossiers'): ?>
  <?php foreach (array_filter($cotations, fn($c) => \App\Models\PortailClient::decisionPossible($c)) as $c): ?>
  <div class="card cota">
    <span class="badge ye">Action attendue</span>
    <h2 style="margin-top:8px">Cotation <?= View::e($c['reference']) ?> — <?= View::e($c['dossier_objet']) ?></h2>
    <div class="mut"><?= !empty($c['validite_devis']) ? 'Valable jusqu\'au ' . date('d/m/Y', strtotime($c['validite_devis'])) : 'Sans date limite' ?></div>
    <div class="big" style="margin:10px 0 2px"><?= $fm($c['montant_total'], $c['devise']) ?></div>
    <div class="act"><a class="btn bp" href="<?= $base ?>/cotations/<?= (int) $c['id'] ?>">Consulter et répondre</a></div>
  </div>
  <?php endforeach; ?>
  <div class="card"><h2>Mes dossiers</h2>
  <?php if (empty($dossiers)): ?><div class="mut">Aucun dossier pour le moment.</div><?php endif; ?>
  <?php foreach ($dossiers as $d): $idx = array_search($d['etape'], Dossier::ETAPES, true); $idx = $idx === false ? 0 : $idx; ?>
    <div class="row"><div>
      <div class="ref"><?= View::e($d['reference']) ?> — <?= View::e($d['objet']) ?></div>
      <div class="mut"><?= View::e($etapesClient[$d['etape']] ?? $d['etape']) ?><?= !empty($d['echeance']) && $d['statut'] === 'actif' ? ' · échéance prévue ' . date('d/m/Y', strtotime($d['echeance'])) : '' ?></div>
      <div class="steps"><?php for ($i = 0; $i < 6; $i++): ?><i class="<?= $i <= $idx ? 'd' : '' ?>"></i><?php endfor; ?></div>
    </div><span class="badge <?= $d['statut'] === 'actif' ? 'bl' : 'gy' ?>"><?= $d['statut'] === 'actif' ? 'En cours' : 'Terminé' ?></span></div>
  <?php endforeach; ?></div>

<?php elseif ($onglet === 'cotations'): ?>
  <div class="card"><h2>Mes cotations</h2>
  <?php if (empty($cotations)): ?><div class="mut">Aucune cotation à consulter pour le moment.</div><?php endif; ?>
  <?php $badges = ['envoyee' => ['À valider', 'ye'], 'acceptee' => ['Acceptée', 'gr'], 'refusee' => ['Refusée', 'rd']]; foreach ($cotations as $c): $bg = $badges[$c['statut']]; ?>
    <div class="row"><div><div class="ref"><?= View::e($c['reference']) ?> — <?= View::e($c['dossier_objet']) ?></div>
      <div class="mut"><?= $fm($c['montant_total'], $c['devise']) ?><?= !empty($c['validite_devis']) ? ' · valable jusqu\'au ' . date('d/m/Y', strtotime($c['validite_devis'])) : '' ?></div></div>
      <div><span class="badge <?= $bg[1] ?>"><?= $bg[0] ?></span> &nbsp;<a class="btn bs" style="padding:6px 12px" href="<?= $base ?>/cotations/<?= (int) $c['id'] ?>">Voir</a></div></div>
  <?php endforeach; ?></div>

<?php elseif ($onglet === 'factures'): ?>
  <div class="card"><h2>Mes factures</h2>
  <?php if (empty($factures)): ?><div class="mut">Aucune facture pour le moment.</div><?php endif; ?>
  <?php foreach ($factures as $f): ?>
    <div class="row"><div><div class="ref"><?= View::e($f['reference']) ?></div>
      <div class="mut"><?= !empty($f['date_emission']) ? 'Émise le ' . date('d/m/Y', strtotime($f['date_emission'])) . ' · ' : '' ?><?= View::e($f['dossier_reference']) ?><?= ($f['statut'] === 'emise' && !empty($f['date_echeance'])) ? ' · à régler avant le ' . date('d/m/Y', strtotime($f['date_echeance'])) : '' ?></div></div>
      <div><b><?= $fm($f['montant'], $f['devise']) ?></b> &nbsp;<span class="badge <?= $f['statut'] === 'payee' ? 'gr' : 'ye' ?>"><?= $f['statut'] === 'payee' ? 'Payée' : 'À régler' ?></span> &nbsp;<a class="btn bs" style="padding:6px 12px" href="<?= $base ?>/factures/<?= (int) $f['id'] ?>">Voir</a></div></div>
  <?php endforeach; ?></div>

<?php else: ?>
  <div class="card"><h2>Déposer une nouvelle demande</h2>
  <div class="mut">Décrivez votre besoin : votre interlocuteur le reçoit directement et vous répond.</div>
  <?php if ((int) $client['is_active'] !== 1): ?>
    <div class="alert al-e">Votre compte client est actuellement inactif. Merci de contacter directement votre interlocuteur.</div>
  <?php else: ?>
  <form method="post" action="<?= $base ?>/demande" novalidate>
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="hp" aria-hidden="true"><label>Site web</label><input type="text" name="site_web" tabindex="-1" autocomplete="off"></div>
    <label for="objet">Objet de la demande *</label>
    <input type="text" id="objet" name="objet" maxlength="200" value="<?= $valeur('objet') ?>" placeholder="Ex. : 20 tonnes de farine de blé, livraison Douala">
    <?php if (!empty($erreurs['objet'])): ?><div class="err"><?= View::e($erreurs['objet']) ?></div><?php endif; ?>
    <label for="activite">Type de besoin</label>
    <select id="activite" name="activite"><option value="">— Je ne sais pas —</option>
      <?php foreach (Demande::ACTIVITES as $a): ?><option <?= ($old['activite'] ?? '') === $a ? 'selected' : '' ?>><?= View::e($a) ?></option><?php endforeach; ?></select>
    <label for="message">Votre besoin *</label>
    <textarea id="message" name="message" rows="6" maxlength="5000" placeholder="Produits, quantités, destination, contraintes…"><?= $valeur('message') ?></textarea>
    <?php if (!empty($erreurs['message'])): ?><div class="err"><?= View::e($erreurs['message']) ?></div><?php endif; ?>
    <label for="date_souhaitee">Date souhaitée (facultatif)</label>
    <input type="date" id="date_souhaitee" name="date_souhaitee" value="<?= $valeur('date_souhaitee') ?>">
    <div class="act"><button type="submit" class="btn bp">Envoyer ma demande</button></div>
  </form>
  <?php endif; ?></div>
<?php endif; ?>
  <div class="priv">Ce lien est personnel : ne le partagez pas. Vous ne voyez ici que vos propres informations.</div>
</div></body></html>
