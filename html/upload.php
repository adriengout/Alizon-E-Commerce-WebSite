<?php
session_start();
include('loginBdd.php');
$id = $_SESSION['id_compte'];

print_r($_FILES['file']);
if (isset($_FILES['file'])) {
    $uploadDir = 'media/profils/';

    
    $filename = basename($_FILES['file']['name']);
    $extension = '.' . pathinfo($filename, PATHINFO_EXTENSION);
    $fileNameBdd = pathinfo($filename, PATHINFO_FILENAME);
    
    $destination = $uploadDir . $filename;
    if (move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {
        echo "Fichier uploadé avec succès !";
    } else {
        echo "Erreur lors de l'upload.";
    }

    // On suppose que $pdo est déjà connecté

try {

    $dbh = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   
    $stmt = $dbh->prepare('SELECT id_image FROM sae._compte
    WHERE sae._compte.id_compte = :id');
    $stmt->execute([':id' => $id]);
    $id_image = $stmt->fetchColumn();
    
    
    // 1. Démarrer la transaction
    // Tout ce qui se passe après ceci peut être annulé si une erreur survient
    $dbh->beginTransaction();

    // 2. Préparer la requête SQL de mise à jour
    // On met à jour tous les champs sauf la clé primaire
    $stmt = $dbh->prepare('UPDATE sae. _image 
            SET nom_fichier = :nom_fichier, 
                chemin = :chemin, 
                extension = :extension
            WHERE id_image = :id_image');


    // 3. Exécuter la requête avec les données
    // Remplace les variables ($nouveauNom, etc.) par tes vraies données
    $succes = $stmt->execute([
        ':nom_fichier' => $fileNameBdd,      // ex: "avatar_12"
        ':chemin'      => $uploadDir,   // ex: "uploads/avatar_12.jpg"
        ':extension'   => $extension,     // ex: "jpg"
        ':id_image'    => $id_image   // L'ID de l'image à modifier
    ]);

    // 4. Valider la transaction
    // Si on arrive ici sans erreur, on valide les changements en base de données
    $dbh->commit();
    
    echo "L'image a été modifiée avec succès.";
    

} catch (Exception $e) {
    // 5. En cas d'erreur (SQL ou autre)
    // On annule tout ce qui a été fait depuis le beginTransaction()
    if ($dbh->inTransaction()) {
            $dbh->rollBack();
        }
    
    echo "Erreur lors de la modification : " . $e->getMessage();
}
}
header("Location: modifProfil.php");
exit();
?>
   
    



   
    
