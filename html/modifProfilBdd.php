<?php
session_start();
include('loginBdd.php');
print_r($_SESSION);
if (!isset($_GET['vendeur'])){


$nom = $_SESSION['data']['nom'];
$prenom = $_SESSION['data']['prenom'];
$login = $_SESSION['data']['login'];
$mail = $_SESSION['data']['mail'];
$tel = $_SESSION['data']['tel'];
$id= $_SESSION['id_compte'];

try {
    $dbh = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Use prepared statements for security and correctness
    $stmt = $dbh->prepare('SELECT nom, prenom, date_naissance, login ,mail, tel FROM sae._client 
inner join sae._compte on _client.id_client = _compte.id_compte 
WHERE id_client = :id');
    $stmt->execute([':id' => $id]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);
    $new = $_SESSION['data'];
    
    $updatesClient = [];
    $paramsClient = [];
    $updatesCompte = [];
    $paramsCompte = [];
    $cmp = 0;
    
foreach ($info as $column => $oldValue) {
    $cmp++;
    if ($new[$column] !== $oldValue && $cmp < 4) {
        $updatesClient[] = "$column = :$column";
        $paramsClient[":$column"] = $new[$column];
    }
    elseif(($new[$column] !== $oldValue && $cmp > 3)){
        $updatesCompte[] = "$column = :$column";
        $paramsCompte[":$column"] = $new[$column];
    }
}

if (empty($updatesClient) && empty($updatesCompte)) {
    header("Location: modifProfil.php");
    exit();

}


// Ajout de l'ID
$paramsClient[':id'] = $id;
$paramsCompte[':id'] = $id;
// Construction de la requête
if (!empty($updatesClient)){
    $sql = "UPDATE sae._client SET " . implode(", ", $updatesClient) . " WHERE id_client = :id";
    $stmt = $dbh->prepare($sql);
    $stmt->execute($paramsClient);
}

if (!empty($updatesCompte)){
    $sql = "UPDATE sae._compte SET " . implode(", ", $updatesCompte) . " WHERE id_compte = :id";
    $stmt = $dbh->prepare($sql);
    $stmt->execute($paramsCompte);
}
header("Location: modifProfil.php");
exit();
}
catch (PDOException $e) {
    echo "Error: " . $e->getMessage();

}
}else {



$nomEntreprise = $_SESSION['data']['nom_entreprise'];
$siret = $_SESSION['data']['siret'];
$descriptionVendeur = $_SESSION['data']['description_vendeur'];
$login = $_SESSION['data']['login'];
$mail = $_SESSION['data']['mail'];
$tel = $_SESSION['data']['tel'];
$id= 6;

try {
    $dbh = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Use prepared statements for security and correctness
    $stmt = $dbh->prepare('SELECT nom_entreprise, siret, description_vendeur, login ,mail, tel FROM sae._vendeur
    inner join sae._compte on _vendeur.id_vendeur = _compte.id_compte 
    WHERE id_vendeur = :id');
    $stmt->execute([':id' => $id]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);
    $new = $_SESSION['data'];
    
    $updatesVendeur = [];
    $paramsVendeur = [];
    $updatesCompte = [];
    $paramsCompte = [];
    $cmp = 0;
    
foreach ($info as $column => $oldValue) {
    $cmp++;
    if ($new[$column] !== $oldValue && $cmp < 4) {
        $updatesVendeur[] = "$column = :$column";
        $paramsVendeur[":$column"] = $new[$column];
    }
    elseif(($new[$column] !== $oldValue && $cmp > 3)){
        $updatesCompte[] = "$column = :$column";
        $paramsCompte[":$column"] = $new[$column];
    }
}

if (empty($updatesVendeur) && empty($updatesCompte)) {
    echo "Rien à modifier.";
    exit;
}


// Ajout de l'ID
$paramsVendeur[':id'] = $id;
$paramsCompte[':id'] = $id;
// Construction de la requête
if (!empty($updatesVendeur)){
    $sql = "UPDATE sae._vendeur SET " . implode(", ", $updatesVendeur) . " WHERE id_vendeur = :id";
    $stmt = $dbh->prepare($sql);
    $stmt->execute($paramsVendeur);
}

if (!empty($updatesCompte)){
    $sql = "UPDATE sae._compte SET " . implode(", ", $updatesCompte) . " WHERE id_compte = :id";
    $stmt = $dbh->prepare($sql);
    $stmt->execute($paramsCompte);
}

header("Location: modifProfil.php?vendeur=1");
exit();
}
catch (PDOException $e) {
    echo "Error: " . $e->getMessage();

}
}

?>
