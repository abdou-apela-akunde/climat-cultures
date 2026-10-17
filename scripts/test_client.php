<?php
declare(strict_types=1);
/** Teste le client API avec de fausses réponses HTTP (aucun appel réseau). Usage : php scripts/test_client.php */
require __DIR__ . '/lib/OpenMeteoClient.php';
$ok = 0; $ko = 0;
function t(string $n, bool $b): void { global $ok, $ko; $b ? $ok++ : $ko++; echo ($b ? '  OK ' : '  KO '), $n, "\n"; }
function rep(array $over = []): string {
    $jours = []; for ($d = new DateTime('2049-01-01'); $d->format('Y') === '2049'; $d->modify('+1 day')) { $jours[] = $d->format('Y-m-d'); }
    $n = count($jours);
    return json_encode(['daily' => array_merge([
        'time' => $jours, 'temperature_2m_max' => array_fill(0, $n, 15.0), 'temperature_2m_min' => array_fill(0, $n, 5.0),
        'temperature_2m_mean' => array_fill(0, $n, 10.0), 'precipitation_sum' => array_fill(0, $n, 1.0)], $over)]);
}
function nouveau(callable $http, ?string &$dir = null): OpenMeteoClient {
    $dir = sys_get_temp_dir() . '/cc_cache_' . bin2hex(random_bytes(4));
    return new OpenMeteoClient('SECRET123', $dir, $http, 3600, [0, 0]);
}
$appels = 0;

echo "Réponse nominale\n";
$c = nouveau(function ($u) use (&$appels) { $appels++; return [200, rep()]; }, $dir);
$s = $c->serie('75056', 48.85, 2.35, 'MRI_AGCM3_2_S', 2049, 2049);
t('365 jours renvoyés', count($s) === 365);
t('colonnes du contrat', array_keys($s[0]) === ['code_insee','date','t_min','t_max','t_moy','precip','modele']);
t('code INSEE gardé en texte', $s[0]['code_insee'] === '75056');
$c->serie('75056', 48.85, 2.35, 'MRI_AGCM3_2_S', 2049, 2049);
t('2e appel servi par le cache (1 seul appel réseau)', $appels === 1);
t('la clé API n\'est pas écrite dans le cache', strpos(implode('', array_map('file_get_contents', glob("$dir/*.json"))), 'SECRET123') === false);

echo "Valeurs manquantes\n";
$c = nouveau(fn($u) => [200, rep(['temperature_2m_min' => array_merge([null], array_fill(0, 364, 5.0))])]);
$s = $c->serie('75056', 48.85, 2.35, 'MRI_AGCM3_2_S', 2049, 2049);
t('une valeur nulle reste null (pas 0)', $s[0]['t_min'] === null && $s[1]['t_min'] === 5.0);

echo "Erreurs\n";
$essai = function (callable $http, string $classe) {
    try { nouveau($http)->serie('75056', 48.85, 2.35, 'MRI_AGCM3_2_S', 2049, 2049); } catch (Throwable $e) { return $e instanceof $classe; }
    return false;
};
t('429 -> QuotaDepasse', $essai(fn($u) => [429, ''], QuotaDepasse::class));
t('400 -> ReponseInvalide', $essai(fn($u) => [400, '{"error":true}'], ReponseInvalide::class));
t('JSON illisible -> ReponseInvalide', $essai(fn($u) => [200, 'pas du json'], ReponseInvalide::class));
t('longueurs différentes -> ReponseInvalide', $essai(fn($u) => [200, rep(['precipitation_sum' => [1.0]])], ReponseInvalide::class));
t('tout vide -> DonneesVides', $essai(fn($u) => [200, rep(['temperature_2m_max' => array_fill(0, 365, null), 'temperature_2m_min' => array_fill(0, 365, null), 'precipitation_sum' => array_fill(0, 365, null)])], DonneesVides::class));
t('réseau coupé -> ApiIndisponible', $essai(function ($u) { throw new RuntimeException('x'); }, ApiIndisponible::class));
$n = 0;
$c = nouveau(function ($u) use (&$n) { $n++; return $n < 3 ? [503, ''] : [200, rep()]; });
t('503 puis 503 puis 200 : réussit au 3e essai', count($c->serie('75056', 48.85, 2.35, 'MRI_AGCM3_2_S', 2049, 2049)) === 365 && $n === 3);
$n = 0;
try { nouveau(function ($u) use (&$n) { $n++; return [503, '']; })->serie('75056', 48.85, 2.35, 'MRI_AGCM3_2_S', 2049, 2049); } catch (ApiIndisponible $e) {}
t('503 permanent : 3 essais puis abandon', $n === 3);
$msg = ''; try { nouveau(fn($u) => [429, ''])->serie('75056', 48.85, 2.35, 'MRI_AGCM3_2_S', 2049, 2049); } catch (Throwable $e) { $msg = $e->getMessage(); }
t('message d\'erreur sans la clé', strpos($msg, 'SECRET123') === false);
$bad = false; try { nouveau(fn($u) => [200, rep()])->serie('75056', 48.85, 2.35, 'MRI_AGCM3_2_S', 2049, 2050); } catch (InvalidArgumentException $e) { $bad = true; }
t('période au-delà de 2049 refusée avant tout appel', $bad);
echo "\n$ok OK, $ko KO\n"; exit($ko === 0 ? 0 : 1);
