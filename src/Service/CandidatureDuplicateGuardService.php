<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Candidature;
use App\Repository\CandidatureRepository;
use App\Repository\CandidatureStatusHistoryRepository;

final class CandidatureDuplicateGuardService
{
    private const ACTIVE_STATUSES = ['En attente', 'Validée RH', 'Entretien'];
    private const TERMINAL_STATUSES = ['Acceptée', 'Refusée'];
    private const COOLDOWN_DAYS = 30;

    public function __construct(
        private readonly CandidatureRepository $candidatureRepository,
        private readonly CandidatureStatusHistoryRepository $historyRepository,
    ) {
    }

    /**
     * @return array{
     *   isDuplicate: bool,
     *   severity: string,
     *   reason: ?string,
     *   matchingStrategy: ?string,
     *   canSubmit: bool,
     *   existingActiveCount: int,
     *   existingTerminalCount: int,
     *   duplicates: array<int, array{id:int, statut:string, dateCandidature:?string, offreLabel:string, candidateLabel:string}>
     * }
     */
    public function analyze(Candidature $candidature, ?int $excludeId = null): array
    {
        $duplicateRows = $this->collectDuplicateRows($candidature, $excludeId);
        if ($duplicateRows === []) {
            return [
                'isDuplicate' => false,
                'severity' => 'NONE',
                'reason' => null,
                'matchingStrategy' => null,
                'canSubmit' => true,
                'existingActiveCount' => 0,
                'existingTerminalCount' => 0,
                'duplicates' => [],
            ];
        }

        $existingActiveCount = 0;
        $existingTerminalCount = 0;
        $hasAccepted = false;
        $hasRefusedRecent = false;
        $hasRefusedOld = false;
        $dominantStrategy = null;

        foreach ($duplicateRows as $row) {
            $status = $row['entity']->getStatut() ?? '';
            if (in_array($status, self::ACTIVE_STATUSES, true)) {
                ++$existingActiveCount;
            }
            if (in_array($status, self::TERMINAL_STATUSES, true)) {
                ++$existingTerminalCount;
            }
            if ($status === 'Acceptée') {
                $hasAccepted = true;
            }
            if ($status === 'Refusée') {
                if ($this->isRefusedWithinCooldown($row['entity'])) {
                    $hasRefusedRecent = true;
                } else {
                    $hasRefusedOld = true;
                }
            }
            if ($dominantStrategy === null || $row['priority'] < ($dominantStrategy['priority'] ?? PHP_INT_MAX)) {
                $dominantStrategy = ['name' => $row['strategy'], 'priority' => $row['priority']];
            }
        }

        $severity = 'WARNING';
        $reason = 'Candidature potentiellement en doublon.';

        if ($existingActiveCount > 0) {
            $severity = 'BLOCKING';
            $reason = 'Une candidature en cours existe déjà pour cette offre.';
        } elseif ($hasAccepted) {
            $severity = 'BLOCKING';
            $reason = 'Une candidature déjà acceptée existe pour cette offre.';
        } elseif ($hasRefusedRecent) {
            $severity = 'BLOCKING';
            $reason = 'Une candidature récente existe déjà pour cette offre. Merci d\'attendre avant de soumettre une nouvelle candidature.';
        } elseif ($hasRefusedOld) {
            $severity = 'WARNING';
            $reason = 'Une ancienne candidature refusée existe déjà pour cette offre.';
        } elseif (($dominantStrategy['name'] ?? null) === 'fallback_text') {
            $severity = 'WARNING';
            $reason = 'Doublon potentiel détecté sur email + poste + entreprise.';
        }

        return [
            'isDuplicate' => true,
            'severity' => $severity,
            'reason' => $reason,
            'matchingStrategy' => $dominantStrategy['name'] ?? null,
            'canSubmit' => $severity !== 'BLOCKING',
            'existingActiveCount' => $existingActiveCount,
            'existingTerminalCount' => $existingTerminalCount,
            'duplicates' => array_map(fn (array $row): array => $this->toDisplayRow($row['entity']), $duplicateRows),
        ];
    }

    public function isBlockingDuplicate(Candidature $candidature, ?int $excludeId = null): bool
    {
        return $this->analyze($candidature, $excludeId)['severity'] === 'BLOCKING';
    }

