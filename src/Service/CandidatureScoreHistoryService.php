<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Candidature;
use App\Entity\CandidatureStatusHistory;

/**
 * Service de suivi et d'analyse de l'évolution des scores d'une candidature.
 *
 * Valeur ajoutée : permet au RH de visualiser la progression d'une candidature
 * au fil du temps et de comprendre les facteurs qui influencent son score final.
 *
 * Fonctionnalités :
 * - Calcul du score global pondéré (matching + complétude + expérience)
 * - Tendance de progression (en hausse / stable / en baisse)
 * - Indicateurs de blocage
 * - Recommandations d'amélioration automatiques
 */
final class CandidatureScoreHistoryService
{
    // Poids des composantes du score global (total = 100 %)
    private const WEIGHT_MATCHING     = 0.50;
    private const WEIGHT_COMPLETENESS = 0.30;
    private const WEIGHT_EXP_EDU      = 0.20;

    // Niveaux d'études → points (max 10)
    private const EDUCATION_POINTS = [
        'Bac'      => 2,
        'Bac+2'    => 4,
        'Bac+3'    => 6,
        'Bac+5'    => 8,
        'Doctorat' => 10,
        'Autre'    => 1,
    ];

    public function __construct(
        private readonly CandidatureCompletenessService $completenessService,
    ) {
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Calcule la snapshot de score à l'instant présent.
     *
     * @return array{
     *   scoreGlobal: int,
     *   components: array{matching: int, completeness: int, expEdu: int},
     *   trend: string,
     *   trendLabel: string,
     *   recommendations: list<string>,
     *   isReadyForInterview: bool,
     *   timestamp: string,
     * }
     */
    public function computeSnapshot(Candidature $candidature): array
    {
        $matchingScore    = max(0, min(100, $candidature->getMatchingScore() ?? 0));
        $completeness     = $this->completenessService->analyze($candidature);
        $expEduScore      = $this->computeExpEduScore($candidature);

        $components = [
            'matching'     => (int) round($matchingScore * self::WEIGHT_MATCHING),
            'completeness' => (int) round($completeness['score'] * self::WEIGHT_COMPLETENESS),
            'expEdu'       => $expEduScore,
        ];

        $scoreGlobal = max(0, min(100, array_sum($components)));

        return [
            'scoreGlobal'         => $scoreGlobal,
            'components'          => $components,
            'trend'               => $this->getTrend($scoreGlobal),
            'trendLabel'          => $this->getTrendLabel($scoreGlobal),
            'recommendations'     => $this->buildRecommendations($completeness, $matchingScore, $expEduScore),
            'isReadyForInterview' => $completeness['canMoveToInterview'] && $scoreGlobal >= 50,
            'timestamp'           => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Compare deux snapshots et retourne l'évolution.
     *
     * @param array{scoreGlobal: int, ...} $previous
     * @param array{scoreGlobal: int, ...} $current
     * @return array{delta: int, direction: string, directionLabel: string}
     */
    public function compareSnapshots(array $previous, array $current): array
    {
        $delta = $current['scoreGlobal'] - $previous['scoreGlobal'];

        return [
            'delta'          => $delta,
            'direction'      => $delta > 0 ? 'UP' : ($delta < 0 ? 'DOWN' : 'STABLE'),
            'directionLabel' => $delta > 0 ? '↑ Progression' : ($delta < 0 ? '↓ Régression' : '→ Stable'),
        ];
    }

    /**
     * Construit un historique de scores à partir des transitions de statut.
     *
     * @param list<CandidatureStatusHistory> $statusHistory
     * @return list<array{status: string, date: string, estimatedScore: int}>
     */
    public function buildTimelineFromHistory(array $statusHistory): array
    {
        $timeline = [];
        $statusScoreMap = [
            'En attente'  => 25,
            'Validée RH'  => 55,
            'Entretien'   => 75,
            'Acceptée'    => 100,
            'Refusée'     => 10,
        ];

        foreach ($statusHistory as $entry) {
            $status = $entry->getToStatus() ?? 'En attente';
            $timeline[] = [
                'status'         => $status,
                'date'           => $entry->getChangedAt()?->format('Y-m-d H:i') ?? 'N/A',
                'estimatedScore' => $statusScoreMap[$status] ?? 0,
            ];
        }

        return $timeline;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function computeExpEduScore(Candidature $candidature): int
    {
        $score = 0;

        // Expérience (max 10 pts)
        $years = $candidature->getAnneesExperience() ?? 0;
        $score += match (true) {
            $years >= 5  => 10,
            $years >= 3  => 7,
            $years >= 1  => 4,
            default      => 1,
        };

        // Éducation (max 10 pts)
        $level = $candidature->getNiveauEtudes() ?? '';
        $score += self::EDUCATION_POINTS[$level] ?? 0;

        // Pondéré sur WEIGHT_EXP_EDU
        return (int) round(($score / 20) * 100 * self::WEIGHT_EXP_EDU);
    }

    private function getTrend(int $score): string
    {
        if ($score >= 70) return 'EXCELLENT';
        if ($score >= 50) return 'GOOD';
        if ($score >= 30) return 'AVERAGE';
        return 'LOW';
    }

    private function getTrendLabel(int $score): string
    {
        return match ($this->getTrend($score)) {
            'EXCELLENT' => 'Excellent profil',
            'GOOD'      => 'Bon profil',
            'AVERAGE'   => 'Profil moyen',
            default     => 'Profil faible',
        };
    }

    /**
     * @param array{score: int, missingFields: list<string>, missingDocuments: list<string>, canMoveToInterview: bool} $completeness
     * @return list<string>
     */
    private function buildRecommendations(array $completeness, int $matchingScore, int $expEduScore): array
    {
        $recs = [];

        if (!empty($completeness['missingFields'])) {
            $recs[] = 'Compléter les champs manquants : ' . implode(', ', array_slice($completeness['missingFields'], 0, 3));
        }
        if (!empty($completeness['missingDocuments'])) {
            $recs[] = 'Ajouter les documents : ' . implode(', ', $completeness['missingDocuments']);
        }
        if ($matchingScore < 40) {
            $recs[] = 'Le score de correspondance est faible. Enrichir les compétences et la description du candidat.';
        }
        if ($expEduScore < 5) {
            $recs[] = 'L\'expérience ou le niveau d\'études est insuffisant pour ce poste.';
        }
        if (empty($recs)) {
            $recs[] = 'Dossier complet. Le candidat est prêt pour la prochaine étape.';
        }

        return $recs;
    }
}
