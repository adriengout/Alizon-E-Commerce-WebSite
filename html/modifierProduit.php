<?php
session_start();

require_once("loginBdd.php");

try {
    $connexionPDO = new PDO("pgsql:host=$host;dbname=$dbname;options='--client_encoding=UTF8'", $username, $password);
    $connexionPDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connexionPDO->exec("SET search_path TO sae, public");
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

if (!isset($_SESSION['id_compte']) || !isset($_GET['id'])) {
    header('Location: connexion.php');
    exit();
}

if (!isset($_SESSION['is_vendeur']) || !$_SESSION['is_vendeur']) {
    header('Location: index.php');
    exit();
}

$identifiantVendeur = $_SESSION['id_compte'];
$identifiantProduit = intval($_GET['id']);

$requete = $connexionPDO->prepare("
    SELECT p.*,
           s.quantite_dispo, s.seuil_alerte,
           t.type_tva, t.taux_tva,
           i.nom_fichier as img_nom, i.chemin as img_chemin, i.extension
    FROM _produit p
    LEFT JOIN _stock s ON p.id_produit = s.id_produit
    LEFT JOIN _tva t ON p.id_produit = t.id_produit
    LEFT JOIN _image i ON p.id_image = i.id_image
    WHERE p.id_produit = ? AND p.id_vendeur = ?
");
$requete->execute([$identifiantProduit, $identifiantVendeur]);
$detailsProduit = $requete->fetch(PDO::FETCH_ASSOC);

if (!$detailsProduit) {
    die("Produit introuvable ou accès refusé.");
}

$requeteCategorie = $connexionPDO->query("SELECT id_categ, nom_categ FROM _categorieproduit ORDER BY nom_categ ASC");
$listeCategories = $requeteCategorie->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'modifier') {

    $connexionPDO->beginTransaction();

    try {
        $requeteProduit = $connexionPDO->prepare("
            UPDATE _produit SET
                nom_produit = :nom,
                description_prod = :desc,
                prix_ht = :prix,
                dep_origine = :dep,
                ville_origine = :ville,
                pays_origine = :pays,
                date_prod = :datep,
                id_categ = :categ
            WHERE id_produit = :id AND id_vendeur = :vend
        ");

        $requeteProduit->execute([
            ":nom" => $_POST["nom"],
            ":desc" => $_POST["description"],
            ":prix" => $_POST["prix_ht"],
            ":dep" => $_POST["departement"],
            ":ville" => $_POST["ville"],
            ":pays" => $_POST["pays"],
            ":datep" => $_POST["date_prod"],
            ":categ" => $_POST["categorie"],
            ":id" => $identifiantProduit,
            ":vend" => $identifiantVendeur
        ]);

        $requeteStock = $connexionPDO->prepare("UPDATE _stock SET quantite_dispo = :q, seuil_alerte = :s, derniere_maj = CURRENT_DATE WHERE id_produit = :id");
        $requeteStock->execute([':q' => $_POST['stock'], ':s' => $_POST['seuil'], ':id' => $identifiantProduit]);

        if ($requeteStock->rowCount() == 0) {
            $connexionPDO->prepare("INSERT INTO _stock (id_vendeur, id_produit, quantite_dispo, seuil_alerte, derniere_maj, alerte) VALUES (?, ?, ?, ?, CURRENT_DATE, false)")
                ->execute([$identifiantVendeur, $identifiantProduit, $_POST['stock'], $_POST['seuil']]);
        }

        $connexionPDO->prepare("DELETE FROM _tva WHERE id_produit = ?")->execute([$identifiantProduit]);
        $connexionPDO->prepare("INSERT INTO _tva (id_produit, type_tva, taux_tva) VALUES (?, ?, ?)")
            ->execute([$identifiantProduit, $_POST['type_tva'], $_POST['taux_tva']]);

        $dossierPhysique = __DIR__ . "/media/produits/";
        $cheminWeb = "media/produits/";

        if (!is_dir($dossierPhysique)) {
            mkdir($dossierPhysique, 0777, true);
        }

        if (isset($_FILES['image_main']) && $_FILES['image_main']['error'] === 0) {
            $tmp = $_FILES['image_main']['tmp_name'];
            $ext = strtolower(strrchr($_FILES['image_main']['name'], '.'));
            $nouveauNom = "produit" . $identifiantProduit . "_1" . $ext;

            if (move_uploaded_file($tmp, $dossierPhysique . $nouveauNom)) {
                $connexionPDO->prepare("UPDATE _image SET nom_fichier = ?, chemin = ?, extension = ? WHERE id_image = ?")
                    ->execute([$nouveauNom, $cheminWeb, $ext, $detailsProduit['id_image']]);
            }
        }

        for ($i = 2; $i <= 4; $i++) {
            $inputKey = "image_sec_" . ($i - 1);
            if (isset($_FILES[$inputKey]) && $_FILES[$inputKey]['error'] === 0) {
                $tmp = $_FILES[$inputKey]['tmp_name'];
                $ext = strtolower(strrchr($_FILES[$inputKey]['name'], '.'));
                $nouveauNom = "produit" . $identifiantProduit . "_" . $i . $ext;

                if (move_uploaded_file($tmp, $dossierPhysique . $nouveauNom)) {
                    $nomRecherche = "produit" . $identifiantProduit . "_" . $i;
                    $verificationImage = $connexionPDO->prepare("SELECT id_image FROM _image WHERE nom_fichier LIKE ?");
                    $verificationImage->execute([$nomRecherche . '%']);
                    $idImageExistante = $verificationImage->fetchColumn();

                    if ($idImageExistante) {
                        $connexionPDO->prepare("UPDATE _image SET nom_fichier = ?, chemin = ?, extension = ? WHERE id_image = ?")
                            ->execute([$nouveauNom, $cheminWeb, $ext, $idImageExistante]);
                    } else {
                        $connexionPDO->prepare("INSERT INTO _image (nom_fichier, chemin, extension, alt) VALUES (?, ?, ?, ?)")
                            ->execute([$nouveauNom, $cheminWeb, $ext, $_POST['nom'] . " - Vue " . $i]);
                    }
                }
            }
        }

        $connexionPDO->commit();

        header('Location: stockVendeur.php');
        exit;

    } catch (Exception $e) {
        $connexionPDO->rollBack();
        $messageErreur = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier Produit - <?= htmlspecialchars($detailsProduit['nom_produit']) ?></title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="creerProduitStyle.css">
    <link rel="stylesheet" href="footer.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="conteneur">

        <?php if(isset($messageErreur)): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 15px; margin-bottom: 20px; border-radius: 10px;">
                <?= htmlspecialchars($messageErreur) ?>
            </div>
        <?php endif; ?>

        <h1 class="titre-page">Modifier : <?= htmlspecialchars($detailsProduit['nom_produit']) ?></h1>

        <form id="form-modifier-produit" method="POST" enctype="multipart/form-data" action="modifierProduit.php?id=<?= $identifiantProduit ?>">
            <input type="hidden" name="action" value="modifier">

            <div class="grille">
                <div class="carte">
                    <h3 style="color: var(--accent); margin-bottom: 15px;">Informations</h3>

                    <label>Nom du produit</label>
                    <input type="text" name="nom" value="<?= htmlspecialchars($detailsProduit['nom_produit']) ?>" required>

                    <label>Description</label>
                    <textarea name="description" rows="5" required><?= htmlspecialchars($detailsProduit['description_prod']) ?></textarea>

                    <label>Catégorie</label>
                    <select name="categorie" required>
                        <?php foreach ($listeCategories as $c): ?>
                            <option value="<?= $c['id_categ'] ?>" <?= ($c['id_categ'] == $detailsProduit['id_categ']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nom_categ']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label>Prix HT (€)</label>
                    <input type="number" step="0.01" name="prix_ht" value="<?= $detailsProduit['prix_ht'] ?>" required>
                </div>

                <div class="carte">
                    <h3 style="color: var(--accent); margin-bottom: 15px;">Logistique</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div><label>Pays</label><input type="text" name="pays" value="<?= htmlspecialchars($detailsProduit['pays_origine']) ?>" required></div>
                        <div><label>Département</label><input type="text" name="departement" value="<?= htmlspecialchars($detailsProduit['dep_origine']) ?>" required></div>
                    </div>
                    <label>Ville</label><input type="text" name="ville" value="<?= htmlspecialchars($detailsProduit['ville_origine']) ?>" required>
                    <label>Date fabrication</label><input type="date" name="date_prod" value="<?= $detailsProduit['date_prod'] ?>" required>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div><label>Stock</label><input type="number" name="stock" value="<?= $detailsProduit['quantite_dispo'] ?>" required></div>
                        <div><label>Seuil Alerte</label><input type="number" name="seuil" value="<?= $detailsProduit['seuil_alerte'] ?>" required></div>
                    </div>
                </div>
            </div>

            <div class="grille">
                <div class="carte">
                    <h3 style="color: var(--accent); margin-bottom: 15px;">Visuel</h3>

                    <div style="display:flex; gap:15px; align-items:center; margin-bottom:15px; background:#f9f9f9; padding:10px; border-radius:8px;">
                        <img src="<?= htmlspecialchars($detailsProduit['img_chemin'] . $detailsProduit['img_nom'] . $detailsProduit['extension']) ?>" style="width:60px; height:60px; object-fit:cover; border-radius:8px;">
                        <div>
                            <p style="font-size:0.9rem; font-weight:bold;">Image actuelle</p>
                            <p style="font-size:0.8rem; color:grey;"><?= htmlspecialchars($detailsProduit['img_nom']) ?></p>
                        </div>
                    </div>

                    <label>Changer l'image principale</label>
                    <input type="file" name="image_main" accept="image/*">

                    <hr style="margin: 20px 0; border-top: 1px solid #eee;">
                    <p style="font-size:0.9rem; margin-bottom:10px; color:var(--text-secondary);">
                        Ajouter ou remplacer des images secondaires :
                    </p>
                    <input type="file" name="image_sec_1" style="margin-bottom: 10px;">
                    <input type="file" name="image_sec_2" style="margin-bottom: 10px;">
                    <input type="file" name="image_sec_3">
                </div>

                <div class="carte">
                    <h3 style="color: var(--accent); margin-bottom: 15px;">Fiscalité</h3>
                    <label>Taux de TVA *</label>
                    <?php
                        $typeTvaActuel = $detailsProduit['type_tva'] ?? '';
                    ?>
                    <select name="tva_select" id="tva_select" onchange="updateTva()" required>
                        <option value="">-- Sélectionner --</option>
                        <option value="Normal|0.20" <?= ($typeTvaActuel == 'Normal') ? 'selected' : '' ?>>TVA Normale - 20%</option>
                        <option value="Réduite|0.055" <?= ($typeTvaActuel == 'Réduite') ? 'selected' : '' ?>>TVA Réduite - 5.5%</option>
                        <option value="Super réduite|0.021" <?= ($typeTvaActuel == 'Super réduite') ? 'selected' : '' ?>>TVA Super réduite - 2.1%</option>
                    </select>
                    <input type="hidden" name="type_tva" id="type_tva" value="<?= htmlspecialchars($detailsProduit['type_tva'] ?? '') ?>">
                    <input type="hidden" name="taux_tva" id="taux_tva" value="<?= $detailsProduit['taux_tva'] ?? '' ?>">
                </div>

                <script>
                function updateTva() {
                    var select = document.getElementById('tva_select');
                    var value = select.value;
                    if (value) {
                        var parts = value.split('|');
                        document.getElementById('type_tva').value = parts[0];
                        document.getElementById('taux_tva').value = parts[1];
                    }
                }
                </script>
            </div>

            <div class="actions">
                <button type="button" onclick="window.location.href='stockVendeur.php'" class="bouton bouton-annuler">Annuler</button>
                <button type="button" id="btn-modifier-produit" class="bouton bouton-sauvegarder">Mettre à jour</button>
            </div>
        </form>
    </div>

    <script src="scriptLibrary/confirmDialog.js"></script>
    <script>
        const dialogModifier = new ConfirmDialog({
            lang: 'french',
            message: 'Modifier ce produit ?',
            subtitle: 'Les modifications seront enregistrées.',
            okText: 'Modifier',
            cancelText: 'Annuler'
        });

        document.getElementById('btn-modifier-produit').addEventListener('click', function() {
            const form = document.getElementById('form-modifier-produit');
            if (form.checkValidity()) {
                dialogModifier.show().then(result => {
                    if (result.ok) {
                        form.submit();
                    }
                });
            } else {
                form.reportValidity();
            }
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
