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

// Vérification de la session
if (!isset($_SESSION['id_compte'])) {
    header('Location: connexion.php');
    exit();
}

// Vérification de l'ID du produit
if (!isset($_POST['id_produit'])) {
    header('Location: stockVendeur.php');
    exit();
}

$identifiantVendeur = $_SESSION['id_compte'];
$identifiantProduit = intval($_POST['id_produit']);

try {
    $connexionPDO->beginTransaction();

    // Vérifier que le produit appartient bien au vendeur
    $verificationVendeur = $connexionPDO->prepare("
        SELECT id_produit, id_image FROM _produit 
        WHERE id_produit = ? AND id_vendeur = ?
    ");
    $verificationVendeur->execute([$identifiantProduit, $identifiantVendeur]);
    $produit = $verificationVendeur->fetch(PDO::FETCH_ASSOC);
    
    if (!$produit) {
        throw new Exception("Produit introuvable ou accès refusé.");
    }

    $idImagePrincipale = $produit['id_image'];

    // ===== SUPPRESSION DES DÉPENDANCES =====

    // 1. Supprimer les réponses liées aux avis du produit
    $connexionPDO->prepare("
        DELETE FROM _reponse 
        WHERE id_avis IN (
            SELECT id_avis FROM _avis WHERE id_produit = ?
        )
    ")->execute([$identifiantProduit]);

    // 2. Supprimer les avis du produit
    $connexionPDO->prepare("
        DELETE FROM _avis 
        WHERE id_produit = ?
    ")->execute([$identifiantProduit]);

    // 3. Supprimer les remises du produit
    $connexionPDO->prepare("
        DELETE FROM _remise 
        WHERE id_produit = ?
    ")->execute([$identifiantProduit]);

    // 4. Supprimer les promotions du produit
    $connexionPDO->prepare("
        DELETE FROM _promotion 
        WHERE id_produit = ?
    ")->execute([$identifiantProduit]);

    // 5. Supprimer les lignes de commande (sauf si commande validée/livrée)
    // On supprime uniquement les lignes de commandes "En préparation"
    $connexionPDO->prepare("
        DELETE FROM _ligneCommande 
        WHERE id_produit = ? 
    ")->execute([$identifiantProduit]);

    // 6. Supprimer le stock
    $connexionPDO->prepare("
        DELETE FROM _stock 
        WHERE id_produit = ? AND id_vendeur = ?
    ")->execute([$identifiantProduit, $identifiantVendeur]);

    // 7. Supprimer la TVA
    $connexionPDO->prepare("
        DELETE FROM _tva 
        WHERE id_produit = ?
    ")->execute([$identifiantProduit]);


    // 9. Supprimer le produit
    $connexionPDO->prepare("
        DELETE FROM _produit 
        WHERE id_produit = ? AND id_vendeur = ?
    ")->execute([$identifiantProduit, $identifiantVendeur]);

    // 10. Supprimer l'image principale (après avoir supprimé le produit)
    $connexionPDO->prepare("
        DELETE FROM _image 
        WHERE id_image = ?
    ")->execute([$idImagePrincipale]);

    // 11. Supprimer les fichiers physiques des images
    $cheminImages = __DIR__ . "/media/produits/";
    $motif = "produit" . $identifiantProduit . "_*";
    $fichiers = glob($cheminImages . $motif);
    
    if ($fichiers) {
        foreach ($fichiers as $fichier) {
            if (file_exists($fichier) && is_file($fichier)) {
                unlink($fichier);
            }
        }
    }

    $connexionPDO->commit();

    // Message de succès et redirection
    $_SESSION['message_succes'] = "Le produit a été supprimé avec succès.";
    header('Location: stockVendeur.php');
    exit();

} catch (Exception $e) {
    $connexionPDO->rollBack();
    // AFFICHE L'ERREUR AU LIEU DE REDIRIGER
    die("ERREUR SQL : " . $e->getMessage()); 

}
