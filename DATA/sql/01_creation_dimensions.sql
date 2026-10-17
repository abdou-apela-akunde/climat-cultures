-- SAE 301 - Climat & Cultures
-- Scripts de creation des dimensions (DATA 3 - Marie)
-- Base : djayjiamina_sae301
-- Statut : Dim_Commune, Dim_Modele et Dim_Temps crees le 12/10/2026 ; Fact_Indicateur en cours (colonne RGPD a venir)
-- Dim_Culture : deja creee (a completer, voir points a confirmer J1)

-- ------------------------------------------------------------
-- Dim_Commune : 1 ligne = 1 commune etudiee
-- latitude/longitude servent a l'appel API (maille ~10 km)
-- code_insee en VARCHAR(5) : conserve le zero initial (ex. Briancon = 05023)
-- ------------------------------------------------------------
CREATE TABLE Dim_Commune (
  id_commune INT NOT NULL AUTO_INCREMENT,
  code_insee VARCHAR(5) NOT NULL,
  nom VARCHAR(100) NOT NULL,
  latitude DECIMAL(8,5) NOT NULL,
  longitude DECIMAL(8,5) NOT NULL,
  altitude INT DEFAULT NULL,
  PRIMARY KEY (id_commune),
  UNIQUE KEY uq_commune_insee (code_insee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Dim_Modele : 1 ligne = 1 modele climatique valide
-- nom_modele = identifiant exact attendu par l'API (parametre "models")
-- Seuls les modeles testes sans donnees vides sont charges
-- ------------------------------------------------------------
CREATE TABLE Dim_Modele (
  id_modele INT NOT NULL AUTO_INCREMENT,
  nom_modele VARCHAR(100) NOT NULL,
  organisme VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (id_modele),
  UNIQUE KEY uq_modele_nom (nom_modele)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Dim_Temps : 1 ligne = 1 annee
-- annee = grain fin ; horizon = etiquette de regroupement (plusieurs annees par horizon)
-- UNIQUE sur annee : pas deux lignes pour la meme annee
-- Point a confirmer : definition de l'horizon 2050 (API limitee au 01/01/2050)
-- ------------------------------------------------------------
CREATE TABLE Dim_Temps (
  id_temps INT NOT NULL AUTO_INCREMENT,
  annee INT NOT NULL,
  horizon VARCHAR(20) NOT NULL,
  PRIMARY KEY (id_temps),
  UNIQUE KEY uq_temps_annee (annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Fact_Indicateur : table de faits
-- 1 ligne = 1 culture x 1 commune x 1 annee x 1 modele, avec les indicateurs calcules
-- UNIQUE sur les 4 cles etrangeres : pas de doublon de resultat
-- ------------------------------------------------------------
CREATE TABLE Fact_Indicateur (
  id_indicateur INT NOT NULL AUTO_INCREMENT,
  id_culture INT NOT NULL,
  id_commune INT NOT NULL,
  id_temps INT NOT NULL,
  id_modele INT NOT NULL,
  PRIMARY KEY (id_indicateur),
  UNIQUE KEY uq_fact (id_culture, id_commune, id_temps, id_modele),
  FOREIGN KEY (id_culture) REFERENCES Dim_Culture (id_culture),
  FOREIGN KEY (id_commune) REFERENCES Dim_Commune (id_commune),
  FOREIGN KEY (id_temps) REFERENCES Dim_Temps (id_temps),
  FOREIGN KEY (id_modele) REFERENCES Dim_Modele (id_modele)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indicateurs calcules. NULL = non calculable (donnees manquantes) : different de 0.
ALTER TABLE Fact_Indicateur
  ADD COLUMN gdd DECIMAL(8,1) DEFAULT NULL,
  ADD COLUMN jours_gel INT DEFAULT NULL,
  ADD COLUMN jours_stress INT DEFAULT NULL,
  ADD COLUMN bilan_hydrique DECIMAL(8,1) DEFAULT NULL,
  ADD COLUMN verdict VARCHAR(30) DEFAULT NULL;
