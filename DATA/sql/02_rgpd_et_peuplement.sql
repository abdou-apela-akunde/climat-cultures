-- SAE 301 - Climat & Cultures
-- Script 02 (DATA 3 - Marie) : colonne RGPD + remplissage des dimensions
-- A executer APRES 01_creation_dimensions.sql, dans phpMyAdmin (onglet SQL),
-- base du projet selectionnee. Executer BLOC PAR BLOC et verifier la Structure ensuite.
-- ATTENTION : les valeurs inserees (communes, horizons) sont des PROPOSITIONS a valider
-- avec l'equipe (voir J2-conception-DATA3.md, section "Points a valider").

-- ============================================================
-- BLOC 1 - Colonne d'identite RGPD + horodatage
-- id_session : identifiant anonyme du visiteur (fourni par DEV, cookie).
--   Les lignes de faits portent l'id_session de leur proprietaire :
--   isolation entre visiteurs + effacement (DELETE ... WHERE id_session = ...).
-- date_calcul : permet de purger les sessions abandonnees.
-- ============================================================
ALTER TABLE Fact_Indicateur
  ADD COLUMN id_session VARCHAR(64) NOT NULL AFTER id_indicateur,
  ADD COLUMN date_calcul DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- ============================================================
-- BLOC 2 - Nouvelle cle UNIQUE (elle doit inclure id_session)
-- Sans id_session, deux visiteurs ne pourraient pas faire la meme simulation.
-- Les FOREIGN KEY ont besoin d'un index qui commence par leur colonne :
-- l'ancien uq_fact servait d'index a id_culture, on en cree donc un autre AVANT
-- de supprimer uq_fact (sinon erreur "needed in a foreign key constraint").
-- ============================================================
ALTER TABLE Fact_Indicateur ADD INDEX idx_fact_culture (id_culture);
ALTER TABLE Fact_Indicateur DROP INDEX uq_fact;
ALTER TABLE Fact_Indicateur
  ADD UNIQUE KEY uq_fact (id_session, id_culture, id_commune, id_temps, id_modele);

-- ============================================================
-- BLOC 3 - Dim_Modele : seuls les modeles testes (donnees presentes,
-- test du 10/10/2026 sur Le Plessis-Trevise, annee 2049) sont charges.
-- ============================================================
INSERT INTO Dim_Modele (nom_modele, organisme) VALUES
  ('MRI_AGCM3_2_S', 'MRI, Japon'),
  ('EC_Earth3P_HR', 'Consortium EC-Earth');

-- ============================================================
-- BLOC 4 - Dim_Temps : 1 ligne = 1 annee.
-- Deux horizons de 10 ans a comparer (meme logique que le kit MVP :
-- 10 ans = minimum pour une moyenne climatique, et economie du quota API).
-- 2040-2049 et non 2041-2050 : l'API s'arrete au 01/01/2050 (derniere annee complete = 2049).
-- ============================================================
INSERT INTO Dim_Temps (annee, horizon) VALUES
  (2011, 'Reference recente'), (2012, 'Reference recente'), (2013, 'Reference recente'),
  (2014, 'Reference recente'), (2015, 'Reference recente'), (2016, 'Reference recente'),
  (2017, 'Reference recente'), (2018, 'Reference recente'), (2019, 'Reference recente'),
  (2020, 'Reference recente'),
  (2040, 'Horizon 2050'), (2041, 'Horizon 2050'), (2042, 'Horizon 2050'),
  (2043, 'Horizon 2050'), (2044, 'Horizon 2050'), (2045, 'Horizon 2050'),
  (2046, 'Horizon 2050'), (2047, 'Horizon 2050'), (2048, 'Horizon 2050'),
  (2049, 'Horizon 2050');

-- ============================================================
-- BLOC 5 - Dim_Commune : 16 communes de communes-reference.csv
-- (id_commune attribue automatiquement, dans l'ordre du CSV : 1 a 16).
-- A VALIDER par DATA 1 : codes INSEE, coordonnees, altitudes (sources).
-- code_insee en texte : Briancon = '05023' (le zero est conserve).
-- ============================================================
INSERT INTO Dim_Commune (code_insee, nom, latitude, longitude, altitude) VALUES
  ('59350', 'Lille', 50.63, 3.06, 25),
  ('29019', 'Brest', 48.39, -4.49, 50),
  ('35238', 'Rennes', 48.11, -1.68, 30),
  ('51454', 'Reims', 49.26, 4.03, 85),
  ('75056', 'Paris', 48.85, 2.35, 35),
  ('45234', 'Orléans', 47.90, 1.90, 100),
  ('67482', 'Strasbourg', 48.58, 7.75, 140),
  ('54395', 'Nancy', 48.69, 6.18, 210),
  ('21231', 'Dijon', 47.32, 5.04, 245),
  ('63113', 'Clermont-Ferrand', 45.78, 3.08, 330),
  ('69123', 'Lyon', 45.76, 4.84, 170),
  ('33063', 'Bordeaux', 44.84, -0.58, 10),
  ('31555', 'Toulouse', 43.60, 1.44, 150),
  ('84007', 'Avignon', 43.95, 4.81, 25),
  ('34172', 'Montpellier', 43.61, 3.88, 30),
  ('05023', 'Briançon', 44.90, 6.64, 1326);

-- ============================================================
-- BLOC 6 - Verifications (a lancer une par une)
-- ============================================================
-- SELECT COUNT(*) FROM Dim_Commune;   -- attendu : 16
-- SELECT * FROM Dim_Commune WHERE nom = 'Briancon' OR code_insee = '05023';  -- le 0 doit rester
-- SELECT COUNT(*) FROM Dim_Temps;     -- attendu : 20
-- SELECT horizon, MIN(annee), MAX(annee), COUNT(*) FROM Dim_Temps GROUP BY horizon;
-- SELECT * FROM Dim_Modele;           -- attendu : 2 lignes
-- SHOW CREATE TABLE Fact_Indicateur;  -- id_session, date_calcul, uq_fact a 5 colonnes
