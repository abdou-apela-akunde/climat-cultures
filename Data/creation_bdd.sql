-- ============================================================
-- SAÉ 3.01 « Climat & Cultures » — Équipe 7 — Jalon 2
-- Script de création de la base (schéma en étoile) — SQLite
-- Auteur : pôle DATA. Statut : proposition de travail, à valider à 5.
--
-- Règles de conception :
--  * 4 dimensions + 1 table de faits. Les séries climatiques brutes
--    ne sont PAS stockées : seuls les indicateurs calculés le sont.
--  * Grain de Fact_Indicateur : 1 ligne = 1 commune x 1 culture
--    x 1 horizon (Dim_Temps) x 1 modèle climatique.
--  * Les valeurs agronomiques du seed sont celles du CSV d'amorçage
--    (inventaire DATA 1, 10/10/2026). Elles NE SONT PAS validées :
--    statut_source = 'non_renseignee' ou 'a_verifier'. Aucune valeur
--    n'a été ajoutée ou corrigée par l'IA.
--  * Une valeur absente est stockée en NULL (jamais 0).
-- ============================================================

PRAGMA foreign_keys = ON;

DROP TABLE IF EXISTS Consultation;
DROP TABLE IF EXISTS Session;
DROP TABLE IF EXISTS Fact_Indicateur;
DROP TABLE IF EXISTS Ref_Source_Seuil;
DROP TABLE IF EXISTS Dim_Modele;
DROP TABLE IF EXISTS Dim_Temps;
DROP TABLE IF EXISTS Dim_Culture;
DROP TABLE IF EXISTS Dim_Commune;

-- ---------- Dimensions ----------

CREATE TABLE Dim_Commune (
    id_commune        INTEGER PRIMARY KEY AUTOINCREMENT,
    code_insee        TEXT    NOT NULL UNIQUE
                      CHECK (length(code_insee) = 5),      -- '05023' : le zéro initial est conservé
    nom               TEXT    NOT NULL,
    departement       TEXT,                                -- affiché dans les maquettes : « Reims · Marne »
    latitude          REAL    NOT NULL CHECK (latitude  BETWEEN -90  AND 90),
    longitude         REAL    NOT NULL CHECK (longitude BETWEEN -180 AND 180),
    altitude_m        INTEGER,
    zone_climatique   TEXT,
    remarque_agricole TEXT
);

CREATE TABLE Dim_Culture (
    id_culture               INTEGER PRIMARY KEY AUTOINCREMENT,
    nom                      TEXT    NOT NULL UNIQUE,
    t_base_c                 REAL,       -- GDD : température de base (°C)
    gdd_maturite_cj          REAL,       -- GDD : cumul à maturité (°C.jour) ; NULL si inconnu
    seuil_gel_c              REAL,       -- jour de gel si t_min < seuil (strict)
    besoin_eau_mm            REAL,       -- besoin indicatif (mm)
    t_opt_min_c              REAL,
    t_opt_max_c              REAL,
    seuil_stress_thermique_c REAL,       -- jour de stress si t_max > seuil (strict)
    periode_debut_mmjj       TEXT CHECK (periode_debut_mmjj IS NULL OR periode_debut_mmjj GLOB '[01][0-9]-[0-3][0-9]'),
    periode_fin_mmjj         TEXT CHECK (periode_fin_mmjj   IS NULL OR periode_fin_mmjj   GLOB '[01][0-9]-[0-3][0-9]'),
                                         -- période de végétation : NULL tant qu'aucune source
    source                   TEXT,       -- mention de provenance du CSV d'amorçage
    statut_source            TEXT    NOT NULL DEFAULT 'non_renseignee'
                      CHECK (statut_source IN ('non_renseignee','a_verifier','verifiee'))
);

CREATE TABLE Dim_Temps (
    id_temps     INTEGER PRIMARY KEY AUTOINCREMENT,
    horizon      TEXT    NOT NULL UNIQUE,   -- code : 'reference', 'horizon_2050'
    libelle      TEXT    NOT NULL,          -- texte affiché à l'écran
    annee_debut  INTEGER NOT NULL,
    annee_fin    INTEGER NOT NULL,
    nb_annees    INTEGER NOT NULL,
    CHECK (annee_fin >= annee_debut),
    CHECK (nb_annees = annee_fin - annee_debut + 1),
    CHECK (annee_fin <= 2049)               -- l'API s'arrête au 01/01/2050
);

CREATE TABLE Dim_Modele (
    id_modele    INTEGER PRIMARY KEY AUTOINCREMENT,
    nom_modele   TEXT    NOT NULL UNIQUE,   -- identifiant exact attendu par l'API
    organisme    TEXT,
    statut_test  TEXT    NOT NULL DEFAULT 'a_tester'
                 CHECK (statut_test IN ('a_tester','valide','donnees_vides')),
    note_test    TEXT
);

