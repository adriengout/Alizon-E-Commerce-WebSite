<?php
session_start();
require_once("loginBdd.php");
require_once("emailHelper.php");

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['id_compte']) && !isset($_SESSION['id_client'])) {
    header("Location: connexion.php");
    exit();
}

$id_client = isset($_SESSION['id_client']) ? $_SESSION['id_client'] : $_SESSION['id_compte'];

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_avis']) && isset($_POST['id_produit'])) {
        $id_avis = intval($_POST['id_avis']);
        $id_produit = intval($_POST['id_produit']);

        // Vérifier que l'avis appartient bien à l'utilisateur
        $stmtCheck = $pdo->prepare("
            SELECT A.id_avis, I.nom_fichier, I.chemin, I.extension, P.nom_produit, C.prenom, CPT.mail
            FROM sae._avis A
            LEFT JOIN sae._image I ON A.id_image = I.id_image
            LEFT JOIN sae._produit P ON A.id_produit = P.id_produit
            LEFT JOIN sae._client C ON A.id_client = C.id_client
            LEFT JOIN sae._compte CPT ON C.id_client = CPT.id_compte
            WHERE A.id_avis = :id_avis AND A.id_client = :id_client
        ");
        $stmtCheck->execute([':id_avis' => $id_avis, ':id_client' => $id_client]);
        $avis = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$avis) {
            $_SESSION['avis_message'] = "Vous n'êtes pas autorisé à supprimer cet avis.";
            header("Location: descriptionProduitClient.php?id_produit=" . $id_produit);
            exit();
        }

        // Supprimer l'image physique si ce n'est pas default_image
        if ($avis['nom_fichier'] !== 'default_image' && !empty($avis['chemin'])) {
            $imagePath = $avis['chemin'] . $avis['nom_fichier'] . $avis['extension'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }

        // Supprimer d'abord les votes associés à cet avis (si la table existe)
        try {
            $stmtDeleteVotes = $pdo->prepare("DELETE FROM sae._vote_avis WHERE id_avis = :id_avis");
            $stmtDeleteVotes->execute([':id_avis' => $id_avis]);
        } catch (PDOException $e) {
            // La table n'existe peut-être pas encore, on continue
        }

        // Supprimer les réponses du vendeur associées à cet avis (si la table existe)
        try {
            $stmtDeleteReponse = $pdo->prepare("DELETE FROM sae._reponse WHERE id_avis = :id_avis");
            $stmtDeleteReponse->execute([':id_avis' => $id_avis]);
        } catch (PDOException $e) {
            // La table n'existe peut-être pas encore, on continue
        }

        // Enfin supprimer l'avis de la base de données
        $stmtDelete = $pdo->prepare("DELETE FROM sae._avis WHERE id_avis = :id_avis AND id_client = :id_client");
        $stmtDelete->execute([':id_avis' => $id_avis, ':id_client' => $id_client]);

        // Envoyer une notification par email au client (en mode non bloquant)
        //if (!empty($avis['mail'])) {
            //ry {
                //$emailHTML = genererEmailSuppressionAvis($avis['prenom'], $avis['nom_produit']);
                //envoyerEmail($avis['mail'], "Suppression de votre avis sur Alizon", $emailHTML);
            //} catch (Exception $emailException) {
                // L'échec de l'envoi d'email ne doit pas bloquer la suppression
                //error_log("Erreur envoi email suppression avis : " . $emailException->getMessage());
            //}
        //}

        $_SESSION['avis_message'] = "Votre avis a été supprimé avec succès.";
        header("Location: descriptionProduitClient.php?id_produit=" . $id_produit . "#avis_client");
        exit();
    }

} catch (PDOException $e) {
    $_SESSION['avis_message'] = "Erreur lors de la suppression : " . $e->getMessage();
    if (isset($id_produit)) {
        header("Location: descriptionProduitClient.php?id_produit=" . $id_produit);
    } else {
        header("Location: catalogue.php");
    }
    exit();
}

// Si on arrive ici sans avoir fait de redirection (requête non POST ou paramètres manquants)
header("Location: catalogue.php");
exit();
?>
