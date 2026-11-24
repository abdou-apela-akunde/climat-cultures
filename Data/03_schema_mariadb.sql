-- ============================================================
-- SAE 3.01 Climat & Cultures - Equipe 7 - Jalon 2 (corrections)
-- Schema en etoile pour MariaDB (AlwaysData) - DATA 3
-- Adapte du script SQLite de l'equipe (Data/creation_bdd.sql), avec 3 decisions de DATA 3 :
--   1. Grain par horizon : 1 ligne de faits = commune x culture x horizon x modele x session
--   2. Calcul a la demande : l'identite (id_session) est dans la cle de Fact_Indicateur
--   3. Horizons de 30 ans : 1990-2019 (reference) et 2020-2049 (horizon 2050)
-- A EXECUTER SUR UNE BASE SANS DONNEES A CONSERVER : le script supprime puis recree les tables.
-- Avant de lancer : verifier que les tables sont vides et prevenir l'equipe (base partagee).
-- Execution conseillee : bloc par bloc dans phpMyAdmin (onglet SQL).
-- ============================================================

-- ------------------------------------------------------------
-- BLOC 0 - Verification avant suppression (lecture seule, a lancer en premier)
-- ------------------------------------------------------------
-- SELECT (SELECT COUNT(*) FROM Fact_Indicateur) AS faits,
--        (SELECT COUNT(*) FROM Dim_Commune)  AS communes,
--        (SELECT COUNT(*) FROM Dim_Culture)  AS cultures,
--        (SELECT COUNT(*) FROM Dim_Temps)    AS temps,
--        (SELECT COUNT(*) FROM Dim_Modele)   AS modeles;
-- Si une valeur n'est pas 0 : ne pas continuer sans en parler a l'equipe.

