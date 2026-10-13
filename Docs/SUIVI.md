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

## Décisions
| Date | Décision | Pourquoi |
|---|---|---|
| 10/10 | Horizons d'environ 30 ans, jusqu'en 2049 | L'API s'arrête au 01/01/2050 |
| 10/10 | MVP : blé tendre d'hiver, maïs grain et vigne de cuve (choix de travail) | Ce sont les cultures qui ont déjà une mention de source |
| 13/10 | Stack : PHP avec PDO, SQLite en local | Déploiement simple sur AlwaysData, clé API cachée au navigateur |
| 13/10 | Température moyenne = (Tmax + Tmin) / 2, comparaisons strictes, jours manquants ignorés | Calcul unique pour tous les indicateurs |

## Problèmes et risques
| Date | Problème | Action |
|---|---|---|
| 13/10 | Seuils agronomiques non sourcés | DATA 1 relève les valeurs avec source et date de consultation |
| 13/10 | Quota de l'API Open-Meteo (10 000 appels par jour et par adresse) | Cache côté serveur et clé de la promotion |
