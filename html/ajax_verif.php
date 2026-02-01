<?php
// ajax_verif.php
header('Content-Type: application/json');
require_once 'verif.php'; // On réutilise vos fonctions

// On récupère les données JSON envoyées par le JS
$input = json_decode(file_get_contents('php://input'), true);
$field = $input['field'] ?? '';
$value = $input['value'] ?? '';

$response = ['valide' => true, 'erreur' => ''];

if ($field === 'login') {
    // Vérification format
    $checkFormat = validerLogin($value);
    if (!$checkFormat['valide']) {
        $response = $checkFormat;
    } else {
        // Vérification disponibilité (BDD)
        // Note: Vos tableaux $loginsExistant sont générés dans verif.php
        // Idéalement, il faudrait une fonction qui fait un SELECT COUNT
        // Mais utilisons votre structure actuelle :
        if (in_array($value, $loginsExistant)) {
            $response = ['valide' => false, 'erreur' => "Ce login est déjà utilisé."];
        }
    }
}

if ($field === 'email') {
    // Vérification format
    $checkFormat = validerEmail($value);
    if (!$checkFormat['valide']) {
         $response = $checkFormat;
    } else {
        // Vérification disponibilité
        if (in_array($value, $mailsExistant)) {
            $response = ['valide' => false, 'erreur' => "Cet email est déjà utilisé."];
        }
    }
}

echo json_encode($response);
exit;
?>