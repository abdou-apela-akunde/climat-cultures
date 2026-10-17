# Contrat d'interface DEV ↔ DATA

> Version **1.0.0** — 15/10/2026 — Équipe 7. Tout changement passe par une Pull Request relue par un membre de **chaque** pôle, et augmente le numéro de version (MAJEUR si un champ change de sens ou disparaît, MINEUR si on ajoute un champ, CORRECTIF pour une précision de texte).

## 1. Flux A — DEV → DATA : la série climatique

Le client API (DEV) fournit, pour une commune, un modèle et une période, une liste de jours. Format d'échange : tableau PHP ou CSV UTF-8 avec **en-tête**, séparateur `,`, point décimal.

| Colonne | Type | Unité | Règle |
|---|---|---|---|
| `code_insee` | texte, 5 caractères | | Clé de recherche dans `Dim_Commune`. Jamais en entier : `05023`, pas `5023`. |
| `date` | texte `AAAA-MM-JJ` | | Un jour par ligne, triées par date croissante, sans doublon. |
| `t_min` | décimal ou vide | °C | Vide = valeur manquante. |
| `t_max` | décimal ou vide | °C | Vide = valeur manquante. |
| `t_moy` | décimal ou vide | °C | Fournie par l'API, **informative** : DATA calcule `(t_max + t_min) / 2`. |
| `precip` | décimal ou vide | mm | Somme journalière. Vide = valeur manquante. |
| `modele` | texte | | Identifiant exact de l'API (`MRI_AGCM3_2_S`). Valeur de `Dim_Modele.nom_modele`. |

Règles :
1. **Une valeur manquante reste vide (`null`).** DEV ne la remplace jamais par 0 et ne l'interpole pas.
2. DEV ne filtre pas, ne corrige pas les biais, ne calcule aucun indicateur.
3. Si l'API renvoie **uniquement des valeurs vides** sur la période (cas déjà observé avec EC_Earth3P_HR), DEV lève une erreur `DonneesVides` au lieu de transmettre une série vide : DATA ne doit pas calculer sur du vide.
4. La série couvre des années civiles **entières** (1er janvier au 31 décembre) et reste dans `[1950, 2049]`.
5. Exemple de référence : `Docs/Analyse/test-api/series_exemple.csv` (Paris 75056, 2049, MRI_AGCM3_2_S). Jeu de tests : `DATA/tests/series_test.csv` (le champ `modele` y porte le nom du cas : `TEST_A_SANS_GEL`, etc.).

## 2. Flux B — DATA → DEV : le module de calcul

Fichier : `scripts/lib/indicateurs.php`. DEV appelle, ne réécrit jamais.

| Fonction | Entrée | Sortie |
|---|---|---|
| `calculer_periode(array $jours, array $culture, float $latitude)` | jours du flux A, ligne de `Dim_Culture`, latitude de la commune | `gdd`, `jours_gel`, `jours_stress`, `bilan_hydrique`, `nb_jours`, `nb_jours_manquants` |
| `calculer_horizon(array $serie, array $culture, float $latitude, int $annee_debut, int $annee_fin, float $part_min = 0.95)` | série complète, bornes de l'horizon | moyennes annuelles + `nb_annees_valides`, `nb_jours_manquants` : **une ligne de `Fact_Indicateur`** |

Règles de calcul (choix de travail, à confirmer par DATA 2) :
- `Tmoy = (t_max + t_min) / 2`.
- Jour de gel : `t_min < seuil_gel_c` (strict). Jour de stress : `t_max > seuil_stress_thermique_c` (strict). Un jour exactement au seuil n'est pas compté.
- GDD : somme de `max(0, Tmoy − t_base_c)`.
- Bilan hydrique : somme de `precip − ET0`, ET0 de Hargreaves (latitude de la **commune**, jour de l'année).
- Valeur manquante : le jour est ignoré pour l'indicateur qui en a besoin et compté dans `nb_jours_manquants`.
- Seuil `NULL` dans le référentiel : l'indicateur vaut `null`, jamais 0.
- Période de végétation : si `periode_debut_mmjj` et `periode_fin_mmjj` sont renseignées, seuls ces jours comptent ; sinon l'année entière (aujourd'hui, aucune période n'est sourcée).
- Une année dont moins de 95 % des jours sont complets est écartée de la moyenne.

Contrat de test : `php scripts/test_indicateurs.php` doit rester à 0 KO. Les résultats attendus sont dans `DATA/tests/resultats_attendus.csv`.

## 3. Flux C — écriture dans la base

DEV écrit **une ligne par couple** dans `Fact_Indicateur` en `INSERT ... ON CONFLICT (id_commune, id_culture, id_temps, id_modele) DO UPDATE`, avec requêtes préparées PDO. Les identifiants numériques viennent des dimensions (`SELECT id_commune FROM Dim_Commune WHERE code_insee = ?`). Les clés étrangères sont actives (`PRAGMA foreign_keys = ON` à chaque connexion).

## 4. Erreurs

| Erreur | Émise par | Sens |
|---|---|---|
| `QuotaDepasse` | client API | HTTP 429 : on ne réessaie pas, on sert le cache ou on affiche un message |
| `ApiIndisponible` | client API | délai dépassé ou HTTP 5xx après 2 nouvelles tentatives |
| `ReponseInvalide` | client API | JSON illisible, champs absents, listes de longueurs différentes |
| `DonneesVides` | client API | toutes les valeurs de la période sont vides |
| résultat `null` | module de calcul | indicateur non calculable (donnée ou seuil absent) |
