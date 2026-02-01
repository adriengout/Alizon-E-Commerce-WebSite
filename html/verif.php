<?php
include('loginBdd.php');

try {
    $dbh = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $dbh->prepare('SELECT login,mail FROM sae._compte');
    $stmt->execute();
    
    $loginsExistant = [] ;
    $mailsExistant = [] ;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $info) {
        $loginsExistant[] = $info['login'];
        $mailsExistant[] = $info['mail'];
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();

}

function getMotDePasseParId($dbh, $idCompte) {
    try {
        $stmt = $dbh->prepare("SELECT mot_de_passe FROM sae._compte WHERE id_compte = ?");
        $stmt->execute([$idCompte]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['mot_de_passe'];
        }
        
        return false;
        
    } catch (PDOException $e) {
        return false;
    }
}

function estClient($dbh, $idCompte) {
    try {
        $stmt = $dbh->prepare("SELECT COUNT(*) FROM sae._client WHERE id_client = ?");
        $stmt->execute([$idCompte]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function validerNomEntreprise($nomEntreprise) {
    $nomEntreprise = trim($nomEntreprise);
    
    if (empty($nomEntreprise)) {
        return ['valide' => false, 'erreur' => "Le nom de l'entreprise est obligatoire."];
    }
    
    if (strlen($nomEntreprise) < 2) {
        return ['valide' => false, 'erreur' => "Le nom de l'entreprise doit contenir au moins 2 caractères."];
    }
    
    if (strlen($nomEntreprise) > 100) {
        return ['valide' => false, 'erreur' => "Le nom de l'entreprise ne peut pas dépasser 100 caractères."];
    }
    
    // Caractères autorisés : lettres, chiffres, espaces, tirets, apostrophes, points, virgules, esperluette
    if (!preg_match("/^[a-zA-Z0-9À-ÿ\s\-'.,&]+$/u", $nomEntreprise)) {
        return ['valide' => false, 'erreur' => "Le nom de l'entreprise contient des caractères non autorisés."];
    }
    
    return ['valide' => true, 'erreur' => ''];
}

function validerSiret($siret) {
    $siret = trim($siret);
    
    if (empty($siret)) {
        return ['valide' => false, 'erreur' => "Le numéro SIRET est obligatoire."];
    }
    
    // Retirer les espaces pour la validation
    $siretNettoye = preg_replace('/\s/', '', $siret);
    
    // Vérifier que c'est exactement 14 chiffres
    if (!preg_match('/^\d{14}$/', $siretNettoye)) {
        return ['valide' => false, 'erreur' => "Le numéro SIRET doit contenir exactement 14 chiffres."];
    }
    
    // Vérifier la validité avec l'algorithme de Luhn
    if (!validerSiretLuhn($siretNettoye)) {
        return ['valide' => false, 'erreur' => "Le numéro SIRET n'est pas valide (clé de contrôle incorrecte)."];
    }
    
    return ['valide' => true, 'erreur' => ''];
}

function validerSiretLuhn($siret) {
    $somme = 0;

    for ($i = 0; $i < 14; $i++) {
        $chiffre = (int)$siret[$i];

        // Pour les positions impaires (1, 3, 5...), on multiplie par 2
        if ($i % 2 === 1) {
            $chiffre *= 2;
            // Si le résultat dépasse 9, on soustrait 9
            if ($chiffre > 9) {
                $chiffre -= 9;
            }
        }

        $somme += $chiffre;
    }

    // Le SIRET est valide si la somme est un multiple de 10
    return ($somme % 10 === 0);
}
function validerNomPrenom($valeur) {
    $valeur = trim($valeur);
    
    if (empty($valeur)) {
        return ['valide' => false, 'erreur' => "Le champ est obligatoire."];
    }
    
    if (strlen($valeur) < 2) {
        return ['valide' => false, 'erreur' => "Le champ doit contenir au moins 2 caractères."];
    }
    
    if (strlen($valeur) > 50) {
        return ['valide' => false, 'erreur' => "Le champ ne peut pas dépasser 50 caractères."];
    }
    
    if (!preg_match("/^[a-zA-ZÀ-ÿ\s\-']+$/u", $valeur)) {
        return ['valide' => false, 'erreur' => "Le champ ne peut contenir que des lettres, espaces, tirets et apostrophes."];
    }
    
    return ['valide' => true, 'erreur' => ''];
}

function validerLoginBdd($login, $loginsExistant) {
    $login = trim($login);
    
    if (empty($login)) {
        return ['valide' => false, 'erreur' => "Le pseudo est obligatoire."];
    }
    
    if (strlen($login) < 3) {
        return ['valide' => false, 'erreur' => "Le pseudo doit contenir au moins 3 caractères."];
    }
    
    if (strlen($login) > 20) {
        return ['valide' => false, 'erreur' => "Le pseudo ne peut pas dépasser 20 caractères."];
    }
    
    if (!preg_match("/^[a-zA-Z0-9_-]+$/", $login)) {
        return ['valide' => false, 'erreur' => "Le pseudo ne peut contenir que des lettres, chiffres, tirets et underscores."];
    }
    
    foreach($loginsExistant as $loginExistant){
        if ($login == $loginExistant){
            return ['valide' => false, 'erreur' => "Le login est déja utiliser."];
        }
    }
    return ['valide' => true, 'erreur' => ''];
}

function validerLogin($login) {
    $login = trim($login);
    
    if (empty($login)) {
        return ['valide' => false, 'erreur' => "Le pseudo est obligatoire."];
    }
    
    if (strlen($login) < 3) {
        return ['valide' => false, 'erreur' => "Le pseudo doit contenir au moins 3 caractères."];
    }
    
    if (strlen($login) > 20) {
        return ['valide' => false, 'erreur' => "Le pseudo ne peut pas dépasser 20 caractères."];
    }
    
    if (!preg_match("/^[a-zA-Z0-9_-]+$/", $login)) {
        return ['valide' => false, 'erreur' => "Le pseudo ne peut contenir que des lettres, chiffres, tirets et underscores."];
    }
    
    
    return ['valide' => true, 'erreur' => ''];
}

function validerDateNaissance($dateNaissance) {
    $dateNaissance = trim($dateNaissance);
    
    if (empty($dateNaissance)) {
        return ['valide' => false, 'erreur' => "La date de naissance est obligatoire."];
    }
    
    // Vérifier le format de la date
    $dateObj = DateTime::createFromFormat('Y-m-d', $dateNaissance);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $dateNaissance) {
        return ['valide' => false, 'erreur' => "Le format de la date est invalide."];
    }
    
    // Vérifier que la date n'est pas dans le futur
    $aujourdhui = new DateTime();
    if ($dateObj > $aujourdhui) {
        return ['valide' => false, 'erreur' => "La date de naissance ne peut pas être dans le futur."];
    }
    
    // Vérifier que la personne a au moins 18 ans
    $age = $aujourdhui->diff($dateObj)->y;
    if ($age < 18) {
        return ['valide' => false, 'erreur' => "Vous devez avoir au moins 18 ans pour créer un compte."];
    }
    
    // Vérifier que la date est réaliste (pas plus de 120 ans)
    if ($age > 120) {
        return ['valide' => false, 'erreur' => "La date de naissance saisie n'est pas réaliste."];
    }
    
    return ['valide' => true, 'erreur' => ''];
}

function validerEmailBdd($email, $mailsExistant) {
    $email = trim($email);
    
    if (empty($email)) {
        return ['valide' => false, 'erreur' => "L'email est obligatoire."];
    }
    
    if (strlen($email) > 255) {
        return ['valide' => false, 'erreur' => "L'email ne peut pas dépasser 255 caractères."];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valide' => false, 'erreur' => "Le format de l'email est invalide."];
    }

    foreach($mailsExistant as $mail){
        if ($mail == $email){
            return ['valide' => false, 'erreur' => "L'adresse mail est déja utiliser."];
        }
    }
    
    return ['valide' => true, 'erreur' => ''];
}

function validerEmail($email) {
    $email = trim($email);
    
    if (empty($email)) {
        return ['valide' => false, 'erreur' => "L'email est obligatoire."];
    }
    
    if (strlen($email) > 255) {
        return ['valide' => false, 'erreur' => "L'email ne peut pas dépasser 255 caractères."];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valide' => false, 'erreur' => "Le format de l'email est invalide."];
    }


    return ['valide' => true, 'erreur' => ''];
}

function validerAdresse($adresse) {
    $adresse = trim($adresse);
    
    if (empty($adresse)) {
        return ['valide' => false, 'erreur' => "L'adresse est obligatoire."];
    }
    
    if (strlen($adresse) < 5) {
        return ['valide' => false, 'erreur' => "L'adresse doit contenir au moins 5 caractères."];
    }
    
    if (strlen($adresse) > 200) {
        return ['valide' => false, 'erreur' => "L'adresse ne peut pas dépasser 200 caractères."];
    }
    
    if (!preg_match("/^[a-zA-Z0-9À-ÿ\s,.\-']+$/u", $adresse)) {
        return ['valide' => false, 'erreur' => "L'adresse contient des caractères non autorisés."];
    }
    
    return ['valide' => true, 'erreur' => ''];
}

function validerTelephone($telephone) {
    $telephone = trim($telephone);
    
    if (empty($telephone)) {
        return ['valide' => false, 'erreur' => "Le téléphone est obligatoire."];
    }
    
    $telephoneNettoye = preg_replace('/[\s.\-]/', '', $telephone);
    
    if (!preg_match('/^0[1-9]\d{8}$/', $telephoneNettoye) && 
        !preg_match('/^\+33[1-9]\d{8}$/', $telephoneNettoye)) {
        return ['valide' => false, 'erreur' => "Le numéro de téléphone n'est pas valide."];
    }
    
    return ['valide' => true, 'erreur' => ''];
}

function validerMotDePasse($motDePasse) {
    if (empty($motDePasse)) {
        return ['valide' => false, 'erreur' => "Le mot de passe est obligatoire."];
    }
    
    if (strlen($motDePasse) < 8) {
        return ['valide' => false, 'erreur' => "Le mot de passe doit contenir au moins 8 caractères."];
    }
    
    if (strlen($motDePasse) > 100) {
        return ['valide' => false, 'erreur' => "Le mot de passe ne peut pas dépasser 100 caractères."];
    }
    
    if (!preg_match('/[A-Z]/', $motDePasse)) {
        return ['valide' => false, 'erreur' => "Le mot de passe doit contenir au moins une majuscule."];
    }
    
    if (!preg_match('/[a-z]/', $motDePasse)) {
        return ['valide' => false, 'erreur' => "Le mot de passe doit contenir au moins une minuscule."];
    }
    
    if (!preg_match('/[0-9]/', $motDePasse)) {
        return ['valide' => false, 'erreur' => "Le mot de passe doit contenir au moins un chiffre."];
    }
    
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>_\-+=]/', $motDePasse)) {
        return ['valide' => false, 'erreur' => "Le mot de passe doit contenir au moins un caractère spécial."];
    }
    
    return ['valide' => true, 'erreur' => ''];
}

