<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Candidature;
use App\Entity\Offre;
use App\Entity\User;
use App\Repository\CandidatureRepository;
use App\Repository\CandidatureStatusHistoryRepository;
use App\Repository\EntretienRepository;
use App\Service\CandidatureAnalysisService;
use App\Service\CandidatureAiRecommendationService;
use App\Service\CandidatureCompletenessService;
use App\Service\CandidatureDuplicateGuardService;
use App\Service\CandidatureMatchingService;
use App\Service\CandidaturePriorityService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CandidatureAnalysisServiceTest extends TestCase
{
    private CandidatureMatchingService&MockObject $matchingService;
    private CandidatureRepository&MockObject $candidatureRepository;
    private CandidatureStatusHistoryRepository&MockObject $historyRepository;
    private EntretienRepository&MockObject $entretienRepository;

    private CandidatureAnalysisService $service;

    protected function setUp(): void
    {
        $this->matchingService = $this->createMock(CandidatureMatchingService::class);
        $this->candidatureRepository = $this->createMock(CandidatureRepository::class);
        $this->historyRepository = $this->createMock(CandidatureStatusHistoryRepository::class);
        $this->entretienRepository = $this->createMock(EntretienRepository::class);

        $completenessService = new CandidatureCompletenessService();
        $duplicateService = new CandidatureDuplicateGuardService($this->candidatureRepository, $this->historyRepository);
        $priorityService = new CandidaturePriorityService($completenessService, $this->entretienRepository, $this->historyRepository);
        $aiService = new CandidatureAiRecommendationService();

        $this->service = new CandidatureAnalysisService(
            $this->matchingService,
            $completenessService,
            $duplicateService,
            $priorityService,
            $aiService,
        );
    }

    public function testAnalyzeAggregatesAndRecommendsValideeRh(): void
    {
        $candidature = $this->makeRichCandidature('En attente');
        $candidature->setMatchingScore(null); // force fallback matching service

        $this->matchingService
            ->expects($this->once())
            ->method('computeScore')
            ->with($candidature)
            ->willReturn(72);

        $this->candidatureRepository
            ->method('findPotentialDuplicatesForCandidature')
            ->willReturn([]);

        $this->historyRepository
            ->method('findLatestTransitionToStatus')
            ->willReturn(null);

        $this->entretienRepository
            ->method('findFuturePlannedInterviewForCandidatureId')
            ->willReturn(null);

        $result = $this->service->analyze($candidature);

        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('scores', $result);
        $this->assertArrayHasKey('decision', $result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertArrayHasKey('ai', $result);

        $this->assertSame('En attente', $result['summary']['currentStatus']);
        $this->assertSame('Validée RH', $result['summary']['recommendedStatus']);
        $this->assertSame('COMPLET', $result['summary']['completenessLevel']);

        $this->assertSame(72, $result['scores']['matching']);
        $this->assertGreaterThanOrEqual(80, $result['scores']['completeness']);
        $this->assertSame('NONE', $result['decision']['duplicateSeverity']);

        $this->assertSame(72, $result['analysis']['matchingScore']);
        $this->assertGreaterThanOrEqual(80, $result['analysis']['completenessScore']);
        $this->assertSame('COMPLET', $result['analysis']['completenessLevel']);
        $this->assertFalse($result['analysis']['isDuplicate']);
        $this->assertSame('NONE', $result['analysis']['duplicateSeverity']);
        $this->assertSame('Validée RH', $result['analysis']['recommendedStatus']);
        $this->assertArrayHasKey('summary', $result['ai']);
        $this->assertArrayHasKey('candidateMessage', $result['ai']);
        $this->assertArrayHasKey('rhSummary', $result['ai']);
        $this->assertArrayHasKey('recommendations', $result['ai']);
    }

    public function testRecommendedStatusStaysCurrentWhenDuplicateBlocking(): void
    {
        $candidature = $this->makeRichCandidature('En attente');
        $candidature->setMatchingScore(80);

        $existing = $this->makeRichCandidature('Validée RH');
        $existing->setOffre($candidature->getOffre());
        $existing->setCandidat($candidature->getCandidat());

        $this->candidatureRepository
            ->method('findPotentialDuplicatesForCandidature')
            ->willReturn([$existing]);

        $this->historyRepository
            ->method('findLatestRefusedAt')
            ->willReturn(null);

        $this->historyRepository
            ->method('findLatestTransitionToStatus')
            ->willReturn(null);

        $this->entretienRepository
            ->method('findFuturePlannedInterviewForCandidatureId')
            ->willReturn(null);

        $result = $this->service->analyze($candidature);

        $this->assertContains('Vérifier le doublon avant traitement.', $result['actions']);
        $this->assertSame('BLOCKING', $result['analysis']['duplicateSeverity']);
        $this->assertSame('En attente', $result['analysis']['recommendedStatus']);
        $this->assertNotEmpty($result['analysis']['blockingReasons']);
    }

    public function testBlockingReasonsContainHumanMessages(): void
    {
        $candidature = new Candidature();
        $candidature->setStatut('Validée RH');
        $candidature->setTitrePoste('Dev');
        $candidature->setEntreprise('X');
        $candidature->setTypeContrat('CDI');
        $candidature->setMatchingScore(10);

        $this->candidatureRepository
            ->method('findPotentialDuplicatesForCandidature')
            ->willReturn([]);

        $this->historyRepository
            ->method('findLatestTransitionToStatus')
            ->willReturn(null);

        $this->entretienRepository
            ->method('findFuturePlannedInterviewForCandidatureId')
            ->willReturn(null);

        $result = $this->service->analyze($candidature);
        $reasons = $result['analysis']['blockingReasons'];
        $actions = $result['actions'];

        $this->assertContains('Le CV est manquant.', $reasons);
        $this->assertContains('Le numéro de téléphone est manquant.', $reasons);
        $this->assertContains('Les compétences ne sont pas renseignées.', $reasons);
        $this->assertContains('L\'adresse e-mail est manquante ou invalide.', $reasons);
        $this->assertContains('Le dossier est incomplet pour un passage à l\'étape RH.', $reasons);
        $this->assertContains('Le dossier ne peut pas encore passer à l\'étape entretien.', $reasons);

        $this->assertContains('Ajouter un CV pour permettre la validation RH.', $actions);
        $this->assertContains('Compléter les années d\'expérience.', $actions);
        $this->assertContains('Renseigner le niveau d\'études.', $actions);
        $this->assertContains('Ajouter une lettre de motivation.', $actions);

        $this->assertSame('En attente', $result['analysis']['recommendedStatus']);
    }

    private function makeRichCandidature(string $status): Candidature
    {
        $offer = new Offre();
        $offer->setTitre('Offre Symfony');
        $offer->setDescription('Description offre');
        $offer->setTypeContrat('CDI');
        $this->setEntityId($offer, 10);

        $candidate = new User();
        $candidate->setNom('TEST');
        $candidate->setPrenom('User');
        $candidate->setEmail('candidate@test.local');
        $this->setEntityId($candidate, 20);

        $candidature = new Candidature();
        $candidature->setOffre($offer);
        $candidature->setCandidat($candidate);
        $candidature->setStatut($status);
        $candidature->setTitrePoste('Développeur Symfony');
        $candidature->setEntreprise('Tech Corp');
        $candidature->setTypeContrat('CDI');
        $candidature->setEmail('candidate@test.local');
        $candidature->setTelephone('22345678');
        $candidature->setCompetences('PHP, Symfony, API');
        $candidature->setNiveauEtudes('Bac+5');
        $candidature->setAnneesExperience(5);
        $candidature->setCvFilename('cv.pdf');
        $candidature->setLettreMotivationFilename('lettre.pdf');
        $candidature->setSalaireSouhaite('3000.00');
        $candidature->setMatchingScore(80);
        $this->setEntityId($candidature, 30);

        return $candidature;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
