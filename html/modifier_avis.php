<?php
session_start();
require_once("loginBdd.php");

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id_compte']) && !isset($_SESSION['id_client'])) {
    header("Location: connexion.php");
    exit();
}

$id_client = isset($_SESSION['id_client']) ? $_SESSION['id_client'] : $_SESSION['id_compte'];
$id_avis = isset($_GET['id_avis']) ? intval($_GET['id_avis']) : 0;
$id_produit = isset($_GET['id_produit']) ? intval($_GET['id_produit']) : 0;
$message = "";

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer l'avis existant et vérifier qu'il appartient bien à l'utilisateur
    $stmt = $pdo->prepare("
        SELECT A.*, P.nom_produit, I.nom_fichier as image_nom, I.chemin as image_chemin, I.extension as image_ext
        FROM sae._avis A
        JOIN sae._produit P ON A.id_produit = P.id_produit
        LEFT JOIN sae._image I ON A.id_image = I.id_image
        WHERE A.id_avis = :id_avis AND A.id_client = :id_client
    ");
    $stmt->execute([':id_avis' => $id_avis, ':id_client' => $id_client]);
    $avis = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$avis) {
        $_SESSION['avis_message'] = "Vous n'êtes pas autorisé à modifier cet avis.";
        header("Location: descriptionProduitClient.php?id_produit=" . $id_produit);
        exit();
    }

    // Traiter la modification
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $note = intval($_POST['note']);
        $titre = htmlspecialchars($_POST['titre']);
        $commentaire = htmlspecialchars($_POST['commentaire']);
        $id_image = $avis['id_image']; // Garder l'ancienne image par défaut

        // Gestion de l'upload d'une nouvelle image
        if (isset($_FILES['image_avis']) && $_FILES['image_avis']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['image_avis']['name'];
            $filesize = $_FILES['image_avis']['size'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed) && $filesize < 5000000) {
                $uploadDir = 'media/avis/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                $newFileName = 'avis_' . uniqid() . '_' . $id_produit;
                $destPath = $uploadDir . $newFileName . '.' . $ext;

                if (move_uploaded_file($_FILES['image_avis']['tmp_name'], $destPath)) {
                    // Supprimer l'ancienne image si ce n'est pas default_image
                    if ($avis['image_nom'] !== 'default_image' && !empty($avis['image_chemin'])) {
                        $oldImagePath = $avis['image_chemin'] . $avis['image_nom'] . $avis['image_ext'];
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }

                    // Insérer la nouvelle image
                    $sqlImg = "INSERT INTO sae._image (nom_fichier, chemin, extension, alt) 
                               VALUES (:nom, :chemin, :ext, :alt) RETURNING id_image";
                    $stmtImg = $pdo->prepare($sqlImg);
                    $stmtImg->execute([
                        ':nom' => $newFileName,
                        ':chemin' => $uploadDir,
                        ':ext' => '.' . $ext,
                        ':alt' => "Image avis client"
                    ]);
                    $rowImg = $stmtImg->fetch(PDO::FETCH_ASSOC);
                    $id_image = $rowImg['id_image'];
                }
            } else {
                $message = "Format d'image invalide ou fichier trop lourd.";
            }
        }

        // Mettre à jour l'avis
        if (empty($message)) {
            $sqlUpdate = "UPDATE sae._avis 
                          SET note = :note, titre_avis = :titre, commentaire = :comm, id_image = :id_img
                          WHERE id_avis = :id_avis AND id_client = :id_client";
            
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([
                ':note' => $note,
                ':titre' => $titre,
                ':comm' => $commentaire,
                ':id_img' => $id_image,
                ':id_avis' => $id_avis,
                ':id_client' => $id_client
            ]);

            $_SESSION['avis_message'] = "Votre avis a été modifié avec succès !";
            header("Location: descriptionProduitClient.php?id_produit=" . $id_produit . "#avis_client");
            exit();
        }
    }

} catch (PDOException $e) {
    die("Erreur BDD : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier mon avis - <?php echo htmlspecialchars($avis['nom_produit']); ?></title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="footer.css">
    <link rel="stylesheet" href="ajouterAvisStyle.css">
</head>
<body>
    <?php include "header.php"; ?>

    <div class="avis-container">
        <h1>Modifier mon avis sur : <?php echo htmlspecialchars($avis['nom_produit']); ?></h1>
        
        <?php if($message): ?>
            <p class="error"><?php echo $message; ?></p>
        <?php endif; ?>

        <form action="" method="post" enctype="multipart/form-data">
            
            <div class="form-group">
                <label>Votre note :</label>
                <div class="rating">
                    <input type="radio" name="note" id="star5" value="5" <?= $avis['note'] == 5 ? 'checked' : '' ?> required><label for="star5" title="Excellent"></label>
                    <input type="radio" name="note" id="star4" value="4" <?= $avis['note'] == 4 ? 'checked' : '' ?>><label for="star4" title="Très bien"></label>
                    <input type="radio" name="note" id="star3" value="3" <?= $avis['note'] == 3 ? 'checked' : '' ?>><label for="star3" title="Moyen"></label>
                    <input type="radio" name="note" id="star2" value="2" <?= $avis['note'] == 2 ? 'checked' : '' ?>><label for="star2" title="Pas terrible"></label>
                    <input type="radio" name="note" id="star1" value="1" <?= $avis['note'] == 1 ? 'checked' : '' ?>><label for="star1" title="Mauvais"></label>
                </div>
            </div>

            <div class="form-group">
                <label for="titre">Titre de votre avis :</label>
                <input type="text" name="titre" id="titre" required placeholder="Ex: Super produit !" value="<?= htmlspecialchars(html_entity_decode($avis['titre_avis'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?>">
            </div>

            <div class="form-group">
                <label for="commentaire">Votre commentaire :</label>
                <textarea name="commentaire" id="commentaire" required placeholder="Détaillez votre expérience..."><?= htmlspecialchars(html_entity_decode($avis['commentaire'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?></textarea>
            </div>

            <?php if ($avis['image_nom'] !== 'default_image' && !empty($avis['image_chemin'])): ?>
                <div class="form-group">
                    <label>Image actuelle :</label>
                    <img src="<?= htmlspecialchars($avis['image_chemin'] . $avis['image_nom'] . $avis['image_ext']) ?>" alt="Image actuelle" style="max-width: 200px; max-height: 200px; border-radius: 8px; margin-top: 10px;">
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="image_avis">Changer la photo (optionnel) :</label>
                <input type="file" name="image_avis" id="image_avis" accept="image/*">
            </div>

            <button type="submit" class="btn-submit">Enregistrer les modifications</button>
            <a href="descriptionProduitClient.php?id_produit=<?php echo $id_produit; ?>" class="back-link">Annuler</a>
        </form>
    </div>

    <?php include "footer.php"; ?>
</body>
</html>
