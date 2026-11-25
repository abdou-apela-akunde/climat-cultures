-- ============================================================
-- Requêtes en étoile — Fact_Indicateur joint aux 4 dimensions
-- Chaque requête répond à une question du produit (SAÉ 3.01).
-- Paramètres : :commune (code INSEE), :culture (nom), :modele (nom_modele)
-- ============================================================

-- Q1. Fiche d'une commune pour une culture : aujourd'hui (référence) et 2050, côte à côte
SELECT t.horizon, t.libelle, f.gdd, f.jours_gel, f.jours_stress, f.bilan_hydrique,
       f.nb_annees_valides, f.nb_jours_manquants, f.verdict
FROM Fact_Indicateur f
JOIN Dim_Commune c ON c.id_commune = f.id_commune
JOIN Dim_Culture u ON u.id_culture = f.id_culture
JOIN Dim_Temps   t ON t.id_temps   = f.id_temps
JOIN Dim_Modele  m ON m.id_modele  = f.id_modele
WHERE c.code_insee = :commune AND u.nom = :culture AND m.nom_modele = :modele
ORDER BY t.annee_debut;

-- Q2. Évolution 2050 − référence pour chaque indicateur (un modèle donné)
SELECT c.nom AS commune, u.nom AS culture,
       h.gdd            - r.gdd            AS delta_gdd,
       h.jours_gel      - r.jours_gel      AS delta_jours_gel,
       h.jours_stress   - r.jours_stress   AS delta_jours_stress,
       h.bilan_hydrique - r.bilan_hydrique AS delta_bilan_hydrique
FROM Fact_Indicateur h
JOIN Dim_Temps  th ON th.id_temps = h.id_temps AND th.horizon = 'horizon_2050'
JOIN Fact_Indicateur r ON r.id_commune = h.id_commune AND r.id_culture = h.id_culture
                      AND r.id_modele  = h.id_modele
JOIN Dim_Temps  tr ON tr.id_temps = r.id_temps AND tr.horizon = 'reference'
JOIN Dim_Commune c ON c.id_commune = h.id_commune
JOIN Dim_Culture u ON u.id_culture = h.id_culture
JOIN Dim_Modele  m ON m.id_modele  = h.id_modele
WHERE m.nom_modele = :modele
ORDER BY c.nom, u.nom;

-- Q3. Classement des communes pour une culture à l'horizon 2050 (moins de jours de stress d'abord)
SELECT c.nom AS commune, c.departement, c.zone_climatique, f.jours_stress, f.jours_gel, f.gdd, f.bilan_hydrique
FROM Fact_Indicateur f
JOIN Dim_Commune c ON c.id_commune = f.id_commune
JOIN Dim_Culture u ON u.id_culture = f.id_culture
JOIN Dim_Temps   t ON t.id_temps   = f.id_temps
JOIN Dim_Modele  m ON m.id_modele  = f.id_modele
WHERE u.nom = :culture AND t.horizon = 'horizon_2050' AND m.nom_modele = :modele
ORDER BY f.jours_stress ASC, f.jours_gel ASC;

-- Q4. Incertitude : écart entre modèles pour une commune, une culture, l'horizon 2050
SELECT m.nom_modele, f.gdd, f.jours_gel, f.jours_stress, f.bilan_hydrique
FROM Fact_Indicateur f
JOIN Dim_Commune c ON c.id_commune = f.id_commune
JOIN Dim_Culture u ON u.id_culture = f.id_culture
JOIN Dim_Temps   t ON t.id_temps   = f.id_temps
JOIN Dim_Modele  m ON m.id_modele  = f.id_modele
WHERE c.code_insee = :commune AND u.nom = :culture AND t.horizon = 'horizon_2050'
ORDER BY m.nom_modele;

-- Q5. Cultures les plus adaptées dans une commune (verdict 'favorable' à l'horizon 2050)
SELECT u.nom AS culture, f.verdict, f.jours_gel, f.jours_stress, f.bilan_hydrique
FROM Fact_Indicateur f
JOIN Dim_Commune c ON c.id_commune = f.id_commune
JOIN Dim_Culture u ON u.id_culture = f.id_culture
JOIN Dim_Temps   t ON t.id_temps   = f.id_temps
JOIN Dim_Modele  m ON m.id_modele  = f.id_modele
WHERE c.code_insee = :commune AND t.horizon = 'horizon_2050' AND m.nom_modele = :modele
  AND f.verdict = 'favorable'
ORDER BY f.jours_stress ASC;

-- Q6. Contrôle qualité : couples jamais calculés (trous dans l'étoile)
SELECT c.nom AS commune, u.nom AS culture, m.nom_modele, t.horizon
FROM Dim_Commune c
CROSS JOIN Dim_Culture u CROSS JOIN Dim_Modele m CROSS JOIN Dim_Temps t
LEFT JOIN Fact_Indicateur f ON f.id_commune = c.id_commune AND f.id_culture = u.id_culture
                           AND f.id_modele  = m.id_modele   AND f.id_temps   = t.id_temps
WHERE f.id_indicateur IS NULL AND m.statut_test = 'valide';

-- RGPD : supprimer toutes les données d'un utilisateur (les consultations partent en cascade)
-- DELETE FROM Session WHERE id_session = :id_session;
-- Purge des sessions expirées (à lancer régulièrement)
-- DELETE FROM Session WHERE expire_le < datetime('now');
