<?php
session_start();
$bordereau = $_GET['bord'];

// Initialisation des données par défaut
$donnees_colis = [
    'statut' => 'Inconnu',
    'etape_num' => 0,
    'dates' => array_fill(0, 9, null), // Tableau de 9 dates vides
    'raison' => null,
    'image' => null
];

$host = '127.0.0.1';
$port = 9000;

$socket = @fsockopen($host, $port, $errno, $errstr, 2);

if ($socket) {
    fwrite($socket, "LOGIN alizon admin\n"); 
    fgets($socket);
    
    // 2. Demande des détails complets
    fwrite($socket, "SUIVRE_COLIS $bordereau\n");
    
    $reponseTexte = "";
    $donneesBinaires = "";
    $marqueurTrouve = false;

    stream_set_timeout($socket, 0, 10000);

    $reponseTexte = fgets($socket);

    $ligneSuivante = fgets($socket);
    if (trim($ligneSuivante) === "START_IMG") {
        $marqueurTrouve = true;
    } else {
        $reponseTexte .= $ligneSuivante;
    }

    if ($marqueurTrouve) {
        while (!feof($socket)) {
            $chunk = fread($socket, 8192);
            if ($chunk === "" || $chunk === false) break;
            $donneesBinaires .= $chunk;
        }
    }

    fwrite($socket, "QUIT\n");
    fclose($socket);

    $champs = explode(',', trim($reponseTexte));

    if (isset($champs[0]) && $champs[0] === 'OK') {
        $donnees_colis['statut'] = $champs[1];
        $donnees_colis['dates'] = array_slice($champs, 2, 9);
        $donnees_colis['raison'] = end($champs); 
        $donnees_colis['image'] = $donneesBinaires;

        $etapes_cles = [
            "Bordereau généré" => 1,
            "Expedition vers transporteur" => 2,
            "Arrivée chez le transporteur" => 3,
            "Départ vers la plateforme régionale" => 4,
            "Arrivée sur la plateforme régionale" => 5,
            "Départ vers le centre local" => 6,
            "Arrivée au centre local" => 7,
            "Départ pour la livraison finale (en cours)" => 8,
            "Livraison finalisée" => 9,
            "Livré en mains propres" => 9,
            "Livré en l'absence du destinataire" => 9,
            "Refusé par le destinataire" => 9
        ];
        
        if (array_key_exists($donnees_colis['statut'], $etapes_cles)) {
            $donnees_colis['etape_num'] = $etapes_cles[$donnees_colis['statut']];
        } else {
            $donnees_colis['etape_num'] = 1; 
        }
    }
}

$etapes_affichage = [
    1 => "Préparation",
    2 => "Expédition",
    3 => "Chez Transporteur",
    4 => "Transit Régional",
    5 => "Plateforme Régionale",
    6 => "Transit Local",
    7 => "Centre Local",
    8 => "En Livraison",
    9 => "Livré / Terminé"
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Suivi de commande - Alizon</title>
    <link rel="icon" type="image/png" href="media/universel/logo_alizon_petit.png">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="suivreColisStyle.css">
    <link rel="stylesheet" href="footer.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&display=swap" rel="stylesheet">
    <style>
        .date-etape {
            font-size: 0.75rem;
            color: #666;
            margin-top: 5px;
            display: block;
            text-align: center;
        }
        .preuve-image {
            max-width: 100%;
            border: 3px solid #ddd;
            border-radius: 8px;
            margin-top: 15px;
        }
        .motif-refus {
            color: #d32f2f;
            font-weight: bold;
            background: #ffebee;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <?php include "header.php"; ?>

    <main>
        <a href="modifProfil.php">← Retour à mes commandes</a>

        <div class="suivi-container">
            <h1>Suivi de votre colis</h1>
            <p class="bordereau-info">N° de bordereau : <strong><?= htmlspecialchars($bordereau) ?></strong></p>

            <?php if ($donnees_colis['etape_num'] > 0): ?>
                
                <div class="progress-track">
                    <?php 
                    $acc = 0;
                    foreach ($etapes_affichage as $num => $label): 
                        $statusClass = '';
                        if ($donnees_colis['etape_num'] > $num) $statusClass = 'completed';
                        elseif ($donnees_colis['etape_num'] == $num) $statusClass = 'current';
                        
                        $isTransport = ($num % 2 == 0); 
                        
                        $date_a_afficher = "";
                        if ($num >= 2 && isset($donnees_colis['dates'][$num - 2])) {
                            $raw_date = $donnees_colis['dates'][$num - 2];
                            if (!empty($raw_date) && $raw_date != '(null)') {
                                $date_a_afficher = date("d/m", strtotime($raw_date));
                            }
                        }
                    ?>
                        <div class="step <?= $statusClass ?> <?= $isTransport ? 'transport-step' : 'standard-step' ?>">
                            
                            <div class="etapeIcon">
                                <?php if ($isTransport): ?>
                                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                                        <path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
                                    </svg>
                                <?php else: ?>
                                    <?= ceil($num / 2) ?>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!$isTransport): ?>
                                <p class="label"><?= $label ?></p>
                            <?php endif; ?>

                            <?php if ($date_a_afficher): ?>
                                <span class="date-etape"><?= $date_a_afficher ?></span>
                            <?php endif; ?>
                            
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="status-card">
                    <h3>Statut actuel</h3>
                    <p class="status-text"><?= htmlspecialchars($donnees_colis['statut']) ?></p>
                    
                    <?php if (!empty($donnees_colis['raison']) && strpos($donnees_colis['statut'], 'Refusé') !== false): ?>
                        <div class="motif-refus">
                            Motif du refus : <?= htmlspecialchars($donnees_colis['raison']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($donnees_colis['image'])): ?>
                        <div class="preuve-livraison">
                            <h4>Preuve de livraison (Absence) :</h4>
                            <img src="data:image/jpeg;base64,<?= base64_encode($donnees_colis['image']) ?>" alt="Preuve de dépôt" class="preuve-image"/>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="error-box">
                    <p>Impossible de récupérer les informations de ce bordereau. Le service est peut-être indisponible.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include "footer.php"; ?>
</body>
</html>