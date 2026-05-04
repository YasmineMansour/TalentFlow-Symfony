<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Candidature;

final class CandidatureAnalysisService
{
    private const PRIORITY_LABELS = [
        'PRIORITAIRE' => 'Prioritaire',
        'A_EXAMINER' => 'À examiner',
        'INCOMPLETE' => 'Incomplète',
        'FAIBLE_PRIORITE' => 'Faible priorité',
    ];

    private const COMPLETENESS_LABELS = [
        'COMPLET' => 'Complet',
        'A_COMPLETER' => 'À compléter',
        'BLOQUANT' => 'Bloquant',
    ];

    public function __construct(
        private readonly CandidatureMatchingService $matchingService,
        private readonly CandidatureCompletenessService $completenessService,
        private readonly CandidatureDuplicateGuardService $duplicateGuard,
        private readonly CandidaturePriorityService $priorityService,
        private readonly CandidatureAiRecommendationService $aiRecommendationService,
    ) {
    }

    /**
     * @return array{
        *   summary: array{
        *     id:int,
        *     titrePoste:string,
        *     entreprise:string,
        *     currentStatus:string,
        *     recommendedStatus:string,
        *     priorityCategory:string,
        *     priorityLabel:string,
        *     completenessLevel:string,
        *     completenessLabel:string,
        *   },
        *   scores: array{matching:int, completeness:int, priority:int},
        *   decision: array{
        *     canMoveToRhValidation:bool,
        *     canMoveToInterview:bool,
        *     isDuplicate:bool,
        *     duplicateSeverity:string,
        *     duplicateReason:?string,
        *   },
        *   issues: array{
        *     blockingReasons:list<string>,
        *     missingFields:list<string>,
        *     missingDocuments:list<string>,
        *     delayAlerts:array,
        *   },
        *   actions:list<string>,
        *   technical: array{raw: array<string,mixed>},
     *   candidature: array{id:int, statut:string, titrePoste:string, entreprise:string},
     *   analysis: array{
     *     matchingScore:int,
     *     completenessScore:int,
     *     priorityScore:int,
     *     completenessLevel:string,
     *     priorityCategory:string,
     *     missingFields:list<string>,
     *     missingDocuments:list<string>,
     *     isDuplicate:bool,
     *     duplicateSeverity:string,
     *     duplicateReason:?string,
     *     blockingReasons:list<string>,
     *     recommendedStatus:string,
     *     canMoveToRhValidation:bool,
     *     canMoveToInterview:bool,
     *     delayAlerts:array,
     *   }
     * }
     */
    public function analyze(Candidature $candidature): array
    {
        $matchingScore = $candidature->getMatchingScore();
        if ($matchingScore === null) {
            $matchingScore = $this->matchingService->computeScore($candidature);
        }
        $matchingScore = max(0, min(100, (int) $matchingScore));

        $completeness = $this->completenessService->analyze($candidature);
        $duplicate    = $this->duplicateGuard->analyze($candidature, $candidature->getId());
        $priority     = $this->priorityService->analyze($candidature);

        $analysis = [
            'matchingScore'          => $matchingScore,
            'completenessScore'      => (int) ($completeness['score'] ?? 0),
            'priorityScore'          => (int) ($priority['priorityScore'] ?? 0),
            'completenessLevel'      => (string) ($completeness['level'] ?? 'BLOQUANT'),
            'priorityCategory'       => (string) ($priority['category'] ?? 'FAIBLE_PRIORITE'),
            'missingFields'          => array_values($completeness['missingFields'] ?? []),
            'missingDocuments'       => array_values($completeness['missingDocuments'] ?? []),
            'isDuplicate'            => (bool) ($duplicate['isDuplicate'] ?? false),
            'duplicateSeverity'      => (string) ($duplicate['severity'] ?? 'NONE'),
            'duplicateReason'        => isset($duplicate['reason']) ? (string) $duplicate['reason'] : null,
            'canMoveToRhValidation'  => (bool) ($completeness['canMoveToRhValidation'] ?? false),
            'canMoveToInterview'     => (bool) ($completeness['canMoveToInterview'] ?? false),
            'delayAlerts'            => $priority['delayAlerts'] ?? [],
        ];

        $analysis['blockingReasons'] = $this->getBlockingReasons($candidature, $analysis);
        $analysis['recommendedStatus'] = $this->getRecommendedStatus($candidature, $analysis);

        $summary = [
            'id' => (int) $candidature->getId(),
            'titrePoste' => (string) ($candidature->getTitrePoste() ?? ''),
            'entreprise' => (string) ($candidature->getEntreprise() ?? ''),
            'currentStatus' => (string) ($candidature->getStatut() ?? 'En attente'),
            'recommendedStatus' => (string) ($analysis['recommendedStatus'] ?? 'En attente'),
            'priorityCategory' => (string) ($analysis['priorityCategory'] ?? 'FAIBLE_PRIORITE'),
            'priorityLabel' => $this->priorityLabel((string) ($analysis['priorityCategory'] ?? 'FAIBLE_PRIORITE')),
            'completenessLevel' => (string) ($analysis['completenessLevel'] ?? 'BLOQUANT'),
            'completenessLabel' => $this->completenessLabel((string) ($analysis['completenessLevel'] ?? 'BLOQUANT')),
        ];

        $scores = [
            'matching' => (int) ($analysis['matchingScore'] ?? 0),
            'completeness' => (int) ($analysis['completenessScore'] ?? 0),
            'priority' => (int) ($analysis['priorityScore'] ?? 0),
        ];

        $decision = [
            'canMoveToRhValidation' => (bool) ($analysis['canMoveToRhValidation'] ?? false),
            'canMoveToInterview' => (bool) ($analysis['canMoveToInterview'] ?? false),
            'isDuplicate' => (bool) ($analysis['isDuplicate'] ?? false),
            'duplicateSeverity' => (string) ($analysis['duplicateSeverity'] ?? 'NONE'),
            'duplicateReason' => isset($analysis['duplicateReason']) ? (string) $analysis['duplicateReason'] : null,
        ];

        $issues = [
            'blockingReasons' => array_values($analysis['blockingReasons'] ?? []),
            'missingFields' => array_values($analysis['missingFields'] ?? []),
            'missingDocuments' => array_values($analysis['missingDocuments'] ?? []),
            'delayAlerts' => array_values($analysis['delayAlerts'] ?? []),
        ];

        $actions = $this->buildRhActions($summary, $decision, $issues);
        $ai = $this->aiRecommendationService->generateRecommendation($candidature, [
            'summary' => $summary,
            'scores' => $scores,
            'decision' => $decision,
            'issues' => $issues,
            'actions' => $actions,
        ]);

        return [
            // Business-first payload for RH frontends
            'summary' => $summary,
            'scores' => $scores,
            'decision' => $decision,
            'issues' => $issues,
            'actions' => $actions,
            'ai' => $ai,
            'technical' => [
                'raw' => [
                    'completeness' => $completeness,
                    'duplicate' => $duplicate,
                    'priority' => $priority,
                ],
            ],
            // Legacy-compatible block kept for existing consumers
            'candidature' => [
                'id'         => (int) $candidature->getId(),
                'statut'     => (string) ($candidature->getStatut() ?? 'En attente'),
                'titrePoste' => (string) ($candidature->getTitrePoste() ?? ''),
                'entreprise' => (string) ($candidature->getEntreprise() ?? ''),
            ],
            'analysis' => $analysis,
        ];
    }