-- Référentiel de sourçage : une ligne par couple culture / paramètre (DATA 1)
CREATE TABLE Ref_Source_Seuil (
    id_source          INTEGER PRIMARY KEY AUTOINCREMENT,
    id_culture         INTEGER NOT NULL REFERENCES Dim_Culture(id_culture),
    parametre          TEXT    NOT NULL,       -- ex. 't_base_c', 'seuil_gel_c'
    valeur_retenue     REAL,
    unite              TEXT,
    source_primaire    TEXT    NOT NULL,       -- organisme, titre du document
    url                TEXT,
    date_consultation  TEXT,                   -- AAAA-MM-JJ
    niveau_confiance   TEXT CHECK (niveau_confiance IN ('eleve','moyen','faible')),
    statut             TEXT NOT NULL DEFAULT 'a_verifier'
                       CHECK (statut IN ('a_verifier','verifie','valeur_absente','non_applicable')),
    UNIQUE (id_culture, parametre, source_primaire)
);

-- ---------- Table de faits ----------

CREATE TABLE Fact_Indicateur (
    id_indicateur    INTEGER PRIMARY KEY AUTOINCREMENT,
    id_commune       INTEGER NOT NULL REFERENCES Dim_Commune(id_commune),
    id_culture       INTEGER NOT NULL REFERENCES Dim_Culture(id_culture),
    id_temps         INTEGER NOT NULL REFERENCES Dim_Temps(id_temps),
    id_modele        INTEGER NOT NULL REFERENCES Dim_Modele(id_modele),
    -- Moyennes annuelles sur l'horizon. NULL = non calculable (seuil ou donnée absente).
    gdd              REAL,                 -- degrés-jours de croissance (°C.jour/an)
    jours_gel        REAL,                 -- jours/an
    jours_stress     REAL,                 -- jours/an
    bilan_hydrique   REAL,                 -- mm/an (précipitations - ET0)
    nb_annees_valides INTEGER NOT NULL DEFAULT 0,   -- années réellement utilisées
    nb_jours_manquants INTEGER NOT NULL DEFAULT 0,  -- jours ignorés (valeurs nulles)
    verdict          TEXT CHECK (verdict IS NULL OR verdict IN ('favorable','a_surveiller','defavorable')),
    calcule_le       TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (id_commune, id_culture, id_temps, id_modele)   -- la PK seule n'empêche pas les doublons
);
CREATE INDEX idx_fact_commune ON Fact_Indicateur(id_commune);
CREATE INDEX idx_fact_culture ON Fact_Indicateur(id_culture);

-- ---------- RGPD : données d'usage minimales, hors étoile ----------
-- Les faits sont des données climatiques publiques, identiques pour tous :
-- ils ne contiennent aucune donnée personnelle. Seule la consultation en a,
-- sous forme d'un jeton aléatoire sans nom, e-mail ni adresse IP.

CREATE TABLE Session (
    id_session  TEXT PRIMARY KEY CHECK (length(id_session) = 32),  -- 16 octets aléatoires en hexadécimal
    cree_le     TEXT NOT NULL DEFAULT (datetime('now')),
    expire_le   TEXT NOT NULL DEFAULT (datetime('now','+30 days'))
);

