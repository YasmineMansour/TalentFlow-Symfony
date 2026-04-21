<?php

namespace App\Service;

use App\Entity\Candidature;
use App\Entity\CandidatureStatusHistory;
use App\Entity\User;
use App\Service\CandidatureCompletenessService;
use App\Service\CandidatureDuplicateGuardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

class CandidatureWorkflowService
{
    private const ACTIVE_STATUSES = ['En attente', 'Validée RH', 'Entretien'];
    private const ACTIVATING_TRANSITIONS = ['validate_rh', 'schedule_interview'];

    public function __construct(
        #[Autowire(service: 'state_machine.candidature_process')]
        private readonly WorkflowInterface $workflow,
        private readonly EntityManagerInterface $entityManager,
        private readonly RecruitmentService $recruitmentService,
        private readonly CandidatureCompletenessService $completenessService,
        private readonly CandidatureDuplicateGuardService $duplicateGuard,
    ) {}

    /**
     * @return string[]
     */
    public function getEnabledTransitions(Candidature $candidature): array
    {
        $enabled = [];
        foreach ($this->workflow->getEnabledTransitions($candidature) as $transition) {
            $enabled[] = $transition->getName();
        }

        return $enabled;
    }

    public function applyTransition(Candidature $candidature, string $transitionName, ?User $actor = null, ?string $note = null): void
    {
        if (!$this->workflow->can($candidature, $transitionName)) {
            throw new \InvalidArgumentException(sprintf('Transition "%s" non autorisee depuis le statut "%s".', $transitionName, (string) $candidature->getStatut()));
        }

        if (in_array($transitionName, self::ACTIVATING_TRANSITIONS, true)) {
            $analysis = $this->duplicateGuard->analyze($candidature, $candidature->getId());
            if ($analysis['severity'] === 'BLOCKING') {
                throw new \DomainException((string) ($analysis['reason'] ?? 'Doublon bloquant detecte.'));
            }
        }

        $blockMessage = $this->completenessService->checkTransitionAllowed($candidature, $transitionName);
        if ($blockMessage !== null) {
            throw new \DomainException($blockMessage);
        }

        $fromStatus = $candidature->getStatut();
        $this->workflow->apply($candidature, $transitionName);

        $history = (new CandidatureStatusHistory())
            ->setCandidature($candidature)
            ->setChangedBy($actor)
            ->setFromStatus($fromStatus)
            ->setToStatus((string) $candidature->getStatut())
            ->setTransitionName($transitionName)
            ->setNote($note);

        $candidature->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($history);

        try {
            $this->recruitmentService->sendCandidatureStatusNotification($candidature, $fromStatus);
        } catch (\Throwable) {
            // Email notifications are non-blocking for workflow transitions.
        }
    }

    public function transitionToStatus(Candidature $candidature, string $targetStatus, ?User $actor = null): void
    {
        $targetStatus = trim($targetStatus);
        if ($targetStatus === (string) $candidature->getStatut()) {
            return;
        }

        if (in_array($targetStatus, self::ACTIVE_STATUSES, true)) {
            $analysis = $this->duplicateGuard->analyze($candidature, $candidature->getId());
            if ($analysis['severity'] === 'BLOCKING') {
                throw new \DomainException((string) ($analysis['reason'] ?? 'Doublon bloquant detecte.'));
            }
        }

        $map = [
            'Validée RH' => 'validate_rh',
            'Entretien' => 'schedule_interview',
            'Acceptée' => 'accept',
            'Refusée' => 'reject',
        ];

        if (!isset($map[$targetStatus])) {
            throw new \InvalidArgumentException('Statut cible invalide.');
        }

        $this->applyTransition($candidature, $map[$targetStatus], $actor, 'Transition demandee depuis le formulaire.');
    }
}
