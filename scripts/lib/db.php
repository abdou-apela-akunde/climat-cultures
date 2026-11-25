<?php
declare(strict_types=1);

/**
 * Connexion à la base MariaDB (AlwaysData) et petits outils SQL.
 * Les identifiants viennent de l'environnement ou du fichier .env (jamais du dépôt) :
 *   DB_HOST, DB_NAME, DB_USER, DB_PASS
 */

/** Lit un fichier .env (lignes CLE=valeur) sans écraser les variables déjà définies. */
function charger_env(string $chemin): void
{
    if (!is_file($chemin)) { return; }
    foreach (file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '' || $ligne[0] === '#' || !str_contains($ligne, '=')) { continue; }
        [$cle, $val] = explode('=', $ligne, 2);
        $cle = trim($cle); $val = trim($val, " \t\"'");
        if (getenv($cle) === false) { putenv("$cle=$val"); }
    }
}

/** Ouvre une connexion PDO MariaDB. $prefixe = 'DB' (réelle) ou 'DB_TEST' (base de test). */
function connexion_bdd(string $prefixe = 'DB'): PDO
{
    $v = [];
    foreach (['HOST', 'NAME', 'USER', 'PASS'] as $k) {
        $val = getenv("{$prefixe}_$k");
        if ($val === false || ($val === '' && $k !== 'PASS')) {
            throw new RuntimeException("Variable {$prefixe}_$k absente de l'environnement (voir .env.example).");
        }
        $v[$k] = $val;
    }
    return new PDO(
        "mysql:host={$v['HOST']};dbname={$v['NAME']};charset=utf8mb4", $v['USER'], $v['PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
}

/**
 * Découpe un script SQL en instructions : retire les lignes de commentaire « -- »
 * et coupe sur « ; » en fin de ligne (un « ; » au milieu d'un texte ne coupe pas).
 * @return string[]
 */
function decouper_sql(string $sql): array
{
    $lignes = array_filter(explode("\n", str_replace("\r", '', $sql)),
        static fn(string $l): bool => !str_starts_with(ltrim($l), '--'));
    $out = [];
    foreach (preg_split('/;[ \t]*(?:\n|$)/', implode("\n", $lignes)) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') { $out[] = $stmt; }
    }
    return $out;
}

/** Exécute un script SQL instruction par instruction (une erreur est remontée tout de suite). */
function executer_script(PDO $pdo, string $sql): int
{
    $n = 0;
    foreach (decouper_sql($sql) as $stmt) { $pdo->exec($stmt); $n++; }
    return $n;
}
