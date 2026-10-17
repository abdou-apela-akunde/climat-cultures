<?php
declare(strict_types=1);
/**
 * Remplit Fact_Indicateur depuis Open-Meteo.
 * Usage : php scripts/precalcul.php [--base=chemin.sqlite] [--commune=75056,05023] [--modele=MRI_AGCM3_2_S]
 * La clé API est lue dans la variable d'environnement OPENMETEO_API_KEY (jamais dans le code ni dans git).
 */
require __DIR__ . '/lib/precalcul.php';

$opt = [];
foreach (array_slice($argv, 1) as $a) { if (preg_match('/^--(\w+)=(.*)$/', $a, $m)) { $opt[$m[1]] = $m[2]; } }
$cle = getenv('OPENMETEO_API_KEY');
if ($cle === false || $cle === '') { fwrite(STDERR, "OPENMETEO_API_KEY absente de l'environnement.\n"); exit(2); }
$base = $opt['base'] ?? (__DIR__ . '/../../data/climat.sqlite');
$pdo = new PDO('sqlite:' . $base, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$client = new OpenMeteoClient($cle, dirname($base) . '/cache');
$r = precalculer($pdo, $client,
    isset($opt['commune']) ? explode(',', $opt['commune']) : null,
    isset($opt['modele'])  ? explode(',', $opt['modele'])  : null,
    static function (string $m): void { echo $m, "\n"; });
echo "\n{$r['ecrits']} ligne(s) écrite(s), " . count($r['ignores']) . " couple(s) ignoré(s).\n";
foreach ($r['ignores'] as $i) { echo " - $i\n"; }
if ($r['arret']) { echo $r['arret'], "\n"; exit(1); }
