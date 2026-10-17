<?php
declare(strict_types=1);
require_once __DIR__ . '/indicateurs.php';
require_once __DIR__ . '/OpenMeteoClient.php';

/**
 * Pré-calcul : pour chaque modèle, commune et horizon, récupère la série (flux A),
 * calcule les indicateurs de chaque culture (flux B) et les écrit dans Fact_Indicateur (flux C).
 * Relançable sans risque : l'écriture est un UPSERT sur la clé unique à 4 colonnes.
 * @return array{ecrits:int,ignores:array<int,string>,arret:?string}
 */
function precalculer(PDO $pdo, OpenMeteoClient $client, ?array $codes = null, ?array $modeles = null, ?callable $log = null): array
{
    $log ??= static function (string $m): void {};
    $pdo->exec('PRAGMA foreign_keys = ON');
    $communes = $pdo->query('SELECT * FROM Dim_Commune ORDER BY id_commune')->fetchAll(PDO::FETCH_ASSOC);
    $cultures = $pdo->query('SELECT * FROM Dim_Culture ORDER BY id_culture')->fetchAll(PDO::FETCH_ASSOC);
    $temps    = $pdo->query('SELECT * FROM Dim_Temps ORDER BY annee_debut')->fetchAll(PDO::FETCH_ASSOC);
    $mods     = $pdo->query("SELECT * FROM Dim_Modele WHERE statut_test <> 'donnees_vides' ORDER BY id_modele")->fetchAll(PDO::FETCH_ASSOC);

    $up = $pdo->prepare(
        'INSERT INTO Fact_Indicateur (id_commune,id_culture,id_temps,id_modele,gdd,jours_gel,jours_stress,bilan_hydrique,nb_annees_valides,nb_jours_manquants)
         VALUES (:co,:cu,:t,:m,:gdd,:gel,:str,:bil,:na,:nm)
         ON CONFLICT (id_commune,id_culture,id_temps,id_modele) DO UPDATE SET
           gdd=excluded.gdd, jours_gel=excluded.jours_gel, jours_stress=excluded.jours_stress,
           bilan_hydrique=excluded.bilan_hydrique, nb_annees_valides=excluded.nb_annees_valides,
           nb_jours_manquants=excluded.nb_jours_manquants, calcule_le=datetime(\'now\')');
    $res = ['ecrits' => 0, 'ignores' => [], 'arret' => null];

    foreach ($mods as $m) {
        if ($modeles !== null && !in_array($m['nom_modele'], $modeles, true)) { continue; }
        foreach ($communes as $c) {
            if ($codes !== null && !in_array($c['code_insee'], $codes, true)) { continue; }
            foreach ($temps as $t) {
                $etiquette = "{$m['nom_modele']} / {$c['nom']} / {$t['horizon']}";
                try {
                    $serie = $client->serie($c['code_insee'], (float) $c['latitude'], (float) $c['longitude'],
                                            $m['nom_modele'], (int) $t['annee_debut'], (int) $t['annee_fin']);
                } catch (QuotaDepasse $e) {
                    $res['arret'] = "Quota atteint à : $etiquette. Relancer demain, le cache évite de refaire les appels déjà réussis.";
                    return $res;
                } catch (ApiException $e) {
                    $res['ignores'][] = "$etiquette : " . get_class($e) . ' — ' . $e->getMessage();
                    $log("IGNORÉ $etiquette (" . get_class($e) . ')');
                    continue;
                }
                $pdo->beginTransaction();
                foreach ($cultures as $u) {
                    $r = calculer_horizon($serie, $u, (float) $c['latitude'], (int) $t['annee_debut'], (int) $t['annee_fin']);
                    $up->execute([':co' => $c['id_commune'], ':cu' => $u['id_culture'], ':t' => $t['id_temps'], ':m' => $m['id_modele'],
                        ':gdd' => $r['gdd'], ':gel' => $r['jours_gel'], ':str' => $r['jours_stress'], ':bil' => $r['bilan_hydrique'],
                        ':na' => $r['nb_annees_valides'], ':nm' => $r['nb_jours_manquants']]);
                    $res['ecrits']++;
                }
                $pdo->commit();
                $log("OK $etiquette");
            }
        }
    }
    return $res;
}