CREATE TABLE Consultation (
    id_consultation INTEGER PRIMARY KEY AUTOINCREMENT,
    id_session      TEXT    NOT NULL REFERENCES Session(id_session) ON DELETE CASCADE,
    id_commune      INTEGER NOT NULL REFERENCES Dim_Commune(id_commune),
    id_culture      INTEGER NOT NULL REFERENCES Dim_Culture(id_culture),
    consulte_le     TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_consultation_session ON Consultation(id_session);

-- ============================================================
-- Données d'amorçage
-- ============================================================

-- 16 communes (kit communes-reference.csv, à confirmer par DATA 3)
INSERT INTO Dim_Commune (id_commune, nom, departement, code_insee, latitude, longitude, altitude_m, zone_climatique, remarque_agricole) VALUES
 (1, 'Lille', 'Nord',            '59350', 50.63,  3.06,   25, 'océanique dégradé',  'Flandre - betterave et blé'),
 (2, 'Brest', 'Finistère',            '29019', 48.39, -4.49,   50, 'océanique',          'Littoral ouest - polyculture élevage'),
 (3, 'Rennes', 'Ille-et-Vilaine',           '35238', 48.11, -1.68,   30, 'océanique',          'Bretagne - polyculture élevage'),
 (4, 'Reims', 'Marne',            '51454', 49.26,  4.03,   85, 'océanique dégradé',  'Champagne - vigne'),
 (5, 'Paris', 'Paris',            '75056', 48.85,  2.35,   35, 'océanique dégradé',  'Bassin parisien - îlot de chaleur urbain'),
 (6, 'Orléans', 'Loiret',          '45234', 47.90,  1.90,  100, 'océanique dégradé',  'Beauce - grandes cultures céréalières'),
 (7, 'Strasbourg', 'Bas-Rhin',       '67482', 48.58,  7.75,  140, 'semi-continental',   'Plaine d''Alsace - maïs'),
 (8, 'Nancy', 'Meurthe-et-Moselle',            '54395', 48.69,  6.18,  210, 'semi-continental',   'Lorraine - polyculture'),
 (9, 'Dijon', 'Côte-d''Or',            '21231', 47.32,  5.04,  245, 'semi-continental',   'Bourgogne - vigne'),
 (10,'Clermont-Ferrand', 'Puy-de-Dôme', '63113', 45.78,  3.08,  330, 'semi-continental',   'Limagne - céréales'),
 (11,'Lyon', 'Rhône',             '69123', 45.76,  4.84,  170, 'semi-continental',   'Vallée du Rhône'),
 (12,'Bordeaux', 'Gironde',         '33063', 44.84, -0.58,   10, 'océanique aquitain', 'Sud-Ouest - vigne et maïs'),
 (13,'Toulouse', 'Haute-Garonne',         '31555', 43.60,  1.44,  150, 'océanique aquitain', 'Sud-Ouest - céréales tournesol maïs'),
 (14,'Avignon', 'Vaucluse',          '84007', 43.95,  4.81,   25, 'méditerranéen',      'Vallée du Rhône - vergers vigne maraîchage'),
 (15,'Montpellier', 'Hérault',      '34172', 43.61,  3.88,   30, 'méditerranéen',      'Languedoc - vigne'),
 (16,'Briançon', 'Hautes-Alpes',         '05023', 44.90,  6.64, 1326, 'montagnard',         'Alpes - alpages - ville la plus haute de France');

-- 10 cultures : inventaire DATA 1 (CSV d'amorçage), valeurs NON validées
INSERT INTO Dim_Culture (id_culture, nom, t_base_c, gdd_maturite_cj, seuil_gel_c, besoin_eau_mm, t_opt_min_c, t_opt_max_c, seuil_stress_thermique_c, source, statut_source) VALUES
 (1, 'Blé tendre d''hiver',  0, 2150, -4, 500, 15, 22, 30, 'ARVALIS (mention pour t_base ; autres paramètres à compléter)', 'a_verifier'),
 (2, 'Orge d''hiver',        0, 1900, -5, 450, 12, 20, 30, NULL, 'non_renseignee'),
 (3, 'Colza d''hiver',       5, 2200, -5, 450, 12, 22, 27, NULL, 'non_renseignee'),
 (4, 'Maïs grain',           6, 1600,  0, 600, 20, 30, 35, 'ARVALIS (mention pour t_base et GDD ; autres paramètres à compléter)', 'a_verifier'),
 (5, 'Tournesol',            6, 1550, -1, 500, 20, 28, 34, NULL, 'non_renseignee'),
 (6, 'Soja',                10, 1500,  0, 550, 20, 30, 35, 'Valeurs à vérifier (couple t_base / GDD douteux)', 'a_verifier'),
 (7, 'Betterave sucrière',   3, 2400, -4, 650, 18, 22, 30, NULL, 'non_renseignee'),
 (8, 'Pomme de terre',       7, 1500, -2, 600, 15, 20, 28, NULL, 'non_renseignee'),
 (9, 'Vigne (raisin de cuve)', 10, 1300, -2, 400, 25, 30, 35, 'IFV / INRAE (mention pour le seuil de gel ; autres paramètres à compléter)', 'a_verifier'),
 (10,'Olivier',             10, NULL, -8, 300, 25, 32, 40, NULL, 'non_renseignee');   -- GDD maturité absent : NULL

-- Horizons : 2 périodes de 30 ans (proposition à valider, décision « à 5 »)
INSERT INTO Dim_Temps (id_temps, horizon, libelle, annee_debut, annee_fin, nb_annees) VALUES
 (1, 'reference',    'Période de référence (1990-2019)', 1990, 2019, 30),
 (2, 'horizon_2050', 'Horizon 2050 (2020-2049)',         2020, 2049, 30);

-- Modèles climatiques Open-Meteo (identifiants exacts)
INSERT INTO Dim_Modele (id_modele, nom_modele, organisme, statut_test, note_test) VALUES
 (1, 'MRI_AGCM3_2_S',  'MRI (Japon)',        'valide',        'Test 10/10/2026 : Paris, 2049, données présentes'),
 (2, 'EC_Earth3P_HR',  'Consortium EC-Earth','a_tester',      'Test DATA 3 OK sur 1 commune / 1 année ; le sujet signale des réponses vides déjà observées : à retester sur les 16 communes'),
 (3, 'CMCC_CM2_VHR4',  'CMCC (Italie)',      'a_tester',      'Non testé');