    public function getRecommendedStatus(Candidature $candidature, array $analysis): string
    {
        $currentStatus = (string) ($candidature->getStatut() ?? 'En attente');

        // 1) Statuts terminaux
        if (in_array($currentStatus, ['Acceptée', 'Refusée'], true)) {
            return $currentStatus;
        }

        // 2) Doublon bloquant
        if (($analysis['duplicateSeverity'] ?? 'NONE') === 'BLOCKING') {
            return $currentStatus !== '' ? $currentStatus : 'En attente';
        }

        // 3) Dossier incomplet
        $criticalMissing = $this->hasCriticalMissingData($analysis);
        if (($analysis['completenessLevel'] ?? 'BLOQUANT') === 'BLOQUANT'
            || (int) ($analysis['completenessScore'] ?? 0) < 50
            || $criticalMissing) {
            return 'En attente';
        }

        // 4) Validée RH -> Entretien si possible
        if ($currentStatus === 'Validée RH'
            && (bool) ($analysis['canMoveToInterview'] ?? false)
            && ($analysis['duplicateSeverity'] ?? 'NONE') !== 'BLOCKING') {
            return 'Entretien';
        }

        // 5) En attente -> Validée RH si prêt
        if ($currentStatus === 'En attente'
            && (int) ($analysis['completenessScore'] ?? 0) >= 80
            && ($analysis['completenessLevel'] ?? 'BLOQUANT') !== 'BLOQUANT'
            && ($analysis['duplicateSeverity'] ?? 'NONE') !== 'BLOCKING') {
            return 'Validée RH';
        }

        // 6) fallback
        return $currentStatus !== '' ? $currentStatus : 'En attente';
    }

