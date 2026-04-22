<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Candidature;
use App\Service\CandidatureAiRecommendationService;
use PHPUnit\Framework\TestCase;

class CandidatureAiRecommendationServiceTest extends TestCase
{
    private CandidatureAiRecommendationService $service;

    protected function setUp(): void
    {
        $this->service = new CandidatureAiRecommendationService();
    }

    public function testBlockingCandidatureWithMissingCvProducesUrgentRecommendation(): void
    {
        $candidature = $this->makeCandidature('Developpeur PHP');

        $result = $this->service->generateRecommendation($candidature, [
            'summary' => [
                'completenessLevel' => 'BLOQUANT',
                'priorityCategory' => 'INCOMPLETE',
            ],
            'decision' => [
                'canMoveToRhValidation' => false,
                'duplicateSeverity' => 'NONE',
            ],
            'issues' => [
                'missingFields' => ['Competences'],
                'missingDocuments' => ['CV'],
                'blockingReasons' => ['Le CV est manquant.'],
            ],
        ]);

        $this->assertSame('needs_improvement', $result['status']);
        $this->assertStringContainsString('n est pas encore pret', $result['summary']);
        $this->assertTrue($this->contains($result['recommendations'], 'CV a jour'));
    }

    public function testIncompleteCandidatureWithMissingFieldsSuggestsProfileCompletion(): void
    {
        $candidature = $this->makeCandidature('Analyste Data');

        $result = $this->service->generateRecommendation($candidature, [
            'summary' => [
                'completenessLevel' => 'A_COMPLETER',
                'priorityCategory' => 'A_EXAMINER',
            ],
            'decision' => [
                'canMoveToRhValidation' => true,
                'duplicateSeverity' => 'NONE',
            ],
            'issues' => [
                'missingFields' => ['Compétences', "Niveau d'études", "Années d'expérience"],
                'missingDocuments' => [],
                'blockingReasons' => [],
            ],
        ]);

        $this->assertContains($result['status'], ['improvable', 'good']);
        $this->assertTrue($this->contains($result['recommendations'], 'competences principales'));
        $this->assertTrue($this->contains($result['recommendations'], 'niveau d etudes'));
        $this->assertTrue($this->contains($result['recommendations'], 'annees d experience'));
    }

    public function testLowPriorityWithoutBlockingSuggestsStrengtheningProfile(): void
    {
        $candidature = $this->makeCandidature('Chef de projet');

        $result = $this->service->generateRecommendation($candidature, [
            'summary' => [
                'completenessLevel' => 'COMPLET',
                'priorityCategory' => 'FAIBLE_PRIORITE',
            ],
            'decision' => [
                'canMoveToRhValidation' => true,
                'duplicateSeverity' => 'NONE',
            ],
            'issues' => [
                'missingFields' => [],
                'missingDocuments' => [],
                'blockingReasons' => [],
            ],
        ]);

        $this->assertSame('improvable', $result['status']);
        $this->assertStringContainsString('priorite faible', $result['rhSummary']);
        $this->assertTrue($this->contains($result['recommendations'], 'Renforcer la candidature'));
    }

    public function testCompleteCandidatureReturnsConstructivePositiveMessage(): void
    {
        $candidature = $this->makeCandidature('Ingénieur QA');

        $result = $this->service->generateRecommendation($candidature, [
            'summary' => [
                'completenessLevel' => 'COMPLET',
                'priorityCategory' => 'PRIORITAIRE',
            ],
            'decision' => [
                'canMoveToRhValidation' => true,
                'duplicateSeverity' => 'NONE',
            ],
            'issues' => [
                'missingFields' => [],
                'missingDocuments' => [],
                'blockingReasons' => [],
            ],
        ]);

        $this->assertSame('good', $result['status']);
        $this->assertStringContainsString('globalement complet', $result['summary']);
        $this->assertNotEmpty($result['candidateMessage']);
    }

    public function testPartialAnalysisDoesNotCrashAndReturnsSafePayload(): void
    {
        $candidature = $this->makeCandidature('QA Engineer');

        $result = $this->service->generateRecommendation($candidature, [
            'summary' => [],
            'decision' => ['duplicateSeverity' => 'NONE'],
            'issues' => [
                'missingFields' => ['Compétences', 123, null],
                'missingDocuments' => [],
                'blockingReasons' => [],
            ],
        ]);

        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('candidateMessage', $result);
        $this->assertArrayHasKey('rhSummary', $result);
        $this->assertArrayHasKey('recommendations', $result);
        $this->assertArrayHasKey('tone', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertSame('professional', $result['tone']);
        $this->assertNotEmpty($result['recommendations']);
    }

    public function testBlockingCandidatureRhSummaryMentionsBlockingState(): void
    {
        $candidature = $this->makeCandidature('Développeur Symfony');

        $result = $this->service->generateRecommendation($candidature, [
            'summary' => [
                'completenessLevel' => 'BLOQUANT',
                'priorityCategory' => 'INCOMPLETE',
            ],
            'decision' => [
                'canMoveToRhValidation' => false,
                'duplicateSeverity' => 'NONE',
            ],
            'issues' => [
                'missingFields' => ['Compétences'],
                'missingDocuments' => ['CV'],
                'blockingReasons' => ['CV manquant'],
            ],
        ]);

        $this->assertSame('needs_improvement', $result['status']);
        $this->assertStringContainsString('bloquant', mb_strtolower($result['rhSummary']));
    }

    private function makeCandidature(string $titre): Candidature
    {
        $candidature = new Candidature();
        $candidature->setTitrePoste($titre);
        $candidature->setEntreprise('TalentFlow');
        $candidature->setTypeContrat('CDI');
        $candidature->setStatut('En attente');

        return $candidature;
    }

    /**
     * @param list<string> $haystack
     */
    private function contains(array $haystack, string $needle): bool
    {
        foreach ($haystack as $item) {
            if (str_contains($item, $needle)) {
                return true;
            }
        }

        return false;
    }
}
