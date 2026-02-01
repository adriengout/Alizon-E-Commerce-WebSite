drop schema if exists "sae" cascade;




create schema sae;
set schema 'sae';




CREATE TABLE _image (
    id_image serial,
    nom_fichier varchar(60) not null,
    chemin varchar(60) not null,
    extension varchar(60) not null,
    alt varchar(60) not null,
    CONSTRAINT _image_pk PRIMARY KEY (id_image)
);








create table _compte(
    id_compte serial,
    "login" varchar(40) not null,
    mot_de_passe varchar(255) not null,
    mail varchar(40) not null,
    tel varchar(15) not null,
    id_image integer not null,
    constraint _compte_pk primary key(id_compte),
    constraint _compte_fk_image foreign key (id_image) references _image(id_image)
);








create table _client(
    id_client int,
    nom varchar(40) not null,
    prenom varchar(40) not null,
    date_naissance date not null,
    compe_bloquer boolean not null,
    constraint _client_pk primary key(id_client),
    constraint _client_fk_compte foreign key(id_client) references _compte(id_compte)
);








create table _gestionnaire(
    id_gestionnaire serial,
    nom varchar(40) not null,
    prenom varchar(40) not null,
    constraint _gestionnaire_pk primary key(id_gestionnaire),
    constraint _gestionnaire foreign key(id_gestionnaire) references _compte(id_compte)
);








create table _vendeur(
    id_vendeur serial,
    nom_entreprise varchar(40) not null,
    siret varchar(14) not null,
    description_vendeur varchar(500) not null,
    comission_cobrec float not null,
    constraint _vendeur_pk primary key(id_vendeur),
    constraint _vendeur_fk_compte foreign key(id_vendeur) references _compte(id_compte)
);








CREATE TABLE _categorieProduit (
    id_categ serial ,
    nom_categ varchar(50) not null,
    description_categ varchar(100) not null,
    id_image integer not null,
    CONSTRAINT _categorieProduit_pk PRIMARY KEY (id_categ),
    CONSTRAINT _categorieProduit_fk_image FOREIGN KEY (id_image) REFERENCES _image(id_image)
);








create table _produit(
    id_produit serial,
    nom_produit varchar(60) not null,
    description_prod varchar(500) not null,
    prix_ht float not null,
    dep_origine varchar(60) not null,
    ville_origine varchar(60) not null,
    pays_origine varchar(60) not null,
    date_prod date not null,
    nb_ventes integer not null,
    id_image integer not null,
    id_categ integer not null,
    id_vendeur integer not null,
    constraint _produit_pk primary key(id_produit),
    CONSTRAINT _produit_fk_image FOREIGN KEY (id_image) REFERENCES _image(id_image),
    CONSTRAINT _produit_fk_categ FOREIGN KEY (id_categ) REFERENCES _categorieProduit(id_categ),
    CONSTRAINT _produit_fk_vendeur FOREIGN KEY (id_vendeur) REFERENCES _vendeur(id_vendeur)
);








create table _stock(
    id_vendeur serial,
    id_produit serial,
    quantite_dispo integer not null,
    seuil_alerte integer not null,
    derniere_maj date not null,
    alerte boolean not null,
    constraint _stock_pk primary key(id_vendeur, id_produit),
    constraint _stock_fk_produit foreign key(id_produit) references _produit(id_produit),
    constraint _stock_fk_vendeur foreign key(id_vendeur) references _vendeur(id_vendeur)
);








create table _promotion(
    id_promotion serial,
    id_produit integer not null,
    nom_promotion varchar(40) not null,
    descrip_promotion varchar(40) not null,
    date_debut date not null,
    date_fin date not null,
    banniere_promo varchar(40) not null,
    constraint _promotion_pk primary key(id_promotion),
    constraint _promotion_fk_produit foreign key(id_produit) references _produit(id_produit)
);








create table _tva(
    id_tva serial,
    id_produit integer not null,
    type_tva varchar(40) not null,
    taux_tva float not null,
    constraint _tva_pk primary key(id_tva),
    constraint _tva_fk_produit foreign key(id_produit) references _produit(id_produit)
);








CREATE TABLE _livraison (
    num_suivi serial,
    date_exped date not null,
    date_livraison_prevue date not null,
    date_livraison_reel date not null,
    statut_livraison varchar(40) not null,
    signature_livraison boolean not null,
    url_suivi varchar(50) not null,
    CONSTRAINT _livraison_pk PRIMARY KEY (num_suivi)
);








CREATE TABLE _commande (
    num_commande serial,
    frais_livraison float not null,
    montant_total_ht float not null,
    statut_commande varchar(40) not null,
    date_commande date not null,
    total_nb_prod integer not null,
    montant_total_ttc float not null,
    id_client integer not null,
    CONSTRAINT _commande_pk PRIMARY KEY (num_commande),
    CONSTRAINT _commande_fk_client FOREIGN KEY (id_client) REFERENCES _client(id_client)
    
);

CREATE UNIQUE INDEX idx_unique_panier_actif ON _commande (id_client)
WHERE statut_commande = 'En préparation';








CREATE TABLE Transporteur (
    num_suivi serial,
    num_commande serial,
    colis_livre boolean not null,
    confirmation_retour boolean not null,
    CONSTRAINT _transporteur_pk PRIMARY KEY (num_suivi, num_commande),
    CONSTRAINT _transporteur_fk_livraison FOREIGN KEY (num_suivi) REFERENCES _livraison(num_suivi),
    CONSTRAINT _transporteur_fk_commande FOREIGN KEY (num_commande) REFERENCES _commande(num_commande)
);








create table _coordonnees_banquaire (
    id_coord serial,
    numero_cb_masque varchar(30) not null,
    nom_porteur varchar(40) not null,
    cryptogramme varchar(10) not null,
    date_exp_cb date not null,
    id_client integer not null,
    constraint _coordonnees_banquaire_pk primary key (id_coord),
    constraint _coordonnees_banquaire_fk_client foreign key (id_client) references _client(id_client)
);








CREATE TABLE _paiement (
    id_paiement serial,
    id_coord integer not null,
    num_commande integer not null,
    montant_ttc float not null,
    CONSTRAINT _paiement_pk PRIMARY KEY (id_paiement),
    CONSTRAINT _paiement_fk_num_commande FOREIGN KEY (num_commande) REFERENCES  _commande(num_commande),
    CONSTRAINT _paiement_fk_id_coord FOREIGN KEY (id_coord) REFERENCES _coordonnees_banquaire(id_coord)
);








CREATE TABLE _facture (
    id_facture serial,
    date_facture date not null,
    montant_facture float not null,
    num_commande integer not null,
    CONSTRAINT _facture_pk PRIMARY KEY (id_facture),
    CONSTRAINT _facture_fk_commande FOREIGN KEY (num_commande) REFERENCES  _commande(num_commande)
);








CREATE TABLE _avis (
    id_avis serial,
    note integer not null,
    titre_avis varchar(40) not null,
    commentaire varchar(400) not null,
    date_avis date not null,
    avis_verif boolean not null,
    votes_utiles int not null,
    votes_inutiles int not null,
    signale boolean not null,
    raison_signalement varchar(100) not null,
    epingle boolean not null,
    id_image integer,
    id_client integer not null,
    id_produit integer not null,
    CONSTRAINT _avis_pk PRIMARY KEY (id_avis),
    CONSTRAINT _avis_fk_client FOREIGN KEY (id_client) REFERENCES _client(id_client),
    CONSTRAINT _avis_fk_image FOREIGN KEY (id_image) REFERENCES _image(id_image),
    CONSTRAINT _avis_fk_produit FOREIGN KEY (id_produit) REFERENCES _produit(id_produit)
);








create table _remise (
    id_remise serial,
    valeur_remise float not null,
    taux_remise float not null,
    id_produit integer not null,
    constraint _remise_pk PRIMARY key (id_remise),
    constraint _remise_fk_produit foreign key (id_produit) references _produit(id_produit)
);








create table _reponse (
    id_reponse serial,
    reponse_vendeur varchar(400) not null,
    date_reponse date not null,
    id_avis integer not null,
    constraint _reponse_pk primary key (id_reponse),
    constraint _reponse_fk_avis foreign key (id_avis) references _avis(id_avis)
);








create table _notification (
    id_notif serial ,
    type_notification varchar(20) not null,
    titre varchar(40) not null,
    message varchar(100) not null,
    date_creation date not null,
    lue boolean not null,
    id_client integer not null,
    constraint _notification_pk primary key (id_notif),
    constraint _notification_fk_client foreign key (id_client) references _client(id_client)
);








CREATE TABLE _adresse (
    id_adresse SERIAL,
    ville VARCHAR(40) NOT NULL,
    complement_adresse VARCHAR(20) NOT NULL,
    code_postal VARCHAR(10) NOT NULL,
    num_rue INTEGER NOT NULL,
    nom_rue VARCHAR(40) NOT NULL,
    id_client INTEGER,
    id_vendeur INTEGER,
    CONSTRAINT _adresse_pk PRIMARY KEY (id_adresse),
    CONSTRAINT _adresse_fk_client FOREIGN KEY (id_client) REFERENCES _client(id_client),
    CONSTRAINT _adresse_fk_vendeur FOREIGN KEY (id_vendeur) REFERENCES _vendeur(id_vendeur),
    CONSTRAINT chk_adresse_client_ou_vendeur CHECK (
        id_client IS NOT NULL OR id_vendeur IS NOT NULL
    )
);







create table _ligneCommande (
    id_produit integer,
    num_commande integer,
    quantite_prod integer not null,
    total_ttc float not null,
    total_ligne_commande_ht float not null,
    total_ligne_commande_ttc float not null,
    constraint _ligneCommande_pk primary key (id_produit, num_commande),
    constraint _ligneCommande_fk_produit foreign key (id_produit) references _produit(id_produit),
    constraint _ligneCommande_fk_commande foreign key (num_commande) references _commande(num_commande)
);


create table _colis(
    id_colis integer,
    id_bordereau varchar(20),
    num_suivi integer not null,
    poids_colis float not null,
    etape integer,
    date_etape date,
    dimension_colis varchar(10) not null,
    constraint _colis_pk primary key (id_colis),
    constraint _colis_fk_livraison foreign key (num_suivi) references _livraison(num_suivi)
);


