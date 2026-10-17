<?php
declare(strict_types=1);
/** Test d'intégration du pré-calcul avec de fausses réponses HTTP. Usage : php scripts/test_precalcul.php */
require __DIR__ . '/lib/precalcul.php';
$ok = 0; $ko = 0;
function t(string $n, bool $b): void { global $ok, $ko; $b ? $ok++ : $ko++; echo ($b ? '  OK ' : '  KO '), $n, "\n"; }

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec(file_get_contents(dirname(__DIR__) . '/DATA/creation_bdd.sql'));

// Fausse API : EC_Earth renvoie du vide ; les autres, 10 jours de gel (t_min = -6) par an en janvier
$http = function (string $url): array {
    parse_str(parse_url($url, PHP_URL_QUERY), $q);
    $a0 = (int) substr($q['start_date'], 0, 4); $a1 = (int) substr($q['end_date'], 0, 4);
    $time = $tx = $tn = $tm = $pr = [];
    for ($d = new DateTime("$a0-01-01"); (int) $d->format('Y') <= $a1; $d->modify('+1 day')) {
        $time[] = $d->format('Y-m-d');
        $vide = $q['models'] === 'EC_Earth3P_HR';
        $gel = (int) $d->format('n') === 1 && (int) $d->format('j') <= 10;
        $tx[] = $vide ? null : 12.0; $tn[] = $vide ? null : ($gel ? -6.0 : 4.0);
        $tm[] = $vide ? null : 5.0;  $pr[] = $vide ? null : 2.0;
    }
    return [200, json_encode(['daily' => ['time' => $time, 'temperature_2m_max' => $tx, 'temperature_2m_min' => $tn,
        'temperature_2m_mean' => $tm, 'precipitation_sum' => $pr]])];
};
$cache = sys_get_temp_dir() . '/cc_pre_' . bin2hex(random_bytes(4));
$client = new OpenMeteoClient('K', $cache, $http);

$r = precalculer($pdo, $client, ['75056'], ['MRI_AGCM3_2_S', 'EC_Earth3P_HR']);
$n = (int) $pdo->query('SELECT COUNT(*) FROM Fact_Indicateur')->fetchColumn();
t('MRI : 10 cultures x 2 horizons = 20 faits écrits', $r['ecrits'] === 20 && $n === 20);
t('EC_Earth (données vides) ignoré avec un message, sans planter', count($r['ignores']) === 2 && str_contains($r['ignores'][0], 'DonneesVides'));
$g = $pdo->query("SELECT jours_gel, nb_annees_valides FROM Fact_Indicateur f JOIN Dim_Culture u USING(id_culture) WHERE u.nom LIKE 'Blé%' AND id_temps = 2")->fetch(PDO::FETCH_ASSOC);
t('blé (gel < -4) : 10 jours de gel par an sur 30 ans', abs($g['jours_gel'] - 10.0) < 1e-9 && (int) $g['nb_annees_valides'] === 30);
$mais = $pdo->query("SELECT jours_gel FROM Fact_Indicateur f JOIN Dim_Culture u USING(id_culture) WHERE u.nom = 'Maïs grain' AND id_temps = 2")->fetchColumn();
t('maïs (seuil 0) : mêmes 10 jours de gel, seuil propre à la culture', abs((float) $mais - 10.0) < 1e-9);
$ol = $pdo->query("SELECT gdd FROM Fact_Indicateur f JOIN Dim_Culture u USING(id_culture) WHERE u.nom = 'Olivier' AND id_temps = 2")->fetchColumn();
t('olivier : GDD calculé malgré gdd_maturite_cj NULL', $ol !== null);
$r2 = precalculer($pdo, $client, ['75056'], ['MRI_AGCM3_2_S']);
t('relance : aucun doublon (UPSERT)', (int) $pdo->query('SELECT COUNT(*) FROM Fact_Indicateur')->fetchColumn() === 20);
$ver = $pdo->query('SELECT COUNT(*) FROM Fact_Indicateur WHERE verdict IS NOT NULL')->fetchColumn();
t('verdict laissé NULL (règle non définie)', (int) $ver === 0);

$quota = new OpenMeteoClient('K', $cache . '_q', fn($u) => [429, '']);
$r3 = precalculer($pdo, $quota, ['59350'], ['MRI_AGCM3_2_S']);
t('quota atteint : arrêt propre avec message', $r3['arret'] !== null && $r3['ecrits'] === 0);
echo "\n$ok OK, $ko KO\n"; exit($ko === 0 ? 0 : 1);
