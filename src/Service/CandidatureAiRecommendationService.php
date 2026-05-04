<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Candidature;

final class CandidatureAiRecommendationService
{
    /**
     * @param array<string,mixed> $analysis
     * @return array{
     *   summary:string,
     *   candidateMessage:string,
     *   rhSummary:string,
     *   recommendations:list<string>,
     *   tone:string,
     *   status:string
     * }
     */
    public function generateRecommendation(Candidature $candidature, array $analysis = []): array
    {
        $summaryData = is_array($analysis['summary'] ?? null) ? $analysis['summary'] : [];
        $decision = is_array($analysis['decision'] ?? null) ? $analysis['decision'] : [];
        $issues = is_array($analysis['issues'] ?? null) ? $analysis['issues'] : [];
        $scores = is_array($analysis['scores'] ?? null) ? $analysis['scores'] : [];

        $completenessLevel = (string) ($summaryData['completenessLevel'] ?? 'BLOQUANT');
        $priorityCategory = (string) ($summaryData['priorityCategory'] ?? 'FAIBLE_PRIORITE');
        $duplicateSeverity = (string) ($decision['duplicateSeverity'] ?? 'NONE');
        $canMoveToRhValidation = (bool) ($decision['canMoveToRhValidation'] ?? false);

        $missingFields = $this->asArrayOfString($issues['missingFields'] ?? []);
        $missingDocuments = $this->asArrayOfString($issues['missingDocuments'] ?? []);
        $blockingReasons = $this->asArrayOfString($issues['blockingReasons'] ?? []);

        $recommendations = [];

        if (in_array('CV', $missingDocuments, true)) {
            $recommendations[] = 'Ajouter un CV a jour en priorite afin de permettre une evaluation RH complete.';
        }

        if (in_array('Lettre de motivation', $missingDocuments, true)) {
            $recommendations[] = 'Ajouter une lettre de motivation concise pour mieux expliciter votre projet professionnel.';
        }

        if ($this->hasMissingField($missingFields, ['competence'])) {
            $recommendations[] = 'Preciser vos competences principales et les technologies maitrisées en lien avec le poste.';
        }

        if ($this->hasMissingField($missingFields, ['niveau', 'etude'])) {
            $recommendations[] = 'Completer le niveau d etudes pour faciliter la lecture de votre parcours academique.';
        }

        if ($this->hasMissingField($missingFields, ['annee', 'experience'])) {
            $recommendations[] = 'Indiquer vos annees d experience pour mieux positionner votre seniorite sur le poste.';
        }

        if ($priorityCategory === 'FAIBLE_PRIORITE' && $completenessLevel !== 'BLOQUANT') {
            $recommendations[] = 'Renforcer la candidature avec des realisations concretes et des competences directement applicables au poste.';
        }

        if ($recommendations === []) {
            $recommendations[] = 'Le dossier est exploitable. Vous pouvez encore renforcer votre candidature avec des exemples de realisations recentes et mesurables.';
        }

        $recommendations = array_values(array_unique($recommendations));

        $status = 'good';
        if ($completenessLevel === 'BLOQUANT' || !$canMoveToRhValidation || count($blockingReasons) > 0) {
            $status = 'needs_improvement';
        } elseif ($priorityCategory === 'FAIBLE_PRIORITE') {
            $status = 'improvable';
        }

        $summary = $this->buildSummary($status, $candidature, $missingFields, $missingDocuments);
        $candidateMessage = $this->buildCandidateMessage($status, $candidature, $recommendations);
        $rhSummary = $this->buildRhSummary($status, $duplicateSeverity, $blockingReasons, $priorityCategory);

        if (($duplicateSeverity === 'WARNING' || $duplicateSeverity === 'BLOCKING')
            && !str_contains($rhSummary, 'doublon')) {
            $rhSummary .= ' Une verification de doublon est recommandee avant decision RH.';
        }

        return [
            'summary' => $summary,
            'candidateMessage' => $candidateMessage,
            'rhSummary' => $rhSummary,
            'recommendations' => $recommendations,
            'tone' => 'professional',
            'status' => $status,
        ];
    }

