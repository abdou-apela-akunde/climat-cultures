<?php
declare(strict_types=1);

class ApiException extends RuntimeException {}
class QuotaDepasse extends ApiException {}
class ApiIndisponible extends ApiException {}
class ReponseInvalide extends ApiException {}
class DonneesVides extends ApiException {}

/**
 * Client Open-Meteo Climate API (DEV). Produit le flux A du contrat d'interface.
 * - un appel = une commune, un modèle, une période ;
 * - cache fichier (hors dossier public) : les projections ne changent pas ;
 * - 2 nouvelles tentatives sur erreur réseau / 5xx, aucune sur 429 ni 4xx ;
 * - la clé n'apparaît jamais dans un message d'erreur ni dans le cache.
 */
class OpenMeteoClient
{
    private const VARIABLES = 'temperature_2m_max,temperature_2m_min,temperature_2m_mean,precipitation_sum';
    private $http;

    /** @param callable|null $http fn(string $url): array{0:int,1:string} — injectable pour les tests */
    public function __construct(
        private string $apiKey,
        private string $cacheDir,
        ?callable $http = null,
        private int $ttlSecondes = 2592000,          // 30 jours
        private array $pausesRetry = [1, 3],         // secondes
        private string $base = 'https://customer-climate-api.open-meteo.com/v1/climate'
    ) {
        $this->http = $http ?? [$this, 'httpCurl'];
    }

    /** @return array<int,array> lignes au format du contrat (flux A) */
    public function serie(string $codeInsee, float $lat, float $lon, string $modele, int $anneeDebut, int $anneeFin): array
    {
        if ($anneeDebut < 1950 || $anneeFin > 2049 || $anneeDebut > $anneeFin) {
            throw new InvalidArgumentException('Période hors de [1950, 2049]');
        }
        $cle = sha1(implode('|', [round($lat, 4), round($lon, 4), $modele, $anneeDebut, $anneeFin]));
        $fichier = rtrim($this->cacheDir, '/') . "/$cle.json";
        if (is_file($fichier) && time() - filemtime($fichier) < $this->ttlSecondes) {
            $json = json_decode((string) file_get_contents($fichier), true);
        } else {
            $json = $this->appeler($lat, $lon, $modele, $anneeDebut, $anneeFin);
            $this->verifier($json);
            if (!is_dir($this->cacheDir)) { mkdir($this->cacheDir, 0775, true); }
            file_put_contents($fichier, json_encode($json), LOCK_EX);
        }
        return $this->versContrat($json, $codeInsee, $modele);
    }

    private function appeler(float $lat, float $lon, string $modele, int $a0, int $a1): array
    {
        $url = $this->base . '?' . http_build_query([
            'latitude' => $lat, 'longitude' => $lon, 'models' => $modele,
            'start_date' => "$a0-01-01", 'end_date' => "$a1-12-31",
            'daily' => self::VARIABLES, 'apikey' => $this->apiKey,
        ]);
        $essais = count($this->pausesRetry) + 1;
        for ($i = 0; $i < $essais; $i++) {
            try {
                [$code, $corps] = ($this->http)($url);
            } catch (Throwable $e) {
                $code = 0; $corps = '';
            }
            if ($code === 200) {
                $json = json_decode($corps, true);
                if (!is_array($json)) { throw new ReponseInvalide('JSON illisible'); }
                return $json;
            }
            if ($code === 429) { throw new QuotaDepasse('Quota Open-Meteo atteint'); }
            if ($code >= 400 && $code < 500) { throw new ReponseInvalide("Requête refusée (HTTP $code)"); }
            if ($i < $essais - 1) { sleep($this->pausesRetry[$i]); }
        }
        throw new ApiIndisponible('Open-Meteo ne répond pas après ' . $essais . ' essais');
    }

    private function verifier(array $json): void
    {
        $d = $json['daily'] ?? null;
        if (!is_array($d) || !isset($d['time'])) { throw new ReponseInvalide('Champ daily absent'); }
        $n = count($d['time']);
        foreach (['temperature_2m_max', 'temperature_2m_min', 'temperature_2m_mean', 'precipitation_sum'] as $v) {
            if (!isset($d[$v]) || count($d[$v]) !== $n) { throw new ReponseInvalide("Champ $v absent ou de longueur différente"); }
        }
        $tout_vide = true;
        foreach (['temperature_2m_max', 'temperature_2m_min', 'precipitation_sum'] as $v) {
            foreach ($d[$v] as $x) { if ($x !== null) { $tout_vide = false; break 2; } }
        }
        if ($tout_vide) { throw new DonneesVides('Toutes les valeurs sont vides'); }
    }

    private function versContrat(array $json, string $codeInsee, string $modele): array
    {
        $this->verifier($json);
        $d = $json['daily']; $out = [];
        $f = static fn($x) => $x === null ? null : (float) $x;   // json_decode peut rendre un entier
        foreach ($d['time'] as $i => $date) {
            $out[] = [
                'code_insee' => $codeInsee, 'date' => $date,
                't_min' => $f($d['temperature_2m_min'][$i]), 't_max' => $f($d['temperature_2m_max'][$i]),
                't_moy' => $f($d['temperature_2m_mean'][$i]), 'precip' => $f($d['precipitation_sum'][$i]),
                'modele' => $modele,
            ];
        }
        return $out;
    }

    private function httpCurl(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 5]);
        $corps = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($corps === false) { throw new RuntimeException('Erreur réseau'); }
        return [$code, (string) $corps];
    }
}
