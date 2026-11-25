<?php
declare(strict_types=1);
/**
 * Lance une simulation (commune × culture × modèle, 2 horizons) pour une session, en ligne de commande.
 * Usage : php scripts/simuler.php --commune=75056 --culture="Maïs grain" --modele=MRI_AGCM3_2_S [--session=<32 hex>]
 * Sans --session, un jeton est généré et affiché. La clé API est lue dans OPENMETEO_API_KEY (.env), jamais dans le code.
 */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/simulation.php';

$opt = [];
foreach (array_slice($argv, 1) as $a) { if (preg_match('/^--(\w+)=(.*)$/', $a, $m)) { $opt[$m[1]] = $m[2]; } }
foreach (['commune', 'culture', 'modele'] as $k) {
    if (empty($opt[$k])) { fwrite(STDERR, "Option --$k manquante.\n"); exit(2); }
}
charger_env(__DIR__ . '/../.env');
$cle = getenv('OPENMETEO_API_KEY');
if ($cle === false || $cle === '') { fwrite(STDERR, "OPENMETEO_API_KEY absente de l'environnement.\n"); exit(2); }

$session = $opt['session'] ?? nouvelle_session();
$client = new OpenMeteoClient($cle, sys_get_temp_dir() . '/cache_openmeteo');
try {
    $n = lancer_simulation(connexion_bdd(), $client, $session, $opt['commune'], $opt['culture'], $opt['modele']);
    echo "$n ligne(s) écrite(s) pour la session $session\n";
} catch (QuotaDepasse $e) {
    fwrite(STDERR, "Quota Open-Meteo atteint : réessayer plus tard (le cache évite de refaire les appels réussis).\n"); exit(1);
} catch (ApiException | InvalidArgumentException $e) {
    fwrite(STDERR, get_class($e) . ' : ' . $e->getMessage() . "\n"); exit(1);
}
