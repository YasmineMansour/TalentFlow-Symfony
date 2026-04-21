<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Candidature;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAiCandidatureRecommendationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly CandidatureAiRecommendationService $fallbackService,
        private readonly string $openAiApiKey,
        private readonly string $openAiModel,
        private readonly string $openAiBaseUrl,
        private readonly string $openAiOrganization,
        private readonly string $openAiProject,
    ) {
    }

    /**
     * @param array<string,mixed> $analysis
     * @return array{
     *   success:bool,
     *   source:string,
     *   data:array{
    *     provider:string,
    *     model:?string,
     *     candidateMessage:string,
     *     rhSummary:string,
     *     recommendations:list<string>
     *   }
     * }
     */
    public function generateRecommendation(Candidature $candidature, array $analysis = []): array
    {
        $this->logger->info('AI recommendation request started.', [
            'candidature_id' => $candidature->getId(),
        ]);

        if (trim($this->openAiApiKey) === '') {
            $this->logger->warning('AI recommendation fallback used: OPENAI_API_KEY missing.', [
                'candidature_id' => $candidature->getId(),
            ]);

            return $this->buildFallbackRecommendation($candidature, $analysis, false, 'missing_api_key');
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->openAiBaseUrl, '/') . '/responses', [
                'headers' => $this->buildHeaders(),
                'json' => [
                    'model' => $this->openAiModel,
                    'input' => $this->buildPrompt($candidature, $analysis),
                    'temperature' => 0.3,
                ],
                'timeout' => 20,
            ]);

            $statusCode = $response->getStatusCode();
            $payload = $response->toArray(false);

            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->error('AI recommendation failed with non-2xx OpenAI status.', [
                    'candidature_id' => $candidature->getId(),
                    'status_code' => $statusCode,
                ]);

                return $this->buildFallbackRecommendation($candidature, $analysis, false, 'openai_http_error');
            }

            $normalized = $this->normalizeOpenAiResponse($payload);
            if ($normalized === null) {
                $this->logger->error('AI recommendation fallback used: malformed OpenAI response.', [
                    'candidature_id' => $candidature->getId(),
                ]);

                return $this->buildFallbackRecommendation($candidature, $analysis, false, 'malformed_openai_response');
            }

            $this->logger->info('AI recommendation succeeded.', [
                'candidature_id' => $candidature->getId(),
            ]);

            return [
                'success' => true,
                'source' => 'openai',
                'data' => $normalized,
            ];
        } catch (TransportExceptionInterface|\Throwable $e) {
            $this->logger->error('AI recommendation failed, fallback used.', [
                'candidature_id' => $candidature->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->buildFallbackRecommendation($candidature, $analysis, false, 'exception');
        }
    }

    /**
     * @param array<string,mixed> $analysis
     * @return array<int,array<string,mixed>>
     */
    public function buildPrompt(Candidature $candidature, array $analysis = []): array
    {
        $businessContext = [
            'titrePoste' => $candidature->getTitrePoste(),
            'entreprise' => $candidature->getEntreprise(),
            'statut' => $candidature->getStatut(),
            'analysis' => $analysis,
        ];

        return [
            [
                'role' => 'system',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => 'Tu es un assistant RH professionnel pour TalentFlow. Tu aides a ameliorer une candidature, sans jamais prendre de decision finale. Reponds en francais, ton professionnel, clair et constructif. Evite le jargon technique et n expose pas les scores internes de facon brute. Retourne strictement un JSON valide avec les cles: candidateMessage (string), rhSummary (string), recommendations (array de strings, 3 a 5 elements).',
                    ],
                ],
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => 'Analyse ces donnees metier et genere des recommandations d amelioration concises. Priorise les elements bloquants et les informations manquantes. Donnees: ' . json_encode($businessContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $response
     * @return array{candidateMessage:string,rhSummary:string,recommendations:list<string>}|null
     */
    public function normalizeOpenAiResponse(array $response): ?array
    {
        $text = $this->extractOutputText($response);
        if ($text === '') {
            return null;
        }

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            if (preg_match('/\{.*\}/s', $text, $matches) !== 1) {
                return null;
            }
            $decoded = json_decode($matches[0], true);
            if (!is_array($decoded)) {
                return null;
            }
        }

        $candidateMessage = trim((string) ($decoded['candidateMessage'] ?? ''));
        $rhSummary = trim((string) ($decoded['rhSummary'] ?? ''));
        $recommendationsRaw = $decoded['recommendations'] ?? [];

        if ($candidateMessage === '' || $rhSummary === '' || !is_array($recommendationsRaw)) {
            return null;
        }

        $recommendations = [];
        foreach ($recommendationsRaw as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $value = trim((string) $item);
            if ($value !== '') {
                $recommendations[] = $value;
            }
        }

        $recommendations = array_values(array_unique($recommendations));

        if ($recommendations === []) {
            return null;
        }

        return [
            'provider' => 'openai',
            'model' => $this->resolveConfiguredModel(),
            'candidateMessage' => $candidateMessage,
            'rhSummary' => $rhSummary,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * @param array<string,mixed> $analysis
     * @return array{
     *   success:bool,
     *   source:string,
    *   data:array{provider:string,model:?string,candidateMessage:string,rhSummary:string,recommendations:list<string>}
     * }
     */
    public function buildFallbackRecommendation(Candidature $candidature, array $analysis = [], bool $success = false, string $reason = 'fallback'): array
    {
        $fallback = $this->fallbackService->generateRecommendation($candidature, $analysis);

        $this->logger->info('AI recommendation fallback generated.', [
            'candidature_id' => $candidature->getId(),
            'reason' => $reason,
        ]);

        return [
            'success' => $success,
            'source' => 'fallback',
            'data' => [
                'provider' => 'fallback',
                'model' => null,
                'candidateMessage' => (string) ($fallback['candidateMessage'] ?? ''),
                'rhSummary' => (string) ($fallback['rhSummary'] ?? ''),
                'recommendations' => is_array($fallback['recommendations'] ?? null)
                    ? array_values($fallback['recommendations'])
                    : [],
            ],
        ];
    }

    private function resolveConfiguredModel(): ?string
    {
        $model = trim($this->openAiModel);

        return $model !== '' ? $model : null;
    }

    /**
     * @return array<string,string>
     */
    private function buildHeaders(): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->openAiApiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (!$this->isBlankLike($this->openAiOrganization)) {
            $headers['OpenAI-Organization'] = $this->openAiOrganization;
        }

        if (!$this->isBlankLike($this->openAiProject)) {
            $headers['OpenAI-Project'] = $this->openAiProject;
        }

        return $headers;
    }

    private function isBlankLike(string $value): bool
    {
        $normalized = mb_strtolower(trim($value));

        return $normalized === ''
            || $normalized === 'none'
            || $normalized === 'null'
            || $normalized === 'n/a';
    }

    /**
     * @param array<string,mixed> $response
     */
    private function extractOutputText(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text'])) {
            return trim($response['output_text']);
        }

        $output = $response['output'] ?? null;
        if (!is_array($output)) {
            return '';
        }

        foreach ($output as $item) {
            if (!is_array($item)) {
                continue;
            }

            $content = $item['content'] ?? null;
            if (!is_array($content)) {
                continue;
            }

            foreach ($content as $contentItem) {
                if (!is_array($contentItem)) {
                    continue;
                }

                $text = $contentItem['text'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    return trim($text);
                }
            }
        }

        return '';
    }
}
