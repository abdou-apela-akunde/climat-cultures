# Climat & Cultures

Application web qui croise des projections climatiques (Open-Meteo) et des seuils agronomiques pour aider agriculteurs, collectivités et coopératives à anticiper quelles cultures resteront viables dans une commune, à l'horizon 2050.

Projet réalisé dans le cadre de la SAÉ 3.01 (BUT Informatique 2, IUT Créteil-Vitry, UPEC), équipe 7.

> Les résultats sont des **projections** issues de modèles climatiques, pas des prédictions. Les valeurs brutes des modèles sont biaisées : seule la tendance est exploitable.

## Équipe

| Nom | Pôle |
|---|---|
| Abdelwadoud ALLOUNE | DEV |
| Abdou APELA AKUNDE | DATA (avec des travaux DEV) |
| Aïcha DABO | DATA |
| Amina DJAYJI | DATA |
| Marie-Chrislaine ETAMANDO | DATA |

## Organisation

- Communication : Microsoft Teams
- Suivi des tâches : GitHub Projects, et `Docs/SUIVI.md` (cahier de suivi)
- Git : une branche par sujet, `dev` pour l'intégration, `main` pour les rendus. Les Pull Requests sont relues par au moins un autre membre. Les rendus sont marqués par les tags `jalon-1` à `jalon-5`.

## Stack

- PHP avec PDO
- SQLite en local (choix définitif de la base à justifier en J2)
- Appels à l'API Open-Meteo côté serveur uniquement, avec un cache
- Hébergement : AlwaysData (site PHP)

Site de test : https://djayjiamina.alwaysdata.net

## Structure du dépôt

```
App/                code de l'application (front et back), à venir
DATA/               référentiel des cultures et seuils, scripts, requêtes SQL
Docs/
  Analyse/          document d'analyse, contributions DATA, cahier de réponses
    test-api/       test de l'API Open-Meteo : commande, réponse, CSV d'exemple, captures
  Maquettes/        captures des maquettes (5 écrans)
  Rapport/          rapport final, à venir
  Test-PHP/         capture de la page de test PHP
  CHARTE-IA.md      charte d'usage de l'IA
  JOURNAL-IA.md     journal d'usage de l'IA
  SUIVI.md          cahier de suivi
scripts/
  page-statique/    pages de test déployées (index.html, test.php)
```

## Lancer en local

Il faut PHP 8 avec le pilote SQLite de PDO.

```
php -S localhost:8000 -t scripts/page-statique
```

Puis ouvrir http://localhost:8000/test.php, qui doit afficher la version de PHP et « SQLite PDO : disponible ».

## Configuration et secrets

Aucun secret n'est versionné. Copier `.env.example` en `.env` et renseigner les valeurs. Le fichier `.env` est ignoré par Git. Sur AlwaysData, les mêmes variables se déclarent dans la configuration du site.

| Variable | Rôle |
|---|---|
| `OPENMETEO_API_KEY` | Clé Open-Meteo de la promotion (facultative : sans elle, l'API gratuite est utilisée avec un quota plus faible) |

Le fichier SQLite et le `.env` se placent hors du dossier public du site.

## Avancement

- J0 : dépôt, charte IA, page statique en ligne.
- J1 (analyse) : document d'analyse, cahier de réponses, contributions DATA 1, 2 et 3, test de l'API et export CSV, maquettes, cahier de suivi, journal IA.
- J2 (conception) : en cours.

## Données et crédits

- Projections climatiques : [Open-Meteo Climate API](https://open-meteo.com), licence CC BY 4.0.
- Seuils agronomiques : référentiel d'amorçage fourni par l'enseignement. Les valeurs sont en cours de validation et de sourçage, et ne sont utilisées dans les calculs qu'une fois sourcées.

## Usage de l'IA

L'équipe utilise l'IA comme copilote, en respectant la charte `Docs/CHARTE-IA.md`. Chaque usage significatif est déclaré dans `Docs/JOURNAL-IA.md`. L'IA n'est jamais une source des données agronomiques.
