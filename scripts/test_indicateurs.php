<?php
declare(strict_types=1);
/** Tests du module de calcul. Usage : php scripts/test_indicateurs.php */
require __DIR__ . '/lib/indicateurs.php';

$racine = dirname(__DIR__);
// Blé tendre d'hiver : valeurs du schéma MariaDB (DATA/sql/03_schema_mariadb.sql) ; Paris : 48,85° N.
// Les tests du calcul n'ont pas besoin de base de données.
$culture = ['nom' => "Blé tendre d'hiver", 't_base_c' => 0.0, 'gdd_maturite_cj' => 2150.0, 'seuil_gel_c' => -4.0,
            'besoin_eau_mm' => 500.0, 't_opt_min_c' => 15.0, 't_opt_max_c' => 22.0, 'seuil_stress_thermique_c' => 30.0,
            'periode_debut_mmjj' => null, 'periode_fin_mmjj' => null];
$lat = 48.85;

// Séries de test (format du contrat : le champ `modele` porte le nom du cas)
$series = [];
$h = fopen("$racine/DATA/tests/series_test.csv", 'r'); $cols = fgetcsv($h);
while (($l = fgetcsv($h)) !== false) {
    $r = array_combine($cols, $l);
    foreach (['t_min','t_max','t_moy','precip'] as $c) { $r[$c] = $r[$c] === '' ? null : (float) $r[$c]; }
    $series[$r['modele']][] = $r;
}
$ok = 0; $ko = 0;
function verifier(string $nom, $obtenu, $attendu, float $tol = 1e-9): void {
    global $ok, $ko;
    if ($attendu === null) { return; }          // colonne non contrôlée
    $bon = $obtenu !== null && abs($obtenu - $attendu) <= $tol;
    $bon ? $ok++ : $ko++;
    printf("  %s %-22s obtenu=%s attendu=%s\n", $bon ? 'OK' : 'KO', $nom, var_export($obtenu, true), var_export($attendu, true));
}

$h = fopen("$racine/DATA/tests/resultats_attendus.csv", 'r'); $cols = fgetcsv($h);
while (($l = fgetcsv($h)) !== false) {
    $e = array_combine($cols, $l);
    echo $e['cas'], "\n";
    $r = calculer_periode($series[$e['cas']], $culture, $lat);
    $f = fn($v) => $v === '' ? null : (float) $v;
    verifier('gdd', $r['gdd'], $f($e['gdd']));
    verifier('jours_gel', $r['jours_gel'] === null ? null : (float) $r['jours_gel'], $f($e['jours_gel']));
    verifier('jours_stress', $r['jours_stress'] === null ? null : (float) $r['jours_stress'], $f($e['jours_stress']));
    verifier('nb_jours_manquants', (float) $r['nb_jours_manquants'], $f($e['nb_jours_manquants']));
    verifier('bilan_hydrique', $r['bilan_hydrique'], $f($e['bilan_hydrique']), 0.5);
}

// Seuil NULL dans le référentiel : indicateur non calculable (olivier : GDD maturité NULL n'empêche pas le reste)
echo "SEUIL_ABSENT\n";
$c2 = $culture; $c2['seuil_gel_c'] = null;
$r = calculer_periode($series['TEST_D_SEUIL_EXACT'], $c2, $lat);
$bon = $r['jours_gel'] === null && $r['jours_stress'] === 1; $bon ? $ok++ : $ko++;
echo "  ", $bon ? 'OK' : 'KO', " jours_gel = null quand seuil_gel_c est NULL (et pas 0)\n";

// Horizon : 3 années complètes, gel = 3, 1 et année avec 20 % de jours manquants (écartée)
echo "HORIZON\n";
$serie = [];
foreach ([2040 => 3, 2041 => 1, 2042 => 0] as $an => $nbgel) {
    for ($d = new DateTime("$an-01-01"), $i = 0; $d->format('Y') == $an; $d->modify('+1 day'), $i++) {
        $serie[] = ['date' => $d->format('Y-m-d'), 't_min' => $i < $nbgel ? -6.0 : 5.0, 't_max' => 15.0, 'precip' => 1.0];
    }
}
foreach ($serie as $k => &$j) { if (substr($j['date'], 0, 4) === '2042' && $k % 5 === 0) { $j['t_min'] = null; } } unset($j);
$r = calculer_horizon($serie, $culture, $lat, 2040, 2042);
$b1 = $r['nb_annees_valides'] === 2; $b2 = abs($r['jours_gel'] - 2.0) < 1e-9;
($b1 ? $ok++ : $ko++); ($b2 ? $ok++ : $ko++);
echo "  ", $b1 ? 'OK' : 'KO', " nb_annees_valides = 2 (2042 écartée : trop de jours manquants)\n";
echo "  ", $b2 ? 'OK' : 'KO', " jours_gel moyen = (3+1)/2 = 2\n";

echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