---PEUPLEMENT DES BASES------

TRUNCATE TABLE 
    sae._ligneCommande,
    sae.Transporteur,
    sae._paiement,
    sae._facture,
    sae._colis,
    sae._livraison,
    sae._coordonnees_banquaire,
    sae._avis,
    sae._reponse,
    sae._notification,
    sae._adresse,
    sae._remise,
    sae._promotion,
    sae._tva,
    sae._stock,
    sae._commande,
    sae._produit,
    sae._categorieProduit,
    sae._client,
    sae._gestionnaire,
    sae._vendeur,
    sae._compte,
    sae._image
RESTART IDENTITY
CASCADE;

truncate table "_image" cascade;

-- Insertion des livraisons parentes
INSERT INTO _livraison (num_suivi, date_exped, date_livraison_prevue, date_livraison_reel, statut_livraison, signature_livraison, url_suivi)
VALUES 
(101, '2026-01-12', '2026-01-15', '2026-01-15', 'En transit', false, 'http://delivraptor.fr/track/101'),
(102, '2026-01-12', '2026-01-15', '2026-01-15', 'En transit', false, 'http://delivraptor.fr/track/102'),
(103, '2026-01-12', '2026-01-16', '2026-01-16', 'En transit', false, 'http://delivraptor.fr/track/103'),
(104, '2026-01-12', '2026-01-14', '2026-01-14', 'En transit', false, 'http://delivraptor.fr/track/104'),
(105, '2026-01-12', '2026-01-15', '2026-01-15', 'En transit', false, 'http://delivraptor.fr/track/105'),
(106, '2026-01-12', '2026-01-17', '2026-01-17', 'En transit', false, 'http://delivraptor.fr/track/106'),
(107, '2026-01-12', '2026-01-15', '2026-01-15', 'En transit', false, 'http://delivraptor.fr/track/107');


-- Insertion de 7 colis de test
INSERT INTO _colis (id_colis, id_bordereau, num_suivi, poids_colis, etape, date_etape, dimension_colis) 
VALUES 
(1, 'BORD-TPTB-3312-0001', 101, 1.5, 1, CURRENT_DATE, '20x20x10'),
(2, 'BORD-ZNXV-2555-0002', 102, 0.8, 1, CURRENT_DATE, '10x15x05'),
(3, 'BORD-ERLZ-2434-0003', 103, 12.4, 1, CURRENT_DATE, '50x40x30'),
(4, 'BORD-ZPKM-5636-0004', 104, 3.2, 1, CURRENT_DATE, '30x20x20'),
(5, 'BORD-IYJV-8059-0005', 105, 0.5, 1, CURRENT_DATE, '10x10x10'),
(6, 'BORD-FKYG-4927-0006', 106, 7.1, 1, CURRENT_DATE, '40x30x25'),
(7, 'BORD-IDIS-9012-0007', 107, 2.3, 1, CURRENT_DATE, '25x20x15');


-- 1️⃣ Images (placeholders)
INSERT INTO _image (nom_fichier, chemin, extension, alt) VALUES
-- Images de Profils, Vendeurs, et Catégories (IDs 1 à 13) - Conservées car non listées
('produit1_1', 'media/produits/', '.jpg', 'Galettes de blé noir'), 
('produit2_1', 'media/produits/', '.png', 'Crêpes sucrées bretonnes'), 
('produit3_1', 'media/produits/', '.png', 'Kouign-amann'), 
('produit4_1', 'media/produits/', '.png', 'Far breton aux pruneaux'),
('produit5_1', 'media/produits/', '.jpg', 'Palets bretons'),
('produit6_1', 'media/produits/', '.jpg', 'Caramel au beurre salé'), 
('produit7_1', 'media/produits/', '.jpg', 'Confiture de lait sel de Guérande'),
('produit8_1', 'media/produits/', '.jpg', 'Miel de Bretagne'), 
('produit9_1', 'media/produits/', '.jpg', 'Sel de Guérande IGP 1kg'), 
('produit10_1', 'media/produits/', '.jpg', 'Fleur de sel de Guérande aromatisée'), 
('produit11_1', 'media/produits/', '.jpg', 'Beurre demi-sel artisanal'), -- ID 24
('produit12_1', 'media/produits/', '.jpg', 'Rillettes de maquereau aux oignons roses'), 
('produit13_1', 'media/produits/', '.jpg', 'Rillettes de sardine citron algues'), 
('produit14_1', 'media/produits/', '.jpg', 'Rillettes de noix de St-Jacques'), 
('produit15_1', 'media/produits/', '.jpg', 'Conserve thon blanc Germon 160 g'), 
('produit16_1', 'media/produits/', '.jpg', 'Soupe de poissons artisanale 1L'), 
('produit17_1', 'media/produits/', '.png', 'Cidre brut breton traditionnel'), 
('produit18_1', 'media/produits/', '.jpg', 'Cidre doux breton IGP bio'), 
('produit19_1', 'media/produits/', '.jpg', 'Chouchen hydromel breton 37,5 cl'), 
('produit20_1', 'media/produits/', '.png', 'Bière artisanale bretonne coffret'), 
('produit21_1', 'media/produits/', '.jpg', 'Whisky breton Celtic Kornog'), 
('produit22_1', 'media/produits/', '.jpg', 'Liqueur fraise de Plougastel 50 cl'), 
('produit23_1', 'media/produits/', '.jpg', 'Jus de pomme artisanal breton 1L'), 
('produit24_1', 'media/produits/', '.png', 'Galettes de Pont-Aven 130 g'), 
('produit25_1', 'media/produits/', '.jpg', 'Sablés bretons pur beurre pépites chocolat'), 
('produit26_1', 'media/produits/', '.jpg', 'Gâteau breton fourré confiture 350 g'), 
('produit27_1', 'media/produits/', '.jpg', 'Pâté breton de campagne'), 
('produit28_1', 'media/produits/', '.jpg', 'Andouille bretonne traditionnelle'), 
('produit29_1', 'media/produits/', '.jpg', 'Kig ha farz plat traditionnel Léon'), 
('produit30_1', 'media/produits/', '.png', 'Chocolat artisanal au sel de Guérande'), 
('produit31_1', 'media/produits/', '.jpg', 'Bol breton personnalisé'), 
('produit32_1', 'media/produits/', '.png', 'Faïence de Quimper'), 
('produit33_1', 'media/produits/', '.jpg', 'Figurine céramique bretonne'), 
('produit34_1', 'media/produits/', '.png', 'Sculpture sur bois bretonne'), 
('produit35_1', 'media/produits/', '.jpg', 'Tableau marin paysage breton'), 
('produit36_1', 'media/produits/', '.png', 'Photographie encadrée côte bretonne'), 
('produit37_1', 'media/produits/', '.png', 'Magnet décoratif triskell'), 
('produit38_1', 'media/produits/', '.png', 'Bougie parfumée "bord de mer"'), 
('produit39_1', 'media/produits/', '.png', 'Savon artisanal sel marin'), 
('produit40_1', 'media/produits/', '.jpg', 'Savon lait de chèvre breton'), 
('produit41_1', 'media/produits/', '.png', 'Céramique motif celtique'),
('produit42_1', 'media/produits/', '.jpg', 'Objet déco granit breton'), 
('produit43_1', 'media/produits/', '.jpg', 'Broderie nappe motif breton'), 
('produit44_1', 'media/produits/', '.jpg', 'Attrape-rêves celte'),
('produit45_1', 'media/produits/', '.jpg', 'Porte-clés triskell hermine'), 
('produit46_1', 'media/produits/', '.png', 'Suspension murale "Bienvenue en Bretagne"'),
('produit47_1', 'media/produits/', '.png', 'Coussin brodé "Kenavo"'), 
('produit48_1', 'media/produits/', '.jpg', 'Dessous de plat ardoise triskell'), 
('produit49_1', 'media/produits/', '.png', 'Planche à découper gravée "Bretagne"'), 
('produit50_1', 'media/produits/', '.jpg', 'Plateau apéritif décor breton'), -- ID 63
('produit51_1', 'media/produits/', '.png', 'Bougeoirs bois flotté'), -- ID 64
('produit52_1', 'media/produits/', '.png', 'Photophore "Phare breton"'), -- ID 65
('produit53_1', 'media/produits/', '.jpg', 'Carte postale artistique Bretagne'), -- ID 66
('produit54_1', 'media/produits/', '.png', 'Horloge murale motif marin'), -- ID 67
('produit55_1', 'media/produits/', '.png', 'Cabane à oiseaux décorative bois local'), -- ID 68
('produit56_1', 'media/produits/', '.jpg', 'Marinière bretonne'), -- ID 69
('produit57_1', 'media/produits/', '.png', 'Bonnet marin'), -- ID 70
('produit58_1', 'media/produits/', '.png', 'Écharpe en laine bretonne'), -- ID 71
('produit59_1', 'media/produits/', '.png', 'Chaussettes tricotées Bretagne'), -- ID 72
('produit60_1', 'media/produits/', '.jpg', 'Cabas "Bretagne" toile de jute'), -- ID 73
('produit61_1', 'media/produits/', '.jpg', 'Sac voile bateau recyclée'), -- ID 74
('produit62_1', 'media/produits/', '.png', 'Bracelet triskell cuir'), -- ID 75
('produit63_1', 'media/produits/', '.png', 'Collier argent "triskell"'), -- ID 76
('produit64_1', 'media/produits/', '.png', 'Boucles d''oreilles hermine'), -- ID 77
('produit65_1', 'media/produits/', '.png', 'T-shirt "Breizh Power"'), -- ID 78
('produit66_1', 'media/produits/', '.png', 'Casquette Armor Lux'), -- ID 79
('produit67_1', 'media/produits/', '.png', 'Ceinture toile marine'), -- ID 80
('produit68_1', 'media/produits/', '.png', 'Montre design breton'), -- ID 81
('produit69_1', 'media/produits/', '.jpg', 'Broche ancre marine'), -- ID 82
('produit70_1', 'media/produits/', '.jpg', 'Parapluie breton triskell'), -- ID 83
('produit71_1', 'media/produits/', '.jpg', 'Porte-monnaie cuir breton'), -- ID 84
('produit72_1', 'media/produits/', '.png', 'Ciré jaune breton'), -- ID 85
('produit73_1', 'media/produits/', '.jpg', 'Blouse de pêcheur'), -- ID 86
('produit74_1', 'media/produits/', '.jpg', 'Cabas "Douarnenez" tissu imprimé'), -- ID 87
('produit75_1', 'media/produits/', '.jpg', 'Foulard motif celtique'), -- ID 88
('produit76_1', 'media/produits/', '.jpg', 'Bracelet corde marine'), -- ID 89
('produit77_1', 'media/produits/', '.png', 'Bague triskell argent'), -- ID 90
('produit78_1', 'media/produits/', '.png', 'Porte-clés cuir gravé "Breizh"'), -- ID 91
('produit79_1', 'media/produits/', '.jpg', 'Chaussons feutre Bretagne'), -- ID 92
('produit80_1', 'media/produits/', '.jpg', 'Chapeau breton traditionnel'), -- ID 93
('produit81_1', 'media/produits/', '.jpg', 'Livre recettes bretonnes'), -- ID 94
('produit82_1', 'media/produits/', '.jpg', 'Roman breton contemporain'), -- ID 95
('produit83_1', 'media/produits/', '.jpg', 'BD "Les Bidochon"'), -- ID 96
('produit84_1', 'media/produits/', '.jpg', 'Livre légendes bretonnes'), -- ID 97
('produit85_1', 'media/produits/', '.jpg', 'Guide touristique Bretagne'), -- ID 98
('produit86_1', 'media/produits/', '.png', 'Carte illustrée phares bretons'), -- ID 99
('produit87_1', 'media/produits/', '.jpg', 'CD musique celtique bretonne'), -- ID 100
('produit88_1', 'media/produits/', '.jpg', 'CD chants marins'), -- ID 101
('produit89_1', 'media/produits/', '.jpg', 'DVD danses bretonnes'), -- ID 102
('produit90_1', 'media/produits/', '.jpg', 'Poster drapeaux bretons'), -- ID 103
('produit91_1', 'media/produits/', '.jpg', 'Carnet croquis Côte d''Armor'), -- ID 104
('produit92_1', 'media/produits/', '.png', 'Puzzle phares de Bretagne'), -- ID 105
('produit93_1', 'media/produits/', '.jpg', 'Jeu de société "Découvre la Bretagne"'), -- ID 106
('produit94_1', 'media/produits/', '.jpg', 'Livre photo "Bretagne Sauvage"'), -- ID 107
('produit95_1', 'media/produits/', '.jpg', 'Agenda breton illustré'), -- ID 108
('produit96_1', 'media/produits/', '.jpg', 'Livre enfants "Contes de Bretagne"'), -- ID 109
('produit97_1', 'media/produits/', '.jpg', 'Carte sentiers côtiers GR34'), -- ID 110
('produit98_1', 'media/produits/', '.png', 'Recueil de poèmes bretons'), -- ID 111
('produit99_1', 'media/produits/', '.png', 'Calendrier des marées breton'), -- ID 112
('produit100_1', 'media/produits/', '.jpg', 'Roman historique Anne de Bretagne'), -- ID 113
('produit101_1', 'media/produits/', '.jpg', 'Mug Collector StarBrew'), -- ID 114
('produit102_1', 'media/produits/', '.jpg', 'T-shirt Retro Pixel'), -- ID 115
('produit103_1', 'media/produits/', '.jpg', 'Figurine Super Cat'), -- ID 116
('produit104_1', 'media/produits/', '.png', 'Poster Edition Limitée Synthwave'), -- ID 117
('produit105_1', 'media/produits/', '.JPG', 'Casquette GamerX'), -- ID 118 (Attention: majuscule JPG)
('produit106_1', 'media/produits/', '.png', 'Porte-clés DragonFire'), -- ID 119
('produit107_1', 'media/produits/', '.jpg', 'Tote Bag Retro Comics'), -- ID 120
('produit108_1', 'media/produits/', '.jpg', 'Carnet Space Travel'), -- ID 121
('produit109_1', 'media/produits/', '.jpg', 'Sweat HeroLeague'), -- ID 122
('produit110_1', 'media/produits/', '.png', 'Lampe Néon PixelHeart'), -- ID 123
('profil1', 'media/profils/', '.jpg', 'photo profil client 1'),
('profil2', 'media/profils/', '.jpg', 'photo profil client 2'),
('profil3', 'media/profils/', '.jpg', 'photo profil client 3'),
('profil4', 'media/profils/', '.jpg', 'photo profil client 4'),
('profil5', 'media/profils/', '.jpg', 'photo profil client 5'),
('vendeur1', 'media/vendeurs/', '.png', 'logo vendeur 1'),
('vendeur2', 'media/vendeurs/', '.png', 'logo vendeur 2'),
('vendeur3', 'media/vendeurs/', '.png', 'logo vendeur 3'),
('categ_alim', 'media/categories/', '.jpg', 'catégorie alimentation'),
('categ_artisanat', 'media/categories/', '.jpg', 'catégorie artisanat'),
('categ_mode', 'media/categories/', '.jpg', 'catégorie mode'),
('categ_culture', 'media/categories/', '.jpg', 'catégorie culture'),
('categ_derived', 'media/categories/', '.jpg', 'catégorie produits dérivés'),
('avis1_galettes', 'media/avis/', '.jpg', 'Galettes bien croustillantes par le client'),   -- ID 125
('avis2_crepes', 'media/avis/', '.jpg', 'Crêpes sucrées bien faites par le client'),      -- ID 126
('avis3_kouign', 'media/avis/', '.jpg', 'Kouign-amann découpé par le client'),
('default_image', 'media/universel/', 'jpg', 'image par defaut');