-- ------------------------------------------------------------
-- BLOC 1 - Suppression des anciennes tables (ordre : faits d'abord)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS Fact_Indicateur;
DROP TABLE IF EXISTS Ref_Source_Seuil;
DROP TABLE IF EXISTS Dim_Modele;
DROP TABLE IF EXISTS Dim_Temps;
DROP TABLE IF EXISTS Dim_Culture;
DROP TABLE IF EXISTS Dim_Commune;

-- ------------------------------------------------------------
-- BLOC 2 - Dimensions
-- ------------------------------------------------------------
CREATE TABLE Dim_Commune (
  id_commune        INT NOT NULL AUTO_INCREMENT,
  code_insee        VARCHAR(5) NOT NULL,
  nom               VARCHAR(100) NOT NULL,
  departement       VARCHAR(60) DEFAULT NULL,
  latitude          DECIMAL(8,5) NOT NULL,
  longitude         DECIMAL(8,5) NOT NULL,
  altitude_m        INT DEFAULT NULL,
  zone_climatique   VARCHAR(60) DEFAULT NULL,
  remarque_agricole VARCHAR(200) DEFAULT NULL,
  PRIMARY KEY (id_commune),
  UNIQUE KEY uq_commune_insee (code_insee),
  CONSTRAINT ck_commune_insee CHECK (CHAR_LENGTH(code_insee) = 5),
  CONSTRAINT ck_commune_lat CHECK (latitude BETWEEN -90 AND 90),
  CONSTRAINT ck_commune_lon CHECK (longitude BETWEEN -180 AND 180)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Dim_Culture (
  id_culture               INT NOT NULL AUTO_INCREMENT,
  nom                      VARCHAR(60) NOT NULL,
  t_base_c                 DECIMAL(5,1) DEFAULT NULL,
  gdd_maturite_cj          DECIMAL(7,1) DEFAULT NULL,
  seuil_gel_c              DECIMAL(5,1) DEFAULT NULL,
  besoin_eau_mm            DECIMAL(7,1) DEFAULT NULL,
  t_opt_min_c              DECIMAL(5,1) DEFAULT NULL,
  t_opt_max_c              DECIMAL(5,1) DEFAULT NULL,
  seuil_stress_thermique_c DECIMAL(5,1) DEFAULT NULL,
  periode_debut_mmjj       CHAR(5) DEFAULT NULL,
  periode_fin_mmjj         CHAR(5) DEFAULT NULL,
  source                   VARCHAR(255) DEFAULT NULL,
  statut_source            VARCHAR(20) NOT NULL DEFAULT 'non_renseignee',
  PRIMARY KEY (id_culture),
  UNIQUE KEY uq_culture_nom (nom),
  CONSTRAINT ck_culture_statut CHECK (statut_source IN ('non_renseignee','a_verifier','verifiee'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Dim_Temps (
  id_temps     INT NOT NULL AUTO_INCREMENT,
  horizon      VARCHAR(20) NOT NULL,
  libelle      VARCHAR(100) NOT NULL,
  annee_debut  INT NOT NULL,
  annee_fin    INT NOT NULL,
  nb_annees    INT NOT NULL,
  PRIMARY KEY (id_temps),
  UNIQUE KEY uq_temps_horizon (horizon),
  CONSTRAINT ck_temps_ordre CHECK (annee_fin >= annee_debut),
  CONSTRAINT ck_temps_nb CHECK (nb_annees = annee_fin - annee_debut + 1),
  CONSTRAINT ck_temps_api CHECK (annee_fin <= 2049)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Dim_Modele (
  id_modele    INT NOT NULL AUTO_INCREMENT,
  nom_modele   VARCHAR(100) NOT NULL,
  organisme    VARCHAR(100) DEFAULT NULL,
  statut_test  VARCHAR(20) NOT NULL DEFAULT 'a_tester',
  note_test    VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id_modele),
  UNIQUE KEY uq_modele_nom (nom_modele),
  CONSTRAINT ck_modele_statut CHECK (statut_test IN ('a_tester','valide','donnees_vides'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Referentiel de sourcage : une ligne par couple culture / parametre (DATA 1)
CREATE TABLE Ref_Source_Seuil (
  id_source         INT NOT NULL AUTO_INCREMENT,
  id_culture        INT NOT NULL,
  parametre         VARCHAR(40) NOT NULL,
  valeur_retenue    DECIMAL(8,2) DEFAULT NULL,
  unite             VARCHAR(20) DEFAULT NULL,
  source_primaire   VARCHAR(255) NOT NULL,
  url               VARCHAR(500) DEFAULT NULL,
  date_consultation DATE DEFAULT NULL,
  niveau_confiance  VARCHAR(10) DEFAULT NULL,
  statut            VARCHAR(20) NOT NULL DEFAULT 'a_verifier',
  PRIMARY KEY (id_source),
  UNIQUE KEY uq_source (id_culture, parametre, source_primaire),
  FOREIGN KEY (id_culture) REFERENCES Dim_Culture (id_culture),
  CONSTRAINT ck_source_confiance CHECK (niveau_confiance IS NULL OR niveau_confiance IN ('eleve','moyen','faible')),
  CONSTRAINT ck_source_statut CHECK (statut IN ('a_verifier','verifie','valeur_absente','non_applicable'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- BLOC 3 - Table de faits
-- Grain : 1 ligne = 1 session x 1 commune x 1 culture x 1 horizon x 1 modele.
-- Les indicateurs sont des MOYENNES ANNUELLES sur l'horizon (donc decimaux, pas entiers :
-- 11,4 jours de gel par an en moyenne ne peut pas etre stocke dans un INT).
-- NULL = non calculable (jamais 0).
-- id_session : jeton aleatoire du visiteur (32 caracteres hexadecimaux), fourni par DEV.
-- ------------------------------------------------------------
CREATE TABLE Fact_Indicateur (
  id_indicateur      INT NOT NULL AUTO_INCREMENT,
  id_session         CHAR(32) NOT NULL,
  id_commune         INT NOT NULL,
  id_culture         INT NOT NULL,
  id_temps           INT NOT NULL,
  id_modele          INT NOT NULL,
  gdd                DECIMAL(8,1) DEFAULT NULL,
  jours_gel          DECIMAL(5,1) DEFAULT NULL,
  jours_stress       DECIMAL(5,1) DEFAULT NULL,
  bilan_hydrique     DECIMAL(8,1) DEFAULT NULL,
  nb_annees_valides  INT NOT NULL DEFAULT 0,
  nb_jours_manquants INT NOT NULL DEFAULT 0,
  verdict            VARCHAR(20) DEFAULT NULL,
  calcule_le         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_indicateur),
  UNIQUE KEY uq_fact (id_session, id_commune, id_culture, id_temps, id_modele),
  FOREIGN KEY (id_commune) REFERENCES Dim_Commune (id_commune),
  FOREIGN KEY (id_culture) REFERENCES Dim_Culture (id_culture),
  FOREIGN KEY (id_temps)   REFERENCES Dim_Temps (id_temps),
  FOREIGN KEY (id_modele)  REFERENCES Dim_Modele (id_modele),
  CONSTRAINT ck_fact_verdict CHECK (verdict IS NULL OR verdict IN ('favorable','a_surveiller','defavorable'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- BLOC 4 - Donnees d'amorcage
-- Communes : kit communes-reference.csv (codes INSEE, coordonnees, altitudes a confirmer par DATA 1).
-- Cultures : valeurs du CSV d'amorcage, NON validees (statut_source) ; aucune valeur ajoutee par l'IA.
-- ------------------------------------------------------------
INSERT INTO Dim_Commune (nom, departement, code_insee, latitude, longitude, altitude_m, zone_climatique, remarque_agricole) VALUES
  ('Lille', 'Nord', '59350', 50.63, 3.06, 25, 'océanique dégradé', 'Flandre - betterave et blé'),
  ('Brest', 'Finistère', '29019', 48.39, -4.49, 50, 'océanique', 'Littoral ouest - polyculture élevage'),
  ('Rennes', 'Ille-et-Vilaine', '35238', 48.11, -1.68, 30, 'océanique', 'Bretagne - polyculture élevage'),
  ('Reims', 'Marne', '51454', 49.26, 4.03, 85, 'océanique dégradé', 'Champagne - vigne'),
  ('Paris', 'Paris', '75056', 48.85, 2.35, 35, 'océanique dégradé', 'Bassin parisien - îlot de chaleur urbain'),
  ('Orléans', 'Loiret', '45234', 47.90, 1.90, 100, 'océanique dégradé', 'Beauce - grandes cultures céréalières'),
  ('Strasbourg', 'Bas-Rhin', '67482', 48.58, 7.75, 140, 'semi-continental', 'Plaine d''Alsace - maïs'),
  ('Nancy', 'Meurthe-et-Moselle', '54395', 48.69, 6.18, 210, 'semi-continental', 'Lorraine - polyculture'),
  ('Dijon', 'Côte-d''Or', '21231', 47.32, 5.04, 245, 'semi-continental', 'Bourgogne - vigne'),
  ('Clermont-Ferrand', 'Puy-de-Dôme', '63113', 45.78, 3.08, 330, 'semi-continental', 'Limagne - céréales'),
  ('Lyon', 'Rhône', '69123', 45.76, 4.84, 170, 'semi-continental', 'Vallée du Rhône'),
  ('Bordeaux', 'Gironde', '33063', 44.84, -0.58, 10, 'océanique aquitain', 'Sud-Ouest - vigne et maïs'),
  ('Toulouse', 'Haute-Garonne', '31555', 43.60, 1.44, 150, 'océanique aquitain', 'Sud-Ouest - céréales tournesol maïs'),
  ('Avignon', 'Vaucluse', '84007', 43.95, 4.81, 25, 'méditerranéen', 'Vallée du Rhône - vergers vigne maraîchage'),
  ('Montpellier', 'Hérault', '34172', 43.61, 3.88, 30, 'méditerranéen', 'Languedoc - vigne'),
  ('Briançon', 'Hautes-Alpes', '05023', 44.90, 6.64, 1326, 'montagnard', 'Alpes - alpages - ville la plus haute de France');

INSERT INTO Dim_Culture (nom, t_base_c, gdd_maturite_cj, seuil_gel_c, besoin_eau_mm, t_opt_min_c, t_opt_max_c, seuil_stress_thermique_c, source, statut_source) VALUES
  ('Blé tendre d''hiver', 0, 2150, -4, 500, 15, 22, 30, 'ARVALIS (mention pour t_base ; autres paramètres à compléter)', 'a_verifier'),
  ('Orge d''hiver', 0, 1900, -5, 450, 12, 20, 30, NULL, 'non_renseignee'),
  ('Colza d''hiver', 5, 2200, -5, 450, 12, 22, 27, NULL, 'non_renseignee'),
  ('Maïs grain', 6, 1600, 0, 600, 20, 30, 35, 'ARVALIS (mention pour t_base et GDD ; autres paramètres à compléter)', 'a_verifier'),
  ('Tournesol', 6, 1550, -1, 500, 20, 28, 34, NULL, 'non_renseignee'),
  ('Soja', 10, 1500, 0, 550, 20, 30, 35, 'Valeurs à vérifier (couple t_base / GDD douteux)', 'a_verifier'),
  ('Betterave sucrière', 3, 2400, -4, 650, 18, 22, 30, NULL, 'non_renseignee'),
  ('Pomme de terre', 7, 1500, -2, 600, 15, 20, 28, NULL, 'non_renseignee'),
  ('Vigne (raisin de cuve)', 10, 1300, -2, 400, 25, 30, 35, 'IFV / INRAE (mention pour le seuil de gel ; autres paramètres à compléter)', 'a_verifier'),
  ('Olivier', 10, NULL, -8, 300, 25, 32, 40, NULL, 'non_renseignee');

-- Horizons : 2 periodes de 30 ans, sans chevauchement. L'API s'arrete au 01/01/2050 :
-- 2049 est la derniere annee complete.
INSERT INTO Dim_Temps (horizon, libelle, annee_debut, annee_fin, nb_annees) VALUES
  ('reference', 'Période de référence (1990-2019)', 1990, 2019, 30),
  ('horizon_2050', 'Horizon 2050 (2020-2049)', 2020, 2049, 30);

-- Modeles Open-Meteo (identifiants exacts). Seul MRI est valide a ce jour ;
-- EC_Earth3P_HR a repondu sur 1 commune / 1 annee seulement (a retester sur les 16 communes).
INSERT INTO Dim_Modele (nom_modele, organisme, statut_test, note_test) VALUES
  ('MRI_AGCM3_2_S', 'MRI (Japon)', 'valide', 'Test 10/10/2026 : Le Plessis-Trévise, 2049, données présentes'),
  ('EC_Earth3P_HR', 'Consortium EC-Earth', 'a_tester', 'Test 10/10/2026 OK sur 1 commune et 1 année ; réponses vides signalées par le contrat v1.0.0 de l'équipe : à retester sur les 16 communes'),
  ('CMCC_CM2_VHR4', 'CMCC (Italie)', 'a_tester', 'Non testé');

-- ------------------------------------------------------------
-- BLOC 5 - Verifications (a lancer une par une)
-- ------------------------------------------------------------
-- SELECT COUNT(*) FROM Dim_Commune;   -- attendu : 16
-- SELECT * FROM Dim_Commune WHERE code_insee = '05023';   -- le zero initial doit rester
-- SELECT COUNT(*) FROM Dim_Culture;   -- attendu : 10
-- SELECT horizon, annee_debut, annee_fin, nb_annees FROM Dim_Temps;   -- 2 lignes de 30 ans
-- SELECT nom_modele, statut_test FROM Dim_Modele;   -- 3 lignes
-- SHOW CREATE TABLE Fact_Indicateur;   -- uq_fact a 5 colonnes, 4 FOREIGN KEY
