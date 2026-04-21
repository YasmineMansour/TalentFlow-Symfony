<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Candidature;
use App\Repository\CandidatureStatusHistoryRepository;
use App\Repository\EntretienRepository;

final class CandidaturePriorityService
{
    private const CATEGORIES = [
        'PRIORITAIRE'     => 'Prioritaire',
        'A_EXAMINER'      => 'À examiner',
        'INCOMPLETE'      => 'Incomplète',
        'FAIBLE_PRIORITE' => 'Faible priorité',
    ];

    private const PENDING_ALERT_DAYS    = 5;
    private const RH_VALIDATED_ALERT_DAYS = 7;

    public function __construct(
        private readonly CandidatureCompletenessService $completenessService,
        private readonly EntretienRepository $entretienRepository,
        private readonly CandidatureStatusHistoryRepository $historyRepository,
    ) {
    }

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Full analysis with delay alerts (makes DB calls for alerts).
     *
     * @return array{
     *   priorityScore: int,
     *   category: string,
     *   label: string,
     *   components: array{matching: int, completeness: int, experienceEducation: int},
     *   rawScores: array{matching: int, completeness: int, experienceEducation: int},
     *   delayAlerts: array,
     *   isLate: bool,
     *   needsAttention: bool,
     *   notes: list<string>,
     * }
     */
    public function analyze(Candidature $candidature): array
    {
        $completeness  = $this->completenessService->analyze($candidature);
        $matchingScore = max(0, min(100, $candidature->getMatchingScore() ?? 0));
        $expEduScore   = $this->computeExperienceEducationScore($candidature);

        $components = [
            'matching'            => (int) round($matchingScore * 0.50),
            'completeness'        => (int) round($completeness['score'] * 0.30),
            'experienceEducation' => $expEduScore,
        ];

        $priorityScore = max(0, min(100, array_sum($components)));
        $category      = $this->classifyByRules($priorityScore, $matchingScore, $completeness['score'], $completeness['level']);
        $delayAlerts   = $this->getDelayAlerts($candidature);

        return [
            'priorityScore'  => $priorityScore,
            'category'       => $category,
            'label'          => self::CATEGORIES[$category],
            'components'     => $components,
            'rawScores'      => [
                'matching'            => $matchingScore,
                'completeness'        => $completeness['score'],
                'experienceEducation' => $expEduScore,
            ],
            'delayAlerts'    => $delayAlerts,
            'isLate'         => count($delayAlerts) > 0,
            'needsAttention' => $category === 'PRIORITAIRE' || count($delayAlerts) > 0,
            'notes'          => $this->buildNotes($completeness, $matchingScore, $expEduScore),
        ];
    }

    /**
     * Lightweight summary — no DB calls. Safe for index listings.
     *
     * @return array{priorityScore: int, category: string, label: string, isLate: bool}
     */
    public function summarize(Candidature $candidature): array
    {
        $completeness  = $this->completenessService->analyze($candidature);
        $matchingScore = max(0, min(100, $candidature->getMatchingScore() ?? 0));
        $expEduScore   = $this->computeExperienceEducationScore($candidature);

        $priorityScore = max(0, min(100,
            (int) round($matchingScore * 0.50)
            + (int) round($completeness['score'] * 0.30)
            + $expEduScore
        ));

        $category = $this->classifyByRules($priorityScore, $matchingScore, $completeness['score'], $completeness['level']);

        return [
            'priorityScore' => $priorityScore,
            'category'      => $category,
            'label'         => self::CATEGORIES[$category],
            'isLate'        => false,
        ];
    }

    public function computePriorityScore(Candidature $candidature): int
    {
        return $this->summarize($candidature)['priorityScore'];
    }

    public function classify(Candidature $candidature): string
    {
        return $this->summarize($candidature)['category'];
    }