-- 2️⃣ Comptes clients
INSERT INTO _compte ("login", mot_de_passe, mail, tel, id_image) VALUES
('cdupont', 'mdp123', 'cdupont@mail.com', '0612345678', 111),
('mlefevre', 'mdp123', 'mlefevre@mail.com', '0623456789', 112),
('tbernard', 'mdp123', 'tbernard@mail.com', '0634567890', 113),
('ljoly', 'mdp123', 'ljoly@mail.com', '0645678901', 114),
('fmartin', 'mdp123', 'fmartin@mail.com', '0656789012', 115),
('vendeurA', 'mdp123', 'contact@vendeurA.com', '0667890123', 116),
('vendeurB', 'mdp123', 'contact@vendeurB.com', '0678901234', 117),
('vendeurC', 'mdp123', 'contact@vendeurC.com', '0689012345', 118);

-- 4️⃣ Clients (on suppose que les id_compte sont 1 à 5 pour les clients)
INSERT INTO _client (id_client, nom, prenom, date_naissance, compe_bloquer) VALUES
(1, 'Dupont', 'Claire', '1990-05-12', false),
(2, 'Lefevre', 'Marc', '1988-02-20', false),
(3, 'Bernard', 'Thomas', '1995-09-15', false),
(4, 'Joly', 'Lucie', '1992-07-08', false),
(5, 'Martin', 'François', '1985-12-01', false);

-- 5️⃣ Vendeurs (id_compte 6 à 8)
INSERT INTO _vendeur (id_vendeur, nom_entreprise, siret, description_vendeur, comission_cobrec) VALUES
(6, 'Vendeur A SARL', '12345678900011', 'Spécialiste en produits bio régionaux.', 0.10),
(7, 'Vendeur B SAS', '98765432100022', 'Entreprise de vente de vêtements éthiques.', 0.12),
(8, 'Vendeur C SARL', '19283746500033', 'Vente d’accessoires technologiques.', 0.08);


-- ==============================
-- PEUPLEMENT DES CATÉGORIES
-- ==============================

-- Insertion des catégories principales
INSERT INTO _categorieProduit (nom_categ, description_categ, id_image) VALUES
('Alimentation & Boissons', 'Produits régionaux, confitures, boissons artisanales, etc.', 1),
('Artisanat & Décoration', 'Objets faits main, décorations traditionnelles et modernes.', 2),
('Mode & Accessoires', 'Vêtements, bijoux, accessoires et maroquinerie artisanale.', 3),
('Produits Culturels', 'Livres, CD, œuvres d''art et articles culturels.', 4),
('Produits Dérivés', 'Objets dérivés de films, séries, jeux ou univers pop culture.', 5);





