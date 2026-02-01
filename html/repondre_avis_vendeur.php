<?php
session_start();
require_once("loginBdd.php");

// Vérifier que l'utilisateur est connecté en tant que vendeur
if (!isset($_SESSION['id_compte'])) {
    header('Location: connexion.php');
    exit();
}

$id_vendeur = $_SESSION['id_compte'];

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_avis']) && isset($_POST['reponse'])) {
        $id_avis = intval($_POST['id_avis']);
        $reponse = htmlspecialchars(trim($_POST['reponse']));

        if (empty($reponse)) {
            $_SESSION['avis_message'] = "La réponse ne peut pas être vide.";
            header("Location: gestionAvisVendeur.php");
            exit();
        }

        // Vérifier que l'avis concerne bien un produit du vendeur
        $stmtCheck = $pdo->prepare("
            SELECT A.id_avis 
            FROM sae._avis A
            JOIN sae._produit P ON A.id_produit = P.id_produit
            WHERE A.id_avis = :id_avis AND P.id_vendeur = :id_vendeur
        ");
        $stmtCheck->execute([':id_avis' => $id_avis, ':id_vendeur' => $id_vendeur]);
        $avis = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$avis) {
            $_SESSION['avis_message'] = "Vous n'êtes pas autorisé à répondre à cet avis.";
            header("Location: gestionAvisVendeur.php");
            exit();
        }

        // Vérifier si une réponse existe déjà
        $stmtCheckReponse = $pdo->prepare("
            SELECT id_reponse FROM sae._reponse WHERE id_avis = :id_avis
        ");
        $stmtCheckReponse->execute([':id_avis' => $id_avis]);
        $reponse_existe = $stmtCheckReponse->fetch();

        if ($reponse_existe) {
            // Mettre à jour la réponse existante
            $stmtUpdate = $pdo->prepare("
                UPDATE sae._reponse 
                SET reponse_vendeur = :reponse, date_reponse = CURRENT_DATE
                WHERE id_avis = :id_avis
            ");
            $stmtUpdate->execute([
                ':reponse' => $reponse,
                ':id_avis' => $id_avis
            ]);
        } else {
            // Insérer une nouvelle réponse
            $stmtInsert = $pdo->prepare("
                INSERT INTO sae._reponse (reponse_vendeur, date_reponse, id_avis)
                VALUES (:reponse, CURRENT_DATE, :id_avis)
            ");
            $stmtInsert->execute([
                ':reponse' => $reponse,
                ':id_avis' => $id_avis
            ]);
        }

        $_SESSION['avis_message'] = "Votre réponse a été publiée avec succès !";
        header("Location: gestionAvisVendeur.php");
        exit();
    }

} catch (PDOException $e) {
    $_SESSION['avis_message'] = "Erreur : " . $e->getMessage();
    header("Location: gestionAvisVendeur.php");
    exit();
}
?>
