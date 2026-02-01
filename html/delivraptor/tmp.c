#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <time.h>
#include <getopt.h>   // gestion des arguments
#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <libpq-fe.h>   // Bibliothèque PostgreSQL 


#define MAX_USERS 10 


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

//gestion des logs
void ecrire_log(const char *ip, const char *action) {
    FILE *f = fopen("delivraptor.log", "a");
    if (f == NULL) return;

    time_t maint = time(NULL);
    struct tm *t = localtime(&maint);
    char horodatage[64];

    strftime(horodatage, sizeof(horodatage), "%Y-%m-%d %H:%M:%S", t);
    
    // Date+Heure + IP + ce que fait le service 
    fprintf(f, "%s - [Client: %s] %s\n", horodatage, ip, action);
    
    fclose(f);
}


// Vérifie si la connexion à la BDD est établie 
void verifier_bdd(PGconn *conn) {
    if (PQstatus(conn) == CONNECTION_BAD) {
        fprintf(stderr, "Connexion à la BDD échouée : %s\n", PQerrorMessage(conn));
        PQfinish(conn);
        exit(1);
    }
}

void charger_utilisateurs(const char *nom_fichier, Utilisateur utilisateurs[]) {
    FILE *f = fopen(nom_fichier, "r");

    int temp = 0;
    char ligne[100];
    while (fgets(ligne, sizeof(ligne), f) && temp < MAX_USERS) {
        if (sscanf(ligne, "%s %s", utilisateurs[temp].login, utilisateurs[temp].password) == 2) {
            temp++;
        }
    }

    fclose(f);
}


// Compare le login et le password 
int verifier_login(Utilisateur *liste, int nb_users_total, char *user_recu, char *pass_recu) {
    for (int i = 0; i < nb_users_total; i++) {
        // On compare le login ET le password (hash MD5)
        if (strcmp(liste[i].login, user_recu) == 0 && strcmp(liste[i].password, pass_recu) == 0) {
            return 1; // Connexion réussie
        }
    }
    return 0; // le mec nexiste pas
}


char genL() {
    int random = rand() % 26;
    const char lettre[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    return lettre[random];
}


void genererBordereau(char *bordereau, int id_commande) {
    int aleatoire = rand() % 9000 + 1000;
    sprintf(bordereau, "BORD-%c%c%c%c-%d-%04d", genL(), genL(), genL(), genL(), aleatoire, id_commande); 
}

int main(int argc, char *argv[]) {
    int sock, cnx;
    struct sockaddr_in addr;
    int port = 0;
    int capacite = 0;
    char *fichier_auth = NULL;
    char log_msg[4200];

    char buffer[1024];
    int n;
    char test[50];
    Colis *tableauColis;
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
    // 2. La boucle qui "lit" les tirets (-p, -c, -a) [cite: 33, 34]
    // "p:c:a:h" signifie que p, c, et a attendent une valeur (le :)
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
    // charger les utilisateurs
    charger_utilisateurs(fichier_auth, utilisateurs);
    printf("Utilisateur 1: %s\n", utilisateurs[0].login);
    printf("Lancement sur port %d avec capacité %d\n", port, capacite);

    genererBordereau(test, 1);
    printf("Bordereau test : %s\n", test);

    // connexion bdd 
    PGconn *conn = PQconnectdb("user=postgres password=password dbname=sae host=localhost");
    printf("Serveur connecté à la bdd.\n");
    verifier_bdd(conn);
    
    printf("import des données\n");
    PGresult *res = PQexec(conn, "SELECT num_suivi, id_bordereau, etape, EXTRACT(EPOCH FROM date_etape) FROM sae._colis;");

    // pour l'erreur des requetes :
    if (PQresultStatus(res) != PGRES_TUPLES_OK) {
        fprintf(stderr, "Erreur lors de la requête : %s\n", PQerrorMessage(conn));
        PQclear(res);
    } else {
        int nb_lignes = PQntuples(res);
        printf("Chargement de %d colis en mémoire...\n", nb_lignes);
        tableauColis = malloc(sizeof(Colis) * (nb_lignes > 0 ? nb_lignes : 1));
        
        for(int i = 0; i < nb_lignes; i++) {
            tableauColis[i].num_suivi = atoi(PQgetvalue(res, i, 0));
            strncpy(tableauColis[i].id_bordereau, PQgetvalue(res, i, 1), 49);
            tableauColis[i].etape = atoi(PQgetvalue(res, i, 2));
            tableauColis[i].date_etape = (time_t)atoll(PQgetvalue(res, i, 3));
        }
        PQclear(res);
    }

    // Création du socket 
    sock = socket(AF_INET, SOCK_STREAM, 0);
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
        struct sockaddr_in client_addr;
        socklen_t client_len = sizeof(client_addr);
        printf("Serveur en attente de client...\n");
        cnx = accept(sock, (struct sockaddr *)&client_addr, &client_len); 
        printf("Un client est connecté.\n");
        char *ip_client = inet_ntoa(client_addr.sin_addr);
        sprintf(log_msg, "Connexion du client %s", utilisateurs[0].login);
        ecrire_log(ip_client, log_msg);

        // Boucle des échanges avec le client connecté
        while (1) {
            n = read(cnx, buffer, sizeof(buffer) - 1); // Lecture des données envoyées
            if (n <= 0) {
                printf("Client déconnecté.\n");
                break;
            }

            buffer[n] = '\0';
            buffer[strcspn(buffer, "\r\n")] = 0;
            printf("Reçu : %s", buffer);
            sprintf(log_msg, "Message reçu: %s [Client: %s]", buffer, utilisateurs[0].login);
            ecrire_log(ip_client, log_msg);

            if (strcmp(buffer, "QUIT") == 0 || strcmp(buffer, "quit") == 0) {
                write(cnx, "Au revoir !\n", 13);
                ecrire_log(ip_client, "Commande QUIT reçue");
                break; // Sort de la boucle de lecture
            }

            // Logique du protocole à implémenter ici (LOGIN, NEW, etc.) [cite: 136]
        }

        sprintf(log_msg, "Deconnexion du client %s", utilisateurs[0].login);
        ecrire_log(ip_client, log_msg);
        close(cnx); // fermeture du client en court
    }
    ecrire_log("SERVEUR", "Fermeture du service Délivraptor");
    close(sock); // fermeture du socket
    PQfinish(conn); // fermeture bdd
    return 0;
}