<?php
use App\Core\View;
/** Lien fournisseur — une demande + formulaire de réponse. */
$v = fn(string $k, $def = '') => View::e((string) ($old[$k] ?? $def));
$ouverte = in_array($c['statut'], ['envoyee', 'relance'], true);
$ancien = $precedente ?: null;
$anciensPrix = [];
if ($ancien && empty($old)) { foreach (\App\Models\OffreItem::forOffre((int) $ancien['id']) as $it) { if (!empty($it['demande_article_id'])) { $anciensPrix[(int) $it['demande_article_id']] = $it['prix_unitaire']; } } }
$prixVal = function (int $id) use ($old, $anciensPrix) { if (isset($old['prix'][$id])) { return View::e((string) $old['prix'][$id]); } return isset($anciensPrix[$id]) && $anciensPrix[$id] !== null ? View::e(rtrim(rtrim(number_format((float) $anciensPrix[$id], 4, '.', ''), '0'), '.')) : ''; };
$fq = fn($q) => $q === null || $q === '' ? '' : rtrim(rtrim(number_format((float) $q, 2, ',', ' '), '0'), ',');
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Demande <?= View::e($c['reference'] ?? '') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<?php include __DIR__ . '/../portail/_style.php'; ?></head><body>
<?php include __DIR__ . '/_entete.php'; ?>
<div class="w">
  <div class="hero"><a href="<?= $base ?>" style="font-size:13px;color:var(--m);text-decoration:none">&larr; Toutes mes demandes</a>
  <h1 style="margin-top:8px">Demande <?= View::e($c['reference'] ?? '') ?></h1>
  <div class="sub"><?= !empty($c['echeance_reponse']) ? 'À répondre avant le ' . date('d/m/Y', strtotime($c['echeance_reponse'])) : 'Sans date limite de réponse' ?></div></div>
  <?php if (!empty($flashPortail)): ?><div class="alert <?= $flashPortail['type'] === 'succes' ? 'al-s' : 'al-e' ?>"><?= View::e($flashPortail['message']) ?></div><?php endif; ?>
  <?php if ($erreurs): ?><div class="alert al-e">Vérifiez les champs en rouge ci-dessous.</div><?php endif; ?>
  <div style="margin-top:16px"></div>

  <?php if ($ancien): ?><div class="card" style="border-color:#bfe3d0;background:#f2fbf6"><b>Vous avez déjà envoyé une offre</b> (<?= number_format((float) $ancien['montant_total'], 2, ',', ' ') ?> <?= View::e($ancien['devise']) ?>). Vous pouvez la modifier ci-dessous tant qu'elle n'a pas été traitée.</div><?php endif; ?>

  <div class="card"><h2>Ce que nous demandons</h2>
    <?php if (trim((string) ($c['articles_demandes'] ?? '')) !== ''): ?><div style="white-space:pre-wrap;font-size:14px;margin-bottom:10px"><?= View::e($c['articles_demandes']) ?></div><?php endif; ?>
    <?php if ($livraison['finale'] !== '' || $livraison['incoterm'] !== ''): ?><div class="mut">Livraison : <?= View::e($livraison['finale'] ?: '—') ?><?= $livraison['incoterm'] !== '' ? ' · incoterm souhaité ' . View::e($livraison['incoterm']) : '' ?></div><?php endif; ?>
  </div>

