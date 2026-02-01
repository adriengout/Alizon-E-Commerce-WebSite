#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <time.h>
#include <getopt.h>     // AJOUT : Nécessaire pour getopt_long
#include <sys/types.h>
#include <sys/socket.h>
#include <stdbool.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <libpq-fe.h>   // Bibliothèque PostgreSQL 
#include <openssl/evp.h> //pour le MD5

#define MAX_USERS 10
#define MAX_COLIS 4

typedef struct {
    char login[50];
    char password[33];
} Utilisateur;


typedef struct {
    int num_suivi;
    char id_bordereau[50];
    int etape;
    time_t date_etape;
} Colis;



// Vérifie si la connexion à la BDD est établie 
void verifier_bdd(PGconn *conn) {
    if (PQstatus(conn) == CONNECTION_BAD) {
        fprintf(stderr, "Connexion à la BDD échouée : %s\n", PQerrorMessage(conn));
        PQfinish(conn);
        exit(1);
    }
}

void mdp_vers_md5(const char *mdp_clair, char *output) {
    EVP_MD_CTX *mdctx = EVP_MD_CTX_new();
    const EVP_MD *md = EVP_md5();
    unsigned char digest[EVP_MAX_MD_SIZE];  //unsigned pour null ou positif, besoin pour EVP_DigestFinal_ex
    unsigned int md_len;

    EVP_DigestInit_ex(mdctx, md, NULL);
    EVP_DigestUpdate(mdctx, mdp_clair, strlen(mdp_clair));
    EVP_DigestFinal_ex(mdctx, digest, &md_len);
    EVP_MD_CTX_free(mdctx);

    for(unsigned int i = 0; i < md_len; i++) {
        sprintf(&output[i*2], "%02x", digest[i]);
    }
    output[32] = '\0';
}

//gestion des logs
void ecrire_log(const char *ip, const char *action) {
    FILE *f = fopen("delivraptor.log", "a");
    if (f == NULL) return;

    time_t maint = time(NULL);
    struct tm *t = localtime(&maint);
    char horodatage[64];

    strftime(horodatage, sizeof(horodatage), "%Y-%m-%d %H:%M:%S", t);
    
    fprintf(f, "%s - [Client: %s] %s\n", horodatage, ip, action);
    
    fclose(f);
}


