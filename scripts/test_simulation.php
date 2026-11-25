<?php
declare(strict_types=1);
/** Tests du calcul à la demande et des outils SQL, sans base de données (fausses réponses HTTP). Usage : php scripts/test_simulation.php */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/simulation.php';
$ok = 0; $ko = 0;
function t(string $n, bool $b): void { global $ok, $ko; $b ? $ok++ : $ko++; echo ($b ? '  OK ' : '  KO '), $n, "\n"; }

// Fausse API : EC_Earth renvoie du vide ; les autres, 10 jours de gel (t_min = -6) chaque janvier
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
$client = new OpenMeteoClient('K', sys_get_temp_dir() . '/cc_sim_' . bin2hex(random_bytes(4)), $http);

// Lignes telles que MariaDB les renvoie : tout en TEXTE (DECIMAL → chaîne)
$paris = ['id_commune' => '5', 'code_insee' => '75056', 'nom' => 'Paris', 'latitude' => '48.85000', 'longitude' => '2.35000'];
$ble   = ['id_culture' => '1', 'nom' => "Blé tendre d'hiver", 't_base_c' => '0.0', 'gdd_maturite_cj' => '2150.0', 'seuil_gel_c' => '-4.0',
          'besoin_eau_mm' => '500.0', 't_opt_min_c' => '15.0', 't_opt_max_c' => '22.0', 'seuil_stress_thermique_c' => '30.0',
          'periode_debut_mmjj' => null, 'periode_fin_mmjj' => null];
$mais  = ['id_culture' => '4', 'nom' => 'Maïs grain', 't_base_c' => '6.0', 'gdd_maturite_cj' => '1600.0', 'seuil_gel_c' => '0.0',
          'besoin_eau_mm' => '600.0', 't_opt_min_c' => '20.0', 't_opt_max_c' => '30.0', 'seuil_stress_thermique_c' => '35.0',
          'periode_debut_mmjj' => null, 'periode_fin_mmjj' => null];
$olive = ['id_culture' => '10', 'nom' => 'Olivier', 't_base_c' => '10.0', 'gdd_maturite_cj' => null, 'seuil_gel_c' => '-8.0',
          'besoin_eau_mm' => '300.0', 't_opt_min_c' => '25.0', 't_opt_max_c' => '32.0', 'seuil_stress_thermique_c' => '40.0',
          'periode_debut_mmjj' => null, 'periode_fin_mmjj' => null];
$mri   = ['id_modele' => '1', 'nom_modele' => 'MRI_AGCM3_2_S'];
$ec    = ['id_modele' => '2', 'nom_modele' => 'EC_Earth3P_HR'];
$hz    = [['id_temps' => '1', 'horizon' => 'reference',    'annee_debut' => '1990', 'annee_fin' => '2019'],
          ['id_temps' => '2', 'horizon' => 'horizon_2050', 'annee_debut' => '2020', 'annee_fin' => '2049']];

echo "CALCUL D'UNE SIMULATION\n";
$l = calculer_lignes_simulation($client, $paris, $ble, $mri, $hz);
t('une ligne par horizon (2), identifiants de Dim_Temps conservés', count($l) === 2 && $l[0]['id_temps'] === 1 && $l[1]['id_temps'] === 2);
t('blé (gel < -4) : 10 jours de gel par an, 30 années valides, malgré les valeurs en texte', abs($l[1]['jours_gel'] - 10.0) < 1e-9 && $l[1]['nb_annees_valides'] === 30);
$lm = calculer_lignes_simulation($client, $paris, $mais, $mri, $hz);
t('maïs (seuil 0) : mêmes 10 jours de gel, seuil propre à la culture', abs($lm[1]['jours_gel'] - 10.0) < 1e-9);
$lo = calculer_lignes_simulation($client, $paris, $olive, $mri, $hz);
t('olivier : GDD calculé malgré gdd_maturite_cj NULL', $lo[1]['gdd'] !== null);
t('verdict laissé NULL (règle non définie)', $l[0]['verdict'] === null && $l[1]['verdict'] === null);
$ccult = culture_en_nombres($olive);
t('culture_en_nombres : texte → décimal, NULL reste NULL', $ccult['t_base_c'] === 10.0 && $ccult['gdd_maturite_cj'] === null);

echo "ERREURS DU CLIENT\n";
try { calculer_lignes_simulation($client, $paris, $ble, $ec, $hz); t('données vides : DonneesVides', false); }
catch (DonneesVides $e) { t('données vides : DonneesVides, rien à écrire', true); }
$quota = new OpenMeteoClient('K', sys_get_temp_dir() . '/cc_simq_' . bin2hex(random_bytes(4)), fn($u) => [429, '']);
try { calculer_lignes_simulation($quota, $paris, $ble, $mri, $hz); t('quota : QuotaDepasse', false); }
catch (QuotaDepasse $e) { t('quota atteint : QuotaDepasse', true); }

echo "SESSION\n";
$s = nouvelle_session();
t('jeton : 32 caractères hexadécimaux', strlen($s) === 32 && session_valide($s));
t('jeton différent à chaque appel', nouvelle_session() !== nouvelle_session());
t('jetons refusés : trop court, majuscules, injection', !session_valide('abc') && !session_valide(strtoupper($s)) && !session_valide("$s' OR 1=1"));
try { enregistrer_lignes(new PDO('sqlite::memory:'), 'pas-un-jeton', 1, 1, 1, []); t('écriture avec jeton invalide refusée', false); }
catch (InvalidArgumentException $e) { t('écriture avec jeton invalide refusée avant tout accès à la base', true); }

echo "OUTILS SQL\n";
$d = decouper_sql("-- commentaire\nDROP TABLE IF EXISTS A;\nINSERT INTO B (x) VALUES ('a ; b'),\n  ('c');\n-- SELECT 1;\nCREATE TABLE C (id INT);\n");
t('découpage : 3 instructions, commentaires retirés', count($d) === 3);
t('découpage : un « ; » dans un texte ne coupe pas', str_contains($d[1], "'a ; b'") && str_contains($d[1], "('c')"));
$schema = dirname(__DIR__) . '/DATA/sql/03_schema_mariadb.sql';
if (is_file($schema)) {
    $sql = file_get_contents($schema); $inst = decouper_sql($sql);
    t('schéma MariaDB : 6 DROP + 6 CREATE + 4 INSERT = 16 instructions', count($inst) === 16);
    t('schéma MariaDB : uq_fact contient id_session', (bool) preg_match('/UNIQUE KEY uq_fact \(id_session,/', $sql));
    $dropsOk = true; foreach (array_slice($inst, 0, 6) as $i) { $dropsOk = $dropsOk && str_starts_with($i, 'DROP TABLE'); }
    t('schéma MariaDB : les 6 premières instructions sont les DROP', $dropsOk);
} else { echo "  (schéma absent : contrôles du fichier ignorés)\n"; }

echo "\n$ok OK, $ko KO\n"; exit($ko === 0 ? 0 : 1);
