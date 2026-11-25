<?php
declare(strict_types=1);
/**
 * Tests d'intégration sur une VRAIE base MariaDB de TEST (pas la base de l'équipe).
 * Usage : DB_TEST_HOST=… DB_TEST_NAME=…_test DB_TEST_USER=… DB_TEST_PASS=… php scripts/test_bdd.php
 * (ou les mêmes variables dans .env). Le script supprime puis recrée les tables de la base de test.
 * Sans ces variables, les tests sont IGNORÉS (code de sortie 3) : ils ne comptent pas comme réussis.
 */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/simulation.php';
charger_env(dirname(__DIR__) . '/.env');

if (getenv('DB_TEST_NAME') === false) { echo "IGNORÉ : pas de base de test (DB_TEST_HOST / NAME / USER / PASS).\n"; exit(3); }
$nom = (string) getenv('DB_TEST_NAME');
if ($nom === getenv('DB_NAME') || !str_ends_with($nom, '_test')) {
    fwrite(STDERR, "REFUSÉ : DB_TEST_NAME doit finir par _test et différer de DB_NAME (le script efface les tables).\n"); exit(2);
}
$ok = 0; $ko = 0;
function t(string $n, bool $b): void { global $ok, $ko; $b ? $ok++ : $ko++; echo ($b ? '  OK ' : '  KO '), $n, "\n"; }
/** Code d'erreur MariaDB (1062 doublon, 1452 clé étrangère, 4025 CHECK) de l'instruction, ou 0 si elle réussit. */
function erreur(PDO $pdo, string $sql, array $p = []): int {
    try { $pdo->prepare($sql)->execute($p); return 0; } catch (PDOException $e) { return (int) ($e->errorInfo[1] ?? -1); }
}

