# J2 — Conception DATA 3 : modèle dimensionnel, RGPD, requêtes, contrat d'interface

SAÉ 3.01 « Climat & Cultures » — Marie-Chrislaine ETAMANDO (DATA 3, architecture / intégration / RGPD)
**Version 1.1 du 25/11/2026** — remplace la version 0.1 du 17/10/2026 (grain par année, anciens horizons, anciens verdicts : périmée).
Base de données : MariaDB 11.4 sur AlwaysData (`djayjiamina_sae301`). Script : `03_schema_mariadb.sql`, exécuté le 24/11/2026. Preuves : dossier `PREUVES-J2-DATA3`.

---

## 1. Modèle dimensionnel (schéma en étoile)

Quatre dimensions autour d'une table de faits, plus une table de sourçage. Les séries climatiques brutes ne sont **pas** stockées : elles arrivent de l'API en mémoire, et seuls les indicateurs calculés sont conservés.

| Table | 1 ligne = | Colonnes principales | Clés / contraintes |
|---|---|---|---|
| `Dim_Commune` | 1 commune | `id_commune`, `code_insee` VARCHAR(5), `nom`, `departement`, `latitude`, `longitude` DECIMAL(8,5), `altitude_m`, `zone_climatique`, `remarque_agricole` | PK `id_commune` ; UNIQUE `code_insee` ; CHECK longueur 5 et bornes de latitude / longitude |
| `Dim_Culture` | 1 culture et ses seuils | `id_culture`, `nom`, `t_base_c`, `gdd_maturite_cj`, `seuil_gel_c`, `besoin_eau_mm`, `t_opt_min_c`, `t_opt_max_c`, `seuil_stress_thermique_c`, `periode_debut_mmjj`, `periode_fin_mmjj`, `source`, `statut_source` | PK `id_culture` ; UNIQUE `nom` ; CHECK `statut_source` |
| `Dim_Temps` | 1 horizon de 30 ans | `id_temps`, `horizon`, `libelle`, `annee_debut`, `annee_fin`, `nb_annees` | PK `id_temps` ; UNIQUE `horizon` ; CHECK `annee_fin >= annee_debut`, `nb_annees = annee_fin - annee_debut + 1`, `annee_fin <= 2049` |
| `Dim_Modele` | 1 modèle climatique | `id_modele`, `nom_modele` (identifiant exact de l'API), `organisme`, `statut_test`, `note_test` | PK `id_modele` ; UNIQUE `nom_modele` ; CHECK `statut_test` |
| `Ref_Source_Seuil` | 1 couple culture / paramètre sourcé | `id_source`, `id_culture`, `parametre`, `valeur_retenue`, `unite`, `source_primaire`, `url`, `date_consultation`, `niveau_confiance`, `statut` | PK `id_source` ; FK `id_culture` ; UNIQUE (`id_culture`, `parametre`, `source_primaire`) |
| `Fact_Indicateur` | 1 session × 1 commune × 1 culture × 1 horizon × 1 modèle | `id_indicateur`, `id_session` CHAR(32), 4 clés étrangères, `gdd`, `jours_gel`, `jours_stress`, `bilan_hydrique` (décimaux), `nb_annees_valides`, `nb_jours_manquants`, `verdict`, `calcule_le` | PK `id_indicateur` ; 4 FOREIGN KEY ; UNIQUE `uq_fact` (`id_session`, `id_commune`, `id_culture`, `id_temps`, `id_modele`) ; CHECK `verdict` |

**Contenu au 24/11/2026** : 16 communes, 10 cultures, 2 horizons, 3 modèles ; `Ref_Source_Seuil` et `Fact_Indicateur` vides.
Les seuils des cultures sont ceux du CSV d'amorçage et ne sont **pas validés** (`statut_source` = `non_renseignee` ou `a_verifier`). Les altitudes et coordonnées des communes sont à confirmer par DATA 1.

### Décisions de conception (voir `DECISIONS-DATA3.md`)

1. **Grain par horizon.** Une ligne = une session × une commune × une culture × un horizon × un modèle (formule de l'énoncé, à laquelle s'ajoute la session). Une ligne par année demanderait 30 lignes par horizon et compliquerait les calculs.
2. **RGPD, option A.** `id_session` est dans `Fact_Indicateur` et dans sa clé `UNIQUE`, parce que le calcul est à la demande : chaque visiteur déclenche ses calculs sous son identifiant.
3. **Horizons.** `reference` = 1990-2019 et `horizon_2050` = 2020-2049 : norme climatique de 30 ans, API limitée au 01/01/2050 (2049 = dernière année complète), deux périodes de même longueur sans chevauchement. L'« horizon 2050 » est centré vers 2035 : on l'affiche comme « période de référence » et « horizon 2050 », pas comme « situation actuelle ».

### Choix techniques à pouvoir expliquer

- **PK ≠ UNIQUE.** La clé primaire numérote la ligne mais ne regarde pas son contenu ; c'est la contrainte `UNIQUE` sur la combinaison de clés qui empêche d'avoir deux fois la même simulation pour la même session.
- **Indicateurs = moyennes annuelles sur l'horizon**, donc `DECIMAL` (par exemple `jours_gel DECIMAL(5,1)`), pas `INT` : 11,4 jours de gel par an ne tient pas dans un entier. `DECIMAL` sans précision supprimerait les décimales.
- **NULL ≠ 0.** `NULL` = non calculable (donnée ou seuil absent) ; `0` = calculé, et il n'y en a pas. `nb_annees_valides` et `nb_jours_manquants` indiquent ce qui a réellement servi.
- **`code_insee` en `VARCHAR(5)`** : Briançon = `05023` ; un entier perdrait le 0.
- **Casse des noms de tables** : sensible sous Linux / MariaDB (`Dim_Commune` ≠ `Dim_commune`).
- **Maille de grille** : l'API renvoie la maille la plus proche, pas la commune exacte (test du 10/10 : requête 48.8145 ; 2.5735, réponse 48.800 ; 2.600, altitude 111 m).
- **Modèles** : seul `MRI_AGCM3_2_S` est `valide` (testé le 10/10 sur Le Plessis-Trévise, année 2049). `EC_Earth3P_HR` a répondu sur 1 commune et 1 année, mais le contrat v1.0.0 signale des réponses vides : à retester sur les 16 communes avant de le passer à `valide`. `CMCC_CM2_VHR4` n'est pas testé.
- **Modèles climatiques et biais** : les modèles sous-estiment les jours de gel ; on lit l'évolution plutôt que les valeurs absolues. Un seul scénario d'émissions, proche du pessimiste : jamais appelé « scénario médian ».

---

## 2. Conception RGPD

**Données personnelles traitées.** Aucune donnée nominative n'est stockée par DATA. L'identifiant de session (`id_session`) est un identifiant en ligne : on le traite comme une donnée personnelle (pseudonymisée). Communes, cultures, modèles et seuils sont des données de référence publiques.

| Principe | Application |
|---|---|
| Minimisation | pas de nom, pas d'adresse IP, pas de séries brutes : seulement des indicateurs agrégés |
| Finalité | afficher à l'utilisateur les résultats de sa propre simulation |
| Isolation | toute lecture et écriture est filtrée par `id_session` ; la clé `UNIQUE` contient `id_session` |
| Droit à l'effacement | `DELETE FROM Fact_Indicateur WHERE id_session = ?`, puis DEV oublie l'identifiant (`session_destroy`) |
| Durée de conservation | **proposition** : purge des lignes de plus de 30 jours (`calcule_le`) — durée à valider par l'équipe |
| Sécurité | requêtes préparées ; identifiants de la base et clé API hors du dépôt et hors des archives (`.env`) ; compte applicatif limité à SELECT / INSERT / UPDATE / DELETE |
| Information | mention claire sur le site : quelles données, pourquoi, combien de temps, comment les effacer (DEV) |

**Tests exécutés le 24/11/2026 sur AlwaysData** (captures dans `PREUVES-J2-DATA3`) :
1. `sessionA` et `sessionB` avec la même simulation (1, 1, 1, 1) : les deux insertions réussissent.
2. `sessionA` une seconde fois : refusé, `#1062 Duplicate entry 'sessionA-1-1-1-1' for key 'uq_fact'`.
3. `DELETE … WHERE id_session = 'sessionA'` : la ligne de `sessionB` reste.
4. Doublon de code INSEE : `#1062` sur `uq_commune_insee`. Commune inexistante : `#1452` sur `Fact_Indicateur_ibfk_1`.

*Point ouvert* : le dispositif `Session` / `Consultation` du script SQLite de l'équipe (option B) n'est pas repris dans la base MariaDB. L'équipe a choisi le calcul à la demande le 21/11, ce qui rend l'option A cohérente ; à confirmer par écrit avec DEV.

---

## 3. Requêtes pour DEV (MariaDB, requêtes préparées PDO)

Paramètres : `:s` = id_session, `:c` = code INSEE, `:k` = nom de la culture, `:m` = nom du modèle.

**Enregistrer un résultat (une ligne par horizon)**
```sql
INSERT INTO Fact_Indicateur
  (id_session, id_commune, id_culture, id_temps, id_modele,
   gdd, jours_gel, jours_stress, bilan_hydrique,
   nb_annees_valides, nb_jours_manquants, verdict)
VALUES (:s, :id_commune, :id_culture, :id_temps, :id_modele,
        :gdd, :gel, :stress, :bilan, :nb_annees, :nb_manquants, :verdict)
ON DUPLICATE KEY UPDATE
  gdd = VALUES(gdd), jours_gel = VALUES(jours_gel), jours_stress = VALUES(jours_stress),
  bilan_hydrique = VALUES(bilan_hydrique), nb_annees_valides = VALUES(nb_annees_valides),
  nb_jours_manquants = VALUES(nb_jours_manquants), verdict = VALUES(verdict),
  calcule_le = CURRENT_TIMESTAMP;
```

**Fiche d'une commune pour une culture : référence et 2050 côte à côte**
```sql
SELECT t.horizon, t.libelle, f.gdd, f.jours_gel, f.jours_stress, f.bilan_hydrique,
       f.nb_annees_valides, f.nb_jours_manquants, f.verdict
FROM Fact_Indicateur f
JOIN Dim_Commune c ON c.id_commune = f.id_commune
JOIN Dim_Culture u ON u.id_culture = f.id_culture
JOIN Dim_Temps   t ON t.id_temps   = f.id_temps
JOIN Dim_Modele  m ON m.id_modele  = f.id_modele
WHERE f.id_session = :s AND c.code_insee = :c AND u.nom = :k AND m.nom_modele = :m
ORDER BY t.annee_debut;
```

**Comparer deux modèles (incertitude), horizon 2050**
```sql
SELECT m.nom_modele, f.gdd, f.jours_gel, f.jours_stress, f.bilan_hydrique
FROM Fact_Indicateur f
JOIN Dim_Commune c ON c.id_commune = f.id_commune
JOIN Dim_Culture u ON u.id_culture = f.id_culture
JOIN Dim_Temps   t ON t.id_temps   = f.id_temps
JOIN Dim_Modele  m ON m.id_modele  = f.id_modele
WHERE f.id_session = :s AND c.code_insee = :c AND u.nom = :k AND t.horizon = 'horizon_2050'
ORDER BY m.nom_modele;
```

**Droit à l'effacement**
```sql
DELETE FROM Fact_Indicateur WHERE id_session = :s;
```

**Purge des lignes anciennes (durée à valider)**
```sql
DELETE FROM Fact_Indicateur WHERE calcule_le < NOW() - INTERVAL 30 DAY;
```

> Les requêtes en étoile de l'équipe (`Data/requetes_etoile.sql`, Q1 à Q6) restent valables après ajout de `f.id_session = :s` dans leur `WHERE`.

---

## 4. Contrat d'interface DEV ↔ DATA — modifications proposées (v1.0.0 → v1.1.0)

Statut : **proposition de DATA 3, non validée**. D'après les règles du contrat lui-même, tout changement passe par une pull request relue par un membre de chaque pôle et change le numéro de version. Numéro proposé : **v1.1.0** (champ ajouté).

| Section du contrat v1.0.0 | Constat | Modification proposée |
|---|---|---|
| Flux C (écriture) | Écrit pour SQLite : `ON CONFLICT (id_commune, id_culture, id_temps, id_modele)`, `PRAGMA foreign_keys = ON` | Pour MariaDB : `ON DUPLICATE KEY UPDATE` ; la clé d'unicité contient `id_session` ; les clés étrangères sont actives par défaut (InnoDB). Dépend du choix SQLite ou MariaDB |
| Flux C | Pas d'identifiant de visiteur | DEV fournit `id_session` (jeton aléatoire de 32 caractères hexadécimaux) à chaque écriture et lecture |
| Flux B (`calculer_horizon`) | Une ligne de `Fact_Indicateur` par appel | Sortie inchangée ; DEV ajoute `id_session` à l'écriture |
| Données de référence | `Dim_Temps.horizon` ∈ {`reference`, `horizon_2050`} | Préciser : référence = 1990-2019, `horizon_2050` = 2020-2049 |
| Verdicts | `favorable`, `a_surveiller`, `defavorable` (script de l'équipe) | À écrire dans le contrat, sans accent ni majuscule |

Le reste du contrat (flux A, règles de calcul, erreurs) n'est pas modifié par DATA 3. Le seuil de gel strict (`<`) et les cas limites relèvent de DATA 2.

---

## 5. Points à valider par l'équipe

1. **SQLite ou MariaDB.** Le script `Data/creation_bdd.sql` est en SQLite, la base AlwaysData est en MariaDB. L'une des deux doit être abandonnée.
2. **Numéro de version du contrat et tag du jalon**, après relecture par un membre de chaque pôle.
3. **Quota Open-Meteo** : le calcul à la demande sur des fenêtres de 30 ans risque d'épuiser le quota gratuit (10 000 appels pondérés par jour et par adresse) ; à chiffrer avec DEV.
4. **Durée de purge** des lignes (30 jours proposés).
5. **Modèles** : retester `EC_Earth3P_HR` sur les 16 communes ; tester `CMCC_CM2_VHR4`.
6. **Sourçage** : `Ref_Source_Seuil` est vide (DATA 1) ; les seuils des cultures ne sont pas validés.
7. **Évapotranspiration** : `et0_fao_evapotranspiration_sum` de l'API semble plus élevée que la formule de Hargreaves ; à vérifier avec DATA 2.
