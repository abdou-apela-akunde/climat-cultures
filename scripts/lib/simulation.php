<?php
declare(strict_types=1);
require_once __DIR__ . '/indicateurs.php';
require_once __DIR__ . '/OpenMeteoClient.php';

/**
 * Calcul à la demande (choix d'équipe du 21/11/2026, option A de DATA 3) :
 * chaque visiteur déclenche ses calculs sous son identifiant de session ; la clé unique de
 * Fact_Indicateur contient id_session. Une simulation = une commune × une culture × un modèle,
 * soit une ligne par horizon (référence et horizon_2050).
 */

/** Jeton de session : 32 caractères hexadécimaux (contrat v1.1.0). */
function nouvelle_session(): string { return bin2hex(random_bytes(16)); }

function session_valide(string $id): bool { return preg_match('/^[0-9a-f]{32}$/', $id) === 1; }

/** Colonnes numériques de Dim_Culture : MariaDB les renvoie en texte, le calcul veut des décimaux. */
function culture_en_nombres(array $c): array
{
    foreach (['t_base_c', 'gdd_maturite_cj', 'seuil_gel_c', 'besoin_eau_mm', 't_opt_min_c', 't_opt_max_c', 'seuil_stress_thermique_c'] as $k) {
        $c[$k] = ($c[$k] ?? null) === null ? null : (float) $c[$k];
    }
    return $c;
}

/**
 * Flux A + B pour une simulation, sans toucher à la base.
 * @param array $commune ligne de Dim_Commune ; $culture ligne de Dim_Culture ; $modele ligne de Dim_Modele
 * @param array $horizons lignes de Dim_Temps
 * @return array<int,array> une ligne par horizon, prête pour Fact_Indicateur (id_temps, gdd, …)
 * @throws ApiException (QuotaDepasse, ApiIndisponible, ReponseInvalide, DonneesVides)
 */
function calculer_lignes_simulation(OpenMeteoClient $client, array $commune, array $culture, array $modele, array $horizons): array
{
    $culture = culture_en_nombres($culture);
    $lat = (float) $commune['latitude'];
    $lignes = [];
    foreach ($horizons as $t) {
        $serie = $client->serie($commune['code_insee'], $lat, (float) $commune['longitude'],
                                $modele['nom_modele'], (int) $t['annee_debut'], (int) $t['annee_fin']);
        $r = calculer_horizon($serie, $culture, $lat, (int) $t['annee_debut'], (int) $t['annee_fin']);
        $lignes[] = ['id_temps' => (int) $t['id_temps'], 'verdict' => null] + $r;   // verdict : règle non définie, reste NULL
    }
    return $lignes;
}

/** Flux C : UPSERT (MariaDB) sur la clé uq_fact = (id_session, commune, culture, temps, modèle). */
function enregistrer_lignes(PDO $pdo, string $idSession, int $idCommune, int $idCulture, int $idModele, array $lignes): int
{
    if (!session_valide($idSession)) { throw new InvalidArgumentException('id_session invalide (32 caractères hexadécimaux attendus).'); }
    $up = $pdo->prepare(
        'INSERT INTO Fact_Indicateur
           (id_session, id_commune, id_culture, id_temps, id_modele,
            gdd, jours_gel, jours_stress, bilan_hydrique, nb_annees_valides, nb_jours_manquants, verdict)
         VALUES (:s, :co, :cu, :t, :m, :gdd, :gel, :str, :bil, :na, :nm, :v)
         ON DUPLICATE KEY UPDATE
           gdd = VALUES(gdd), jours_gel = VALUES(jours_gel), jours_stress = VALUES(jours_stress),
           bilan_hydrique = VALUES(bilan_hydrique), nb_annees_valides = VALUES(nb_annees_valides),
           nb_jours_manquants = VALUES(nb_jours_manquants), verdict = VALUES(verdict),
           calcule_le = CURRENT_TIMESTAMP');
    $pdo->beginTransaction();
    try {
        foreach ($lignes as $l) {
            $up->execute([':s' => $idSession, ':co' => $idCommune, ':cu' => $idCulture, ':t' => $l['id_temps'], ':m' => $idModele,
                ':gdd' => $l['gdd'], ':gel' => $l['jours_gel'], ':str' => $l['jours_stress'], ':bil' => $l['bilan_hydrique'],
                ':na' => $l['nb_annees_valides'], ':nm' => $l['nb_jours_manquants'], ':v' => $l['verdict']]);
        }
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    return count($lignes);
}

/**
 * Simulation complète pour un visiteur : cherche les identifiants dans les dimensions,
 * calcule, écrit. Retourne le nombre de lignes écrites.
 * @throws InvalidArgumentException commune, culture ou modèle inconnu, ou modèle à données vides
 * @throws ApiException erreurs du client API (rien n'est écrit dans ce cas)
 */
function lancer_simulation(PDO $pdo, OpenMeteoClient $client, string $idSession, string $codeInsee, string $nomCulture, string $nomModele): int
{
    $un = static function (PDO $pdo, string $sql, string $val, string $quoi): array {
        $q = $pdo->prepare($sql); $q->execute([$val]);
        $r = $q->fetch(PDO::FETCH_ASSOC);
        if (!$r) { throw new InvalidArgumentException("$quoi inconnu : $val"); }
        return $r;
    };
    $commune = $un($pdo, 'SELECT * FROM Dim_Commune WHERE code_insee = ?', $codeInsee, 'Commune');
    $culture = $un($pdo, 'SELECT * FROM Dim_Culture WHERE nom = ?', $nomCulture, 'Culture');
    $modele  = $un($pdo, 'SELECT * FROM Dim_Modele WHERE nom_modele = ?', $nomModele, 'Modèle');
    if ($modele['statut_test'] === 'donnees_vides') { throw new InvalidArgumentException("Modèle sans données : $nomModele"); }
    $horizons = $pdo->query('SELECT * FROM Dim_Temps ORDER BY annee_debut')->fetchAll(PDO::FETCH_ASSOC);

    $lignes = calculer_lignes_simulation($client, $commune, $culture, $modele, $horizons);
    return enregistrer_lignes($pdo, $idSession, (int) $commune['id_commune'], (int) $culture['id_culture'], (int) $modele['id_modele'], $lignes);
}

/** Droit à l'effacement : supprime toutes les lignes d'un visiteur. DEV oublie ensuite l'identifiant (session_destroy). */
function effacer_session(PDO $pdo, string $idSession): int
{
    $d = $pdo->prepare('DELETE FROM Fact_Indicateur WHERE id_session = ?');
    $d->execute([$idSession]);
    return $d->rowCount();
}

/** Durée de conservation : supprime les lignes de plus de $jours jours (30 par défaut, proposition de DATA 3). */
function purger_anciennes_lignes(PDO $pdo, int $jours = 30): int
{
    $d = $pdo->prepare('DELETE FROM Fact_Indicateur WHERE calcule_le < NOW() - INTERVAL ' . max(1, $jours) . ' DAY');
    $d->execute();
    return $d->rowCount();
}
