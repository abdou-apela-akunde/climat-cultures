<?php
declare(strict_types=1);
/**
 * Durée de conservation : supprime les lignes de Fact_Indicateur de plus de N jours (30 par défaut).
 * Usage : php scripts/purger.php [--jours=30]    — à planifier une fois par jour (tâche planifiée AlwaysData).
 */
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/simulation.php';

$jours = 30;
foreach (array_slice($argv, 1) as $a) { if (preg_match('/^--jours=(\d+)$/', $a, $m)) { $jours = (int) $m[1]; } }
charger_env(__DIR__ . '/../.env');
echo purger_anciennes_lignes(connexion_bdd(), $jours), " ligne(s) supprimée(s) (plus de $jours jours).\n";
