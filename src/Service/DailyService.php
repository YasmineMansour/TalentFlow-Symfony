<?php

namespace App\Service;

class DailyService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.daily.co/v1';

    public function __construct(string $dailyApiKey)
    {
        $this->apiKey = $dailyApiKey;
    }

    /**
     * Create a room, or return existing one if it already exists.
     * Handles race conditions and expired rooms.
     */
    public function ensureRoom(string $roomName, int $expireMinutes = 60): array
    {
        // Try to get existing room first
        $existing = $this->getRoom($roomName);
        if ($existing) {
            // Check if room is expired
            $exp = $existing['config']['exp'] ?? 0;
            if ($exp > 0 && $exp < time()) {
                // Room expired — delete and recreate
                $this->deleteRoom($roomName);
            } else {
                return $existing;
            }
        }

        // Create new room
        try {
            return $this->createRoom($roomName, $expireMinutes);
        } catch (\RuntimeException $e) {
            // Race condition: another request created it between our GET and POST
            if (str_contains($e->getMessage(), 'already exists')) {
                $retry = $this->getRoom($roomName);
                if ($retry) {
                    return $retry;
                }
            }
            throw $e;
        }
    }

    public function createRoom(string $roomName, int $expireMinutes = 60): array
    {
        $data = [
            'name' => $roomName,
            'properties' => [
                'exp' => time() + ($expireMinutes * 60),
                'max_participants' => 4,
                'enable_chat' => false,
                'enable_screenshare' => true,
                'start_video_off' => false,
                'start_audio_off' => false,
            ],
        ];

        return $this->request('POST', '/rooms', $data);
    }

    public function getRoom(string $roomName): ?array
    {
        $result = $this->request('GET', '/rooms/' . urlencode($roomName));
        return isset($result['id']) ? $result : null;
    }

    public function createMeetingToken(string $roomName, string $userName, int $expireMinutes = 60): string
    {
        $data = [
            'properties' => [
                'room_name' => $roomName,
                'user_name' => $userName,
                'exp' => time() + ($expireMinutes * 60),
                'is_owner' => false,
                'enable_screenshare' => true,
            ],
        ];

        $result = $this->request('POST', '/meeting-tokens', $data);

        if (empty($result['token'])) {
            throw new \RuntimeException('Failed to generate meeting token');
        }

        return $result['token'];
    }

    public function deleteRoom(string $roomName): void
    {
        try {
            $this->request('DELETE', '/rooms/' . urlencode($roomName));
        } catch (\RuntimeException $e) {
            // Ignore delete errors (room may already be gone)
        }
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->apiUrl . $endpoint;
        $ch = curl_init($url);

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException('Daily.co network error: ' . $curlError);
        }

        $decoded = json_decode($response, true) ?? [];

        // GET 404 = room not found (not an error, caller handles null)
        if ($method === 'GET' && $httpCode === 404) {
            return $decoded;
        }

        if ($httpCode >= 400) {
            $errorMsg = $decoded['info'] ?? $decoded['error'] ?? $response;
            throw new \RuntimeException('Daily.co API error (' . $httpCode . '): ' . $errorMsg);
        }

        return $decoded;
    }
}
