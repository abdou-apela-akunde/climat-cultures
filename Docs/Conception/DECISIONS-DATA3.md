# Décisions de conception — DATA 3 (J2, corrections)

SAÉ 3.01 « Climat & Cultures » — Marie-Chrislaine ETAMANDO (DATA 3 : architecture, intégration, RGPD)
Date : 25/11/2026. Script concerné : `DATA/sql/03_schema_mariadb.sql`, exécuté sur AlwaysData (MariaDB) le 24/11/2026.

Ces trois décisions sont écrites avec mes mots. La mise en forme a été faite avec l'aide de Claude (voir mon journal d'usage de l'IA).

---

## Décision 1 — Grain de la table de faits

**Une ligne de `Fact_Indicateur` = une session × une commune × une culture × un horizon × un modèle climatique.**

Le grain prend en compte une session, une commune, une culture, un horizon et un modèle climatique. Si on faisait une ligne par année, il nous faudrait 30 lignes par horizon, ce qui rendrait les choses beaucoup plus compliquées.

Cela correspond à la formule de l'énoncé (commune × culture × horizon × modèle), à laquelle s'ajoute la session (décision 2). Les indicateurs sont des moyennes annuelles sur l'horizon ; `nb_annees_valides` et `nb_jours_manquants` indiquent combien d'années et de jours ont réellement servi.

## Décision 2 — RGPD : `id_session` dans `Fact_Indicateur` (option A)

**La colonne `id_session` est dans `Fact_Indicateur` et dans la clé `UNIQUE` (`uq_fact`).**

L'`id_session` est dans `Fact_Indicateur` parce que le calcul est à la demande : chaque visiteur déclenche ses calculs sous son identifiant.

On ne peut pas enregistrer deux fois la même simulation pour le même visiteur. En revanche, un autre visiteur peut enregistrer la même simulation, car son `id_session` est différent.

Pour effacer les données d'un visiteur : `DELETE FROM Fact_Indicateur WHERE id_session = ?`.

**Preuves exécutées le 24/11 dans phpMyAdmin :**
- `sessionA` puis `sessionB` avec les mêmes valeurs (1, 1, 1, 1) : les deux lignes sont acceptées ;
- `sessionA` une seconde fois : refusé, `#1062 Duplicate entry 'sessionA-1-1-1-1' for key 'uq_fact'` ;
- `DELETE … WHERE id_session = 'sessionA'` : la ligne de `sessionB` reste ;
- code INSEE en double : `#1062` sur `uq_commune_insee` ;
- fait avec une commune inexistante (999) : `#1452`, clé étrangère `Fact_Indicateur_ibfk_1`.

## Décision 3 — Horizons : 1990-2019 et 2020-2049

**Deux horizons dans `Dim_Temps` : `reference` (1990-2019) et `horizon_2050` (2020-2049).**

La norme climatique est de 30 ans. Les données d'Open-Meteo s'arrêtent au 01/01/2050 (2049 est donc la dernière année complète). Il s'agit de deux périodes de même longueur, qui ne se chevauchent pas.
