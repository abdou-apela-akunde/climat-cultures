<?php
declare(strict_types=1);

/**
 * Module de calcul des indicateurs (DATA -> DEV).
 * SAÉ 3.01 Climat & Cultures — équipe 7 — jalon 2.
 *
 * Statut : implémentation de référence, à valider par DATA 2.
 * Règles de travail (SUIVI.md, décisions du 13/10) :
 *  - Tmoy = (t_max + t_min) / 2 ; la colonne t_moy du flux n'est pas utilisée ;
 *  - comparaisons strictes : gel si t_min < seuil ; stress si t_max > seuil ;
 *  - valeur manquante (null) : le jour est ignoré pour l'indicateur concerné,
 *    jamais remplacé par 0 ni interpolé ;
 *  - seuil absent (NULL) dans le référentiel : indicateur = null (non calculable).
 */

/** Rayonnement extraterrestre Ra (MJ/m²/jour), FAO-56 éq. 21 à 25. */
function rayonnement_extraterrestre(float $latitude_deg, int $jour_annee): float
{
    $phi   = deg2rad($latitude_deg);
    $dr    = 1 + 0.033 * cos(2 * M_PI * $jour_annee / 365);
    $delta = 0.409 * sin(2 * M_PI * $jour_annee / 365 - 1.39);
    $arg   = max(-1.0, min(1.0, -tan($phi) * tan($delta)));   // jour/nuit polaires
    $ws    = acos($arg);
    return (24 * 60 / M_PI) * 0.0820 * $dr
        * ($ws * sin($phi) * sin($delta) + cos($phi) * cos($delta) * sin($ws));
}

/** ET0 journalière (mm/jour), méthode de Hargreaves. */
function et0_hargreaves(float $t_min, float $t_max, float $latitude_deg, int $jour_annee): float
{
    $t_moy = ($t_max + $t_min) / 2;
    $ra    = rayonnement_extraterrestre($latitude_deg, $jour_annee);
    return max(0.0, 0.0023 * ($t_moy + 17.8) * sqrt(max(0.0, $t_max - $t_min)) * 0.408 * $ra);
}

/**
 * Indicateurs sur une liste de jours (une période, ex. une saison de végétation).
 * @param array<int,array{date:string,t_min:?float,t_max:?float,precip:?float}> $jours
 * @param array $culture ligne de Dim_Culture
 */
function calculer_periode(array $jours, array $culture, float $latitude): array
{
    $gdd = 0.0; $gel = 0; $stress = 0; $bilan = 0.0; $manquants = 0;
    $a_gdd = $a_gel = $a_stress = $a_bilan = false;   // au moins un jour exploitable

    foreach ($jours as $j) {
        $tn = $j['t_min'] ?? null; $tx = $j['t_max'] ?? null; $p = $j['precip'] ?? null;
        if ($tn === null || $tx === null || $p === null) { $manquants++; }

        if ($tn !== null && $culture['seuil_gel_c'] !== null) {
            $a_gel = true;
            if ($tn < $culture['seuil_gel_c']) { $gel++; }
        }
        if ($tx !== null && $culture['seuil_stress_thermique_c'] !== null) {
            $a_stress = true;
            if ($tx > $culture['seuil_stress_thermique_c']) { $stress++; }
        }
        if ($tn !== null && $tx !== null && $culture['t_base_c'] !== null) {
            $a_gdd = true;
            $gdd += max(0.0, ($tx + $tn) / 2 - $culture['t_base_c']);
        }
        if ($tn !== null && $tx !== null && $p !== null) {
            $a_bilan = true;
            $doy = (int) date('z', strtotime($j['date'])) + 1;
            $bilan += $p - et0_hargreaves($tn, $tx, $latitude, $doy);
        }
    }
    return [
        'gdd'                => $a_gdd    ? $gdd    : null,
        'jours_gel'          => $a_gel    ? $gel    : null,
        'jours_stress'       => $a_stress ? $stress : null,
        'bilan_hydrique'     => $a_bilan  ? $bilan  : null,
        'nb_jours'           => count($jours),
        'nb_jours_manquants' => $manquants,
    ];
}

/** Filtre une année avec la période de végétation 'MM-JJ' (null = année entière). */
function jours_de_vegetation(array $jours_annee, ?string $debut, ?string $fin): array
{
    if ($debut === null || $fin === null) { return $jours_annee; }
    return array_values(array_filter($jours_annee, static function ($j) use ($debut, $fin) {
        $md = substr($j['date'], 5);
        return $debut <= $fin ? ($md >= $debut && $md <= $fin) : ($md >= $debut || $md <= $fin);
    }));
}

/**
 * Indicateurs moyens sur un horizon (ex. 2020-2049) = une ligne de Fact_Indicateur.
 * Une année est retenue si au moins $part_min de ses jours sont complets.
 * @param array $serie jours triés, avec date AAAA-MM-JJ
 */
function calculer_horizon(array $serie, array $culture, float $latitude,
                          int $annee_debut, int $annee_fin, float $part_min = 0.95): array
{
    $par_annee = [];
    foreach ($serie as $j) {
        $an = (int) substr($j['date'], 0, 4);
        if ($an >= $annee_debut && $an <= $annee_fin) { $par_annee[$an][] = $j; }
    }
    $cles = ['gdd', 'jours_gel', 'jours_stress', 'bilan_hydrique'];
    $somme = array_fill_keys($cles, 0.0); $n = array_fill_keys($cles, 0);
    $annees_valides = 0; $manquants_total = 0;

    foreach ($par_annee as $jours) {
        $jours = jours_de_vegetation($jours, $culture['periode_debut_mmjj'] ?? null, $culture['periode_fin_mmjj'] ?? null);
        $r = calculer_periode($jours, $culture, $latitude);
        $manquants_total += $r['nb_jours_manquants'];
        if ($r['nb_jours'] === 0 || ($r['nb_jours'] - $r['nb_jours_manquants']) / $r['nb_jours'] < $part_min) { continue; }
        $annees_valides++;
        foreach ($cles as $k) { if ($r[$k] !== null) { $somme[$k] += $r[$k]; $n[$k]++; } }
    }
    $out = [];
    foreach ($cles as $k) { $out[$k] = $n[$k] > 0 ? $somme[$k] / $n[$k] : null; }
    $out['nb_annees_valides']  = $annees_valides;
    $out['nb_jours_manquants'] = $manquants_total;
    return $out;
}
