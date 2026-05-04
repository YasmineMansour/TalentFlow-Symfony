<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class CountryService
{
    private const API_URL = 'https://restcountries.com/v3.1';

    /**
     * Mapping villes → code pays ISO 3166-1 alpha2 (fallback local).
     */
    private const CITY_COUNTRY_MAP = [
        'tunis' => 'TN', 'sfax' => 'TN', 'sousse' => 'TN', 'bizerte' => 'TN',
        'gabès' => 'TN', 'kairouan' => 'TN', 'monastir' => 'TN', 'nabeul' => 'TN',
        'ariana' => 'TN', 'ben arous' => 'TN', 'manouba' => 'TN', 'zaghouan' => 'TN',
        'paris' => 'FR', 'lyon' => 'FR', 'marseille' => 'FR', 'toulouse' => 'FR',
        'nice' => 'FR', 'nantes' => 'FR', 'bordeaux' => 'FR', 'lille' => 'FR',
        'london' => 'GB', 'manchester' => 'GB', 'birmingham' => 'GB', 'edinburgh' => 'GB',
        'new york' => 'US', 'los angeles' => 'US', 'chicago' => 'US', 'san francisco' => 'US',
        'washington' => 'US', 'boston' => 'US', 'seattle' => 'US', 'austin' => 'US',
        'toronto' => 'CA', 'montreal' => 'CA', 'vancouver' => 'CA', 'ottawa' => 'CA',
        'berlin' => 'DE', 'munich' => 'DE', 'frankfurt' => 'DE', 'hamburg' => 'DE',
        'madrid' => 'ES', 'barcelona' => 'ES', 'valencia' => 'ES', 'sevilla' => 'ES',
        'rome' => 'IT', 'milan' => 'IT', 'naples' => 'IT', 'turin' => 'IT',
        'dubai' => 'AE', 'abu dhabi' => 'AE',
        'riyadh' => 'SA', 'jeddah' => 'SA', 'mecca' => 'SA',
        'casablanca' => 'MA', 'rabat' => 'MA', 'marrakech' => 'MA', 'fès' => 'MA', 'tanger' => 'MA',
        'alger' => 'DZ', 'oran' => 'DZ', 'constantine' => 'DZ',
        'le caire' => 'EG', 'cairo' => 'EG', 'alexandria' => 'EG',
        'doha' => 'QA',
        'koweït' => 'KW', 'kuwait' => 'KW',
        'tokyo' => 'JP', 'osaka' => 'JP',
        'beijing' => 'CN', 'shanghai' => 'CN',
        'istanbul' => 'TR', 'ankara' => 'TR',
        'genève' => 'CH', 'zurich' => 'CH', 'berne' => 'CH',
        'bruxelles' => 'BE', 'anvers' => 'BE',
        'amsterdam' => 'NL', 'rotterdam' => 'NL',
        'lisbonne' => 'PT', 'porto' => 'PT',
        // Noms de pays directs
        'tunisie' => 'TN', 'france' => 'FR', 'allemagne' => 'DE', 'espagne' => 'ES',
        'italie' => 'IT', 'maroc' => 'MA', 'algérie' => 'DZ', 'égypte' => 'EG',
        'qatar' => 'QA', 'émirats' => 'AE', 'arabie saoudite' => 'SA',
        'canada' => 'CA', 'états-unis' => 'US', 'usa' => 'US',
        'royaume-uni' => 'GB', 'angleterre' => 'GB',
        'japon' => 'JP', 'chine' => 'CN', 'turquie' => 'TR',
        'suisse' => 'CH', 'belgique' => 'BE', 'pays-bas' => 'NL', 'portugal' => 'PT',
    ];

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /**
     * Détecte le code pays ISO alpha2 à partir d'une localisation (ville ou pays).
     */
    public function detectCountryCode(string $localisation): ?string
    {
        $loc = mb_strtolower(trim($localisation));

        // Chercher dans le mapping local
        foreach (self::CITY_COUNTRY_MAP as $key => $code) {
            if (str_contains($loc, $key)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Récupère les infos pays depuis REST Countries API par code alpha2.
     *
     * @return array{name: string, capital: string, flag: string, flagImg: string, currency: string, currencySymbol: string, code: string, region: string}|null
     */
    public function getCountryInfo(string $countryCode): ?array
    {
        $countryCode = strtoupper($countryCode);

        try {
            $response = $this->httpClient->request('GET', self::API_URL . '/alpha/' . $countryCode, [
                'timeout' => 5,
                'query' => ['fields' => 'name,capital,flag,flags,currencies,cca2,region'],
            ]);

            $data = $response->toArray();

            $currencies = $data['currencies'] ?? [];
            $currencyCode = '';
            $currencySymbol = '';
            if (!empty($currencies)) {
                $first = array_key_first($currencies);
                $currencyCode = $first;
                $currencySymbol = $currencies[$first]['symbol'] ?? '';
            }

            return [
                'name' => $data['name']['common'] ?? $countryCode,
                'capital' => $data['capital'][0] ?? '—',
                'flag' => $data['flag'] ?? '',
                'flagImg' => $data['flags']['svg'] ?? $data['flags']['png'] ?? '',
                'currency' => $currencyCode,
                'currencySymbol' => $currencySymbol,
                'code' => $data['cca2'] ?? $countryCode,
                'region' => $data['region'] ?? '',
            ];
        } catch (\Throwable $e) {
            // Fallback statique pour les pays les plus courants
            return $this->getFallbackInfo($countryCode);
        }
    }

    /**
     * Raccourci : localisation → infos pays complètes.
     */
    public function getCountryFromLocalisation(string $localisation): ?array
    {
        $code = $this->detectCountryCode($localisation);
        if (!$code) {
            return null;
        }

        return $this->getCountryInfo($code);
    }

    private function getFallbackInfo(string $code): ?array
    {
        $fallback = [
            'TN' => ['name' => 'Tunisie', 'capital' => 'Tunis', 'flag' => '🇹🇳', 'currency' => 'TND', 'currencySymbol' => 'د.ت', 'region' => 'Africa'],
            'FR' => ['name' => 'France', 'capital' => 'Paris', 'flag' => '🇫🇷', 'currency' => 'EUR', 'currencySymbol' => '€', 'region' => 'Europe'],
            'DE' => ['name' => 'Allemagne', 'capital' => 'Berlin', 'flag' => '🇩🇪', 'currency' => 'EUR', 'currencySymbol' => '€', 'region' => 'Europe'],
            'US' => ['name' => 'États-Unis', 'capital' => 'Washington D.C.', 'flag' => '🇺🇸', 'currency' => 'USD', 'currencySymbol' => '$', 'region' => 'Americas'],
            'GB' => ['name' => 'Royaume-Uni', 'capital' => 'Londres', 'flag' => '🇬🇧', 'currency' => 'GBP', 'currencySymbol' => '£', 'region' => 'Europe'],
            'CA' => ['name' => 'Canada', 'capital' => 'Ottawa', 'flag' => '🇨🇦', 'currency' => 'CAD', 'currencySymbol' => '$', 'region' => 'Americas'],
            'MA' => ['name' => 'Maroc', 'capital' => 'Rabat', 'flag' => '🇲🇦', 'currency' => 'MAD', 'currencySymbol' => 'د.م.', 'region' => 'Africa'],
            'AE' => ['name' => 'Émirats arabes unis', 'capital' => 'Abu Dhabi', 'flag' => '🇦🇪', 'currency' => 'AED', 'currencySymbol' => 'د.إ', 'region' => 'Asia'],
            'SA' => ['name' => 'Arabie saoudite', 'capital' => 'Riyad', 'flag' => '🇸🇦', 'currency' => 'SAR', 'currencySymbol' => '﷼', 'region' => 'Asia'],
            'ES' => ['name' => 'Espagne', 'capital' => 'Madrid', 'flag' => '🇪🇸', 'currency' => 'EUR', 'currencySymbol' => '€', 'region' => 'Europe'],
            'IT' => ['name' => 'Italie', 'capital' => 'Rome', 'flag' => '🇮🇹', 'currency' => 'EUR', 'currencySymbol' => '€', 'region' => 'Europe'],
        ];

        if (!isset($fallback[$code])) {
            return null;
        }

        $info = $fallback[$code];
        $info['code'] = $code;
        $info['flagImg'] = '';

        return $info;
    }
}
