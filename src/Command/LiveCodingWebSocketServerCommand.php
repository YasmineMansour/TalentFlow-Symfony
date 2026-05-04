<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\LiveCodingSnapshotService;
use App\Service\LiveCodingTokenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:websocket:live-coding',
    description: 'Demarre le serveur WebSocket pour le live coding en temps reel.',
)]
class LiveCodingWebSocketServerCommand extends Command
{
    /** @var array<int, array{socket: resource, handshaken: bool, entretienId: int|null, userId: int|null, buffer: string, userName: string, roleLabel: string, isTyping: bool}> */
    private array $clients = [];

    /** @var array<int, array<int, true>> */
    private array $rooms = [];

    /** @var array<int, string> */
    private array $roomCode = [];

    /** @var array<int, array<string, mixed>> */
    private array $roomMetadata = [];

    public function __construct(
        private readonly LiveCodingTokenService $tokenService,
        private readonly LiveCodingSnapshotService $snapshotService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host WebSocket', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Port WebSocket', '8081');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $host = (string) $input->getOption('host');
        $port = (int) $input->getOption('port');

        $server = @stream_socket_server(
            sprintf('tcp://%s:%d', $host, $port),
            $errno,
            $errstr
        );

        if ($server === false) {
            $io->error(sprintf('Impossible de demarrer le serveur WS: %s (%d)', $errstr, $errno));
            return Command::FAILURE;
        }

        stream_set_blocking($server, false);
        $io->success(sprintf('Live coding WS serveur demarre sur ws://%s:%d', $host, $port));

