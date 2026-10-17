<?php
declare(strict_types=1);
/**
 * Crée (ou recrée) la base SQLite à partir de DATA/creation_bdd.sql.
 * Usage : php scripts/init_db.php [chemin/vers/climat.sqlite]
 * Le fichier .sqlite doit rester HORS du dossier public du site.
 * ATTENTION : le script efface les tables existantes.
 */
$sql  = __DIR__ . '/../DATA/creation_bdd.sql';
$base = $argv[1] ?? (__DIR__ . '/../../data/climat.sqlite');
@mkdir(dirname($base), 0775, true);
$pdo = new PDO('sqlite:' . $base, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec(file_get_contents($sql));
foreach (['Dim_Commune','Dim_Culture','Dim_Temps','Dim_Modele','Fact_Indicateur'] as $t) {
    echo str_pad($t, 16), $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn(), " ligne(s)\n";
}
echo "Base créée : $base\n";