-- ==============================
-- PRODUITS - Alimentation & Boissons (catégorie = 1) - 30 produits
-- ==============================
INSERT INTO _produit (nom_produit, description_prod, prix_ht, dep_origine, ville_origine, pays_origine, date_prod, nb_ventes, id_image, id_categ, id_vendeur) VALUES
('Galettes de blé noir (sarrasin)', 'Galettes bio au sarrasin, recette traditionnelle bretonne.', 3.90, 'Finistère', 'Quimper', 'France', CURRENT_DATE, 0, 1, 1, 6),
('Crêpes sucrées bretonnes', '12 crêpes de froment Keltia Nevez, sucrées.', 2.80, 'Côtes-d''Armor', 'Saint-Brieuc', 'France', CURRENT_DATE, 0, 2, 1, 6),
('Kouign-amann', 'Gâteau breton beurré et sucré, spécialité de Bretagne.', 4.50, 'Finistère', 'Quimper', 'France', CURRENT_DATE, 0, 3, 1, 7),
('Far breton aux pruneaux', 'Far aux pruneaux traditionnel breton.', 4.20, 'Ille-et-Vilaine', 'Rennes', 'France', CURRENT_DATE, 0, 4, 1, 7),
('Palets bretons', 'Mini gâteaux bretons natures, sachet individuel ½ kg.', 5.90, 'Côtes-d''Armor', 'Lannion', 'France', CURRENT_DATE, 0, 5, 1, 6),
('Caramel au beurre salé', 'Crème de caramel au beurre salé 220 g.', 4.80, 'Morbihan', 'Vannes', 'France', CURRENT_DATE, 0, 6, 1, 8),
('Confiture de lait sel de Guérande', 'Confiture de lait artisanale à la fleur de sel de Guérande.', 6.50, 'Loire-Atlantique', 'Nantes', 'France', CURRENT_DATE, 0, 7, 1, 6),
('Miel de Bretagne', 'Miel pur de Bretagne, récolte locale.', 7.20, 'Ille-et-Vilaine', 'Saint-Malo', 'France', CURRENT_DATE, 0, 8, 1, 7),
('Sel de Guérande IGP 1kg', 'Sel marin de Guérande IGP, sac 1 kg.', 3.50, 'Loire-Atlantique', 'Guérande', 'France', CURRENT_DATE, 0, 9, 1, 7),
('Fleur de sel de Guérande aromatisée 250 g', 'Sel de Guérande aux herbes aromatiques.', 4.30, 'Loire-Atlantique', 'Guérande', 'France', CURRENT_DATE, 0, 10, 1, 6),
('Beurre demi-sel artisanal', 'Beurre traditionnel demi-sel, fabriqué artisanalement.', 5.40, 'Côtes-d''Armor', 'Paimpol', 'France', CURRENT_DATE, 0, 11, 1, 8),
('Rillettes de maquereau aux oignons roses', 'Rillettes de maquereau breton, avec oignons roses de Bretagne et poivre de São Tomé.', 6.80, 'Finistère', 'Douarnenez', 'France', CURRENT_DATE, 0, 12, 1, 7),
('Rillettes de sardine citron algues', 'Rillettes de sardines aux zestes de citron et aux algues de Bretagne.', 5.90, 'Morbihan', 'Lorient', 'France', CURRENT_DATE, 0, 13, 1, 6),
('Rillettes de noix de St-Jacques 90 g', 'Rillettes de noix de Saint-Jacques artisanales.', 8.50, 'Côtes-d''Armor', 'Tréguier', 'France', CURRENT_DATE, 0, 14, 1, 8),
('Conserve thon blanc Germon 160 g', 'Thon Germon au naturel, boîte 160 g.', 7.90, 'Finistère', 'Roscoff', 'France', CURRENT_DATE, 0, 15, 1, 7),
('Soupe de poissons artisanale 1L', 'Soupe de poissons bretonne artisanale, litre.', 9.50, 'Ille-et-Vilaine', 'Saint-Malo', 'France', CURRENT_DATE, 0, 16, 1, 6),
('Cidre brut breton traditionnel', 'Cidre brut pur jus, IGP Bretagne.', 4.10, 'Ille-et-Vilaine', 'Rennes', 'France', CURRENT_DATE, 0, 17, 1, 8),
('Cidre doux breton IGP bio', 'Cidre doux bio, IGP Bretagne.', 4.60, 'Finistère', 'Brest', 'France', CURRENT_DATE, 0, 18, 1, 7),
('Chouchen hydromel breton 37,5 cl', 'Hydromel breton traditionnel, bouteille 37,5 cl.', 12.90, 'Morbihan', 'Lorient', 'France', CURRENT_DATE, 0, 19, 1, 6),
('Bière artisanale bretonne coffret', 'Coffret bière artisanale bretonne (ex : Britt, Coreff, Philomenn).', 18.50, 'Bretagne', 'Saint-Brieuc', 'France', CURRENT_DATE, 0, 20, 1, 8),
('Whisky breton Celtic Kornog', 'Whisky breton single malt, édition limitée.', 45.00, 'Bretagne', 'Lannion', 'France', CURRENT_DATE, 0, 21, 1, 7),
('Liqueur fraise de Plougastel 50 cl', 'Liqueur de fraise de Plougastel, bouteille 50 cl.', 22.00, 'Finistère', 'Plougastel-Daoulas', 'France', CURRENT_DATE, 0, 22, 1, 6),
('Jus de pomme artisanal breton 1L', 'Jus de pomme pur jus artisanal breton, litre.', 3.70, 'Côtes-d''Armor', 'Lannion', 'France', CURRENT_DATE, 0, 23, 1, 8),
('Galettes de Pont-Aven 130 g', 'Traou Mad - Galettes de Pont-Aven spécialité bretonne 130 g.', 4.20, 'Finistère', 'Pont-Aven', 'France', CURRENT_DATE, 0, 24, 1, 7),
('Sablés bretons pur beurre pépites chocolat', 'Sablés pur beurre aux pépites de chocolat.', 5.80, 'Ille-et-Vilaine', 'Rennes', 'France', CURRENT_DATE, 0, 25, 1, 6),
('Gâteau breton fourré confiture 350 g', 'Gâteau breton fourré à la confiture, 350 g.', 6.10, 'Morbihan', 'Vannes', 'France', CURRENT_DATE, 0, 26, 1, 7),
('Pâté breton de campagne', 'Pâté breton de campagne traditionnel.', 7.20, 'Finistère', 'Quimper', 'France', CURRENT_DATE, 0, 27, 1, 6),
('Andouille bretonne traditionnelle', 'Andouille bretonne artisanale.', 8.40, 'Côtes-d''Armor', 'Guingamp', 'France', CURRENT_DATE, 0, 28, 1, 8),
('Kig ha farz plat traditionnel Léon', 'Kig ha farz - plat traditionnel du Léon.', 14.50, 'Finistère', 'Morlaix', 'France', CURRENT_DATE, 0, 29, 1, 7),
('Chocolat artisanal au sel de Guérande', 'Tablette chocolat artisanal au sel de Guérande.', 4.90, 'Loire-Atlantique', 'Guérande', 'France', CURRENT_DATE, 0, 30, 1, 6),
('Bol breton personnalisé avec prénom', 'Bol breton en céramique personnalisable.', 28.00, 'Finistère', 'Quimper', 'France', CURRENT_DATE, 0, 31, 2, 7),
('Faïence de Quimper authentique', 'Pièce de faïence de Quimper peinte à la main.', 45.00, 'Finistère', 'Quimper', 'France', CURRENT_DATE, 0, 32, 2, 8),
('Figurine en céramique bretonne', 'Figurine artisanale motif breton.', 22.00, 'Côtes-d''Armor', 'Lannion', 'France', CURRENT_DATE, 0, 33, 2, 6),
('Sculpture sur bois bretonne', 'Sculpture en bois recyclé inspirée de la Bretagne.', 55.00, 'Morbihan', 'Vannes', 'France', CURRENT_DATE, 0, 34, 2, 8),
('Tableau marin ou paysage breton', 'Tableau décoratif paysage marin breton.', 65.00, 'Ille-et-Vilaine', 'Saint-Malo', 'France', CURRENT_DATE, 0, 35, 2, 7),
('Photographie encadrée de la côte bretonne', 'Photo d''art encadrée de la côte bretonne.', 38.00, 'Finistère', 'Brest', 'France', CURRENT_DATE, 0, 36, 2, 6),
('Magnet décoratif triskell', 'Magnet décoratif métal triskell hermine.', 8.90, 'Ille-et-Vilaine', 'Rennes', 'France', CURRENT_DATE, 0, 37, 2, 7),
('Bougie parfumée "bord de mer"', 'Bougie artisanale parfum "bord de mer".', 18.00, 'Var', 'Toulon', 'France', CURRENT_DATE, 0, 38, 2, 8),
('Savon artisanal sel marin', 'Savon artisanal au sel marin de Bretagne.', 12.50, 'Côtes-d''Armor', 'Saint-Brieuc', 'France', CURRENT_DATE, 0, 39, 2, 6),
('Savon au lait de chèvre breton', 'Savon bio au lait de chèvre breton.', 14.90, 'Finistère', 'Quimper', 'France', CURRENT_DATE, 0, 40, 2, 7),
('Céramique motif celtique', 'Tasse en céramique motif triskell celtique.', 20.00, 'Morbihan', 'Lorient', 'France', CURRENT_DATE, 0, 41, 2, 8),
('Objet déco granit breton', 'Sculpture décorative en granit breton.', 48.00, 'Côtes-d''Armor', 'Ploumanac''h', 'France', CURRENT_DATE, 0, 42, 2, 6),
('Broderie ou nappe motif breton', 'Nappe brodée motif breton bleuenn-carre.', 32.00, 'Finistère', 'Douarnenez', 'France', CURRENT_DATE, 0, 43, 2, 7),
('Attrape-rêves celte', 'Attrape-rêves en corde et perles motif celte.', 15.00, 'Morbihan', 'Auray', 'France', CURRENT_DATE, 0, 44, 2, 8),
('Porte-clés triskell ou hermine', 'Porte-clés métal triskell ou hermine.', 9.50, 'Ille-et-Vilaine', 'Rennes', 'France', CURRENT_DATE, 0, 45, 2, 6),
('Suspension murale "Bienvenue en Bretagne"', 'Suspension murale macramé motif breton.', 29.00, 'Finistère', 'Brest', 'France', CURRENT_DATE, 0, 46, 2, 7),
('Coussin brodé "Kenavo"', 'Coussin décoratif brodé "Kenavo".', 25.00, 'Côtes-d''Armor', 'Lannion', 'France', CURRENT_DATE, 0, 47, 2, 8),
('Dessous de plat ardoise triskell-poisson', 'Dessous de plat en ardoise décor triskell-poisson.', 22.00, 'Finistère', 'Lannion', 'France', CURRENT_DATE, 0, 48, 2, 6),
('Planche à découper gravée "Bretagne"', 'Planche à découper bois gravée motif Bretagne.', 35.00, 'Morbihan', 'Vannes', 'France', CURRENT_DATE, 0, 49, 2, 7),
('Plateau apéritif décor breton', 'Plateau d''apéritif décor breton drapeau Gwenn ha Du.', 28.00, 'Finistère', 'Quimper', 'France', CURRENT_DATE, 0, 50, 2, 8),
('Bougeoirs en bois flotté', 'Bougeoirs en bois flotté fabriqués à la main.', 40.00, 'Loire-Atlantique', 'Nantes', 'France', CURRENT_DATE, 0, 51, 2, 6),
('Photophore "Phare breton"', 'Photophore métal motif phare breton.', 27.00, 'Côtes-d''Armor', 'Perros-Guirec', 'France', CURRENT_DATE, 0, 52, 2, 7),
('Carte postale artistique de Bretagne', 'Carte postale illustrée de Bretagne - édition limitée.', 5.90, 'Côtes-d''Armor', 'Lannion', 'France', CURRENT_DATE, 0, 53, 2, 8),
('Horloge murale motif marin', 'Horloge murale design nautique motif marin.', 55.00, 'Paris', 'Paris', 'France', CURRENT_DATE, 0, 54, 2, 6),
('Cabane à oiseaux décorative en bois local', 'Nichoir décoratif en bois local Breton.', 32.00, 'Morbihan', 'Auray', 'France', CURRENT_DATE, 0, 55, 2, 7),
('Marinière bretonne', 'Marinière Loctudy coton épais, style marin.', 49.00, 'Finistère', 'Loctudy', 'France', CURRENT_DATE, 0, 56, 3, 6),
('Bonnet marin rayé', 'Bonnet marin en coton, rayures navire/blanc.', 24.00, 'Côtes-d''Armor', 'Tréguier', 'France', CURRENT_DATE, 0, 57, 3, 6),
('Écharpe en laine bretonne', 'Écharpe tricotée en laine épaisse, motif breton.', 39.00, 'Ille-et-Vilaine', 'Rennes', 'France', CURRENT_DATE, 0, 58, 3, 7),
('Chaussettes tricotées en Bretagne', 'Chaussettes rayées couleurs marines, fabrication artisanale.', 18.00, 'Côtes-d''Armor', 'Lannion', 'France', CURRENT_DATE, 0, 59, 3, 6),
('Cabas "Bretagne" toile de jute', 'Sac cabas imprimé "Bretagne", toile de jute.', 29.00, 'Finistère', 'Quimper', 'France', CURRENT_DATE, 0, 60, 3, 8),
('Sac en voile de bateau recyclée', 'Sac à main issu de voile recyclée type 727 Sailbags.', 85.00, 'Loire-Atlantique', 'Nantes', 'France', CURRENT_DATE, 0, 61, 3, 8),
('Bracelet triskell cuir', 'Bracelet en cuir et métal avec motif triskell.', 22.00, 'Morbihan', 'Auray', 'France', CURRENT_DATE, 0, 62, 3, 7),
('Collier en argent "triskell"', 'Bijou pendentif triskell en acier inoxydable.', 33.00, 'Côtes-d''Armor', 'Saint-Brieuc', 'France', CURRENT_DATE, 0, 63, 3, 8),
('Boucles d''oreilles hermine', 'Boucles d''oreilles motif hermine bretonne.', 26.00, 'Finistère', 'Quimper', 'France', CURRENT_DATE, 0, 64, 3, 7),
('T-shirt "Breizh Power"', 'T-shirt coton bio imprimé "Breizh Power".', 21.00, 'Seine-Maritime', 'Rouen', 'France', CURRENT_DATE, 0, 65, 3, 6),
('Casquette Armor Lux', 'Casquette brodée marine, marque Armor Lux.', 32.00, 'Morbihan', 'Lorient', 'France', CURRENT_DATE, 0, 66, 3, 8),
('Ceinture toile marine', 'Ceinture tissu bleu marine, style marin.', 19.00, 'Essonne', 'Évry', 'France', CURRENT_DATE, 0, 67, 3, 6),
('Montre design breton', 'Montre au design breton, cadran Gwenn ha Du.', 95.00, 'Paris', 'Paris', 'France', CURRENT_DATE, 0, 68, 3, 7),
('Broche ancre marine', 'Broche décorative ancre marine en métal.', 14.00, 'Nord', 'Lille', 'France', CURRENT_DATE, 0, 69, 3, 8),
('Parapluie breton triskell', 'Parapluie motif triskell, structure renforcée.', 27.00, 'Finistère', 'Brest', 'France', CURRENT_DATE, 0, 70, 3, 6),
('Porte-monnaie cuir breton', 'Portefeuille cuir motif triskell-hermines.', 44.00, 'Côtes-d''Armor', 'Guingamp', 'France', CURRENT_DATE, 0, 71, 3, 7),
('Ciré jaune breton', 'Ciré marin mixte, couleur jaune, fabrication marine.', 59.00, 'Morbihan', 'Vannes', 'France', CURRENT_DATE, 0, 72, 3, 8),
('Blouse de pêcheur', 'Blouse de pêcheur bretonne, coton épais.', 39.00, 'Finistère', 'Douarnenez', 'France', CURRENT_DATE, 0, 73, 3, 6),
('Cabas "Douarnenez" tissu imprimé', 'Tote bag Douarnenez imprimé motif côtier.', 24.00, 'Morbihan', 'Douarnenez', 'France', CURRENT_DATE, 0, 74, 3, 7),
('Foulard motif celtique', 'Foulard léger motif triskell/celtique.', 28.00, 'Ille-et-Vilaine', 'Rennes', 'France', CURRENT_DATE, 0, 75, 3, 8),
('Bracelet corde marine', 'Bracelet nautique en corde marine et métal.', 18.00, 'Bretagne', 'Saint-Malo', 'France', CURRENT_DATE, 0, 76, 3, 6),
('Bague triskell argent', 'Bague argent 925 motif triskell.', 55.00, 'Côtes-d''Armor', 'Paimpol', 'France', CURRENT_DATE, 0, 77, 3, 7),
('Porte-clés cuir gravé "Breizh"', 'Porte-clés en cuir gravé motif "Breizh".', 12.00, 'Finistère', 'Quimper', 'France', CURRENT_DATE, 0, 78, 3, 8),
('Chaussons feutre Bretagne', 'Chaussons feutre laine anthracite motif Bretagne.', 34.00, 'Côtes-d''Armor', 'Lannion', 'France', CURRENT_DATE, 0, 79, 3, 6),
('Chapeau breton traditionnel', 'Chapeau breton ruban velours, artisanat local.', 48.00, 'Ille-et-Vilaine', 'Rennes', 'France', CURRENT_DATE, 0, 80, 3, 7),
('Livre de recettes bretonnes', 'Recueil illustré de recettes traditionnelles bretonnes.', 17.00, 'Bretagne', 'Saint-Brieuc', 'France', CURRENT_DATE, 0, 81, 4, 6),
('Roman breton contemporain', 'Roman contemporain situé en Bretagne.', 14.00, 'Bretagne', 'Lorient', 'France', CURRENT_DATE, 0, 82, 4, 7),
('BD "Les Bidochon" (édition Bretagne)', 'BD humoristique, édition spéciale Bretagne.', 12.00, 'Pays de la Loire', 'Nantes', 'France', CURRENT_DATE, 0, 83, 4, 8),
('Livre sur les légendes bretonnes', 'Livre illustré sur les légendes de Bretagne.', 18.00, 'Bretagne', 'Vannes', 'France', CURRENT_DATE, 0, 84, 4, 6),
('Guide touristique Bretagne', 'Guide touristique complet de la Bretagne.', 19.00, 'Bretagne', 'Brest', 'France', CURRENT_DATE, 0, 85, 4, 7),
('Carte illustrée des phares bretons', 'Carte artistique des phares de Bretagne.', 8.90, 'Côtes-d''Armor', 'Lannion', 'France', CURRENT_DATE, 0, 86, 4, 8),
('CD de musique celtique bretonne', 'Compilation de musique celtique de Bretagne.', 11.00, 'Bretagne', 'Quimper', 'France', CURRENT_DATE, 0, 87, 4, 6),
('CD de chants marins', 'Album de chants marins de toutes les mers.', 13.00, 'Finistère', 'Brest', 'France', CURRENT_DATE, 0, 88, 4, 7),
('DVD de danses bretonnes', 'DVD sur les danses traditionnelles bretonnes.', 15.00, 'Bretagne', 'Vannes', 'France', CURRENT_DATE, 0, 89, 4, 8),
('Poster des drapeaux bretons (Gwenn ha Du)', 'Poster grand format drapeau breton Gwenn ha Du.', 10.00, 'Ille-et-Vilaine', 'Rennes', 'France', CURRENT_DATE, 0, 90, 4, 6),
('Carnet de croquis "Côte d''Armor"', 'Carnet à dessin illustré Côte d''Armor 2022.', 16.00, 'Côtes-d''Armor', 'Saint-Brieuc', 'France', CURRENT_DATE, 0, 91, 4, 7),
('Puzzle des phares de Bretagne', 'Puzzle 1000 pièces phares de Bretagne.', 20.00, 'Finistère', 'Quimper', 'France', CURRENT_DATE, 0, 92, 4, 8),
('Jeu de société "Découvre la Bretagne"', 'Jeu quiz-culture Bretagne pour toute la famille.', 24.00, 'Bretagne', 'Vannes', 'France', CURRENT_DATE, 0, 93, 4, 6),
('Livre photo "Bretagne Sauvage"', 'Livre photo haut de gamme sur la Bretagne sauvage.', 30.00, 'Morbihan', 'Auray', 'France', CURRENT_DATE, 0, 94, 4, 7),
('Agenda breton illustré', 'Agenda de poche illustré Bretagne 2025.', 9.90, 'Ille-et-Vilaine', 'Rennes', 'France', CURRENT_DATE, 0, 95, 4, 8),
('Livre pour enfants "Les contes de Bretagne"', 'Livre illustré contes de Bretagne pour enfants.', 12.50, 'Bretagne', 'Brest', 'France', CURRENT_DATE, 0, 96, 4, 6),
('Carte des sentiers côtiers (GR34)', 'Carte randonnée sentiers côtiers Bretagne GR34.', 14.00, 'Côtes-d''Armor', 'Lannion', 'France', CURRENT_DATE, 0, 97, 4, 7),
('Recueil de poèmes bretons', 'Recueil poétique bilingue français-breton.', 11.00, 'Finistère', 'Douarnenez', 'France', CURRENT_DATE, 0, 98, 4, 8),
('Calendrier des marées breton', 'Calendrier marées édition 2025 Bretagne.', 8.50, 'Ille-et-Vilaine', 'Saint-Malo', 'France', CURRENT_DATE, 0, 99, 4, 6),
('Roman historique "Anne de Bretagne"', 'Roman historique sur Anne de Bretagne.', 17.00, 'Loire-Atlantique', 'Nantes', 'France', CURRENT_DATE, 0, 100, 4, 7),
('Mug Collector StarBrew', 'Mug en céramique inspiré d''une célèbre saga spatiale.', 14.90, 'Ille-et-Vilaine', 'Rennes', 'France', CURRENT_DATE, 0, 101, 5, 6),
('T-shirt Retro Pixel', 'T-shirt en coton bio avec motif pixel-art rétro.', 24.90, 'Gironde', 'Bordeaux', 'France', CURRENT_DATE, 0, 102, 5, 6),
('Figurine Super Cat', 'Figurine en résine peinte à la main du héros Super Cat.', 39.90, 'Loire-Atlantique', 'Nantes', 'France', CURRENT_DATE, 0, 103, 5, 7),
('Poster Edition Limitée - Synthwave', 'Affiche grand format au design néon synth-wave.', 19.90, 'Rhône', 'Lyon', 'France', CURRENT_DATE, 0, 104, 5, 7),
('Casquette GamerX', 'Casquette noire brodée avec logo GamerX.', 29.90, 'Hauts-de-Seine', 'Boulogne-Billancourt', 'France', CURRENT_DATE, 0, 105, 5, 8),
('Porte-clés DragonFire', 'Porte-clés métal inspiré jeu de rôle fantasy.', 8.90, 'Moselle', 'Metz', 'France', CURRENT_DATE, 0, 106, 5, 8),
('Tote Bag Retro Comics', 'Sac en toile imprimé couverture comics vintage.', 12.90, 'Nord', 'Lille', 'France', CURRENT_DATE, 0, 107, 5, 6),
('Carnet Space Travel', 'Carnet A5 couverture rigide thème spatial.', 9.90, 'Finistère', 'Brest', 'France', CURRENT_DATE, 0, 108, 5, 7),
('Sweat HeroLeague', 'Sweat à capuche coton avec logo HeroLeague.', 49.90, 'Paris', 'Paris', 'France', CURRENT_DATE, 0, 109, 5, 8),
('Lampe Néon PixelHeart', 'Lampe décorative forme cœur pixelisé néon.', 34.90, 'Hérault', 'Montpellier', 'France', CURRENT_DATE, 0, 110, 5, 6);

