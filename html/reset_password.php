<?php
session_start();
include('loginBdd.php'); // Vos variables de connexion ($host, $dbname, etc.)
require_once 'verif.php'; // Vos fonctions de validation

$erreurs = [];
$token_valide = false;
$token = "";

// 1. Connexion à la BDD (Nécessaire tout de suite pour vérifier le token)
try {
    $dbh = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// 2. Récupération du token (soit via URL en GET, soit via le formulaire en POST)
if (isset($_GET['token'])) {
    $token = $_GET['token'];
} elseif (isset($_POST['token'])) {
    $token = $_POST['token'];
}

// 3. Vérification : Est-ce que ce token existe et est encore valide (date) ?
if (!empty($token)) {
    // Note : NOW() fonctionne en PostgreSQL et MySQL.
    $stmt = $dbh->prepare("SELECT id_compte FROM sae._compte WHERE token_reset = :token AND token_expiration > NOW()");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if ($user) {
        $token_valide = true;
    } else {
        $erreurs['general'] = "Ce lien de réinitialisation est invalide ou a expiré.";
    }
} else {
    $erreurs['general'] = "Token manquant.";
}

// 4. Traitement du formulaire (si le token est valide et qu'on a posté)
if ($_SERVER["REQUEST_METHOD"] === "POST" && $token_valide) {

    $newMdp = $_POST['newMdp'];
    $confMdp = $_POST['confMdp'];

    // Validation Identique à votre code précédent
    if ($newMdp !== $confMdp){
        $erreurs['confMdp'] = 'Les mots de passe ne correspondent pas.';
    }
    
    $resultMdp = validerMotDePasse($newMdp); // Votre fonction perso
    if (!$resultMdp['valide']) {
        $erreurs['newMdp'] = $resultMdp['erreur'];
    }

    // Si aucune erreur, on fait la mise à jour
    if(empty($erreurs)){
        try {
            // A. Mise à jour du mot de passe
            // B. REMISE À ZERO du token (NULL) pour invalider le lien (sécurité)
            $stmt = $dbh->prepare('UPDATE sae._compte SET mot_de_passe = :mdp, token_reset = NULL, token_expiration = NULL WHERE token_reset = :token');
            
            $stmt->execute([
                ':mdp' => hasherMotDePasse($newMdp), // Votre fonction de hash
                ':token' => $token
            ]);

            // Redirection vers la page de connexion avec un message de succès
            // Remplacez 'connexion.php' par le vrai nom de votre page de login
            header("Location: connexion.php?reset=success"); 
            exit();

        } catch (PDOException $e) {
            $erreurs['general'] = "Erreur technique : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="modifStyleCss2.css">
    <title>Réinitialisation du mot de passe</title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
</head>
<body>
    
    <section>
        <header>
            <img src="media/universel/logo-header.png" alt="Logo Alizon" class="logo">
            <h1>Nouveau mot de passe</h1>
        </header>

        <?php if (!$token_valide): ?>
            <p style="text-align: center; margin-bottom: 20px;">
                <?php echo $erreurs['general'] ?? "Lien invalide."; ?>
            </p>
            <form>
                <input type="button" value="Retour à la connexion" onclick="window.location.href='connexion.php';" style="width: 100%;" />
            </form>

        <?php else: ?>
            <form action="reset_password.php" method="post">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <p style="text-align: center; margin-bottom: 20px;">Veuillez choisir un nouveau mot de passe.</p>

                <label for="newMdp">Nouveau Mot de passe : *</label>
                <input type="password" id="newMdp" name="newMdp" class="<?php echo isset($erreurs['newMdp']) ? 'input-erreur' : ''; ?>" required />
                <span class="erreur-message"><?php echo isset($erreurs['newMdp']) ? htmlspecialchars($erreurs['newMdp']) : ''; ?></span>

                <label for="confMdp">Confirmer le Mot de passe : *</label>
                <input type="password" id="confMdp" name="confMdp" class="<?php echo isset($erreurs['confMdp']) ? 'input-erreur' : ''; ?>" required />
                <span class="erreur-message"><?php echo isset($erreurs['confMdp']) ? htmlspecialchars($erreurs['confMdp']) : ''; ?></span>

                <input type="button" value="Annuler" onclick="window.location.href='connexion.php';" />
                <input type="submit" value="Valider" class="soumettre" />
            </form>
        <?php endif; ?>

        <footer>
            © 2025 Alizon - Tous droits réservés<br>
            <a href="#">Mentions légales</a> | <a href="#">Politique de confidentialité</a> | <a href="#">Cookies</a>
        </footer>
    </section>
    <script src="validation.js"></script>
</body>
</html>