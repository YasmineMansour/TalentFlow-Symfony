<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Candidature;
use App\Service\CandidatureAiRecommendationService;
use App\Service\OpenAiCandidatureRecommendationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OpenAiCandidatureRecommendationServiceTest extends TestCase
{
    public function testSuccessfulOpenAiResponseIsNormalized(): void
    {
        $json = '{"candidateMessage":"Votre dossier est prometteur mais incomplet.","rhSummary":"Dossier exploitable avec compléments à fournir.","recommendations":["Ajouter un CV à jour.","Préciser les compétences clés."]}';

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'output' => [
                    [
                        'content' => [
                            ['text' => $json],
                        ],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE)),
        ]);

        $service = $this->makeService($httpClient, 'valid-key');
        $result = $service->generateRecommendation($this->makeCandidature(), []);

        $this->assertTrue($result['success']);
        $this->assertSame('openai', $result['source']);
        $this->assertSame('Votre dossier est prometteur mais incomplet.', $result['data']['candidateMessage']);
        $this->assertNotEmpty($result['data']['recommendations']);
    }

    public function testOpenAiFailureUsesFallbackRecommendation(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"error":"unauthorized"}', ['http_code' => 401]),
        ]);

        $service = $this->makeService($httpClient, 'valid-key');
        $result = $service->generateRecommendation($this->makeCandidature(), [
            'summary' => ['completenessLevel' => 'BLOQUANT', 'priorityCategory' => 'INCOMPLETE'],
            'decision' => ['canMoveToRhValidation' => false, 'duplicateSeverity' => 'NONE'],
            'issues' => ['missingFields' => ['Compétences'], 'missingDocuments' => ['CV'], 'blockingReasons' => ['CV manquant']],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('fallback', $result['source']);
        $this->assertNotEmpty($result['data']['candidateMessage']);
        $this->assertNotEmpty($result['data']['recommendations']);
    }

    public function testMalformedOpenAiPayloadUsesFallbackRecommendation(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'output' => [
                    [
                        'content' => [
                            ['text' => 'not-json-response'],
                        ],
                    ],
                ],
            ])),
        ]);

        $service = $this->makeService($httpClient, 'valid-key');
        $result = $service->generateRecommendation($this->makeCandidature(), []);

        $this->assertFalse($result['success']);
        $this->assertSame('fallback', $result['source']);
        $this->assertNotEmpty($result['data']['rhSummary']);
    }

    private function makeService(MockHttpClient $httpClient, string $apiKey): OpenAiCandidatureRecommendationService
    {
        return new OpenAiCandidatureRecommendationService(
            $httpClient,
            new NullLogger(),
            new CandidatureAiRecommendationService(),
            $apiKey,
            'gpt-test',
            'https://api.openai.com/v1',
            '',
            '',
        );
    }

    private function makeCandidature(): Candidature
    {
        $candidature = new Candidature();
        $candidature->setTitrePoste('Développeur Symfony');
        $candidature->setEntreprise('TalentFlow');
        $candidature->setTypeContrat('CDI');
        $candidature->setStatut('En attente');

        return $candidature;
    }
}