function verifierConfirmationMotDePasse($motDePasse, $confirmation) {
    if ($motDePasse !== $confirmation) {
        return ['valide' => false, 'erreur' => "Les mots de passe ne correspondent pas."];
    }
    
    return ['valide' => true, 'erreur' => ''];
}

function nettoyerDonnee($donnee) {
    return htmlspecialchars(trim($donnee), ENT_QUOTES, 'UTF-8');
}

function hasherMotDePasse($motDePasse) {
    return md5($motDePasse);
}

function validerCodePostal($codePostal) {
    $codePostal = trim($codePostal);
    
    if (empty($codePostal)) {
        return ['valide' => false, 'erreur' => "Le code postal est obligatoire."];
    }
    
    // Retirer les espaces pour la validation
    $codePostalNettoye = preg_replace('/\s/', '', $codePostal);
    
    // Vérifier que c'est exactement 5 chiffres
    if (!preg_match('/^\d{5}$/', $codePostalNettoye)) {
        return ['valide' => false, 'erreur' => "Le code postal doit contenir exactement 5 chiffres."];
    }
    
    // Vérifier que le code postal commence par un chiffre valide (01 à 95, ou 97/98 pour DOM-TOM)
    $deuxPremiersChiffres = (int)substr($codePostalNettoye, 0, 2);
    if ($deuxPremiersChiffres < 1 || ($deuxPremiersChiffres > 95 && $deuxPremiersChiffres < 97)) {
        return ['valide' => false, 'erreur' => "Le code postal n'est pas valide."];
    }
    
    return ['valide' => true, 'erreur' => ''];
}


