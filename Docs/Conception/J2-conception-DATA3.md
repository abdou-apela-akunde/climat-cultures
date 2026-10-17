# J2 — Conception DATA 3 : modèle dimensionnel, RGPD, requêtes, contrat d'interface

SAÉ 3.01 « Climat & Cultures » — Marie ETAMANDO (DATA 3, architecture / intégration / RGPD)
Version 0.1 du 17/10/2026 — contrat à valider par les 5 membres (DATA et DEV)

---

## 1. Modèle dimensionnel (schéma en étoile)

Quatre dimensions autour d'une table de faits. Les séries climatiques brutes ne sont **pas** stockées : elles arrivent de l'API en mémoire, et seuls les indicateurs calculés sont conservés (base légère).

| Table | 1 ligne = | Colonnes | Clés / contraintes |
|---|---|---|---|
| `Dim_Culture` | 1 culture et ses seuils | `id_culture`, `nom`, `temperature_base`, `seuil_gel`, `seuil_stress`, `besoin_eau` (+ à ajouter : `gdd_maturite`, `temperature_opt_min`, `temperature_opt_max`) | PK `id_culture` |
| `Dim_Commune` | 1 commune | `id_commune`, `code_insee` VARCHAR(5), `nom`, `latitude`, `longitude`, `altitude` | PK `id_commune` ; UNIQUE `code_insee` |
| `Dim_Temps` | 1 année | `id_temps`, `annee`, `horizon` | PK `id_temps` ; UNIQUE `annee` |
| `Dim_Modele` | 1 modèle climatique validé | `id_modele`, `nom_modele` (identifiant exact de l'API), `organisme` | PK `id_modele` ; UNIQUE `nom_modele` |
| `Fact_Indicateur` | 1 culture × 1 commune × 1 année × 1 modèle × 1 session | `id_indicateur`, `id_session`, 4 clés étrangères, `gdd`, `jours_gel`, `jours_stress`, `bilan_hydrique`, `verdict`, `date_calcul` | PK `id_indicateur` ; 4 FOREIGN KEY ; UNIQUE (`id_session`, `id_culture`, `id_commune`, `id_temps`, `id_modele`) |

### Choix de conception (à pouvoir expliquer à l'oral)
- **Granularité** : une ligne de faits par année, pour un modèle, une commune, une culture et une session. L'horizon est un regroupement d'années porté par `Dim_Temps` : on peut calculer une moyenne par horizon ou tracer année par année.
- **PK ≠ UNIQUE** : la clé primaire donne un numéro unique à la ligne, mais ne regarde pas son contenu. C'est la contrainte UNIQUE sur la combinaison de clés qui empêche d'avoir deux fois le même résultat.
- **Indicateurs stockés plutôt que températures brutes** : volume réduit, pas de rappel de l'API, chiffre affiché directement.
- **`Dim_Culture` jamais reliée aux séries climatiques** : le climat ne dépend pas de la culture. Les seuils de la culture sont appliqués au moment du calcul.
- **NULL ≠ 0** : une valeur NULL signifie « non calculable » (données manquantes), alors que 0 signifie « calculé, et il n'y en a pas ».
- **Colonne `id_session` dans la clé UNIQUE** : sinon deux visiteurs ne pourraient pas faire la même simulation.
- **Modèles** : `Dim_Modele` ne contient que des modèles testés. Test du 10/10/2026 (Le Plessis-Trévise, année 2049, tmax/tmin/précipitations) : `MRI_AGCM3_2_S` et `EC_Earth3P_HR` renvoient des données. Le test ne prouve pas que les modèles fonctionnent partout ni sur toute la période.
- **Maille de grille** : l'API renvoie la maille la plus proche (requête 48.8145 ; 2.5735, réponse 48.800 ; 2.600, altitude 111 m), pas la commune exacte.

Scripts : `01_creation_dimensions.sql` (tables) et `02_rgpd_et_peuplement.sql` (colonne RGPD, remplissage).

---

## 2. Conception RGPD

**Données personnelles traitées.** Aucune donnée nominative n'est stockée par DATA. L'identifiant de session (`id_session`, cookie anonyme) est un identifiant en ligne : on le traite comme une donnée personnelle (pseudonyme). Les communes, cultures, modèles et seuils sont des données de référence publiques. Si DEV ajoute des comptes, DATA ne stocke qu'un `id_utilisateur` opaque ; e-mail et mot de passe (haché) restent côté applicatif.

**Principes appliqués.**
| Principe | Application |
|---|---|
| Minimisation | pas de nom, pas d'adresse IP, pas de séries brutes : seulement des indicateurs agrégés |
| Finalité | afficher à l'utilisateur les résultats de sa propre simulation, rien d'autre |
| Isolation | toute lecture et écriture est filtrée par `id_session` ; la clé UNIQUE contient `id_session` |
| Droit à l'effacement | bouton « Effacer mes données » : `DELETE FROM Fact_Indicateur WHERE id_session = ?`, puis DEV oublie l'identifiant (`session.clear()`) pour qu'il ne reste aucun lien avec l'ancien espace |
| Durée de conservation | **proposition** : purge des lignes inactives depuis 30 jours (`date_calcul`) — durée à valider par l'équipe |
| Sécurité | requêtes préparées (anti injection SQL) ; identifiants de la BDD hors du dépôt Git (fichier `.env` ignoré) ; compte applicatif avec droits limités à SELECT/INSERT/UPDATE/DELETE ; en production, uniquement des données utilisateurs fictives |
| Information | mention claire sur le site : quelles données, pourquoi, combien de temps, comment les effacer (rédigée côté DEV) |

**Tests de conception.**
1. Les sessions A et B enregistrent la même simulation : les deux insertions réussissent (grâce à `id_session` dans la clé UNIQUE).
2. La lecture avec l'identifiant de B ne renvoie aucune ligne de A.
3. L'effacement de A ne supprime pas les lignes de B.
4. La purge supprime uniquement les lignes plus anciennes que la durée choisie.

---

## 3. Requêtes pour DEV

Paramètres (requêtes préparées) : `:s` = id_session, `:c` = id_commune, `:k` = id_culture.

**Enregistrer un résultat (écriture sans doublon, grâce à la contrainte UNIQUE)**
```sql
INSERT INTO Fact_Indicateur
  (id_session, id_culture, id_commune, id_temps, id_modele,
   gdd, jours_gel, jours_stress, bilan_hydrique, verdict)
VALUES (:s, :k, :c, :t, :m, :gdd, :gel, :stress, :bilan, :verdict)
ON DUPLICATE KEY UPDATE
  gdd = VALUES(gdd), jours_gel = VALUES(jours_gel), jours_stress = VALUES(jours_stress),
  bilan_hydrique = VALUES(bilan_hydrique), verdict = VALUES(verdict),
  date_calcul = CURRENT_TIMESTAMP;
```

**Résultats par horizon, et comparaison de deux modèles**
```sql
SELECT t.horizon, m.nom_modele,
       AVG(f.gdd) AS gdd_moy, AVG(f.jours_gel) AS jours_gel_moy,
       AVG(f.jours_stress) AS jours_stress_moy, AVG(f.bilan_hydrique) AS bilan_hydrique_moy
FROM Fact_Indicateur f
JOIN Dim_Temps t  ON t.id_temps  = f.id_temps
JOIN Dim_Modele m ON m.id_modele = f.id_modele
WHERE f.id_session = :s AND f.id_commune = :c AND f.id_culture = :k
GROUP BY t.horizon, m.nom_modele
ORDER BY MIN(t.annee), m.nom_modele;
```

**Série année par année (courbe d'évolution)**
```sql
SELECT t.annee, t.horizon, m.nom_modele, f.jours_gel, f.gdd, f.bilan_hydrique, f.verdict
FROM Fact_Indicateur f
JOIN Dim_Temps t  ON t.id_temps  = f.id_temps
JOIN Dim_Modele m ON m.id_modele = f.id_modele
WHERE f.id_session = :s AND f.id_commune = :c AND f.id_culture = :k
ORDER BY t.annee, m.nom_modele;
```

**Droit à l'effacement**
```sql
DELETE FROM Fact_Indicateur WHERE id_session = :s;
```

**Purge des sessions abandonnées (durée à valider)**
```sql
DELETE FROM Fact_Indicateur WHERE date_calcul < NOW() - INTERVAL 30 DAY;
```

> Remarque : les moyennes (`AVG`) ignorent les NULL, ce qui est voulu (années non calculables). DEV doit afficher le nombre d'années réellement utilisées si une valeur manque.

---

## 4. Contrat d'interface DATA ↔ DEV — version 0.1

Statut : **proposition de DATA 3, non validée**. Chaque ligne « à figer » doit être confirmée par les cinq membres ; toute modification change le numéro de version.

### DEV fournit à DATA (séries climatiques, en mémoire)
| Champ | Type | Unité / format |
|---|---|---|
| `id_commune` | entier | identifiant de `Dim_Commune` |
| `date` | texte | `AAAA-MM-JJ` (ISO 8601) |
| `t_min`, `t_max`, `t_moy` | décimal | °C |
| `precip` | décimal | mm par jour |
| modèle | texte | `nom_modele` exact (ex. `MRI_AGCM3_2_S`) |
| période | entier | année (clé `annee` de `Dim_Temps`) |
| données manquantes | — | valeur vide / NULL, **jamais 0** ; DEV signale aussi les erreurs API |

### DATA fournit à DEV
| Élément | Contenu proposé |
|---|---|
| Module de calcul | fonction qui reçoit la série d'une année + les seuils d'une culture et renvoie les indicateurs ; DATA en est propriétaire, DEV l'intègre |
| Champs de sortie | `gdd`, `jours_gel`, `jours_stress`, `bilan_hydrique`, `verdict` (mêmes noms que les colonnes de `Fact_Indicateur`) |
| Unités | `gdd` en °C·jour ; `jours_gel` et `jours_stress` en jours par année ; `bilan_hydrique` en mm sur la période ; 1 décimale pour `gdd` et `bilan_hydrique` |
| `verdict` | `viable`, `a_risque` ou `non_viable` (sans accent) |
| Granularité | 1 enregistrement par (commune, culture, année, modèle) |
| Identifiants | `id_commune`, `id_culture`, `id_temps`, `id_modele` (clés des dimensions) |
| Évapotranspiration | non fournie par l'API : estimée par DATA (méthode de Hargreaves) à partir de `t_min`, `t_max` et de la latitude de la commune |
| Données manquantes | si une année est incomplète, les indicateurs concernés valent NULL (pas 0) ; le seuil de tolérance de données manquantes reste à fixer |
| Récupération | requêtes de la section 3 |
| Jeux de test | séries d'essai + résultats attendus fournis par DATA 2 |

### Points à figer (récapitulatif)
Noms des champs · unités · format des dates · granularité · modèle climatique · horizon · valeurs NULL / données manquantes · codes des indicateurs · format de sortie.

---

## 5. Points à valider par l'équipe

1. **Granularité** : une ligne par année (proposition) ou par horizon (kit MVP). Avec l'année, une sélection stocke 20 lignes par modèle ; avec l'horizon, 2.
2. **Horizons** : « Référence récente » 2011-2020 et « Horizon 2050 » 2040-2049 (l'API s'arrête au 01/01/2050).
3. **Colonne `id_session`** dans la clé UNIQUE, et **durée de purge** (30 jours proposés).
4. **Liste des modèles** : les deux modèles testés ; ajouter un troisième après test ?
5. **Format des indicateurs** : une colonne par indicateur (choix actuel) ou une ligne par indicateur avec un code (kit MVP) ; le contrat ci-dessus suppose une colonne par indicateur.
6. **`Dim_Culture`** : colonnes manquantes (`gdd_maturite`, températures optimales), valeurs NULL pour l'olivier, soja (couple température de base / GDD) à confirmer.
7. **`communes-reference.csv`** : codes INSEE, coordonnées et altitudes à vérifier par DATA 1 avec des sources officielles.
8. **Seuil de tolérance** aux données manquantes.