    /**
     * @return array<int, array{code: string, label: string, severity: string, days: int}>
     */
    public function getDelayAlerts(Candidature $candidature): array
    {
        $alerts = [];
        $statut = $candidature->getStatut();
        $now    = new \DateTimeImmutable();

        if ($statut === 'En attente') {
            $enteredAt = $this->historyRepository->findLatestTransitionToStatus($candidature, 'En attente')
                ?? $candidature->getDateCandidature()
                ?? $candidature->getCreatedAt();

            if ($enteredAt !== null) {
                $days = (int) $enteredAt->diff($now)->days;
                if ($days >= self::PENDING_ALERT_DAYS) {
                    $alerts[] = [
                        'code'     => 'PENDING_TOO_OLD',
                        'label'    => 'Candidature en attente depuis plus de ' . self::PENDING_ALERT_DAYS . ' jours',
                        'severity' => 'warning',
                        'days'     => $days,
                    ];
                }
            }
        }

        if ($statut === 'Validée RH') {
            $enteredAt = $this->historyRepository->findLatestTransitionToStatus($candidature, 'Validée RH')
                ?? $candidature->getUpdatedAt()
                ?? $candidature->getCreatedAt();

            if ($enteredAt !== null) {
                $days = (int) $enteredAt->diff($now)->days;
                if ($days >= self::RH_VALIDATED_ALERT_DAYS) {
                    $candidatureId      = $candidature->getId();
                    $hasFutureInterview = $candidatureId !== null
                        && $this->entretienRepository->findFuturePlannedInterviewForCandidatureId($candidatureId) !== null;

                    if (!$hasFutureInterview) {
                        $alerts[] = [
                            'code'     => 'RH_VALIDATED_WITHOUT_INTERVIEW',
                            'label'    => 'Candidature validée RH sans entretien planifié depuis plus de ' . self::RH_VALIDATED_ALERT_DAYS . ' jours',
                            'severity' => 'danger',
                            'days'     => $days,
                        ];
                    }
                }
            }
        }

        return $alerts;
    }

    /**
     * Sort candidatures by priority score descending (no DB calls per item).
     *
     * @param iterable<Candidature> $candidatures
     * @return Candidature[]
     */
    public function sortByPriority(iterable $candidatures): array
    {
        $items = is_array($candidatures) ? $candidatures : iterator_to_array($candidatures);

        usort($items, function (Candidature $a, Candidature $b): int {
            return $this->computePriorityScore($b) <=> $this->computePriorityScore($a);
        });

        return $items;
    }

    // ── Internal helpers ────────────────────────────────────────────────────

    private function classifyByRules(int $priorityScore, int $matchingScore, int $completenessScore, string $completenessLevel): string
    {
        if ($completenessLevel === 'BLOQUANT' || $completenessScore < 50) {
            return 'INCOMPLETE';
        }

        if ($priorityScore >= 80 && $matchingScore >= 60 && $completenessScore >= 80) {
            return 'PRIORITAIRE';
        }

        if ($priorityScore >= 60) {
            return 'A_EXAMINER';
        }

        return 'FAIBLE_PRIORITE';
    }

    private function computeExperienceEducationScore(Candidature $candidature): int
    {
        return $this->experienceSubscore($candidature->getAnneesExperience())
            + $this->educationSubscore($candidature->getNiveauEtudes());
    }

    private function experienceSubscore(?int $years): int
    {
        if ($years === null || $years <= 0) {
            return 0;
        }

        return match (true) {
            $years === 1  => 3,
            $years === 2  => 5,
            $years <= 4   => 8,
            default       => 12,
        };
    }

    private function educationSubscore(?string $niveauEtudes): int
    {
        // Choices defined in Candidature entity: 'Bac', 'Bac+2', 'Bac+3', 'Bac+5', 'Doctorat', 'Autre'
        // Normalized lowercase comparison for robustness
        $level = mb_strtolower(trim((string) $niveauEtudes));

        return match ($level) {
            'bac'      => 2,
            'bac+2'    => 4,
            'bac+3'    => 6,
            'bac+5'    => 8,
            'doctorat' => 8,
            'autre'    => 1,
            default    => 0,
        };
    }

    /**
     * @return list<string>
     */
    private function buildNotes(array $completeness, int $matchingScore, int $expEduScore): array
    {
        $notes = [];

        if ($matchingScore === 0) {
            $notes[] = 'Score de matching non disponible (aucune offre liée ou non encore calculé).';
        }

        if (!empty($completeness['blockingReasons'])) {
            $notes[] = 'Dossier bloquant : ' . implode(', ', array_slice($completeness['blockingReasons'], 0, 3)) . '.';
        }

        if ($expEduScore === 0) {
            $notes[] = "Expérience et niveau d'études non renseignés.";
        }

        return $notes;
    }
}
