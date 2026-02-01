<?php
require_once("loginBdd.php");
 
try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Liste des utilisateurs à mettre à jour avec leur mot de passe en clair
    $utilisateurs = [
        'cdupont' => 'mdp123',
        'mlefevre' => 'mdp123',
        'tbernard' => 'mdp123',
        'ljoly' => 'mdp123',
        'fmartin' => 'mdp123',
        'vendeurA' => 'mdp123',
        'vendeurB' => 'mdp123',
        'vendeurC' => 'mdp123'
    ];
 
    echo "Début de la mise à jour des mots de passe...\n";
 
    $stmt = $pdo->prepare("UPDATE sae._compte SET mot_de_passe = :hash WHERE login = :login");
 
    foreach ($utilisateurs as $login => $mdpClair) {
        $hash = md5($mdpClair);
        $stmt->execute(['hash' => $hash, 'login' => $login]);
        echo "Mot de passe mis à jour pour : $login\n";
    }
 
    echo "Mise à jour terminée avec succès !";
 
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>
