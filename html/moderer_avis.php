<?php
session_start();
require_once("loginBdd.php");
require_once("emailHelper.php");

// Vérifier que l'utilisateur est connecté et qu'il s'agit d'un vendeur/modérateur
if (!isset($_SESSION['id_compte'])) {
    header("Location: connexion.php");
    exit();
}

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_avis'])) {
        $id_avis = intval($_POST['id_avis']);
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        // Récupérer les informations de l'avis
        $stmtAvis = $pdo->prepare("
            SELECT 
                A.id_avis, 
                A.id_client, 
                A.id_produit,
                A.raison_signalement,
                P.nom_produit,
                P.id_vendeur,
                C.prenom,
                CPT.mail,
                I.nom_fichier,
                I.chemin,
                I.extension
            FROM sae._avis A
            JOIN sae._produit P ON A.id_produit = P.id_produit
            JOIN sae._client C ON A.id_client = C.id_client
            JOIN sae._compte CPT ON C.id_client = CPT.id_compte
            LEFT JOIN sae._image I ON A.id_image = I.id_image
            WHERE A.id_avis = :id_avis
        ");
        $stmtAvis->execute([':id_avis' => $id_avis]);
        $avis = $stmtAvis->fetch(PDO::FETCH_ASSOC);

        if (!$avis) {
            $_SESSION['avis_message'] = "Avis introuvable.";
            header("Location: gestionAvisVendeur.php");
            exit();
        }

        // Vérifier que le vendeur connecté est bien le vendeur du produit
        if ($avis['id_vendeur'] != $_SESSION['id_compte']) {
            $_SESSION['avis_message'] = "Vous n'êtes pas autorisé à gérer cet avis.";
            header("Location: gestionAvisVendeur.php");
            exit();
        }

        if ($action === 'supprimer') {
            // Supprimer l'image physique si elle existe et n'est pas l'image par défaut
            if ($avis['nom_fichier'] !== 'default_image' && !empty($avis['chemin'])) {
                $imagePath = $avis['chemin'] . $avis['nom_fichier'] . $avis['extension'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            // Supprimer d'abord les réponses du vendeur
            $stmtDeleteReponse = $pdo->prepare("DELETE FROM sae._reponse WHERE id_avis = :id_avis");
            $stmtDeleteReponse->execute([':id_avis' => $id_avis]);

            // Supprimer l'avis (les votes sont dans la table _avis, pas dans une table séparée)
            $stmtDelete = $pdo->prepare("DELETE FROM sae._avis WHERE id_avis = :id_avis");
            $stmtDelete->execute([':id_avis' => $id_avis]);

            // Envoyer une notification par email au client avec la raison (en mode non bloquant)
            //if (!empty($avis['mail'])) {
                //try {
                    //$raison = $avis['raison_signalement'] ?: "Votre avis ne respectait pas nos conditions générales d'utilisation.";
                    //$emailHTML = genererEmailSuppressionAvis($avis['prenom'], $avis['nom_produit'], $raison);
                    //envoyerEmail($avis['mail'], "Suppression de votre avis sur Alizon", $emailHTML);
                    //$_SESSION['avis_message'] = "L'avis a été supprimé avec succès et le client a été notifié par email.";
                //} catch (Exception $emailException) {
                    // L'échec de l'envoi d'email ne doit pas bloquer la suppression
                    //error_log("Erreur envoi email suppression avis : " . $emailException->getMessage());
                    //$_SESSION['avis_message'] = "L'avis a été supprimé avec succès (notification email non envoyée).";
                //}
            //} else {
                //$_SESSION['avis_message'] = "L'avis a été supprimé avec succès.";
            //}
        } elseif ($action === 'valider') {
            // Marquer l'avis comme non signalé (le valider)
            $stmtUpdate = $pdo->prepare("
                UPDATE sae._avis 
                SET signale = FALSE, raison_signalement = '' 
                WHERE id_avis = :id_avis
            ");
            $stmtUpdate->execute([':id_avis' => $id_avis]);

            $_SESSION['avis_message'] = "L'avis a été validé.";
        }

        header("Location: gestionAvisVendeur.php");
        exit();
    }

} catch (PDOException $e) {
    $_SESSION['avis_message'] = "Erreur : " . $e->getMessage();
    header("Location: gestionAvisVendeur.php");
    exit();
}
?>