-- ==============================
-- PEUPLEMENT DE LA TVA
-- (Généré automatiquement selon le type de produit)
-- ==============================
INSERT INTO sae._tva (id_produit, type_tva, taux_tva)
SELECT 
    id_produit,
    CASE 
        -- ALCOOL (Cidres, Chouchen, Bière, Whisky, Liqueur) -> 20%
        WHEN id_produit BETWEEN 17 AND 22 THEN 'Normale'
        -- ALIMENTATION GENERALE (Reste de la cat 1) -> 5.5%
        WHEN id_categ = 1 THEN 'Réduite'
        -- LIVRES (IDs identifiés dans la cat 4) -> 5.5%
        WHEN id_produit IN (81, 82, 83, 84, 85, 91, 94, 95, 96, 100) THEN 'Réduite'
        -- PRESSE / CALENDRIERS (Considéré comme presse) -> 2.1%
        WHEN id_produit IN (98, 99) THEN 'Super-réduite'
        -- TOUT LE RESTE (Artisanat, Mode, Déco, High-tech...) -> 20%
        ELSE 'Normale'
    END,
    CASE 
        -- ALCOOL
        WHEN id_produit BETWEEN 17 AND 22 THEN 20.0
        -- ALIMENTATION
        WHEN id_categ = 1 THEN 5.5
        -- LIVRES
        WHEN id_produit IN (81, 82, 83, 84, 85, 91, 94, 95, 96, 100) THEN 5.5
        -- PRESSE
        WHEN id_produit IN (98, 99) THEN 2.1
        -- RESTE
        ELSE 20.0
    END
