<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class CurrencyConverterService
{
    private const API_URL = 'https://open.er-api.com/v6/latest/TND';

    private const SUPPORTED_CURRENCIES = [
        'EUR' => 'Euro',
        'USD' => 'Dollar américain',
        'GBP' => 'Livre sterling',
        'CAD' => 'Dollar canadien',
        'CHF' => 'Franc suisse',
        'SAR' => 'Riyal saoudien',
        'AED' => 'Dirham émirati',
        'MAD' => 'Dirham marocain',
        'QAR' => 'Riyal qatari',
        'KWD' => 'Dinar koweïtien',
        'JPY' => 'Yen japonais',
        'CNY' => 'Yuan chinois',
        'TRY' => 'Livre turque',
    ];

    private const FALLBACK_RATES = [
        'EUR' => 0.29,
        'USD' => 0.32,
        'GBP' => 0.25,
        'CAD' => 0.44,
        'CHF' => 0.28,
        'SAR' => 1.20,
        'AED' => 1.17,
        'MAD' => 3.18,
        'QAR' => 1.16,
        'KWD' => 0.098,
        'JPY' => 47.0,
        'CNY' => 2.31,
        'TRY' => 10.3,
    ];

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /**
     * @return array<string, string> code => label
     */
    public function getSupportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    /**
     * Fetch real exchange rates from API, fallback to static rates on failure.
     *
     * @return array{rate: float, source: string}
     */
    public function getRate(string $targetCurrency): array
    {
        $targetCurrency = strtoupper($targetCurrency);

        if (!isset(self::SUPPORTED_CURRENCIES[$targetCurrency])) {
            throw new \InvalidArgumentException("Devise non supportée : $targetCurrency");
        }

        try {
            $response = $this->httpClient->request('GET', self::API_URL, [
                'timeout' => 5,
            ]);

            $data = $response->toArray();

            if (($data['result'] ?? '') === 'success' && isset($data['rates'][$targetCurrency])) {
                return [
                    'rate' => (float) $data['rates'][$targetCurrency],
                    'source' => 'api',
                ];
            }
        } catch (\Throwable $e) {
            // Fallback to static rates
        }

        return [
            'rate' => self::FALLBACK_RATES[$targetCurrency],
            'source' => 'fallback',
        ];
    }

    /**
     * Convert an amount from DT to target currency.
     *
     * @return array{amount: float, converted: float, currency: string, currencyLabel: string, rate: float, source: string}
     */
    public function convert(float $amount, string $targetCurrency): array
    {
        $targetCurrency = strtoupper($targetCurrency);
        $rateData = $this->getRate($targetCurrency);

        return [
            'amount' => $amount,
            'converted' => round($amount * $rateData['rate'], 2),
            'currency' => $targetCurrency,
            'currencyLabel' => self::SUPPORTED_CURRENCIES[$targetCurrency],
            'rate' => $rateData['rate'],
            'source' => $rateData['source'],
        ];
    }

    /**
     * Convert a salary range (min/max) from DT to target currency.
     *
     * @return array{min: array|null, max: array|null, currency: string, currencyLabel: string, rate: float, source: string}
     */
    public function convertRange(?float $min, ?float $max, string $targetCurrency): array
    {
        $targetCurrency = strtoupper($targetCurrency);
        $rateData = $this->getRate($targetCurrency);

        return [
            'min' => $min !== null ? round($min * $rateData['rate'], 2) : null,
            'max' => $max !== null ? round($max * $rateData['rate'], 2) : null,
            'currency' => $targetCurrency,
            'currencyLabel' => self::SUPPORTED_CURRENCIES[$targetCurrency],
            'rate' => $rateData['rate'],
            'source' => $rateData['source'],
        ];
    }
}