$pdo = connexion_bdd('DB_TEST');
executer_script($pdo, file_get_contents(dirname(__DIR__) . '/DATA/sql/03_schema_mariadb.sql'));
$n = static fn(string $t): int => (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();

echo "SCHÉMA ET DONNÉES D'AMORÇAGE\n";
t('16 communes, 10 cultures, 2 horizons, 3 modèles', $n('Dim_Commune') === 16 && $n('Dim_Culture') === 10 && $n('Dim_Temps') === 2 && $n('Dim_Modele') === 3);
t('faits et sourçage vides', $n('Fact_Indicateur') === 0 && $n('Ref_Source_Seuil') === 0);
t('Briançon garde son zéro : 05023', $pdo->query("SELECT code_insee FROM Dim_Commune WHERE nom = 'Briançon'")->fetchColumn() === '05023');
t('horizons de 30 ans : 1990-2019 et 2020-2049', $pdo->query("SELECT GROUP_CONCAT(annee_debut,'-',annee_fin ORDER BY annee_debut) FROM Dim_Temps")->fetchColumn() === '1990-2019,2020-2049');
t('olivier : GDD de maturité NULL (jamais 0)', $pdo->query("SELECT gdd_maturite_cj FROM Dim_Culture WHERE nom = 'Olivier'")->fetchColumn() === null);

echo "CONTRAINTES\n";
$ins = 'INSERT INTO Fact_Indicateur (id_session,id_commune,id_culture,id_temps,id_modele,gdd) VALUES (?,?,?,?,?,100)';
$A = str_repeat('a', 32); $B = str_repeat('b', 32);
t('sessions A et B : même simulation, deux insertions réussies', erreur($pdo, $ins, [$A,1,1,1,1]) === 0 && erreur($pdo, $ins, [$B,1,1,1,1]) === 0);
t('session A une seconde fois : refusé (#1062, uq_fact)', erreur($pdo, $ins, [$A,1,1,1,1]) === 1062);
t('commune inexistante : refusé (#1452)', erreur($pdo, $ins, [$A,999,1,1,1]) === 1452);
t('doublon de code INSEE : refusé (#1062)', erreur($pdo, "INSERT INTO Dim_Commune (code_insee,nom,latitude,longitude) VALUES ('75056','Doublon',48,2)") === 1062);
t('code INSEE de 4 caractères : refusé (CHECK)', erreur($pdo, "INSERT INTO Dim_Commune (code_insee,nom,latitude,longitude) VALUES ('5023','Court',48,2)") !== 0);
t('verdict hors liste : refusé (CHECK)', erreur($pdo, 'INSERT INTO Fact_Indicateur (id_session,id_commune,id_culture,id_temps,id_modele,verdict) VALUES (?,2,1,1,1,?)', [$A, 'moyen']) !== 0);
t('horizon dépassant 2049 : refusé (CHECK)', erreur($pdo, "INSERT INTO Dim_Temps (horizon,libelle,annee_debut,annee_fin,nb_annees) VALUES ('x','x',2030,2050,21)") !== 0);

echo "RGPD\n";
t('effacer la session A supprime ses lignes et garde celles de B', effacer_session($pdo, $A) === 1 && $n('Fact_Indicateur') === 1
  && $pdo->query('SELECT id_session FROM Fact_Indicateur')->fetchColumn() === $B);
$pdo->exec("UPDATE Fact_Indicateur SET calcule_le = NOW() - INTERVAL 31 DAY");
t('purge 30 jours : une ligne de 31 jours est supprimée', purger_anciennes_lignes($pdo, 30) === 1 && $n('Fact_Indicateur') === 0);
$pdo->prepare($ins)->execute([$A,1,1,1,1]);
t('purge 30 jours : une ligne récente est conservée', purger_anciennes_lignes($pdo, 30) === 0 && $n('Fact_Indicateur') === 1);
$pdo->exec('DELETE FROM Fact_Indicateur');

echo "ÉCRITURE (UPSERT)\n";
$lig = [['id_temps' => 1, 'gdd' => 1500.25, 'jours_gel' => 11.4, 'jours_stress' => 0.0, 'bilan_hydrique' => -53.16, 'nb_annees_valides' => 30, 'nb_jours_manquants' => 0, 'verdict' => null],
        ['id_temps' => 2, 'gdd' => 1700.0,  'jours_gel' => null, 'jours_stress' => 2.5, 'bilan_hydrique' => null,   'nb_annees_valides' => 29, 'nb_jours_manquants' => 40, 'verdict' => 'a_surveiller']];
t('2 lignes écrites (une par horizon)', enregistrer_lignes($pdo, $A, 1, 1, 1, $lig) === 2 && $n('Fact_Indicateur') === 2);
$lig[0]['gdd'] = 1600.0;
enregistrer_lignes($pdo, $A, 1, 1, 1, $lig);
t('relance : aucun doublon, valeur mise à jour', $n('Fact_Indicateur') === 2 && (float) $pdo->query('SELECT gdd FROM Fact_Indicateur WHERE id_temps = 1')->fetchColumn() === 1600.0);
$r = $pdo->query('SELECT jours_gel, jours_stress, bilan_hydrique FROM Fact_Indicateur WHERE id_temps = 1')->fetch();
t('jours de gel décimal : 11,4 conservé (pas arrondi à 11)', (float) $r['jours_gel'] === 11.4 && (float) $r['jours_stress'] === 0.0);
$r2 = $pdo->query('SELECT jours_gel, bilan_hydrique FROM Fact_Indicateur WHERE id_temps = 2')->fetch();
t('non calculable : NULL conservé, jamais 0', $r2['jours_gel'] === null && $r2['bilan_hydrique'] === null);
t('une autre session ne voit pas les lignes de A', (int) $pdo->query("SELECT COUNT(*) FROM Fact_Indicateur WHERE id_session = '$B'")->fetchColumn() === 0);
$pdo->exec('DELETE FROM Fact_Indicateur');

echo "SIMULATION DE BOUT EN BOUT (fausse API)\n";
$http = function (string $url): array {
    parse_str(parse_url($url, PHP_URL_QUERY), $q);
    $a0 = (int) substr($q['start_date'], 0, 4); $a1 = (int) substr($q['end_date'], 0, 4);
    $time = $tx = $tn = $tm = $pr = [];
    for ($d = new DateTime("$a0-01-01"); (int) $d->format('Y') <= $a1; $d->modify('+1 day')) {
        $time[] = $d->format('Y-m-d'); $gel = (int) $d->format('n') === 1 && (int) $d->format('j') <= 10;
        $tx[] = 12.0; $tn[] = $gel ? -6.0 : 4.0; $tm[] = 5.0; $pr[] = 2.0;
    }
    return [200, json_encode(['daily' => ['time' => $time, 'temperature_2m_max' => $tx, 'temperature_2m_min' => $tn, 'temperature_2m_mean' => $tm, 'precipitation_sum' => $pr]])];
};
$client = new OpenMeteoClient('K', sys_get_temp_dir() . '/cc_bdd_' . bin2hex(random_bytes(4)), $http);
t('lancer_simulation : 2 lignes écrites pour le blé à Paris', lancer_simulation($pdo, $client, $A, '75056', "Blé tendre d'hiver", 'MRI_AGCM3_2_S') === 2);
$g = $pdo->query("SELECT f.jours_gel, f.nb_annees_valides FROM Fact_Indicateur f JOIN Dim_Temps t USING(id_temps) WHERE t.horizon = 'horizon_2050'")->fetch();
t('blé : 10 jours de gel par an sur 30 ans, relu depuis la base', abs((float) $g['jours_gel'] - 10.0) < 1e-9 && (int) $g['nb_annees_valides'] === 30);
lancer_simulation($pdo, $client, $A, '75056', "Blé tendre d'hiver", 'MRI_AGCM3_2_S');
t('relance de la même simulation : toujours 2 lignes', $n('Fact_Indicateur') === 2);
try { lancer_simulation($pdo, $client, $A, '00000', 'Maïs grain', 'MRI_AGCM3_2_S'); t('commune inconnue : refusée', false); }
catch (InvalidArgumentException $e) { t('commune inconnue : refusée, rien écrit', $n('Fact_Indicateur') === 2); }

echo "\n$ok OK, $ko KO\n"; exit($ko === 0 ? 0 : 1);
