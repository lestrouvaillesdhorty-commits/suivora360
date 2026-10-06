<?php
use App\Core\Icon;
use App\Core\View;
use App\Models\Demande;
?>
<h1>Importer des dossiers</h1>
<div class="subtitle">Rattacher des dossiers déjà en cours ailleurs (avant Suivora360)</div>

<div class="card">
  <p>Un dossier ne peut pas exister sans demande d'origine : chaque ligne du fichier crée automatiquement
  une demande, la qualifie par la voie <strong>« Reprise hors Suivora »</strong>, puis crée le dossier —
  exactement comme le parcours manuel en 2 écrans, mais en une seule fois.</p>
  <p>Seule la colonne <strong>objet</strong> est obligatoire ; sans <strong>activité</strong> reconnue ou
  <strong>filiale</strong> identifiable, la ligne est ignorée (voir le détail après import).</p>
  <p>
    <a href="/index.php?r=dossiers/importer/modele.csv" class="btn btn-secondary"><?= Icon::svg('download', 'icon', 15) ?> Télécharger le modèle CSV</a>
  </p>
  <table style="margin-top:10px">
    <thead><tr><th>Colonne</th><th>Obligatoire</th><th>Détail</th></tr></thead>
    <tbody>
      <tr><td>objet</td><td>Oui</td><td>Objet du dossier</td></tr>
      <tr><td>activite</td><td>Oui</td><td>Une des valeurs : <?= View::e(implode(', ', Demande::ACTIVITES)) ?></td></tr>
      <tr><td>filiale</td><td>Non si une seule filiale</td><td>Nom exact de la filiale (sinon ligne ignorée)</td></tr>
      <tr><td>takeover_stage</td><td>Non</td><td>Étape actuelle (ex. suivi_operationnel, cotation_envoyee...)</td></tr>
      <tr><td>original_started_at</td><td>Non</td><td>Date réelle de début, format JJ/MM/AAAA</td></tr>
      <tr><td>external_source, external_reference</td><td>Non</td><td>Origine et référence externes (ex. ancien fichier Excel)</td></tr>
      <tr><td>responsable</td><td>Non</td><td>Nom exact d'un collaborateur de votre organisation</td></tr>
      <tr><td>priorite</td><td>Non</td><td>normale / haute / critique — par défaut normale</td></tr>
      <tr><td>echeance</td><td>Non</td><td>Format JJ/MM/AAAA</td></tr>
      <tr><td>notes</td><td>Non</td><td>Notes libres sur la reprise</td></tr>
    </tbody>
  </table>
</div>

<div class="card">
  <form method="post" action="/index.php?r=dossiers/importer" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= View::e($csrfToken) ?>">
    <div class="form-group">
      <label>Fichier CSV *</label>
      <input type="file" name="fichier" accept=".csv,text/csv" required>
    </div>
    <button type="submit" class="btn"><?= Icon::svg('upload', 'icon', 15) ?> Importer</button>
    <a href="/index.php?r=dossiers" class="btn btn-secondary">Annuler</a>
  </form>
</div>
