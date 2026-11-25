<?php
declare(strict_types=1);
/**
 * Crée (ou recrée) les tables MariaDB à partir de DATA/sql/03_schema_mariadb.sql.
 * Usage : php scripts/init_db.php --confirmer
 * ATTENTION : le script SUPPRIME puis recrée les tables (la base est partagée par l'équipe).
 * Variables : DB_HOST, DB_NAME, DB_USER, DB_PASS (fichier .env, jamais dans git).
 */
require __DIR__ . '/lib/db.php';

if (!in_array('--confirmer', $argv, true)) {
    fwrite(STDERR, "Ce script supprime puis recrée les tables. Prévenir l'équipe, puis relancer avec --confirmer.\n");
    exit(2);
}
charger_env(__DIR__ . '/../.env');
$pdo = connexion_bdd();
$n = executer_script($pdo, file_get_contents(__DIR__ . '/../DATA/sql/03_schema_mariadb.sql'));
echo "$n instruction(s) exécutée(s).\n";
foreach (['Dim_Commune', 'Dim_Culture', 'Dim_Temps', 'Dim_Modele', 'Ref_Source_Seuil', 'Fact_Indicateur'] as $t) {
    echo str_pad($t, 17), $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn(), " ligne(s)\n";
}