FROM sae._produit;


TRUNCATE TABLE _commande CASCADE; 

INSERT INTO _commande
(num_commande, frais_livraison, montant_total_ht, statut_commande, date_commande, total_nb_prod, montant_total_ttc, id_client)
VALUES
(1, 4.90, 26.7, 'Livrée', CURRENT_DATE - INTERVAL '14 days', 5, (26.7 * 1.2) + 4.90, 1),
(2, 6.00, 38.5, 'Archivée', CURRENT_DATE - INTERVAL '10 days', 5, (38.5 * 1.2) + 6.00, 2), 
(3, 5.90, 52.4, 'Expédiée', CURRENT_DATE - INTERVAL '8 days', 4, (52.4 * 1.2) + 5.90, 3), 
(4, 4.50, 61.0, 'Livrée', CURRENT_DATE - INTERVAL '5 days', 3, (61.0 * 1.2) + 4.50, 4), 
(5, 6.00, 29.5, 'En attente de paiement', CURRENT_DATE - INTERVAL '3 days', 3, (29.5 * 1.2) + 6.00, 5), 
(6, 4.90, 44.0, 'Expédiée', CURRENT_DATE - INTERVAL '12 days', 4, (44.0 * 1.2) + 4.90, 2), 
(7, 7.00, 95.0, 'Livrée', CURRENT_DATE - INTERVAL '6 days', 5, (95.0 * 1.2) + 7.00, 3), 
(8, 5.50, 73.0, 'Livrée', CURRENT_DATE - INTERVAL '15 days', 2, (73.0 * 1.2) + 5.50, 3), 
(9, 6.50, 83.0, 'Livrée', CURRENT_DATE - INTERVAL '15 days', 4, (83.0 * 1.2) + 6.50, 4), 
(10, 3.90, 63.0, 'Expédiée', CURRENT_DATE - INTERVAL '4 days', 3, (63.0 * 1.2) + 3.90, 5), 
(12, 5.00, 15.0, 'En préparation', CURRENT_DATE, 1, 18.00, 2), -- Panier actif Client 2
(13, 5.00, 15.0, 'En préparation', CURRENT_DATE, 1, 18.00, 3), -- Panier actif Client 3
(14, 6.50, 83.0, 'En préparation', CURRENT_DATE, 1, 99.00, 4), -- Panier actif Client 4
(15, 6.00, 29.5, 'En préparation', CURRENT_DATE, 1, 35.50, 5); -- Panier actif Client 5


-- TABLE : _ligneCommande
truncate table _ligneCommande;


INSERT INTO _ligneCommande 
(id_produit, num_commande, quantite_prod, total_ttc, total_ligne_commande_ht, total_ligne_commande_ttc)
VALUES
(1, 1, 2, 9.36, 7.8, 9.36),
(2, 1, 1, 3.36, 2.8, 3.36),
(5, 1, 1, 7.08, 5.9, 7.08),
(6, 1, 1, 5.76, 4.8, 5.76),
(7, 1, 1, 7.80, 6.5, 7.80),
(9, 2, 1, 4.20, 3.5, 4.20),
(10, 2, 2, 10.32, 8.6, 10.32),
(11, 2, 1, 6.48, 5.4, 6.48),
(12, 2, 1, 8.16, 6.8, 8.16),
(13, 2, 1, 7.08, 5.9, 7.08),
(15, 3, 1, 9.48, 7.9, 9.48),
(16, 3, 1, 11.40, 9.5, 11.40),
(17, 3, 2, 9.84, 8.2, 9.84),
(18, 3, 1, 5.52, 4.6, 5.52),
(19, 4, 1, 15.48, 12.9, 15.48),
(20, 4, 2, 44.40, 37.0, 44.40),
(22, 4, 1, 26.40, 22.0, 26.40),
(23, 5, 1, 4.44, 3.7, 4.44),
(24, 5, 1, 5.04, 4.2, 5.04),
(25, 5, 2, 13.92, 11.6, 13.92),
(31, 6, 1, 33.60, 28.0, 33.60),
(33, 6, 1, 26.40, 22.0, 26.40),
(34, 6, 1, 66.00, 55.0, 66.00),
(35, 6, 1, 78.00, 65.0, 78.00),
(41, 7, 1, 24.00, 20.0, 24.00),
(43, 7, 1, 38.40, 32.0, 38.40),
(45, 7, 2, 22.80, 19.0, 22.80),
(47, 7, 1, 30.00, 25.0, 30.00),
(49, 7, 1, 42.00, 35.0, 42.00),
(56, 8, 1, 58.80, 49.0, 58.80),
(57, 8, 1, 28.80, 24.0, 28.80),
(61, 9, 1, 102.00, 85.0, 102.00),
(62, 9, 1, 26.40, 22.0, 26.40),
(63, 9, 1, 39.60, 33.0, 39.60),
(64, 9, 1, 31.20, 26.0, 31.20),
(81, 10, 1, 20.40, 17.0, 20.40),
(82, 10, 1, 16.80, 14.0, 16.80),
(83, 10, 1, 14.40, 12.0, 14.40);



truncate table _avis cascade;

INSERT INTO sae._avis (note, titre_avis, commentaire, date_avis, avis_verif, votes_utiles, votes_inutiles, signale, raison_signalement, epingle, id_image, id_client, id_produit) VALUES
(5, 'Parfait pour le petit-déj', 'Ces galettes au sarrasin sont délicieuses et croustillantes. Je recommande vivement !', '2025-11-05', true, 12, 1, false, '', false, 124, 1, 1),
(4, 'Bon goût', 'Les crêpes sont très bonnes, juste un peu sucrées à mon goût.', '2025-11-07', true, 8, 0, false, '', false, 125, 2, 2),
(5, 'Une tuerie !', 'Le Kouign-amann est fondant et sucré juste comme il faut.', '2025-11-03', true, 15, 2, false, '', false, 126, 3, 3),
(4, 'Très bon', 'Le far breton aux pruneaux est authentique et savoureux.', '2025-11-08', true, 10, 1, false, '', false, 127, 4, 4),
(5, 'Mes préférés', 'Les palets bretons sont parfaits pour accompagner le café.', '2025-11-06', true, 9, 0, false, '', false, 127, 5, 5),
(4, 'Caramel délicieux', 'Le caramel au beurre salé a un goût très riche et crémeux.', '2025-11-02', true, 11, 1, false, '', false, 127, 1, 6),
(5, 'Excellente confiture', 'La confiture de lait à la fleur de sel est divine sur des crêpes.', '2025-11-04', true, 13, 0, false, '', false, 127, 2, 7),
(2, 'Miel de qualité', 'Miel très parfumé, parfait sur les tartines ou dans les yaourts.', '2025-11-09', true, 14, 1, false, '', false, 127, 3, 8),
(3, 'Sel raffiné', 'Le sel de Guérande est parfait pour assaisonner mes plats.', '2025-11-01', true, 7, 0, false, '', false, 127, 4, 9),
(4, 'Fleur de sel parfumée', 'Idéal pour les légumes et viandes, très aromatique.', '2025-11-10', true, 6, 0, false, '', false, 127, 5, 10),

