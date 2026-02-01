<?php
require_once("loginBdd.php");

try {
    $dbh = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo = $dbh;
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}

function ajouterNotification($pdo, $id_compte, $type, $titre, $message) {
    try {
        
        $sql = "INSERT INTO sae._notification 
                (type_notification, titre, message, date_creation, lue, id_compte) 
                VALUES 
                (:type, :titre, :message, CURRENT_DATE, false, :id_compte)";
        
        $stmt = $pdo->prepare($sql);
        
        // On exécute avec les données
        $resultat = $stmt->execute([
            'type'      => $type,
            'titre'     => $titre,
            'message'   => $message,
            'id_compte' => $id_compte
        ]);

        return $resultat;

    } catch (PDOException $e) {
        return false;
    }
}


?>
