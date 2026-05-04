<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class LiveCodingTokenService
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
    ) {
    }

    public function createToken(int $entretienId, int $userId, int $ttlSeconds = 7200): string
    {
        $payload = [
            'entretienId' => $entretienId,
            'userId' => $userId,
            'iat' => time(),
            'exp' => time() + $ttlSeconds,
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $encodedPayload = $this->base64UrlEncode($json);
        $signature = hash_hmac('sha256', $encodedPayload, $this->appSecret, true);

        return $encodedPayload . '.' . $this->base64UrlEncode($signature);
    }

    public function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $encodedSignature] = $parts;
        $expectedSignature = hash_hmac('sha256', $encodedPayload, $this->appSecret, true);
        $actualSignature = $this->base64UrlDecode($encodedSignature);

        if ($actualSignature === false || !hash_equals($expectedSignature, $actualSignature)) {
            return null;
        }

        $payloadJson = $this->base64UrlDecode($encodedPayload);
        if ($payloadJson === false) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        $entretienId = $payload['entretienId'] ?? null;
        $userId = $payload['userId'] ?? null;
        $exp = $payload['exp'] ?? null;

        if (!is_int($entretienId) || !is_int($userId) || !is_int($exp)) {
            return null;
        }

        if ($exp < time()) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string|false
    {
        $padding = 4 - (strlen($data) % 4);
        if ($padding < 4) {
            $data .= str_repeat('=', $padding);
        }

        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