        while (true) {
            $read = [$server];
            foreach ($this->clients as $client) {
                $read[] = $client['socket'];
            }

            $write = null;
            $except = null;
            $changed = @stream_select($read, $write, $except, 1);
            if ($changed === false) {
                continue;
            }

            foreach ($read as $socket) {
                if ($socket === $server) {
                    $conn = @stream_socket_accept($server, 0);
                    if ($conn !== false) {
                        stream_set_blocking($conn, false);
                        $id = intval($conn);
                        $this->clients[$id] = [
                            'socket' => $conn,
                            'handshaken' => false,
                            'entretienId' => null,
                            'userId' => null,
                            'buffer' => '',
                            'userName' => 'User #' . $id,
                            'roleLabel' => 'Participant',
                            'isTyping' => false,
                        ];
                    }
                    continue;
                }

                $id = intval($socket);
                if (!isset($this->clients[$id])) {
                    continue;
                }

                $data = @fread($socket, 65535);
                if ($data === '' || $data === false) {
                    if (feof($socket)) {
                        $this->disconnect($id);
                    }
                    continue;
                }

                if (!$this->clients[$id]['handshaken']) {
                    $this->clients[$id]['buffer'] .= $data;
                    if (str_contains($this->clients[$id]['buffer'], "\r\n\r\n")) {
                        $this->performHandshake($id, $this->clients[$id]['buffer']);
                        $this->clients[$id]['buffer'] = '';
                    }
                    continue;
                }

                $decoded = $this->decodeFrame($data);
                if ($decoded === null) {
                    continue;
                }

                $this->handleMessage($id, $decoded);
            }
        }
    }

    private function performHandshake(int $id, string $httpRequest): void
    {
        if (!preg_match('/Sec-WebSocket-Key: (.*)\r\n/i', $httpRequest, $matches)) {
            $this->disconnect($id);
            return;
        }

        $key = trim($matches[1]);
        $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

        $response = "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: {$accept}\r\n\r\n";

        @fwrite($this->clients[$id]['socket'], $response);
        $this->clients[$id]['handshaken'] = true;
    }

    private function handleMessage(int $id, string $payload): void
    {
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return;
        }

        $type = $data['type'] ?? null;
        if (!is_string($type)) {
            return;
        }

        if ($type === 'auth') {
            $token = $data['token'] ?? '';
            if (!is_string($token) || $token === '') {
                $this->sendJson($id, ['type' => 'error', 'message' => 'Token manquant']);
                $this->disconnect($id);
                return;
            }

            $payloadData = $this->tokenService->verifyToken($token);
            if ($payloadData === null) {
                $this->sendJson($id, ['type' => 'error', 'message' => 'Token invalide']);
                $this->disconnect($id);
                return;
            }

            $entretienId = (int) $payloadData['entretienId'];
            $userId = (int) $payloadData['userId'];
            $userName = trim((string) ($data['userName'] ?? ''));
            $roleLabel = trim((string) ($data['roleLabel'] ?? 'Participant'));

            $this->clients[$id]['entretienId'] = $entretienId;
            $this->clients[$id]['userId'] = $userId;
            $this->clients[$id]['userName'] = $userName !== '' ? mb_substr($userName, 0, 80) : 'Participant #' . $userId;
            $this->clients[$id]['roleLabel'] = $roleLabel !== '' ? mb_substr($roleLabel, 0, 40) : 'Participant';
            $this->rooms[$entretienId][$id] = true;

            if (!isset($this->roomCode[$entretienId])) {
                $this->roomCode[$entretienId] = $this->snapshotService->loadCode($entretienId);
            }

            $this->sendJson($id, [
                'type' => 'auth_ok',
                'entretienId' => $entretienId,
                'code' => $this->roomCode[$entretienId],
                'userName' => $this->clients[$id]['userName'],
                'roleLabel' => $this->clients[$id]['roleLabel'],
            ]);

            $this->broadcastPresence($entretienId);
            return;
        }

        $entretienId = $this->clients[$id]['entretienId'];
        if (!is_int($entretienId)) {
            return;
        }

        if ($type === 'code_update') {
            $code = $data['code'] ?? '';
            if (!is_string($code)) {
                return;
            }

            $this->roomCode[$entretienId] = $code;
            $this->snapshotService->saveCode($entretienId, $code);

            $this->broadcastToRoom($entretienId, [
                'type' => 'code_update',
                'code' => $code,
                'userName' => $this->clients[$id]['userName'],
                'userId' => $this->clients[$id]['userId'],
                'updatedAt' => date('H:i:s'),
            ], $id);
            return;
        }

        if ($type === 'cursor_update') {
            $line = $data['line'] ?? null;
            $column = $data['column'] ?? null;

            if (!is_int($line) || !is_int($column)) {
                return;
            }

            $this->broadcastToRoom($entretienId, [
                'type' => 'cursor_update',
                'line' => $line,
                'column' => $column,
                'userId' => $this->clients[$id]['userId'],
            ], $id);
            return;
        }

        if ($type === 'chat_message') {
            $message = trim((string) ($data['message'] ?? ''));
            if ($message === '' || strlen($message) > 1000) {
                return;
            }

            $this->broadcastToRoom($entretienId, [
                'type' => 'chat_message',
                'message' => $message,
                'userName' => $this->clients[$id]['userName'],
                'userId' => $this->clients[$id]['userId'],
                'timestamp' => date('H:i:s'),
            ]);
            return;
        }

        if ($type === 'typing_start') {
            $this->clients[$id]['isTyping'] = true;
            $this->broadcastTypingStatus($entretienId);
            return;
        }

        if ($type === 'typing_end') {
            $this->clients[$id]['isTyping'] = false;
            $this->broadcastTypingStatus($entretienId);
            return;
        }
    }

    private function broadcastTypingStatus(int $entretienId): void
    {
        $typingUsers = [];
        if (isset($this->rooms[$entretienId])) {
            foreach (array_keys($this->rooms[$entretienId]) as $clientId) {
                if ($this->clients[$clientId]['isTyping']) {
                    $typingUsers[] = [
                        'userName' => $this->clients[$clientId]['userName'],
                        'userId' => $this->clients[$clientId]['userId'],
                        'roleLabel' => $this->clients[$clientId]['roleLabel'],
                    ];
                }
            }
        }

        $this->broadcastToRoom($entretienId, [
            'type' => 'typing_status',
            'users' => $typingUsers,
        ]);
    }

    private function broadcastPresence(int $entretienId): void
    {
        $count = isset($this->rooms[$entretienId]) ? count($this->rooms[$entretienId]) : 0;
        $users = [];

        if (isset($this->rooms[$entretienId])) {
            foreach (array_keys($this->rooms[$entretienId]) as $clientId) {
                if (!isset($this->clients[$clientId])) {
                    continue;
                }

                $users[] = [
                    'userId' => $this->clients[$clientId]['userId'],
                    'userName' => $this->clients[$clientId]['userName'],
                    'roleLabel' => $this->clients[$clientId]['roleLabel'],
                    'isTyping' => $this->clients[$clientId]['isTyping'],
                ];
            }
        }

        $this->broadcastToRoom($entretienId, [
            'type' => 'presence',
            'count' => $count,
            'users' => $users,
        ]);
    }

    private function broadcastToRoom(int $entretienId, array $data, ?int $exceptId = null): void
    {
        if (!isset($this->rooms[$entretienId])) {
            return;
        }

        foreach (array_keys($this->rooms[$entretienId]) as $clientId) {
            if ($exceptId !== null && $clientId === $exceptId) {
                continue;
            }
            $this->sendJson($clientId, $data);
        }
    }

    private function sendJson(int $id, array $data): void
    {
        if (!isset($this->clients[$id])) {
            return;
        }

        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        @fwrite($this->clients[$id]['socket'], $this->encodeFrame($json));
    }

    private function disconnect(int $id): void
    {
        if (!isset($this->clients[$id])) {
            return;
        }

        $entretienId = $this->clients[$id]['entretienId'];
        $socket = $this->clients[$id]['socket'];
        $wasTyping = $this->clients[$id]['isTyping'];
        @fclose($socket);
        unset($this->clients[$id]);

        if (is_int($entretienId) && isset($this->rooms[$entretienId][$id])) {
            unset($this->rooms[$entretienId][$id]);
            if (empty($this->rooms[$entretienId])) {
                unset($this->rooms[$entretienId]);
            } else {
                if ($wasTyping) {
                    $this->broadcastTypingStatus($entretienId);
                }
                $this->broadcastPresence($entretienId);
            }
        }
    }

    private function encodeFrame(string $payload): string
    {
        $len = strlen($payload);
        $header = chr(0x81);

        if ($len <= 125) {
            $header .= chr($len);
        } elseif ($len <= 65535) {
            $header .= chr(126) . pack('n', $len);
        } else {
            $header .= chr(127) . pack('J', $len);
        }

        return $header . $payload;
    }

    private function decodeFrame(string $data): ?string
    {
        $length = strlen($data);
        if ($length < 6) {
            return null;
        }

        $secondByte = ord($data[1]);
        $masked = ($secondByte & 0x80) === 0x80;
        $payloadLen = $secondByte & 0x7F;
        $offset = 2;

        if ($payloadLen === 126) {
            if ($length < 8) {
                return null;
            }
            $payloadLen = unpack('n', substr($data, $offset, 2))[1];
            $offset += 2;
        } elseif ($payloadLen === 127) {
            return null;
        }

        if (!$masked) {
            return null;
        }

        $mask = substr($data, $offset, 4);
        $offset += 4;

        $payload = substr($data, $offset, $payloadLen);
        if ($payload === false) {
            return null;
        }

        $decoded = '';
        for ($i = 0; $i < strlen($payload); $i++) {
            $decoded .= $payload[$i] ^ $mask[$i % 4];
        }

        return $decoded;
    }
}
