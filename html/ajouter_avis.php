<?php
session_start();
require_once("loginBdd.php");
require_once("creerNotification.php");

if (!isset($_SESSION['id_compte']) && !isset($_SESSION['id_client'])) {
    header("Location: connexion.php");
    exit();
}


$id_produit = isset($_GET['id_produit']) ? $_GET['id_produit'] : 0;
$message = "";

$id_client = isset($_SESSION['id_client']) ? $_SESSION['id_client'] : (isset($_SESSION['id_compte']) ? $_SESSION['id_compte'] : null);

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


    $stmt = $pdo->prepare("SELECT nom_produit FROM sae._produit WHERE id_produit = :id");
    $stmt->execute([':id' => $id_produit]);
    $produit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produit) {
        die("Produit introuvable.");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $note = intval($_POST['note']);
        $titre = htmlspecialchars($_POST['titre']);
        $commentaire = htmlspecialchars($_POST['commentaire']);
        $id_image = null; 

        if (isset($_FILES['image_avis']) && $_FILES['image_avis']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['image_avis']['name'];
            $filetype = $_FILES['image_avis']['type'];
            $filesize = $_FILES['image_avis']['size'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed) && $filesize < 5000000) { // Max 5Mo
                // Chemin de stockage
                $uploadDir = 'media/avis/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                
                // Nouveau nom unique
                $newFileName = 'avis_' . uniqid() . '_' . $id_produit;
                $destPath = $uploadDir . $newFileName . '.' . $ext;

                if (move_uploaded_file($_FILES['image_avis']['tmp_name'], $destPath)) {
                    // Insertion dans la table _image
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

       // 2. Insertion de l'avis
        if (empty($message)) {
            // Helper local: garantir un id_image non-null en récupérant/créant l'image "default_image"
            $ensureDefaultImage = function(PDO $pdo) {
                $stmt = $pdo->prepare("SELECT id_image FROM sae._image WHERE nom_fichier = :nom LIMIT 1");
                $stmt->execute([':nom' => 'default_image']);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['id_image'])) return $row['id_image'];
                $stmtIns = $pdo->prepare("INSERT INTO sae._image (nom_fichier, chemin, extension, alt) VALUES (:nom, :chemin, :ext, :alt) RETURNING id_image");
                $stmtIns->execute([':nom'=>'default_image', ':chemin'=>'media/universel/', ':ext'=>'.png', ':alt'=>'Image par defaut']);
                $r = $stmtIns->fetch(PDO::FETCH_ASSOC);
                if ($r && !empty($r['id_image'])) return $r['id_image'];
                $any = $pdo->query("SELECT id_image FROM sae._image LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                return ($any && !empty($any['id_image'])) ? $any['id_image'] : null;
            };

            if ($id_image === null) {
                $id_image = $ensureDefaultImage($pdo);
            }

            // Si on n'a toujours pas d'id_image, arrêter et définir message
            if ($id_image === null) {
                $message = "Impossible de traiter l'avis pour le moment (image par défaut introuvable).";
            }

            // Vérifier si le client a déjà laissé un avis pour ce produit
            if (empty($message)) {
                $stmtCheck = $pdo->prepare("SELECT id_avis FROM sae._avis WHERE id_produit = :id_prod AND id_client = :id_cli LIMIT 1");
                $stmtCheck->execute([':id_prod' => $id_produit, ':id_cli' => $id_client]);
                if ($stmtCheck->fetch(PDO::FETCH_ASSOC)) {
                    $message = "Vous avez déjà laissé un avis pour ce produit.";
                }
            }

            // Si erreur/doublon, sauvegarder message en session et rediriger vers la page produit
            if (!empty($message)) {
                $_SESSION['avis_message'] = $message;
                header("Location: descriptionProduitClient.php?id_produit=" . urlencode($id_produit) . "#avis_client");
                exit();
            }
            if (empty($message)) {
         
                $sqlAvis = "INSERT INTO sae._avis (
                                id_produit, id_client, note, titre_avis, commentaire, 
                                date_avis, id_image, votes_utiles, votes_inutiles, 
                                avis_verif, signale, epingle, raison_signalement
                            ) 
                            VALUES (
                                :id_prod, :id_cli, :note, :titre, :comm, 
                                NOW(), :id_img, 0, 0, 
                                false, false, false, :raison
                            )";

                $stmtAvis = $pdo->prepare($sqlAvis);
                $stmtAvis->execute([
                    ':id_prod' => $id_produit,
                    ':id_cli' => $id_client,
                    ':note' => $note,
                    ':titre' => $titre,
                    ':comm' => $commentaire,
                    ':id_img' => $id_image,
                    ':raison' => ''
                ]);

                // Message de succès
                $_SESSION['avis_message'] = "Votre avis a été ajouté avec succès !";
                // Créer une notification pour le vendeur nnnn
                $reqVendeur = $pdo->prepare("SELECT id_vendeur FROM sae._produit WHERE id_produit = ?");
                $reqVendeur->execute([$id_produit]);
                $leVendeur = $reqVendeur->fetch(PDO::FETCH_ASSOC);

                // 2. Si on a trouvé un vendeur, on insère la notif direct
                if ($leVendeur) {
                    $id_destinataire = $leVendeur['id_vendeur']; // id_vendeur = id_compte

                    $reqNotif = $pdo->prepare("
                        INSERT INTO sae._notification 
                        (type_notification, titre, message, date_creation, lue, id_compte)
                        VALUES 
                        ('Information', 'Nouvel avis', 'Un avis a été posté sur votre produit.', CURRENT_DATE, false, ?)
                    ");
                    
                    $reqNotif->execute([$id_destinataire]);
                } //lll
                
                // Redirection vers le produit
                header("Location: descriptionProduitClient.php?id_produit=" . $id_produit . "#avis_client");
                exit();
            }
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
    <title>Laisser un avis - <?php echo htmlspecialchars($produit['nom_produit']); ?></title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="footer.css">
    <link rel="stylesheet" href="ajouterAvisStyle.css">
</head>
<body>
    <?php include "header.php"; ?>

    <div class="avis-container">
        <h1>Avis sur : <?php echo htmlspecialchars($produit['nom_produit']); ?></h1>
        
        <?php if($message): ?>
            <p class="error"><?php echo $message; ?></p>
        <?php endif; ?>

        <form action="" method="post" enctype="multipart/form-data">
            
            <div class="form-group">
                <label>Votre note :</label>
                <div class="rating">
                    <input type="radio" name="note" id="star5" value="5" required><label for="star5" title="Excellent"></label>
                    <input type="radio" name="note" id="star4" value="4"><label for="star4" title="Très bien"></label>
                    <input type="radio" name="note" id="star3" value="3"><label for="star3" title="Moyen"></label>
                    <input type="radio" name="note" id="star2" value="2"><label for="star2" title="Pas terrible"></label>
                    <input type="radio" name="note" id="star1" value="1"><label for="star1" title="Mauvais"></label>
                </div>
            </div>

            <div class="form-group">
                <label for="titre">Titre de votre avis :</label>
                <input type="text" name="titre" id="titre" required placeholder="Ex: Super produit !">
            </div>

            <div class="form-group">
                <label for="commentaire">Votre commentaire :</label>
                <textarea name="commentaire" id="commentaire" required placeholder="Détaillez votre expérience..."></textarea>
            </div>

            <div class="form-group">
                <label for="image_avis">Ajouter une photo (optionnel) :</label>
                <input type="file" name="image_avis" id="image_avis" accept="image/*">
            </div>

            <button type="submit" class="btn-submit">Publier mon avis</button>
            <a href="descriptionProduitClient.php?id_produit=<?php echo $id_produit; ?>" class="back-link">Annuler</a>
        </form>
    </div>

    <?php if (!empty($message)): ?>
    <script>
        window.addEventListener('DOMContentLoaded', function(){
            
            alert(<?php echo json_encode($message); ?>);
        
            var el = document.querySelector('.error');
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    </script>
    <?php endif; ?>

    <?php include "footer.php"; ?>
</body>
</html>