(5, 'Beurre exceptionnel', 'Le beurre demi-sel artisanal a un goût unique, je l’adore.', '2025-11-05', true, 12, 0, false, '', false, 127, 1, 11),
(5, 'Rillettes top', 'Rillettes de maquereau savoureuses, texture parfaite.', '2025-11-07', true, 10, 1, false, '', false, 127, 2, 12),
(4, 'Bon produit', 'Rillettes de sardine correctes, goût citron-algues intéressant.', '2025-11-03', true, 8, 0, false, '', false, 127, 3, 13),
(5, 'Délicieux', 'Les rillettes de noix de St-Jacques sont raffinées et gourmandes.', '2025-11-06', true, 15, 2, false, '', false, 127, 4, 14),
(4, 'Thon de qualité', 'Conserve de thon Germon au naturel, très pratique et bon.', '2025-11-02', true, 9, 0, false, '', false, 127, 5, 15),
(5, 'Soupe excellente', 'La soupe de poissons artisanale est très savoureuse et réconfortante.', '2025-11-08', true, 11, 1, false, '', false, 127, 1, 16),
(4, 'Cidre correct', 'Cidre brut agréable, goût traditionnel.', '2025-11-09', true, 6, 0, false, '', false, 127, 2, 17),
(5, 'Cidre doux top', 'Le cidre doux bio est délicieux, pas trop sucré.', '2025-11-07', true, 10, 1, false, '', false, 127, 3, 18),
(4, 'Hydromel original', 'Le chouchen est agréable, goût doux et fruité.', '2025-11-05', true, 7, 0, false, '', false, 127, 4, 19),
(5, 'Bière artisanale', 'Coffret de bières bretonnes variées et de qualité.', '2025-11-06', true, 12, 2, false, '', false, 127, 5, 20),

(5, 'Whisky impressionnant', 'Whisky breton Celtic Kornog très aromatique, excellent pour un digestif.', '2025-11-03', true, 14, 1, false, '', false, 127, 1, 21),
(4, 'Liqueur fruitée', 'Liqueur de fraise parfumée et agréable.', '2025-11-04', true, 9, 0, false, '', false, 127, 2, 22),
(5, 'Jus artisanal', 'Jus de pomme excellent, goût naturel et sucré juste comme il faut.', '2025-11-02', true, 10, 1, false, '', false, 127, 3, 23),
(2, 'Galettes savoureuses', 'Galettes de Pont-Aven authentiques et croustillantes.', '2025-11-08', true, 13, 0, false, '', false, 127, 4, 24),
(4, 'Sablés bons', 'Sablés au chocolat bons mais un peu trop sucrés.', '2025-11-06', true, 7, 0, false, '', false, 127, 5, 25),
(5, 'Gâteau breton parfait', 'Gâteau breton fourré à la confiture, moelleux et savoureux.', '2025-11-05', true, 11, 1, false, '', false, 127, 1, 26),
(4, 'Pâté correct', 'Pâté breton de campagne traditionnel, goût authentique.', '2025-11-07', true, 8, 0, false, '', false, 127, 2, 27),
(5, 'Excellente andouille', 'Andouille bretonne savoureuse, fumée comme il faut.', '2025-11-03', true, 12, 0, false, '', false, 127, 3, 28),
(4, 'Plat traditionnel', 'Kig ha farz copieux et authentique, parfait pour un repas en famille.', '2025-11-06', true, 9, 1, false, '', false, 127, 4, 29),
(5, 'Chocolat top', 'Tablette de chocolat au sel de Guérande, original et délicieux.', '2025-11-02', true, 10, 0, false, '', false, 127, 5, 30),

(5, 'Bol personnalisé', 'Bol breton magnifique, rendu parfait avec le prénom gravé.', '2025-11-08', true, 13, 1, false, '', false, 127, 1, 31),
(5, 'Faïence superbe', 'Pièce de faïence de Quimper peinte à la main, magnifique.', '2025-11-05', true, 14, 0, false, '', false, 127, 2, 32),
(4, 'Figurine sympa', 'Figurine bretonne très jolie, bonne qualité.', '2025-11-07', true, 9, 0, false, '', false, 127, 3, 33),
(5, 'Sculpture originale', 'Sculpture sur bois bretonne superbe, très artisanale.', '2025-11-06', true, 12, 1, false, '', false, 127, 4, 34),
(4, 'Tableau magnifique', 'Tableau marin breton très réaliste et décoratif.', '2025-11-09', true, 10, 0, false, '', false, 127, 5, 35),
(5, 'Photo superbe', 'Photographie encadrée de la côte bretonne, excellente qualité.', '2025-11-03', true, 11, 1, false, '', false, 127, 1, 36),
(4, 'Magnet sympa', 'Magnet triskell joli et bien fini.', '2025-11-05', true, 8, 0, false, '', false, 127, 2, 37),
(5, 'Bougie parfumée', 'Bougie artisanale parfum bord de mer, ambiance cosy.', '2025-11-06', true, 9, 1, false, '', false, 127, 3, 38),
(4, 'Savon agréable', 'Savon au sel marin, doux et parfumé.', '2025-11-07', true, 7, 0, false, '', false, 127, 4, 39),
(5, 'Savon au lait de chèvre top', 'Savon bio très doux et agréable pour la peau.', '2025-11-02', true, 10, 0, false, '', false, 127, 5, 40),

(5, 'Céramique élégante', 'Tasse en céramique motif celtique très jolie.', '2025-11-03', true, 12, 1, false, '', false, 127, 1, 41),
(4, 'Objet déco solide', 'Sculpture en granit breton, belle qualité.', '2025-11-05', true, 8, 0, false, '', false, 127, 2, 42),
(5, 'Nappe brodée superbe', 'Broderie très fine, motif breton réussi.', '2025-11-06', true, 9, 1, false, '', false, 127, 3, 43),
(4, 'Attrape-rêves joli', 'Attrape-rêves celte bien fait et décoratif.', '2025-11-07', true, 7, 0, false, '', false, 127, 4, 44),
(4, 'Porte-clés joli', 'Porte-clés triskell ou hermine.', '2025-11-05', TRUE, 9, 1, FALSE, '', FALSE, 127, 5, 45),
(5, 'Suspension murale', 'Suspension murale macramé motif breton.', '2025-11-06', TRUE, 14, 0, FALSE, '', FALSE, 127, 1, 46),
(4, 'Coussin doux', 'Coussin décoratif brodé "Kenavo".', '2025-11-08', TRUE, 7, 1, FALSE, '', FALSE, 127, 2, 47),
(2, 'Dessous de plat', 'Dessous de plat en ardoise décor triskell-poisson.', '2025-11-02', TRUE, 10, 0, FALSE, '', FALSE, 127, 3, 48),
(3, 'Planche à découper', 'Planche à découper bois gravée motif Bretagne.', '2025-11-07', TRUE, 5, 2, FALSE, '', FALSE, 127, 4, 49),
(5, 'Plateau apéritif', 'Plateau d''apéritif décor breton drapeau Gwenn ha Du.', '2025-11-04', TRUE, 12, 0, FALSE, '', FALSE, 127, 5, 50),

(5, 'Bougeoirs flotté', 'Bougeoirs en bois flotté fabriqués à la main.', '2025-11-05', TRUE, 9, 1, FALSE, '', FALSE, 127, 1, 51),
(4, 'Photophore Phare', 'Photophore métal motif phare breton.', '2025-11-06', TRUE, 10, 0, FALSE, '', FALSE, 127, 2, 52),
(5, 'Carte postale', 'Carte postale illustrée de Bretagne.', '2025-11-08', TRUE, 13, 0, FALSE, '', FALSE, 127, 3, 53),
(4, 'Horloge marine', 'Horloge murale design nautique motif marin.', '2025-11-03', TRUE, 7, 1, FALSE, '', FALSE, 127, 4, 54),
(5, 'Cabane à oiseaux', 'Nichoir décoratif en bois local Breton.', '2025-11-04', TRUE, 11, 0, FALSE, '', FALSE, 127, 5, 55),
(4, 'Marinière classique', 'Marinière Loctudy coton épais, style marin.', '2025-11-09', TRUE, 8, 0, FALSE, '', FALSE, 127, 1, 56),
(5, 'Bonnet chaud', 'Bonnet marin en coton, rayures navire/blanc.', '2025-11-05', TRUE, 9, 1, FALSE, '', FALSE, 127, 2, 57),
(4, 'Écharpe laine', 'Écharpe tricotée en laine épaisse, motif breton.', '2025-11-06', TRUE, 10, 0, FALSE, '', FALSE, 127, 3, 58),
(5, 'Chaussettes marine', 'Chaussettes rayées couleurs marines.', '2025-11-08', TRUE, 13, 0, FALSE, '', FALSE, 127, 4, 59),
(4, 'Cabas jute', 'Sac cabas imprimé "Bretagne", toile de jute.', '2025-11-03', TRUE, 7, 1, FALSE, '', FALSE, 127, 5, 60),