<?php if ($peutRepondre): ?>
  <form method="post" action="<?= $base ?>/demandes/<?= (int) $c['id'] ?>/offre" enctype="multipart/form-data" class="card">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <h2>Votre offre</h2>
    <label>Devise</label>
    <select name="devise"><?php foreach (\App\Models\FournisseurLien::MONNAIES as $m): ?><option <?= (($old['devise'] ?? ($ancien['devise'] ?? 'EUR')) === $m) ? 'selected' : '' ?>><?= $m ?></option><?php endforeach; ?></select>
    <?php if ($articles): foreach ($articles as $a): $id = (int) $a['id']; ?>
      <div style="border:1px solid var(--b);border-radius:10px;padding:10px 12px;margin-top:12px">
        <b><?= View::e($a['designation']) ?></b>
        <div class="mut"><?= $fq($a['quantite']) !== '' ? 'Quantité : ' . View::e($fq($a['quantite'])) . ' ' . View::e($a['unite'] ?? '') : '' ?><?= !empty($a['reference']) ? ' · réf. ' . View::e($a['reference']) : '' ?><?= !empty($a['marque']) ? ' · ' . View::e($a['marque']) : '' ?></div>
        <?php if (!empty($a['caracteristiques'])): ?><div class="mut"><?= View::e($a['caracteristiques']) ?></div><?php endif; ?>
        <label>Votre prix unitaire</label>
        <input type="text" inputmode="decimal" name="prix[<?= $id ?>]" value="<?= $prixVal($id) ?>" placeholder="ex. 916,00" style="<?= isset($erreurs['prix_' . $id]) ? 'border-color:#b42318' : '' ?>">
        <?php if (isset($erreurs['prix_' . $id])): ?><div class="err"><?= View::e($erreurs['prix_' . $id]) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (isset($erreurs['prix'])): ?><div class="err"><?= View::e($erreurs['prix']) ?></div><?php endif; ?>
    <?php else: ?>
      <label>Votre prix total</label>
      <input type="text" inputmode="decimal" name="montant_total" value="<?= $v('montant_total', $ancien ? rtrim(rtrim(number_format((float) $ancien['montant_total'], 2, '.', ''), '0'), '.') : '') ?>">
      <?php if (isset($erreurs['montant_total'])): ?><div class="err"><?= View::e($erreurs['montant_total']) ?></div><?php endif; ?>
    <?php endif; ?>
    <label>Frais de transport (si vous les facturez à part)</label>
    <input type="text" inputmode="decimal" name="transport" value="<?= $v('transport', ($ancien && (float) ($ancien['transport_montant'] ?? 0) > 0) ? rtrim(rtrim(number_format((float) $ancien['transport_montant'], 2, '.', ''), '0'), '.') : '') ?>" style="<?= isset($erreurs['transport']) ? 'border-color:#b42318' : '' ?>">
    <?php if (isset($erreurs['transport'])): ?><div class="err"><?= View::e($erreurs['transport']) ?></div><?php endif; ?>
    <label>Délai de livraison</label>
    <input type="text" name="delai" maxlength="120" value="<?= $v('delai', $ancien['delai_livraison'] ?? '') ?>" placeholder="ex. 10 jours">
    <label>Offre valable jusqu'au</label>
    <input type="date" name="validite" min="<?= date('Y-m-d') ?>" value="<?= $v('validite', $ancien['validite_offre'] ?? '') ?>" style="<?= isset($erreurs['validite']) ? 'border-color:#b42318' : '' ?>">
    <?php if (isset($erreurs['validite'])): ?><div class="err"><?= View::e($erreurs['validite']) ?></div><?php endif; ?>
    <label>Commentaire (facultatif)</label>
    <textarea name="commentaire" rows="3" maxlength="1000"><?= $v('commentaire', $ancien['notes'] ?? '') ?></textarea>
    <label>Joindre votre devis, une fiche technique ou des photos (facultatif)</label>
    <input type="file" name="fichiers[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.txt" style="width:100%;font-size:14px">
    <div class="mut">PDF, images, Word, Excel ou texte · 5 fichiers maximum, 10 Mo chacun.</div>
    <div class="act"><button type="submit" class="btn bp"><?= $ancien ? 'Mettre à jour mon offre' : 'Envoyer mon offre' ?></button></div>
  </form>
  <?php if ($ouverte): ?>
  <div class="card"><h2>Vous ne pouvez pas répondre tout de suite ?</h2>
    <form method="post" action="<?= $base ?>/demandes/<?= (int) $c['id'] ?>/reponse">
      <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
      <label>Message (facultatif)</label><textarea name="note" rows="2" maxlength="500" placeholder="ex. je réponds vendredi / produit indisponible"></textarea>
      <div class="act"><button class="btn bs" name="type" value="delai">J'ai besoin de plus de temps</button><button class="btn bs" name="type" value="decline">Je ne peux pas répondre</button></div>
    </form></div>
  <?php endif; ?>
<?php else: ?>
  <div class="card"><?= !empty($c['reponse_fournisseur']) ? 'Votre réponse a bien été transmise.' : 'Cette demande a déjà été traitée. Merci !' ?></div>
<?php endif; ?>
  <div class="priv">Ce lien est personnel : ne le partagez pas.</div>
</div></body></html>
