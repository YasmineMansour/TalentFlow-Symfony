<?php

namespace App\Tests\Service;

use App\Service\CurrencyConverterService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CurrencyConverterServiceTest extends TestCase
{
    private function createServiceWithMock(array $apiRates = [], bool $shouldFail = false): CurrencyConverterService
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        if ($shouldFail) {
            $httpClient->method('request')->willThrowException(new \RuntimeException('API down'));
        } else {
            $response = $this->createMock(ResponseInterface::class);
            $response->method('toArray')->willReturn([
                'result' => 'success',
                'rates' => $apiRates,
            ]);
            $httpClient->method('request')->willReturn($response);
        }

        return new CurrencyConverterService($httpClient);
    }

    public function testGetSupportedCurrencies(): void
    {
        $service = $this->createServiceWithMock();
        $currencies = $service->getSupportedCurrencies();

        $this->assertArrayHasKey('EUR', $currencies);
        $this->assertArrayHasKey('USD', $currencies);
        $this->assertArrayHasKey('GBP', $currencies);
        $this->assertCount(13, $currencies);
    }

    public function testConvertWithApiSuccess(): void
    {
        $service = $this->createServiceWithMock(['EUR' => 0.30]);
        $result = $service->convert(1000, 'EUR');

        $this->assertSame(1000.0, $result['amount']);
        $this->assertSame(300.0, $result['converted']);
        $this->assertSame('EUR', $result['currency']);
        $this->assertSame('Euro', $result['currencyLabel']);
        $this->assertSame(0.30, $result['rate']);
        $this->assertSame('api', $result['source']);
    }

    public function testConvertWithApiFallback(): void
    {
        $service = $this->createServiceWithMock([], true);
        $result = $service->convert(1000, 'EUR');

        $this->assertSame(1000.0, $result['amount']);
        $this->assertSame('EUR', $result['currency']);
        $this->assertSame('fallback', $result['source']);
        $this->assertGreaterThan(0, $result['converted']);
    }

    public function testConvertUnsupportedCurrency(): void
    {
        $service = $this->createServiceWithMock();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Devise non supportée');
        $service->convert(1000, 'XYZ');
    }

    public function testConvertRangeMinMax(): void
    {
        $service = $this->createServiceWithMock(['USD' => 0.32]);
        $result = $service->convertRange(2000, 4000, 'USD');

        $this->assertSame(640.0, $result['min']);
        $this->assertSame(1280.0, $result['max']);
        $this->assertSame('USD', $result['currency']);
        $this->assertSame('api', $result['source']);
    }

    public function testConvertRangeNullMin(): void
    {
        $service = $this->createServiceWithMock(['EUR' => 0.29]);
        $result = $service->convertRange(null, 5000, 'EUR');

        $this->assertNull($result['min']);
        $this->assertSame(1450.0, $result['max']);
    }

    public function testConvertRangeNullMax(): void
    {
        $service = $this->createServiceWithMock(['EUR' => 0.29]);
        $result = $service->convertRange(3000, null, 'EUR');

        $this->assertSame(870.0, $result['min']);
        $this->assertNull($result['max']);
    }

    public function testConvertCaseInsensitive(): void
    {
        $service = $this->createServiceWithMock(['EUR' => 0.29]);
        $result = $service->convert(100, 'eur');

        $this->assertSame('EUR', $result['currency']);
    }

    public function testGetRateApiSuccess(): void
    {
        $service = $this->createServiceWithMock(['GBP' => 0.25]);
        $rate = $service->getRate('GBP');

        $this->assertSame(0.25, $rate['rate']);
        $this->assertSame('api', $rate['source']);
    }

    public function testGetRateFallback(): void
    {
        $service = $this->createServiceWithMock([], true);
        $rate = $service->getRate('GBP');

        $this->assertSame(0.25, $rate['rate']);
        $this->assertSame('fallback', $rate['source']);
    }
}