function validerVille($ville) {
    $ville = trim($ville);
    
    if (empty($ville)) {
        return ['valide' => false, 'erreur' => "Le nom de la ville est obligatoire."];
    }
    
    if (strlen($ville) < 2) {
        return ['valide' => false, 'erreur' => "Le nom de la ville doit contenir au moins 2 caractères."];
    }
    
    if (strlen($ville) > 100) {
        return ['valide' => false, 'erreur' => "Le nom de la ville ne peut pas dépasser 100 caractères."];
    }
    
    // Caractères autorisés : lettres, espaces, tirets, apostrophes
    // Accepte les accents et caractères spéciaux des noms de villes françaises
    if (!preg_match("/^[a-zA-ZÀ-ÿ\s\-']+$/u", $ville)) {
        return ['valide' => false, 'erreur' => "Le nom de la ville ne peut contenir que des lettres, espaces, tirets et apostrophes."];
    }
    
    return ['valide' => true, 'erreur' => ''];
}


function validerComplAdresse($complement, $numeroRue = '') {
    $complement = trim($complement);
    
    // Le complément d'adresse est optionnel
    if (empty($complement)) {
        return ['valide' => true, 'erreur' => ''];
    }
    
    if (strlen($complement) < 2) {
        return ['valide' => false, 'erreur' => "Le complément d'adresse doit contenir au moins 2 caractères."];
    }
    
    if (strlen($complement) > 100) {
        return ['valide' => false, 'erreur' => "Le complément d'adresse ne peut pas dépasser 100 caractères."];
    }
    
    // Caractères autorisés : lettres, chiffres, espaces, tirets, apostrophes, virgules
    if (!preg_match("/^[a-zA-Z0-9À-ÿ\s,.\-'\/]+$/u", $complement)) {
        return ['valide' => false, 'erreur' => "Le complément d'adresse contient des caractères non autorisés."];
    }
    
    return ['valide' => true, 'erreur' => ''];
}


