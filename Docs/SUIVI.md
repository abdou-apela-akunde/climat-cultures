# Cahier de suivi — Équipe 7

> SAÉ 3.01 « Climat & Cultures ». Document vivant : on ajoute une ligne à chaque tâche, décision ou problème. À mettre à jour à chaque séance.

## Équipe
| Membre | Parcours | Remarque |
|---|---|---|
| Abdelwadoud ALLOUNE | DEV | |
| Abdou APELA AKUNDE | DATA | Participe aussi aux travaux DEV |
| Aicha DABO | DATA | |
| Amina DJAYJI | DATA | |
| Marie-Chrislaine ETAMANDO | DATA | |

Outils : GitHub (dépôt, branches, Pull Requests, Projects) et Teams (échanges).
Règles : une branche par sujet, une Pull Request relue par au moins un autre membre avant de merger, tags `jalon-1` à `jalon-5`.

## Tâches
| Date | Qui | Tâche | Statut | Livrable / lien |
|---|---|---|---|---|
| 10/10 | Abdou | Test de l'API Open-Meteo (Paris, 2049, MRI_AGCM3_2_S) et export en CSV | Fait | `Docs/Analyse/test-api/`, `series_exemple.csv` |
| 10/10 | Abdou | Page statique de test déployée sur AlwaysData | Fait | djayjiamina.alwaysdata.net |
| 10/10 | Amina DJAYJI (DATA 1) | Contribution DATA 1 : inventaire du référentiel des 10 cultures, paramètres agronomiques et état des sources | Fait | `Docs/Analyse/J1_DATA1_Referentiel_Cultures_Climat_Cultures.pdf` |
| 10/10 | Aicha DABO (DATA 2) | Contribution DATA 2 : formules des indicateurs (jours de gel, GDD, stress thermique, bilan hydrique) et trois cas de test | Fait | `Docs/Analyse/DABO_Contribution.pdf` |
| 10/10 | Marie-Chrislaine ETAMANDO (DATA 3) | Contribution DATA 3 : dimensions nécessaires (`Dim_Culture`, `Dim_Commune`, `Dim_Temps`, `Dim_Modele`, `Fact_Indicateur`) | Fait | `Docs/Analyse/J1-dimensions-DATA3.pdf` |
| 10/10 | Abdou | Document d'analyse (premier jet complet, contributions DATA 1, 2 et 3 intégrées) | Fait, à relire | Document d'analyse |
| 13/10 | Abdou | Page PHP de test (PHP 8.4, SQLite PDO disponible) | Fait | `scripts/page-statique/test.php` |
| 13/10 | Abdou | Charte et journal IA mis à jour pour 5 membres | En cours | `Docs/CHARTE-IA.md`, `Docs/JOURNAL-IA.md` |
| 04/10 | Amina DJAYJI (DATA 1) | Sourcer les seuils des cultures retenues (blé tendre, maïs grain, vigne) : valeur, unité, source, date de consultation | À faire | Contribution DATA 1, §8 « Décisions ouvertes » |
| 08/10 | Aicha DABO (DATA 2) | Confirmer les règles de calcul : cas limites et valeurs manquantes (§5 de DATA 2), et calcul de la température moyenne (écart entre sa formule et la variable de l'API relevé à l'intégration) | À faire | Contribution DATA 2, §5, et `SUIVI.md` (décisions du 13/10) |
| 05/10 | Marie-Chrislaine ETAMANDO (DATA 3) avec Amina DJAYJI (DATA 1) | Valider la structure finale de `Dim_Culture` : colonnes manquantes (`gdd_maturite`, températures optimales) et noms de colonnes à harmoniser | À faire | Contribution DATA 1, §8 et DATA 3, tableau des dimensions |
| 24/11 | Marie-Chrislaine ETAMANDO (DATA 3) | Schéma MariaDB recréé sur AlwaysData (5 dimensions et table de faits, grain par horizon, `id_session`) et rempli : 16 communes, 10 cultures, 2 horizons, 3 modèles | Fait | `DATA/sql/03_schema_mariadb.sql` (branche `data3-integration`) |
| 24/11 | Marie-Chrislaine ETAMANDO (DATA 3) | Tests des contraintes sur AlwaysData : doublon INSEE refusé, clé étrangère refusée, deux sessions avec la même simulation acceptées, effacement d'une session sans toucher l'autre | Fait | Captures dans le dossier de preuves de J2 |
| 25/11 | Marie-Chrislaine ETAMANDO (DATA 3) | Note de décisions : grain, RGPD (`id_session`), horizons 1990-2019 et 2020-2049 | Fait | `Docs/Conception/DECISIONS-DATA3.md` (branche `data3-integration`) |
| 25/11 | Marie-Chrislaine ETAMANDO (DATA 3) | Journal d'usage de l'IA individuel déposé et mis à jour | Fait | `Docs/Journal-IA/JOURNAL-IA-Marie.md` (branche `data3-integration`) |
| 25/11 | Marie-Chrislaine ETAMANDO (DATA 3) | Document de conception J2 réécrit (version 1.1 du 25/11, remplace la 0.1 périmée) : modèle, RGPD, requêtes, contrat | Fait | `Docs/Conception/J2-conception-DATA3.md` |
| 25/11 | Marie-Chrislaine ETAMANDO (DATA 3) avec toute l'équipe | Numéro de version, date et tag du contrat d'interface (ajouter `id_session` et les deux horizons) : v1.1.0 rédigée le 25/11 par Abdou, **en attente de relecture** par un membre de chaque pôle | En cours | `Docs/Conception/CONTRAT-INTERFACE.md` |
| 15/10 | Abdou | Jalon 2, première version : schéma en étoile (SQLite), requêtes, module de calcul des indicateurs, client API, tests, contrat d'interface v1.0.0 | Fait, remplacé le 25/11 (lignes ci-dessous) | `Docs/Conception/` |
| 17/10 | Abdou | Maquettes Figma complétées : un graphique par indicateur, bandeau « données incomplètes », trois verdicts, « Modifier ma recherche » | Fait, à valider à 5 | `Docs/Maquettes/` |
| 25/11 | Abdou | Code aligné sur le schéma MariaDB de Marie : connexion par `.env`, calcul à la demande par session (`simulation.php`), purge à 30 jours, requêtes Q1 à Q6 filtrées par session, `MODELE-DONNEES.md` 2.0, `CONCEPTION-DEV.md` 1.1, contrat v1.1.0 | Fait | `scripts/`, `DATA/requetes_etoile.sql`, `Docs/Conception/` |
| 25/11 | Abdou | Tests : calcul 23 OK, client API 16 OK, simulation 17 OK, base MariaDB de test sur AlwaysData 24 OK (`test_bdd.php`) | Fait | `sh scripts/run_tests.sh`, capture |
| 25/11 | Abdou | Corrections du retour J1/J2 : dossier `Data/` regroupé dans `DATA/` (casse), `.env` retiré du suivi Git, tag `jalon-2` refait sur `dev`, `J2.zip` et doublon du contrat supprimés, script SQLite retiré | Fait | historique de `dev` |
| 25/11 | Abdelwadoud ALLOUNE (DEV) | Contribution J2 DEV : à compléter par Abdelwadoud (relecture de l'architecture et du client API) | À compléter | |
| 25/11 | Amina DJAYJI (DATA 1) | Remplir `Ref_Source_Seuil` (vide aujourd'hui) : valeur avec mention ARVALIS ou IFV « à vérifier », sans mention « non sourcé », aucune source inventée par l'IA ; fournir son journal IA individuel | À faire | `DATA/sql/03_schema_mariadb.sql`, `Docs/Journal-IA/` |
| 25/11 | Aicha DABO (DATA 2) | Confirmer le seuil de gel strict (`<`), écrire la règle d'attribution du verdict, vérifier l'évapotranspiration de l'API contre Hargreaves avant d'afficher le bilan hydrique | À faire | `CONTRAT-INTERFACE.md` §2 |
| 25/11 | Équipe | Retester `EC_Earth3P_HR` sur les 16 communes ; tester `CMCC_CM2_VHR4` ; chiffrer le quota du calcul à la demande ; valider la durée de purge | À faire | `MODELE-DONNEES.md` §5 |

## Décisions
| Date | Décision | Pourquoi |
|---|---|---|
| 10/10 | Horizons d'environ 30 ans, jusqu'en 2049 | L'API s'arrête au 01/01/2050 |
| 10/10 | MVP : blé tendre d'hiver, maïs grain et vigne de cuve (choix de travail) | Ce sont les cultures qui ont déjà une mention de source |
| 13/10 | Stack : PHP avec PDO, SQLite en local | Déploiement simple sur AlwaysData, clé API cachée au navigateur. **Remplacée le 25/11 : MariaDB** |
| 13/10 | Température moyenne = (Tmax + Tmin) / 2, comparaisons strictes, jours manquants ignorés | Calcul unique pour tous les indicateurs |
| 20/11 | Grain de `Fact_Indicateur` : une ligne par session, commune, culture, horizon et modèle (et non par année) | Formule de l'énoncé ; 30 lignes par horizon compliqueraient les calculs (décision de Marie, DATA 3) |
| 20/11 | `id_session` dans `Fact_Indicateur` et dans sa clé `UNIQUE` (option A) | Calcul à la demande : chaque visiteur a son espace, et le droit à l'effacement se fait par `DELETE … WHERE id_session = ?` (décision de Marie, DATA 3) |
| 20/11 | Horizons : référence 1990-2019 et horizon_2050 2020-2049 | Norme climatique de 30 ans ; l'API s'arrête au 01/01/2050 ; deux périodes de même longueur sans chevauchement (décision de Marie, DATA 3) |
| 21/11 | Calcul à la demande | Choix de l'équipe ; rend cohérent `id_session` dans `Fact_Indicateur` |
| 25/11 | Une seule base : MariaDB (AlwaysData). Le script SQLite (`creation_bdd.sql`) est abandonné et supprimé du dépôt | Une chose écrite doit être une chose exécutée ; le schéma MariaDB a été exécuté le 24/11. Retenu par Marie et Abdou, relecture des autres dans la PR |
| 25/11 | Conservation des lignes : 30 jours, puis purge | Proposition de DATA 3, **à valider par l'équipe** |
| 25/11 | Seuil de gel : jour de gel si `t_min` < seuil (strict) ; stress si `t_max` > seuil (strict) | Choix de travail écrit dans le contrat v1.1.0, **à confirmer par DATA 2** |

## Problèmes et risques
| Date | Problème | Action |
|---|---|---|
| 13/10 | Seuils agronomiques non sourcés | DATA 1 relève les valeurs avec source et date de consultation |
| 13/10 | Quota de l'API Open-Meteo (10 000 appels par jour et par adresse) | Cache côté serveur et clé de la promotion |
| 21/11 | Calcul à la demande avec des fenêtres de 30 ans : risque d'épuiser le quota gratuit d'Open-Meteo (10 000 appels pondérés par jour et par adresse) | Chiffrer avec DEV le coût d'une consultation ; cache côté serveur |
| 24/11 | Notre script `Data/creation_bdd.sql` est écrit pour SQLite, alors que la base AlwaysData est en MariaDB | Script MariaDB déposé par Marie (`DATA/sql/03_schema_mariadb.sql`) ; **Tranché le 25/11 : MariaDB seule**, script SQLite supprimé |
| 24/11 | Modèle `EC_Earth3P_HR` testé sur 1 commune et 1 année seulement ; `CMCC_CM2_VHR4` non testé | Retester sur les 16 communes avant de passer le statut à `valide` |
| 25/11 | Une clé API Open-Meteo avait été commitée (`.env`) dans le tag `jalon-2`, sur un dépôt public | Fichier retiré du suivi, historique local corrigé avant publication, tag supprimé puis refait. **L'enseignant doit révoquer l'ancienne clé et en fournir une nouvelle** ; la nouvelle ne va que dans `.env` (hors Git) |
| 25/11 | Dossiers `Data/` et `DATA/` côte à côte : les scripts plantaient sous Linux | Regroupés dans `DATA/` ; tests relancés sur AlwaysData |
| 25/11 | `Ref_Source_Seuil` est vide ; les seuils des cultures ne sont pas validés | DATA 1 remplit la table ; aucune valeur agronomique n'est fournie par l'IA |
| 25/11 | Le verdict (`favorable`, `a_surveiller`, `defavorable`) n'a pas de règle : la colonne reste vide | DATA 2 écrit la règle |
| 25/11 | `et0_fao_evapotranspiration_sum` de l'API semble plus élevée que la formule de Hargreaves utilisée pour le bilan hydrique | DATA 2 vérifie avant tout affichage du bilan hydrique |
