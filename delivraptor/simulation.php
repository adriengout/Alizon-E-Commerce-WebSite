<?php
try {
    $pdo = new PDO(
        "pgsql:host=localhost;dbname=bddsae2",
        "postgres",
        "Adri1gout"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Erreur connexion : " . $e->getMessage());
}

$date_actuelle = date('Y-m-d H:i:s');

$etapes = [
    2 => "Expedition vers transporteur",
    3 => "Arrivée chez le transporteur",
    4 => "Départ vers la plateforme régionale",
    5 => "Arrivée sur la plateforme régionale",
    6 => "Départ vers le centre local",
    7 => "Arrivée au centre local",
    8 => "Départ pour la livraison finale (en cours)",
    9 => "Livraison finalisée"
];

$stmt = $pdo->prepare("UPDATE sae._livraison SET etape = etape + 1 WHERE etape < 9");
$stmt->execute();

$stmt = $pdo->query("SELECT * FROM sae._livraison");
$toutColis = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($toutColis as $colis) {
    $id = $colis['bordereau'];
    $etape = $colis['etape'];

    switch($etape) {
        case 2: // Quitte Alizon
            $sql = "UPDATE sae._livraison SET date_exped_transporteur = :d WHERE bordereau = :id";
            break;
        case 3: // Arrive Transporteur
            $sql = "UPDATE sae._livraison SET date_arrive_transporteur = :d WHERE bordereau = :id";
            break;
        case 4: // Quitte Transporteur
            $sql = "UPDATE sae._livraison SET date_exped_plateforme = :d WHERE bordereau = :id";
            break;
        case 5: // Arrive Plateforme Régionale
            $sql = "UPDATE sae._livraison SET date_arrive_plateforme = :d WHERE bordereau = :id";
            break;
        case 6: // Quitte Plateforme Régionale
            $sql = "UPDATE sae._livraison SET date_exped_centreLocal = :d WHERE bordereau = :id";
            break;
        case 7: // Arrive Centre Local
            $sql = "UPDATE sae._livraison SET date_arrive_centreLocal = :d WHERE bordereau = :id";
            break;
        case 8: // Départ Livraison Finale 
            $sql = "UPDATE sae._livraison SET date_exped_domicile = :d WHERE bordereau = :id";
            break;
        case 9: // Livraison finale
            if (!isset($colis['date_livraison_reel'])) {
                $choix = 2;//rand(1, 3);
                $raison = null;
                switch ($choix) {
                    case 1: $statut = "Livré en mains propres"; break;
                    case 2: $statut = "Livré en l'absence du destinataire"; break;
                    case 3: 
                        $statut = "Refusé par le destinataire";
                        $raisons = ["Colis endommagé", "Destinataire inconnu", "Refusé"];
                        $raison = $raisons[array_rand($raisons)];
                        break;
                }
                $sql = "UPDATE sae._livraison SET 
                        statut = :statut, 
                        raison_refus = :raison, 
                        date_livraison_reel = :d,
                        date_arrive_domicile = :d 
                        WHERE bordereau = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['statut' => $statut, ':d' => $date_actuelle, ':id' => $id, ':raison' => $raison]);
            }
            continue 2;
    }

    if (isset($sql)) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':d' => $date_actuelle, ':id' => $id]);
    }

    if ($etape < 9) {
        $stmt = $pdo->prepare("UPDATE sae._livraison SET statut = :st WHERE bordereau = :id");
        $stmt->execute([':st' => $etapes[$etape], ':id' => $id]);
    }
}

echo "Quantum de temps écoulé : progression et horodatage effectués.\n";
?>