function validerNomAdresse($nomRue) {
    $nomRue = trim($nomRue);
    
    if (empty($nomRue)) {
        return ['valide' => false, 'erreur' => "Le nom de rue est obligatoire."];
    }
    
    if (strlen($nomRue) < 3) {
        return ['valide' => false, 'erreur' => "Le nom de rue doit contenir au moins 3 caractères."];
    }
    
    if (strlen($nomRue) > 150) {
        return ['valide' => false, 'erreur' => "Le nom de rue ne peut pas dépasser 150 caractères."];
    }
    
    // Caractères autorisés : lettres, chiffres, espaces, tirets, apostrophes
    // Accepte aussi les types de voies (Rue, Avenue, Boulevard, etc.)
    if (!preg_match("/^[a-zA-Z0-9À-ÿ\s\-'.,]+$/u", $nomRue)) {
        return ['valide' => false, 'erreur' => "Le nom de rue contient des caractères non autorisés."];
    }
    
    return ['valide' => true, 'erreur' => ''];
}


function validerNumAdresse($numeroRue) {
    $numeroRue = trim($numeroRue);
    
    if (empty($numeroRue)) {
        return ['valide' => false, 'erreur' => "Le numéro de rue est obligatoire."];
    }
    
    if (strlen($numeroRue) > 10) {
        return ['valide' => false, 'erreur' => "Le numéro de rue ne peut pas dépasser 10 caractères."];
    }
    
    // Formats acceptés : 
    // - Chiffres seuls : 12, 145
    // - Avec bis/ter : 12bis, 12 bis, 12B, 12 ter
    // - Avec lettre : 12A, 12 A
    if (!preg_match("/^\d+\s?(bis|ter|quater|a|b|c|d)?$/i", $numeroRue)) {
        return ['valide' => false, 'erreur' => "Le format du numéro de rue n'est pas valide (ex: 12, 12bis, 12A)."];
    }
    
    return ['valide' => true, 'erreur' => ''];
}
?>
