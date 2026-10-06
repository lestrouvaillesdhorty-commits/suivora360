<?php
use App\Core\Icon;
use App\Core\View;
use App\Models\Demande;
?>
<h1>Importer des demandes</h1>
<div class="subtitle">Créer plusieurs demandes en une fois à partir d'un fichier CSV</div>

<div class="card">
  <p>Le fichier doit être au format CSV (séparateur <code>;</code> ou <code>,</code>), avec une ligne d'en-têtes.
  Seule la colonne <strong>objet</strong> est obligatoire ; sans <strong>activité</strong> reconnue ou
  <strong>filiale</strong> identifiable, la ligne est ignorée (voir le détail après import).</p>
  <p>
    <a href="/index.php?r=demandes/importer/modele.csv" class="btn btn-secondary"><?= Icon::svg('download', 'icon', 15) ?> Télécharger le modèle CSV</a>
  </p>
  <table style="margin-top:10px">
    <thead><tr><th>Colonne</th><th>Obligatoire</th><th>Détail</th></tr></thead>
    <tbody>
      <tr><td>objet</td><td>Oui</td><td>Objet de la demande</td></tr>
      <tr><td>activite</td><td>Oui</td><td>Une des valeurs : <?= View::e(implode(', ', Demande::ACTIVITES)) ?></td></tr>
      <tr><td>filiale</td><td>Non si une seule filiale</td><td>Nom exact de la filiale (sinon ligne ignorée)</td></tr>
      <tr><td>expediteur_nom, expediteur_entreprise, expediteur_email, expediteur_telephone</td><td>Non</td><td>Coordonnées de l'expéditeur</td></tr>
      <tr><td>canal</td><td>Non</td><td>Par défaut "Import"</td></tr>
      <tr><td>recue_le</td><td>Non</td><td>Format JJ/MM/AAAA — par défaut aujourd'hui</td></tr>
      <tr><td>priorite</td><td>Non</td><td>normale / haute / critique — par défaut normale</td></tr>
      <tr><td>echeance</td><td>Non</td><td>Format JJ/MM/AAAA</td></tr>
      <tr><td>message</td><td>Non</td><td>Message ou détails complémentaires</td></tr>
    </tbody>
  </table>
</div>

<div class="card">
  <form method="post" action="/index.php?r=demandes/importer" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-group">
      <label>Fichier CSV *</label>
      <input type="file" name="fichier" accept=".csv,text/csv" required>
    </div>
    <button type="submit" class="btn"><?= Icon::svg('upload', 'icon', 15) ?> Importer</button>
    <a href="/index.php?r=demandes" class="btn btn-secondary">Annuler</a>
  </form>
</div>
