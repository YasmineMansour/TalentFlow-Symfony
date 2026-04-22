<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LiveCodingRunnerService
{
    private const LOCAL_TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $runnerBaseUrl,
    ) {
    }

    public function execute(string $language, string $code, string $stdin = ''): array
    {
        $statusCode = null;
        $data = null;
        $transportError = null;

        try {
            $response = $this->httpClient->request('POST', rtrim($this->runnerBaseUrl, '/') . '/execute', [
                'json' => [
                    'language' => $language,
                    'code' => $code,
                    'stdin' => $stdin,
                ],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            $transportError = $exception->getMessage();
        } catch (\Throwable $exception) {
            $transportError = $exception->getMessage();
        }

        if ($transportError !== null) {
            return $this->executeLocally($language, $code, $stdin, $transportError);
        }

        if ($statusCode === null || $data === null) {
            return $this->executeLocally($language, $code, $stdin, 'Erreur HTTP inattendue');
        }

        if ($statusCode >= 400) {
            return [
                'ok' => false,
                'error' => $data['error'] ?? 'Erreur runner',
            ];
        }

        return [
            'ok' => true,
            'stdout' => (string) ($data['stdout'] ?? ''),
            'stderr' => (string) ($data['stderr'] ?? ''),
            'exitCode' => (int) ($data['exitCode'] ?? 0),
            'timedOut' => (bool) ($data['timedOut'] ?? false),
            'durationMs' => (int) ($data['durationMs'] ?? 0),
        ];
    }

    private function executeLocally(string $language, string $code, string $stdin, string $transportError): array
    {
        $command = $this->resolveInterpreter($language);
        if ($command === null) {
            $availInterpreters = [];
            if ($this->isExecutableCommandAvailable('php')) $availInterpreters[] = 'php';
            if ($this->isExecutableCommandAvailable('python')) $availInterpreters[] = 'python';
            if ($this->isExecutableCommandAvailable('node')) $availInterpreters[] = 'node';
            
            return [
                'ok' => false,
                'error' => sprintf(
                    'Runner indisponible et aucun interprete local trouve pour %s. Interpretes disponibles: %s. Détail: %s',
                    $language,
                    empty($availInterpreters) ? 'aucun' : implode(', ', $availInterpreters),
                    $transportError
                ),
            ];
        }

        $extension = match ($language) {
            'php' => 'php',
            'python' => 'py',
            'javascript' => 'js',
            default => 'txt',
        };

        $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tf-live-coding-' . bin2hex(random_bytes(6));
        if (!@mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            return [
                'ok' => false,
                'error' => 'Impossible de preparer l execution locale.',
            ];
        }

        $filePath = $tempDir . DIRECTORY_SEPARATOR . 'main.' . $extension;
        
        // Wrap code with language-specific boilerplate if needed
        $wrappedCode = $code;
        if ($language === 'php' && !str_starts_with(trim($code), '<?')) {
            $wrappedCode = '<?php ' . $code;
        }
        
        file_put_contents($filePath, $wrappedCode);

        $startedAt = microtime(true);
        $process = new Process([$command, $filePath], null, null, $stdin, self::LOCAL_TIMEOUT_SECONDS);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            $this->cleanupTempDir($tempDir);

            return [
                'ok' => true,
                'stdout' => '',
                'stderr' => 'Execution interrompue: delai depasse.',
                'exitCode' => 124,
                'timedOut' => true,
                'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
                'engine' => 'local',
            ];
        } catch (\Throwable $exception) {
            $this->cleanupTempDir($tempDir);

            return [
                'ok' => false,
                'error' => 'Execution locale impossible: ' . $exception->getMessage(),
            ];
        }

        $stdout = $process->getOutput() ?? '';
        $stderr = $process->getErrorOutput() ?? '';
        $exitCode = $process->getExitCode();
        
        $result = [
            'ok' => true,
            'stdout' => (string) $stdout,
            'stderr' => (string) $stderr,
            'exitCode' => $exitCode !== null ? (int) $exitCode : 0,
            'timedOut' => false,
            'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
            'engine' => 'local',
        ];

        $this->cleanupTempDir($tempDir);

        return $result;
    }

    private function resolveInterpreter(string $language): ?string
    {
        $windowsCandidates = match ($language) {
            'php' => [PHP_BINARY, 'C:\\xampp\\php\\php.exe', 'php'],
            'javascript' => [
                'C:\\Program Files\\nodejs\\node.exe',
                'C:\\Program Files (x86)\\nodejs\\node.exe',
                'node',
            ],
            'python' => [
                'C:\\Python313\\python.exe',
                'C:\\Python312\\python.exe',
                'C:\\Python311\\python.exe',
                'python',
                'python3',
            ],
            default => [],
        };

        $unixCandidates = match ($language) {
            'php' => [PHP_BINARY, '/usr/bin/php', 'php'],
            'javascript' => ['/usr/bin/node', 'node'],
            'python' => ['/usr/bin/python3', 'python3', 'python'],
            default => [],
        };

        $candidates = DIRECTORY_SEPARATOR === '\\' ? $windowsCandidates : $unixCandidates;

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            if ($this->isExecutableCommandAvailable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isExecutableCommandAvailable(string $candidate): bool
    {
        if (str_contains($candidate, DIRECTORY_SEPARATOR)) {
            return is_file($candidate);
        }

        try {
            $process = new Process(DIRECTORY_SEPARATOR === '\\' ? ['where', $candidate] : ['which', $candidate]);
            $process->setTimeout(2);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function cleanupTempDir(string $tempDir): void
    {
        if (!is_dir($tempDir)) {
            return;
        }

        $files = scandir($tempDir) ?: [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            @unlink($tempDir . DIRECTORY_SEPARATOR . $file);
        }

        @rmdir($tempDir);
    }
}
