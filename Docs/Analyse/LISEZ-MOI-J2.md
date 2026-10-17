# Livraison J2 — où mettre quoi, et comment

## Contenu (à copier tel quel à la racine du dépôt, en respectant les dossiers)

```
DATA/creation_bdd.sql                  script de création + données d'amorçage
DATA/requetes_etoile.sql               6 requêtes en étoile + RGPD
DATA/tests/series_test.csv             jeu de séries de test (5 cas)
DATA/tests/resultats_attendus.csv      résultats attendus
Docs/Conception/MODELE-DONNEES.md      schéma en étoile, grain, RGPD
Docs/Conception/CONTRAT-INTERFACE.md   contrat DEV ↔ DATA v1.0.0
Docs/Conception/CONCEPTION-DEV.md      architecture, client API, plan de test, déploiement
scripts/lib/indicateurs.php            module de calcul (DATA → DEV)
scripts/lib/OpenMeteoClient.php        client API
scripts/lib/precalcul.php              pré-calcul
scripts/init_db.php, precalcul.php     scripts en ligne de commande
scripts/test_*.php, run_tests.sh       tests
```

Ajouter dans `.gitignore` si ce n'est pas déjà fait : `data/`, `cache/`, `*.sqlite`.

## Vérifier avant de commiter
```
sh scripts/run_tests.sh     # doit finir par TOUS LES TESTS PASSENT
```
Nécessite PHP 8.1 ou plus avec `pdo_sqlite` (WampServer l'a).

## Branches et commits suggérés (une branche par sujet, depuis dev)
```
git switch dev && git pull
git switch -c data-modele-etoile
git add DATA/creation_bdd.sql DATA/requetes_etoile.sql Docs/Conception/MODELE-DONNEES.md scripts/init_db.php scripts/test_bdd.php
git commit -m "Ajout du schéma en étoile, du script de création et des requêtes, Refs #<n>"
git push -u origin data-modele-etoile
```
Autres branches : `data-tests-indicateurs` (module de calcul, jeu de tests), `dev-client-api` (client, pré-calcul, conception DEV), `docs-contrat-interface`. Une PR vers `dev` par branche, relue par un autre membre. Rappel : `closes #n` ne ferme pas l'issue tant que le merge ne va pas dans `main` ; dans les PR vers `dev`, écrire `Refs #n`.

## Journal IA (à compléter par vous)
Ajouter dans `Docs/JOURNAL-IA.md`, pour le 15/10/2026 : outil Claude (claude.ai), usage « rédaction du schéma en étoile, du script SQL, du module de calcul PHP, du client API et des tests ; documents de conception », ce qui a été **vérifié** (tests exécutés, 64 contrôles ; ordre de grandeur de l'ET0 recalculé à la main), ce qui a été **modifié** (à compléter par vous après relecture), tokens « non mesurable ». Rappel : l'IA n'a fourni aucune valeur agronomique : celles du seed viennent du CSV d'amorçage et restent « à vérifier ».

## SUIVI.md : lignes à ajouter
| Date | Qui | Tâche | Statut |
|---|---|---|---|
| 15/10 | Abdou | Schéma en étoile, script SQL et requêtes | Fait, à valider à 5 |
| 15/10 | Abdou | Contrat d'interface v1.0.0 | Fait, à valider par DEV et DATA 2 |
| 15/10 | Abdou | Module de calcul, client API, pré-calcul, tests | Fait |
| `[date]` | Aicha (DATA 2) | Relire les règles de calcul et la règle du verdict | À faire |
| `[date]` | Amina (DATA 1) | Sourcer les seuils dans `Ref_Source_Seuil` | À faire |
| `[date]` | Marie-Chrislaine (DATA 3) | Valider les écarts du modèle (grain par horizon, RGPD hors des faits) et les années des horizons | À faire |
| `[date]` | Abdelwadoud (DEV) | Valider l'architecture et le client API ; lancer le pré-calcul réel | À faire |

## Reste à faire pour le 17/10
1. Valider les écarts avec l'équipe (`MODELE-DONNEES.md` §3 et §5).
2. Déployer la base : `CONCEPTION-DEV.md` §4, puis un appel réel sur Paris (clé API nécessaire).
3. Vérifier que les maquettes de la branche `maquettes` sont bien les versions finales.
4. Signatures manquantes de la charte IA (3 personnes), journal IA à jour.
5. Merger les branches dans `dev`, tag `jalon-2` sur `dev`, rendu.