    /**
     * @param array<string,mixed> $analysis
     * @return list<string>
     */
    public function getBlockingReasons(Candidature $candidature, array $analysis): array
    {
        $reasons = [];

        $missingDocs = $analysis['missingDocuments'] ?? [];
        $missingFields = $analysis['missingFields'] ?? [];

        if (is_array($missingDocs) && in_array('CV', $missingDocs, true)) {
            $reasons[] = 'Le CV est manquant.';
        }

        if (is_array($missingFields)) {
            if (in_array('Téléphone', $missingFields, true)) {
                $reasons[] = 'Le numéro de téléphone est manquant.';
            }
            if (in_array('Compétences', $missingFields, true)) {
                $reasons[] = 'Les compétences ne sont pas renseignées.';
            }
            if (in_array('Adresse e-mail valide', $missingFields, true)) {
                $reasons[] = 'L\'adresse e-mail est manquante ou invalide.';
            }
        }

        if (($analysis['duplicateSeverity'] ?? 'NONE') === 'BLOCKING') {
            $reasons[] = (string) (($analysis['duplicateReason'] ?? '') ?: 'Une candidature active en doublon existe déjà pour cette offre.');
        }

        if (!((bool) ($analysis['canMoveToRhValidation'] ?? false))) {
            $reasons[] = 'Le dossier est incomplet pour un passage à l\'étape RH.';
        }

        if (!((bool) ($analysis['canMoveToInterview'] ?? false))
            && (string) ($candidature->getStatut() ?? '') === 'Validée RH') {
            $reasons[] = 'Le dossier ne peut pas encore passer à l\'étape entretien.';
        }

        $reasons = array_values(array_unique(array_filter(array_map('trim', $reasons))));

        return $reasons;
    }

    /**
     * @param array<string,mixed> $analysis
     */
    private function hasCriticalMissingData(array $analysis): bool
    {
        $missingDocs = is_array($analysis['missingDocuments'] ?? null) ? $analysis['missingDocuments'] : [];
        $missingFields = is_array($analysis['missingFields'] ?? null) ? $analysis['missingFields'] : [];

        if (in_array('CV', $missingDocs, true)) {
            return true;
        }

        return in_array('Adresse e-mail valide', $missingFields, true)
            || in_array('Téléphone', $missingFields, true)
            || in_array('Compétences', $missingFields, true);
    }

    /**
     * @param array<string,mixed> $summary
     * @param array<string,mixed> $decision
     * @param array<string,mixed> $issues
     * @return list<string>
     */
    private function buildRhActions(array $summary, array $decision, array $issues): array
    {
        $actions = [];

        $missingFields = is_array($issues['missingFields'] ?? null) ? $issues['missingFields'] : [];
        $missingDocuments = is_array($issues['missingDocuments'] ?? null) ? $issues['missingDocuments'] : [];
        $blockingReasons = is_array($issues['blockingReasons'] ?? null) ? $issues['blockingReasons'] : [];

        if (in_array('CV', $missingDocuments, true)) {
            $actions[] = 'Ajouter un CV pour permettre la validation RH.';
        }

        if (in_array('Lettre de motivation', $missingDocuments, true)) {
            $actions[] = 'Ajouter une lettre de motivation.';
        }

        foreach ($missingFields as $field) {
            $label = (string) $field;
            if ($label === "Années d'expérience") {
                $actions[] = "Compléter les années d'expérience.";
                continue;
            }
            if ($label === "Niveau d'études") {
                $actions[] = "Renseigner le niveau d'études.";
                continue;
            }
            if ($label === 'Compétences') {
                $actions[] = 'Renseigner les compétences clés du poste.';
                continue;
            }

            $actions[] = 'Compléter le champ: ' . $label . '.';
        }

        $duplicateSeverity = (string) ($decision['duplicateSeverity'] ?? 'NONE');
        if ($duplicateSeverity === 'BLOCKING' || $duplicateSeverity === 'WARNING') {
            $actions[] = 'Vérifier le doublon avant traitement.';
        }

        if ((bool) ($decision['canMoveToRhValidation'] ?? false)
            && (string) ($summary['recommendedStatus'] ?? '') === 'Validée RH') {
            $actions[] = 'Cette candidature peut passer à l\'étape Validée RH.';
        }

        if ((bool) ($decision['canMoveToInterview'] ?? false)
            && (string) ($summary['recommendedStatus'] ?? '') === 'Entretien') {
            $actions[] = 'Cette candidature peut passer à l\'étape Entretien.';
        }

        if (empty($blockingReasons)
            && empty($missingFields)
            && empty($missingDocuments)
            && $duplicateSeverity === 'NONE') {
            $actions[] = 'Aucune action bloquante: candidature prête pour traitement RH.';
        }

        return array_values(array_unique(array_filter(array_map('trim', $actions))));
    }

    private function priorityLabel(string $category): string
    {
        return self::PRIORITY_LABELS[$category] ?? 'Faible priorité';
    }

    private function completenessLabel(string $level): string
    {
        return self::COMPLETENESS_LABELS[$level] ?? 'Bloquant';
    }
}
