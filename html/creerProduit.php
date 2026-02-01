<?php
session_start();

require_once("loginBdd.php");

try {
    $connexionPDO = new PDO("pgsql:host=$host;dbname=$dbname;options='--client_encoding=UTF8'", $username, $password);
    $connexionPDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connexionPDO->exec("SET search_path TO sae, public");
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

if (!isset($_SESSION['id_compte'])) {
    header('Location: connexion.php');
    exit();
}

// Vérifier que l'utilisateur est bien un vendeur
if (!isset($_SESSION['is_vendeur']) || $_SESSION['is_vendeur'] !== true) {
    header('Location: index.php');
    exit();
}

$identifiantVendeur = $_SESSION['id_compte'];

// ==================================================================
// TRAITEMENT DU FORMULAIRE
// ==================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $connexionPDO->beginTransaction();

    try {
        $requete = $connexionPDO->query("SELECT nextval('sae._produit_id_produit_seq')");
        $futurIdentifiant = $requete->fetchColumn();

        if (!$futurIdentifiant) {
            throw new Exception("Impossible de récupérer un nouvel ID produit.");
        }

        $cheminWeb = "media/produits/"; 
        $dossierPhysique = __DIR__ . "/media/produits/";

        if (!is_dir($dossierPhysique)) {
            if (!mkdir($dossierPhysique, 0777, true)) {
                throw new Exception("Impossible de créer le dossier de destination.");
            }
        }

        $identifiantImagePrincipale = null; 

        if (isset($_FILES['image_main']) && $_FILES['image_main']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['image_main']['tmp_name'];
            $nomOriginal = $_FILES['image_main']['name'];
            
            // Extraire l'extension avec le point
            $ext = strtolower(strrchr($nomOriginal, '.')); // .jpg, .png, etc.
            
            // Vérifier que c'est une image valide
            $extensionsValides = ['.jpg', '.jpeg', '.png', '.gif', '.webp'];
            if (!in_array($ext, $extensionsValides)) {
                throw new Exception("Format d'image non autorisé. Formats acceptés : JPG, PNG, GIF, WEBP");
            }
            
            // Nom du fichier sans extension
            $nomFichier = "produit" . $futurIdentifiant . "_1";
            
            // Chemin complet avec extension
            $cheminComplet = $dossierPhysique . $nomFichier . $ext;
            
            if (move_uploaded_file($tmp, $cheminComplet)) {
                $requeteImage = $connexionPDO->prepare("
                    INSERT INTO _image (nom_fichier, chemin, extension, alt) 
                    VALUES (:nom, :chemin, :ext, :alt)
                    RETURNING id_image
                ");
                $requeteImage->execute([
                    ":nom" => $nomFichier,
                    ":chemin" => $cheminWeb,
                    ":ext" => $ext,
                    ":alt" => $_POST['nom']
                ]);
                $identifiantImagePrincipale = $requeteImage->fetchColumn();
                
                if (!$identifiantImagePrincipale) {
                    throw new Exception("Erreur lors de l'enregistrement de l'image en base de données.");
                }
            } else {
                throw new Exception("Erreur lors de l'upload de l'image principale. Vérifiez les permissions du dossier.");
            }
        } else {
            // Message d'erreur plus détaillé
            $erreurUpload = isset($_FILES['image_main']['error']) ? $_FILES['image_main']['error'] : 'Fichier non reçu';
            $messagesErreur = [
                UPLOAD_ERR_INI_SIZE => "Le fichier dépasse la limite autorisée par le serveur",
                UPLOAD_ERR_FORM_SIZE => "Le fichier dépasse la limite du formulaire",
                UPLOAD_ERR_PARTIAL => "Le fichier n'a été que partiellement uploadé",
                UPLOAD_ERR_NO_FILE => "Aucun fichier n'a été uploadé",
                UPLOAD_ERR_NO_TMP_DIR => "Dossier temporaire manquant",
                UPLOAD_ERR_CANT_WRITE => "Échec de l'écriture sur le disque",
                UPLOAD_ERR_EXTENSION => "Extension PHP a arrêté l'upload"
            ];
            $messageDetaile = isset($messagesErreur[$erreurUpload]) ? $messagesErreur[$erreurUpload] : "Erreur inconnue ($erreurUpload)";
            throw new Exception("L'image principale est obligatoire. Détail : " . $messageDetaile);
        }

        $requeteProduit = $connexionPDO->prepare("
            INSERT INTO _produit 
            (id_produit, nom_produit, description_prod, prix_ht, dep_origine, ville_origine, pays_origine, date_prod, nb_ventes, id_image, id_categ, id_vendeur)
            VALUES 
            (:id, :nom, :desc, :prix, :dep, :ville, :pays, :datep, 0, :idimg, :categ, :vend)
        ");
        
        $requeteProduit->execute([
            ":id" => $futurIdentifiant, 
            ":nom" => $_POST["nom"],
            ":desc" => $_POST["description"],
            ":prix" => $_POST["prix_ht"],
            ":dep" => $_POST["departement"],
            ":ville" => $_POST["ville"],
            ":pays" => $_POST["pays"],
            ":datep" => $_POST["date_prod"],
            ":idimg" => $identifiantImagePrincipale,
            ":categ" => $_POST["categorie"],
            ":vend" => $identifiantVendeur
        ]);

        // Images secondaires (optionnel)
        for ($i = 2; $i <= 4; $i++) {
            $inputKey = "image_sec_" . ($i - 1);
            
            if (isset($_FILES[$inputKey]) && $_FILES[$inputKey]['error'] === UPLOAD_ERR_OK) {
                $tmp = $_FILES[$inputKey]['tmp_name'];
                $nomOriginal = $_FILES[$inputKey]['name'];
                $ext = strtolower(strrchr($nomOriginal, '.'));
                
                // Vérifier l'extension
                $extensionsValides = ['.jpg', '.jpeg', '.png', '.gif', '.webp'];
                if (!in_array($ext, $extensionsValides)) {
                    continue; // Ignorer les fichiers invalides
                }
                
                $nomFichier = "produit" . $futurIdentifiant . "_" . $i;
                $cheminComplet = $dossierPhysique . $nomFichier . $ext;
                
                if (move_uploaded_file($tmp, $cheminComplet)) {
                    $sqlImgSec = $connexionPDO->prepare("
                        INSERT INTO _image (nom_fichier, chemin, extension, alt) 
                        VALUES (:nom, :chemin, :ext, :alt)
                    ");
                    $sqlImgSec->execute([
                        ":nom" => $nomFichier,
                        ":chemin" => $cheminWeb,
                        ":ext" => $ext,
                        ":alt" => $_POST['nom'] . " - Vue " . $i
                    ]);
                }
            }
        }

        $requeteStock = $connexionPDO->prepare("
            INSERT INTO _stock (id_vendeur, id_produit, quantite_dispo, seuil_alerte, derniere_maj, alerte) 
            VALUES (:vend, :prod, :qte, :seuil, CURRENT_DATE, false)
        ");
        $requeteStock->execute([
            ":vend" => $identifiantVendeur,
            ":prod" => $futurIdentifiant,
            ":qte" => $_POST["stock"],
            ":seuil" => $_POST["seuil"]
        ]);

        $requeteTva = $connexionPDO->prepare("
            INSERT INTO _tva (id_produit, type_tva, taux_tva) 
            VALUES (:prod, :type, :taux)
        ");
        $requeteTva->execute([
            ":prod" => $futurIdentifiant,
            ":type" => $_POST["type_tva"],
            ":taux" => $_POST["taux_tva"]
        ]);

        $connexionPDO->commit();

        header("Location: stockVendeur.php");
        exit;

    } catch (Exception $e) {
        $connexionPDO->rollBack();
        $messageErreur = $e->getMessage();
    }
}

$requeteCategorie = $connexionPDO->query("SELECT id_categ, nom_categ FROM _categorieproduit ORDER BY nom_categ ASC");
$listeCategories = $requeteCategorie->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Création d'un produit</title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <link rel="stylesheet" href="creerProduitStyle.css">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="footer.css">
</head>
<body>
    <?php include "header.php"; ?>

    <div class="conteneur">
        
        <?php if(isset($messageErreur)): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; font-weight: bold;">
                ⚠️ Erreur : <?= htmlspecialchars($messageErreur) ?>
            </div>
        <?php endif; ?>

        <h1 class="titre-page">Création d'un nouveau produit</h1>
        
        <form action="creerProduit.php" method="POST" enctype="multipart/form-data" >
            
            <div class="grille">
                <div class="carte">
                    <h3 style="color: var(--accent); margin-bottom: 15px;">Informations principales</h3>
                    <label>Nom du produit *</label>
                    <input type="text" name="nom" value="<?= isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : '' ?>" required>
                    
                    <label>Description *</label>
                    <textarea name="description" rows="5" required><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                    
                    <label>Catégorie *</label>
                    <select name="categorie" required>
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($listeCategories as $c): ?>
                            <option value="<?= $c['id_categ'] ?>" <?= (isset($_POST['categorie']) && $_POST['categorie'] == $c['id_categ']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nom_categ']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label>Prix HT (€) *</label>
                    <input type="number" step="0.01" name="prix_ht" value="<?= isset($_POST['prix_ht']) ? htmlspecialchars($_POST['prix_ht']) : '' ?>" required>
                </div>

                <div class="carte">
                    <h3 style="color: var(--accent); margin-bottom: 15px;">Détails & Stock</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label>Pays d'origine *</label>
                            <input type="text" name="pays" value="<?= isset($_POST['pays']) ? htmlspecialchars($_POST['pays']) : 'France' ?>" required>
                        </div>
                        <div>
                            <label>Département *</label>
                            <input type="text" name="departement" value="<?= isset($_POST['departement']) ? htmlspecialchars($_POST['departement']) : '' ?>" required>
                        </div>
                    </div>
                    <label>Ville d'origine *</label>
                    <input type="text" name="ville" value="<?= isset($_POST['ville']) ? htmlspecialchars($_POST['ville']) : '' ?>" required>
                    
                    <label>Date de fabrication *</label>
                    <input type="date" name="date_prod" value="<?= isset($_POST['date_prod']) ? htmlspecialchars($_POST['date_prod']) : '' ?>" required>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label>Quantité stock *</label>
                            <input type="number" name="stock" value="<?= isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : '' ?>" required>
                        </div>
                        <div>
                            <label>Seuil d'alerte *</label>
                            <input type="number" name="seuil" value="<?= isset($_POST['seuil']) ? htmlspecialchars($_POST['seuil']) : '5' ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grille">
                <div class="carte">
                    <h3 style="color: var(--accent); margin-bottom: 15px;">Visuel produit</h3>
                    
                    <label>Image Principale * (JPG, PNG, GIF, WEBP - Max 10Mo)</label>
                    <input type="file" name="image_main" accept="image/jpeg,image/png,image/gif,image/webp" required>
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Formats acceptés : .jpg, .jpeg, .png, .gif, .webp
                    </small>
                    
                    <hr style="margin: 20px 0; border: 0; border-top: 1px solid var(--border-color);">
                    
                    <label>Images Secondaires (Optionnel)</label>
                    <input type="file" name="image_sec_1" accept="image/jpeg,image/png,image/gif,image/webp" style="margin-bottom: 10px;">
                    <input type="file" name="image_sec_2" accept="image/jpeg,image/png,image/gif,image/webp" style="margin-bottom: 10px;">
                    <input type="file" name="image_sec_3" accept="image/jpeg,image/png,image/gif,image/webp">
                </div>

                <div class="carte">
                    <h3 style="color: var(--accent); margin-bottom: 15px;">Fiscalité</h3>
                    <label>Taux de TVA *</label>
                    <select name="tva_select" id="tva_select" onchange="updateTva()" required>
                        <option value="">-- Sélectionner --</option>
                        <option value="Normal|0.20" <?= (isset($_POST['type_tva']) && $_POST['type_tva'] == 'Normal') ? 'selected' : '' ?>>TVA Normale - 20%</option>
                        <option value="Réduite|0.055" <?= (isset($_POST['type_tva']) && $_POST['type_tva'] == 'Réduite') ? 'selected' : '' ?>>TVA Réduite - 5.5%</option>
                        <option value="Super réduite|0.021" <?= (isset($_POST['type_tva']) && $_POST['type_tva'] == 'Super réduite') ? 'selected' : '' ?>>TVA Super réduite - 2.1%</option>
                    </select>
                    <input type="hidden" name="type_tva" id="type_tva" value="<?= isset($_POST['type_tva']) ? htmlspecialchars($_POST['type_tva']) : '' ?>">
                    <input type="hidden" name="taux_tva" id="taux_tva" value="<?= isset($_POST['taux_tva']) ? htmlspecialchars($_POST['taux_tva']) : '' ?>">
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
                <button type="submit" class="bouton bouton-sauvegarder">Enregistrer le produit</button>
            </div>
        </form>
    </div>

    
    <?php include "footer.php"; ?>
</body>
</html>