char genL() {
    int random = rand() % 26;
    const char lettre[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    return lettre[random];
}

void charger_utilisateurs(const char *nom_fichier, Utilisateur utilisateurs[]) {
    FILE *f = fopen(nom_fichier, "r");
    if (f == NULL) return;
    int temp = 0;
    char ligne[100];
    while (fgets(ligne, sizeof(ligne), f) && temp < MAX_USERS) {
        if (sscanf(ligne, "%s %s", utilisateurs[temp].login, utilisateurs[temp].password) == 2) {
            temp++;
        }
    }
    fclose(f);
}

void genererBordereau(char *bordereau, int num_commande, PGconn *conn, int cnx) {
    int aleatoire = rand() % 9000 + 1000;
    char sqlQuery[200];
    char reponse[200];
    sprintf(sqlQuery, "SELECT bordereau FROM sae._livraison where num_commande = '%d'", num_commande);
    PGresult *res = PQexec(conn, sqlQuery);
    if (PQresultStatus(res) == PGRES_TUPLES_OK && PQntuples(res) > 0) {
        char *id_existant = PQgetvalue(res, 0, 0);
        printf("Bordereau déjà existant : %s\n", id_existant);
        char erreurExistant[100];
        sprintf(erreurExistant, "Erreur: bordereau deja existant : %s\n", id_existant);
        write(cnx, erreurExistant, strlen(erreurExistant));
        PQclear(res);
        return;
    } else {
        PQclear(res);
        sprintf(bordereau, "BORD-%c%c%c%c-%d-%04d", genL(), genL(), genL(), genL(), aleatoire, num_commande);
        sprintf(sqlQuery, "INSERT INTO sae._livraison (bordereau, num_commande, etape, statut) values ('%s', %d, 1, 'Preparation de la commande')", bordereau, num_commande);
        res = PQexec(conn, sqlQuery);
        PQclear(res);
        sprintf(reponse, "%s\n", bordereau);
        write(cnx, reponse, strlen(reponse));
    }
}


void envoyer_image_binaire(int cnx) {
    FILE *f = fopen("boite_aux_lettres.jpg", "rb");
    if (f == NULL) {
        write(cnx, "Erreur: Image introuvable\n", 26);
        return;
    }

    unsigned char img_buffer[1024];
    size_t n_bytes;
    while ((n_bytes = fread(img_buffer, 1, sizeof(img_buffer), f)) > 0) {
        send(cnx, img_buffer, n_bytes, 0);
    }
    fclose(f);
}


void getDetailsColis(PGconn *conn, int cnx, char *bordereau) {
    char sqlQuery[512], reponse[2048];
    sprintf(sqlQuery, "SELECT etape, statut, date_exped_transporteur, date_arrive_transporteur, "
                      "date_exped_plateforme, date_arrive_plateforme, date_exped_centreLocal, "
                      "date_arrive_centreLocal, date_exped_domicile, date_arrive_domicile, "
                      "date_livraison_reel, raison_refus FROM sae._livraison WHERE bordereau = '%s'", bordereau);

    PGresult *res = PQexec(conn, sqlQuery);
    if (PQresultStatus(res) == PGRES_TUPLES_OK && PQntuples(res) > 0) {
        int etape = atoi(PQgetvalue(res, 0, 0));
        
        sprintf(reponse, "OK,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                PQgetvalue(res, 0, 1),
                PQgetvalue(res, 0, 2),
                PQgetvalue(res, 0, 3),
                PQgetvalue(res, 0, 4),
                PQgetvalue(res, 0, 5),
                PQgetvalue(res, 0, 6),
                PQgetvalue(res, 0, 7),
                PQgetvalue(res, 0, 8),
                PQgetvalue(res, 0, 9),
                PQgetvalue(res, 0, 10),
                PQgetvalue(res, 0, 11));
        
        write(cnx, reponse, strlen(reponse));

        if (etape == 9 && strcmp(PQgetvalue(res, 0, 1), "Livré en l'absence du destinataire") == 0) {
            write(cnx, "START_IMG\n", 10);
            envoyer_image_binaire(cnx);
        }
    } else {
        write(cnx, "ERROR|Bordereau inconnu\n", 24);
    }
    PQclear(res);
}



void getTousLesColisEnTransit(PGconn *conn, int cnx) {
    PGresult *res = PQexec(conn, "SELECT bordereau, etape FROM sae._livraison WHERE etape < 9 ORDER BY etape DESC");
    if (PQresultStatus(res) == PGRES_TUPLES_OK) {
        int rows = PQntuples(res);
        char reponse[1024];
        
        for (int i = 0; i < rows; i++) {
            sprintf(reponse, "%s, %s\n", PQgetvalue(res, i, 0), PQgetvalue(res, i, 1));
            write(cnx, reponse, strlen(reponse));
        }
        write(cnx, "FIN_LISTE\n", 10);
    }
    PQclear(res);
}


int main(int argc, char *argv[]) {
    int sock, cnx;
    struct sockaddr_in addr, client_addr;
    socklen_t addr_len = sizeof(client_addr);
    int port = 0;
    int capacite = 0;
    char *fichier_auth = NULL;
    char log_msg[4200];
    bool connecte = false;
    char login_actuel[50] = "Non-authentifié"; 

    int num_commande;
    char buffer[1024];
    int n;
    srand(time(NULL));
    Utilisateur utilisateurs[MAX_USERS];


    static struct option long_options[] = {
        {"help", no_argument, 0, 'h'},
        {"port", required_argument, 0, 'p'},
        {"capa", required_argument, 0, 'c'},
        {"auth", required_argument, 0, 'a'},
        {0, 0, 0, 0}
    };

    int opt;

    while ((opt = getopt_long(argc, argv, "p:c:a:h", long_options, NULL)) != -1) {
        switch (opt) {
            case 'p':
                port = atoi(optarg);
                break;
            case 'c':
                capacite = atoi(optarg);
                break;
            case 'a':
                fichier_auth = optarg;
                break;
            case 'h':
                printf("Aide Délivraptor :\n");
                printf("-p PORT : définit le port\n");
                printf("-c CAPA : définit la capacité\n");
                printf("-a FILE : fichier d'identifiants\n");
                return 0; //fermer pour laide
        }
    }

    // verifier quil manque pas un argument
    if (port == 0 || capacite == 0 || fichier_auth == NULL) {
        printf("Erreur : arguments manquants ! Tapez --help\n");
        return 1;
    }

    charger_utilisateurs(fichier_auth, utilisateurs); //charge utilisateur dans structure

    printf("Lancement sur port %d avec capacité %d\n", port, capacite);

    // connexion bdd 
    PGconn *conn = PQconnectdb("user=postgres password=Adri1gout dbname=bddsae2 host=localhost");
    printf("Serveur connecté à la bdd.\n");
    verifier_bdd(conn);

    // Création du socket 
    sock = socket(AF_INET, SOCK_STREAM, 0);

    int opt_reuse = 1;
    if (setsockopt(sock, SOL_SOCKET, SO_REUSEADDR, &opt_reuse, sizeof(opt_reuse)) < 0) {
        perror("setsockopt");
    }
    

    addr.sin_family = AF_INET;
    addr.sin_port = htons(port);
    addr.sin_addr.s_addr = INADDR_ANY; // ecoute tous les interfaces possibles 
    
    if (bind(sock, (struct sockaddr *)&addr, sizeof(addr)) < 0) {
        perror("Erreur bind");
        return 1;
    }
    
    listen(sock, 5);
    printf("Serveur prêt.\n");
    ecrire_log("SERVEUR", "Démarrage du service Délivraptor");
    while(1) // Boucle des clients 
    {
        printf("Serveur en attente de client...\n");
        cnx = accept(sock, (struct sockaddr *)&client_addr, &addr_len);
        char *ip_client = inet_ntoa(client_addr.sin_addr); 
        printf("Un client est connecté (%s).\n", ip_client);
        
        connecte = false;
        strcpy(login_actuel, "Non-authentifié");

        sprintf(log_msg, "Connexion du client établie.");
        ecrire_log(ip_client, log_msg);
        // Boucle des échanges avec le client connecté
        while (1) {
            n = read(cnx, buffer, sizeof(buffer) - 1); // Lecture des données envoyées
            if (n <= 0) {
                printf("Client déconnecté.\n");
                break;
            }

            buffer[n] = '\0';
            // Supprimer les sauts de ligne
            buffer[strcspn(buffer, "\r\n")] = 0;
            sprintf(log_msg, "Message reçu: %s [Client: %s]", buffer, login_actuel);
            ecrire_log(ip_client, log_msg);

            // Préparation de la réponse
            char reponse[2048];
            char commande[50];
            char login[50], mdp[50], bordereau[50];
            

            if (sscanf(buffer, "%s", commande) != 1) continue;

            if (strcmp(commande, "LOGIN") == 0){
                if (sscanf(buffer, "LOGIN %s %s", login, mdp) == 2){
                    bool succes = false;
                    char mdp_MD5[33];
                    mdp_vers_md5(mdp, mdp_MD5);
                    for (int i=0; i<MAX_USERS; i++){
                        if (strcmp(login, utilisateurs[i].login) == 0 && strcmp(mdp_MD5, utilisateurs[i].password) == 0){
                            char notifConnecte[100];
                            sprintf(notifConnecte, "Utilisateur Connecté : %s\n", login);
                            write(cnx, notifConnecte, strlen(notifConnecte));
                            connecte = true;
                            strcpy(login_actuel, login);
                            sprintf(log_msg, "Authentification réussie pour %s", login_actuel);
                            ecrire_log(ip_client, log_msg);
                            succes = true;
                            break;
                        }
                    }
                    if (!succes) {
                        write(cnx, "Login incorrect\n", 16);
                        sprintf(log_msg, "Tentative de connexion échouée pour : %s", login);
                        ecrire_log(ip_client, log_msg);
                    }
                }
            }

            else if (strcmp(commande, "GENERER_BORDEREAU") == 0){
                if (sscanf(buffer, "GENERER_BORDEREAU %d", &num_commande) == 1){
                    PGresult *res_occ = PQexec(conn, "SELECT COUNT(*) FROM sae._livraison WHERE etape >= 1 AND etape <= 4");
                    int occupation = atoi(PQgetvalue(res_occ, 0, 0));
                    PQclear(res_occ);
                    if (occupation < capacite){
                        if (connecte == true){
                            genererBordereau(bordereau, num_commande, conn, cnx);
                            sprintf(log_msg, "Nouveau bordereau %s généré pour la commande %d [Client: %s]", bordereau, num_commande, login_actuel);
                        }
                        else{
                            write(cnx, "erreur: connexion requise\n", 26);
                            sprintf(log_msg, "Echec GENERER_BORDEREAU: connexion requise");
                        }
                    }else{
                        sprintf(reponse, "Capacité maximale atteinte : %d\n", capacite);
                        write(cnx, reponse, strlen(reponse));
                        sprintf(log_msg, "Echec GENERER_BORDEREAU: capacite max atteinte [Client: %s]", login_actuel);
                    }
                    ecrire_log(ip_client, log_msg);
                }
            }

            else if (strcmp(commande, "SUIVRE_COLIS")==0){
                if (sscanf(buffer, "SUIVRE_COLIS %s", bordereau) == 1){
                    if (connecte == true){
                        getDetailsColis(conn, cnx, bordereau);
                        sprintf(log_msg, "Suivi du colis %s demandé [Client: %s]", bordereau, login_actuel);
                    }
                    else{
                        write(cnx, "erreur: connexion requise\n", 26);
                        sprintf(log_msg, "Echec SUIVRE_COLIS : connexion requise");
                    }
                    ecrire_log(ip_client, log_msg);
                }
            }

            else if (strcmp(commande, "GET_COLIS_TRANSIT")==0){
                if (connecte == true){
                    getTousLesColisEnTransit(conn, cnx);
                    sprintf(log_msg, "Suivi de tout les colis demandé [Client: %s]", login_actuel);
                }
                else{
                    write(cnx, "erreur: connexion requise\n", 26);
                    sprintf(log_msg, "Echec SUIVRE_COLIS : connexion requise");
                }
                ecrire_log(ip_client, log_msg);
            }

            else if (strcasecmp(buffer, "QUIT") == 0) {
                write(cnx, "Au revoir !\n", 13);
                sprintf(log_msg, "Déconnexion du client %s", login_actuel);
                ecrire_log(ip_client, log_msg);
                break; 
            }
        }
        close(cnx); 
    }
    ecrire_log("SERVEUR", "Fermeture du service Délivraptor");
    close(sock); 
    PQfinish(conn);
    return 0;
}