    /**
     * @param array<string,mixed> $analysis
     */
    public function generateCandidateMessage(Candidature $candidature, array $analysis = []): string
    {
        return $this->generateRecommendation($candidature, $analysis)['candidateMessage'];
    }

    /**
     * @param array<string,mixed> $analysis
     */
    public function generateRhSummary(Candidature $candidature, array $analysis = []): string
    {
        return $this->generateRecommendation($candidature, $analysis)['rhSummary'];
    }

    /**
     * @param array<int,mixed> $values
     * @return list<string>
     */
    private function asArrayOfString(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $text = trim((string) $value);
            if ($text !== '') {
                $result[] = $text;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param list<string> $fields
     * @param list<string> $keywords
     */
    private function hasMissingField(array $fields, array $keywords): bool
    {
        foreach ($fields as $field) {
            $normalized = $this->normalizeText($field);
            $matchCount = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $this->normalizeText($keyword))) {
                    ++$matchCount;
                }
            }

            if ($matchCount === count($keywords)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'ù', 'û', 'ü', 'î', 'ï', 'ô', 'ö', "'", '-'], ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'u', 'u', 'u', 'i', 'i', 'o', 'o', ' ', ' '], $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return $value;
    }

    /**
     * @param list<string> $missingFields
     * @param list<string> $missingDocuments
     */
    private function buildSummary(string $status, Candidature $candidature, array $missingFields, array $missingDocuments): string
    {
        if ($status === 'needs_improvement') {
            return 'Le dossier n est pas encore pret pour une validation RH et necessite des complements prioritaires.';
        }

        if ($status === 'improvable') {
            return 'La candidature est recevable mais peut etre renforcee pour ameliorer son positionnement RH.';
        }

        $poste = trim((string) $candidature->getTitrePoste());
        if ($poste !== '') {
            return 'Le dossier est globalement complet pour le poste de ' . $poste . ', avec des axes d optimisation mineurs.';
        }

        if ($missingFields !== [] || $missingDocuments !== []) {
            return 'Le dossier est exploitable, avec quelques ajustements mineurs recommandés.';
        }

        return 'Le dossier est globalement complet et pret pour la suite du traitement RH.';
    }

    /**
     * @param list<string> $recommendations
     */
    private function buildCandidateMessage(string $status, Candidature $candidature, array $recommendations): string
    {
        $intro = $status === 'needs_improvement'
            ? 'Votre candidature presente un interet, mais certains elements doivent etre completes afin de permettre une evaluation optimale par l equipe RH.'
            : 'Votre candidature est bien prise en compte. Pour maximiser sa qualite, quelques ameliorations peuvent etre apportees.';

        $first = $recommendations[0] ?? 'Completer les informations principales du dossier.';

        $poste = trim((string) $candidature->getTitrePoste());
        if ($poste !== '') {
            return $intro . ' Pour le poste de ' . $poste . ', priorite recommandee: ' . $first;
        }

        return $intro . ' Priorite recommandee: ' . $first;
    }

    /**
     * @param list<string> $blockingReasons
     */
    private function buildRhSummary(string $status, string $duplicateSeverity, array $blockingReasons, string $priorityCategory): string
    {
        if ($status === 'needs_improvement') {
            if ($blockingReasons !== []) {
                return 'Le dossier reste bloquant pour la validation RH en raison d elements manquants et de points structurants a completer.';
            }

            return 'Le dossier presente un potentiel, mais il doit etre complete avant passage au traitement RH standard.';
        }

        if ($priorityCategory === 'FAIBLE_PRIORITE') {
            return 'Le dossier est non bloquant mais de priorite faible; un renforcement du contenu est conseille avant arbitrage RH.';
        }

        if ($duplicateSeverity === 'WARNING' || $duplicateSeverity === 'BLOCKING') {
            return 'Le dossier est exploitable, avec vigilance operationnelle en raison d un contexte de doublon potentiel.';
        }

        return 'Le dossier est globalement coherent et peut etre suivi selon le processus RH habituel.';
    }
}