(5, 'Sac voile', 'Sac à main issu de voile recyclée.', '2025-11-04', TRUE, 11, 0, FALSE, '', FALSE, 127, 1, 61),
(3, 'Bracelet cuir', 'Bracelet en cuir et métal avec motif triskell.', '2025-11-07', TRUE, 6, 1, FALSE, '', FALSE, 127, 2, 62),
(5, 'Collier triskell', 'Bijou pendentif triskell en acier inoxydable.', '2025-11-05', TRUE, 14, 0, FALSE, '', FALSE, 127, 3, 63),
(4, 'Boucles hermine', 'Boucles d''oreilles motif hermine bretonne.', '2025-11-06', TRUE, 8, 0, FALSE, '', FALSE, 127, 4, 64),
(5, 'T-shirt Breizh', 'T-shirt coton bio imprimé "Breizh Power".', '2025-11-09', TRUE, 10, 1, FALSE, '', FALSE, 127, 5, 65),
(2, 'Casquette Lux', 'Casquette brodée marine, marque Armor Lux.', '2025-11-04', TRUE, 12, 0, FALSE, '', FALSE, 127, 1, 66),
(4, 'Ceinture marine', 'Ceinture tissu bleu marine, style marin.', '2025-11-08', TRUE, 7, 0, FALSE, '', FALSE, 127, 2, 67),
(5, 'Montre Gwenn', 'Montre au design breton, cadran Gwenn ha Du.', '2025-11-02', TRUE, 9, 1, FALSE, '', FALSE, 127, 3, 68),
(4, 'Broche ancre', 'Broche décorative ancre marine en métal.', '2025-11-07', TRUE, 11, 0, FALSE, '', FALSE, 127, 4, 69),
(5, 'Parapluie triskell', 'Parapluie motif triskell, structure renforcée.', '2025-11-03', TRUE, 10, 0, FALSE, '', FALSE, 127, 5, 70),

(3, 'Porte-monnaie cuir', 'Portefeuille cuir motif triskell-hermines.', '2025-11-05', TRUE, 6, 0, FALSE, '', FALSE, 127, 1, 71),
(5, 'Ciré jaune', 'Ciré marin mixte, couleur jaune, fabrication marine.', '2025-11-06', TRUE, 13, 1, FALSE, '', FALSE, 127, 2, 72),
(4, 'Blouse pêcheur', 'Blouse de pêcheur bretonne, coton épais.', '2025-11-08', TRUE, 8, 0, FALSE, '', FALSE, 127, 3, 73),
(5, 'Cabas Douarnenez', 'Tote bag Douarnenez imprimé motif côtier.', '2025-11-09', TRUE, 10, 1, FALSE, '', FALSE, 127, 4, 74),
(4, 'Foulard celtique', 'Foulard léger motif triskell/celtique.', '2025-11-04', TRUE, 9, 0, FALSE, '', FALSE, 127, 5, 75),
(5, 'Bracelet corde', 'Bracelet nautique en corde marine et métal.', '2025-11-07', TRUE, 12, 0, FALSE, '', FALSE, 127, 1, 76),
(3, 'Bague triskell', 'Bague argent 925 motif triskell.', '2025-11-05', TRUE, 5, 1, FALSE, '', FALSE, 127, 2, 77),
(4, 'Porte-clés Breizh', 'Porte-clés en cuir gravé motif "Breizh".', '2025-11-06', TRUE, 7, 0, FALSE, '', FALSE, 127, 3, 78),
(5, 'Chaussons feutre', 'Chaussons feutre laine anthracite motif Bretagne.', '2025-11-08', TRUE, 11, 1, FALSE, '', FALSE, 127, 4, 79),
(4, 'Chapeau tradi', 'Chapeau breton ruban velours, artisanat local.', '2025-11-03', TRUE, 9, 0, FALSE, '', FALSE, 127, 5, 80),

(5, 'Livre recettes', 'Recueil illustré de recettes traditionnelles bretonnes.', '2025-11-05', TRUE, 10, 0, FALSE, '', FALSE, 127, 1, 81),
(4, 'Roman breton', 'Roman contemporain situé en Bretagne.', '2025-11-07', TRUE, 8, 1, FALSE, '', FALSE, 127, 2, 82),
(5, 'BD Bidochon', 'BD humoristique, édition spéciale Bretagne.', '2025-11-02', TRUE, 14, 0, FALSE, '', FALSE, 127, 3, 83),
(3, 'Livre légendes', 'Livre illustré sur les légendes de Bretagne.', '2025-11-06', TRUE, 5, 2, FALSE, '', FALSE, 127, 4, 84),
(5, 'Guide Bretagne', 'Guide touristique complet de la Bretagne.', '2025-11-08', TRUE, 12, 0, FALSE, '', FALSE, 127, 5, 85),
(4, 'Carte phares', 'Carte artistique des phares de Bretagne.', '2025-11-04', TRUE, 10, 1, FALSE, '', FALSE, 127, 1, 86),
(5, 'CD celtique', 'Compilation de musique celtique de Bretagne.', '2025-11-09', TRUE, 13, 0, FALSE, '', FALSE, 127, 2, 87),
(4, 'CD chants', 'Album de chants marins de toutes les mers.', '2025-11-05', TRUE, 8, 0, FALSE, '', FALSE, 127, 3, 88),
(5, 'DVD danses', 'DVD sur les danses traditionnelles bretonnes.', '2025-11-07', TRUE, 11, 1, FALSE, '', FALSE, 127, 4, 89),
(4, 'Poster drapeaux', 'Poster grand format drapeau breton Gwenn ha Du.', '2025-11-03', TRUE, 9, 0, FALSE, '', FALSE, 127, 5, 90),

(5, 'Carnet croquis', 'Carnet à dessin illustré Côte d''Armor 2022.', '2025-11-05', TRUE, 10, 0, FALSE, '', FALSE, 127, 1, 91),
(4, 'Puzzle phares', 'Puzzle 1000 pièces phares de Bretagne.', '2025-11-07', TRUE, 8, 1, FALSE, '', FALSE, 127, 2, 92),
(5, 'Jeu Découvre', 'Jeu quiz-culture Bretagne pour toute la famille.', '2025-11-02', TRUE, 14, 0, FALSE, '', FALSE, 127, 3, 93),
(3, 'Livre photo', 'Livre photo haut de gamme sur la Bretagne sauvage.', '2025-11-06', TRUE, 5, 2, FALSE, '', FALSE, 127, 4, 94),
(5, 'Agenda breton', 'Agenda de poche illustré Bretagne 2025.', '2025-11-08', TRUE, 12, 0, FALSE, '', FALSE, 127, 5, 95),
(4, 'Livre enfants', 'Livre illustré contes de Bretagne pour enfants.', '2025-11-04', TRUE, 10, 1, FALSE, '', FALSE, 127, 1, 96),
(5, 'Carte sentiers', 'Carte randonnée sentiers côtiers Bretagne GR34.', '2025-11-09', TRUE, 13, 0, FALSE, '', FALSE, 127, 2, 97),
(4, 'Recueil poèmes', 'Recueil poétique bilingue français-breton.', '2025-11-05', TRUE, 8, 0, FALSE, '', FALSE, 127, 3, 98),
(5, 'Calendrier marées', 'Calendrier marées édition 2025 Bretagne.', '2025-11-07', TRUE, 11, 1, FALSE, '', FALSE, 127, 4, 99),
(4, 'Roman Anne', 'Roman historique sur Anne de Bretagne.', '2025-11-03', TRUE, 9, 0, FALSE, '', FALSE, 127, 5, 100),
(5, 'Mug StarBrew', 'Mug en céramique inspiré d''une célèbre saga spatiale.', '2025-11-05', TRUE, 10, 0, FALSE, '', FALSE, 127, 1, 101),
(4, 'T-shirt Pixel', 'T-shirt en coton bio avec motif pixel-art rétro.', '2025-11-07', TRUE, 8, 1, FALSE, '', FALSE, 127, 2, 102),
(5, 'Figurine Cat', 'Figurine en résine peinte à la main du héros Super Cat.', '2025-11-02', TRUE, 14, 0, FALSE, '', FALSE, 127, 3, 103),
(3, 'Poster Synthwave', 'Affiche grand format au design néon synth-wave.', '2025-11-06', TRUE, 5, 2, FALSE, '', FALSE, 127, 4, 104),
(5, 'Casquette GamerX', 'Casquette noire brodée avec logo GamerX.', '2025-11-08', TRUE, 12, 0, FALSE, '', FALSE, 127, 5, 105),
(4, 'Porte-clés Dragon', 'Porte-clés métal inspiré jeu de rôle fantasy.', '2025-11-04', TRUE, 10, 1, FALSE, '', FALSE, 127, 1, 106),
(5, 'Tote Bag Comics', 'Sac en toile imprimé couverture comics vintage.', '2025-11-09', TRUE, 13, 0, FALSE, '', FALSE, 127, 2, 107),
(4, 'Carnet Space', 'Carnet A5 couverture rigide thème spatial.', '2025-11-05', TRUE, 8, 0, FALSE, '', FALSE, 127, 3, 108),
(5, 'Sweat Hero', 'Sweat à capuche coton avec logo HeroLeague.', '2025-11-07', TRUE, 11, 1, FALSE, '', FALSE, 127, 4, 109),
(4, 'Lampe Pixel', 'Lampe décorative forme cœur pixelisé néon.', '2025-11-03', TRUE, 9, 0, FALSE, '', FALSE, 127, 5, 110);



/***********************PEUPLEMENT SROCK*******************/
TRUNCATE TABLE _stock;
INSERT INTO _stock (id_vendeur, id_produit, quantite_dispo, seuil_alerte, derniere_maj, alerte)
SELECT
    id_vendeur,
    id_produit,
    FLOOR(RANDOM() * 50) + 10 AS quantite_dispo,  
    5 AS seuil_alerte,
    CURRENT_DATE AS derniere_maj,
    false AS alerte
FROM _produit;


SELECT setval('_commande_num_commande_seq', (SELECT MAX(num_commande) FROM sae._commande));

SELECT setval('_produit_id_produit_seq', (SELECT MAX(id_produit) FROM sae._produit));

SELECT setval('_image_id_image_seq', (SELECT MAX(id_image) FROM sae._image));

SELECT setval('_compte_id_compte_seq', (SELECT MAX(id_compte) FROM sae._compte));

SELECT setval('_tva_id_tva_seq', (SELECT MAX(id_tva) FROM sae._tva));