    /**
     * @return array<int, array{id:int, statut:string, dateCandidature:?string, offreLabel:string, candidateLabel:string}>
     */
    public function findDuplicates(Candidature $candidature, ?int $excludeId = null): array
    {
        return array_map(
            fn (array $row): array => $this->toDisplayRow($row['entity']),
            $this->collectDuplicateRows($candidature, $excludeId)
        );
    }

    /**
     * @return array<int, array{entity:Candidature, strategy:string, priority:int}>
     */
    private function collectDuplicateRows(Candidature $candidature, ?int $excludeId = null): array
    {
        $pool = $this->candidatureRepository->findPotentialDuplicatesForCandidature($candidature, $excludeId);
        if ($pool === []) {
            return [];
        }

        $currentOffreId = $candidature->getOffre()?->getId();
        $currentCandidatId = $candidature->getCandidat()?->getId();
        $currentEmail = $this->normalizeEmail($candidature->getEmail());
        $currentTitre = $this->normalizeText($candidature->getTitrePoste());
        $currentEntreprise = $this->normalizeText($candidature->getEntreprise());

        $duplicates = [];
        foreach ($pool as $existing) {
            $existingOffreId = $existing->getOffre()?->getId();
            $existingCandidatId = $existing->getCandidat()?->getId();
            $existingEmail = $this->normalizeEmail($existing->getEmail());
            $existingTitre = $this->normalizeText($existing->getTitrePoste());
            $existingEntreprise = $this->normalizeText($existing->getEntreprise());

            // Rule 1: same offer + same candidate => blocking strategy
            if ($currentOffreId !== null && $existingOffreId !== null && $currentOffreId === $existingOffreId
                && $currentCandidatId !== null && $existingCandidatId !== null && $currentCandidatId === $existingCandidatId) {
                $duplicates[] = ['entity' => $existing, 'strategy' => 'candidate_offer', 'priority' => 1];
                continue;
            }

            // Rule 2: candidate missing/unreliable => same offer + normalized email
            $candidateMissing = $currentCandidatId === null || $existingCandidatId === null;
            if ($candidateMissing
                && $currentOffreId !== null && $existingOffreId !== null && $currentOffreId === $existingOffreId
                && $currentEmail !== '' && $currentEmail === $existingEmail) {
                $duplicates[] = ['entity' => $existing, 'strategy' => 'email_offer', 'priority' => 2];
                continue;
            }

            // Rule 3: fallback warning-only => same email + same titre + same entreprise when no offer
            if ($currentOffreId === null
                && $currentEmail !== '' && $currentEmail === $existingEmail
                && $currentTitre !== '' && $currentTitre === $existingTitre
                && $currentEntreprise !== '' && $currentEntreprise === $existingEntreprise) {
                $duplicates[] = ['entity' => $existing, 'strategy' => 'fallback_text', 'priority' => 3];
            }
        }

        return $duplicates;
    }

    private function normalizeText(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return $value;
    }

    private function normalizeEmail(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function isRefusedWithinCooldown(Candidature $candidature): bool
    {
        $refusedAt = $this->historyRepository->findLatestRefusedAt($candidature);
        if ($refusedAt === null) {
            $refusedAt = $candidature->getUpdatedAt() ?? $candidature->getDateCandidature();
        }
        if ($refusedAt === null) {
            return true;
        }

        $days = $refusedAt->diff(new \DateTimeImmutable())->days;

        return $days < self::COOLDOWN_DAYS;
    }

    /**
     * @return array{id:int, statut:string, dateCandidature:?string, offreLabel:string, candidateLabel:string}
     */
    private function toDisplayRow(Candidature $candidature): array
    {
        return [
            'id' => (int) $candidature->getId(),
            'statut' => (string) ($candidature->getStatut() ?? '—'),
            'dateCandidature' => $candidature->getDateCandidature()?->format('Y-m-d'),
            'offreLabel' => $candidature->getOffre()?->getTitre() ?: ((string) $candidature->getTitrePoste()),
            'candidateLabel' => $candidature->getCandidat()?->getFullName() ?: ((string) $candidature->getEmail()),
        ];
    }
}
