<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Candidature;
use App\Entity\PieceJointe;

/**
 * Calcule le score de complétude d'un dossier de candidature.
 */
final class CandidatureCompletenessService
{
    private const PROTECTED_TRANSITIONS = ['validate_rh', 'schedule_interview'];
    private const PROTECTED_STATUSES    = ['Validée RH', 'Entretien'];

    // ── Seuils ──────────────────────────────────────────────────────────────
    private const SCORE_COMPLET      = 80;
    private const SCORE_A_COMPLETER  = 50;

    // ── Libellés affichés en français ────────────────────────────────────────
    private const LEVEL_LABELS = [
        'COMPLET'     => 'Complet',
        'A_COMPLETER' => 'À compléter',
        'BLOQUANT'    => 'Bloquant',
    ];

    /**
     * Retourne uniquement le score de 0 à 100.
     */
    public function computeScore(Candidature $candidature): int
    {
        return $this->analyze($candidature)['score'];
    }

    /**
     * Analyse complète du dossier.
     *
     * @return array{
     *   score: int,
     *   level: string,
     *   label: string,
     *   missingFields: list<string>,
     *   missingDocuments: list<string>,
     *   blockingReasons: list<string>,
     *   hasCv: bool,
     *   hasLettreMotivation: bool,
     *   canMoveToRhValidation: bool,
     *   canMoveToInterview: bool,
     * }
     */
    public function analyze(Candidature $candidature): array
    {
        $score         = 0;
        $missingFields = [];

        // ── Champs du profil ────────────────────────────────────────────────
        if (!empty(trim((string) $candidature->getTitrePoste()))) {
            $score += 5;
        } else {
            $missingFields[] = 'Titre du poste';
        }

        if (!empty(trim((string) $candidature->getEntreprise()))) {
            $score += 5;
        } else {
            $missingFields[] = 'Entreprise';
        }

        if (!empty(trim((string) $candidature->getTypeContrat()))) {
            $score += 5;
        } else {
            $missingFields[] = 'Type de contrat';
        }

        $hasEmail = false;
        $rawEmail = trim((string) $candidature->getEmail());
        if ($rawEmail !== '' && filter_var($rawEmail, FILTER_VALIDATE_EMAIL) !== false) {
            $score    += 10;
            $hasEmail  = true;
        } else {
            $missingFields[] = 'Adresse e-mail valide';
        }

        $hasTelephone = false;
        if (!empty(trim((string) $candidature->getTelephone()))) {
            $score        += 5;
            $hasTelephone  = true;
        } else {
            $missingFields[] = 'Téléphone';
        }

        $hasCompetences = false;
        if (!empty(trim((string) $candidature->getCompetences()))) {
            $score          += 15;
            $hasCompetences  = true;
        } else {
            $missingFields[] = 'Compétences';
        }

        if (!empty(trim((string) $candidature->getNiveauEtudes()))) {
            $score += 10;
        } else {
            $missingFields[] = "Niveau d'études";
        }

        if ($candidature->getAnneesExperience() !== null && $candidature->getAnneesExperience() >= 0) {
            $score += 10;
        } else {
            $missingFields[] = "Années d'expérience";
        }

        if ($candidature->getSalaireSouhaite() !== null) {
            $score += 5;
        } else {
            $missingFields[] = 'Salaire souhaité';
        }

        if ($candidature->getOffre() !== null) {
            $score += 5;
        } else {
            $missingFields[] = 'Offre liée';
        }

        if ($candidature->getCandidat() !== null) {
            $score += 5;
        } else {
            $missingFields[] = 'Candidat lié';
        }

        // ── Documents ───────────────────────────────────────────────────────
        $hasCv               = $this->detectCv($candidature);
        $hasLettreMotivation = $this->detectLettre($candidature);
        $hasAnyDocument      = $hasCv || $hasLettreMotivation || $candidature->getPiecesJointes()->count() > 0;

        $missingDocuments = [];

        if ($hasAnyDocument) {
            $score += 5;
        } else {
            $missingDocuments[] = 'Au moins une pièce jointe';
        }

        if ($hasCv) {
            $score += 10;
        } else {
            $missingDocuments[] = 'CV';
        }

        if ($hasLettreMotivation) {
            $score += 5;
        } else {
            $missingDocuments[] = 'Lettre de motivation';
        }

        // ── Niveau de complétude ────────────────────────────────────────────
        $blockingReasons = [];

        $isBlocking = false;
        if ($score < self::SCORE_A_COMPLETER) {
            $isBlocking        = true;
            $blockingReasons[] = 'Score insuffisant (< 50)';
        }
        if (!$hasCv) {
            $isBlocking        = true;
            $blockingReasons[] = 'CV manquant';
        }
        if (!$hasEmail) {
            $isBlocking        = true;
            $blockingReasons[] = 'E-mail manquant ou invalide';
        }
        if (!$hasTelephone) {
            $isBlocking        = true;
            $blockingReasons[] = 'Téléphone manquant';
        }
        if (!$hasCompetences) {
            $isBlocking        = true;
            $blockingReasons[] = 'Compétences non renseignées';
        }

        if ($isBlocking) {
            $level = 'BLOQUANT';
        } elseif ($score >= self::SCORE_COMPLET) {
            $level = 'COMPLET';
        } else {
            $level = 'A_COMPLETER';
        }

        $canMoveToRhValidation = ($level !== 'BLOQUANT') && ($score >= self::SCORE_COMPLET) && $hasCv;
        $canMoveToInterview    = ($level === 'COMPLET');

        return [
            'score'               => $score,
            'level'               => $level,
            'label'               => self::LEVEL_LABELS[$level],
            'missingFields'       => $missingFields,
            'missingDocuments'    => $missingDocuments,
            'blockingReasons'     => $blockingReasons,
            'hasCv'               => $hasCv,
            'hasLettreMotivation' => $hasLettreMotivation,
            'canMoveToRhValidation' => $canMoveToRhValidation,
            'canMoveToInterview'  => $canMoveToInterview,
        ];
    }

    /**
     * Vérifie si le dossier bloque une transition protégée.
     * Retourne un message d'erreur en français, ou null si autorisé.
     */
    public function checkTransitionAllowed(Candidature $candidature, string $transitionName): ?string
    {
        if (!in_array($transitionName, self::PROTECTED_TRANSITIONS, true)) {
            return null;
        }

        $analysis = $this->analyze($candidature);

        if ($analysis['level'] === 'BLOQUANT' || $analysis['score'] < self::SCORE_COMPLET || !$analysis['hasCv']) {
            $details = array_merge($analysis['blockingReasons'], $analysis['missingDocuments'], $analysis['missingFields']);
            $details = array_slice($details, 0, 5); // limiter pour rester lisible

            $message = 'Le dossier est incomplet. Merci de compléter les champs/documents manquants avant de passer cette candidature à l\'étape suivante.';
            if (!empty($details)) {
                $message .= ' Éléments manquants : ' . implode(', ', $details) . '.';
            }

            return $message;
        }

        return null;
    }

    // ── Détection des documents ──────────────────────────────────────────────

    private function detectCv(Candidature $candidature): bool
    {
        // Vérifier les PièceJointe
        foreach ($candidature->getPiecesJointes() as $pj) {
            if ($this->isCvType($pj->getTypeDocument())) {
                return true;
            }
        }

        // Fallback : champ direct legacy
        return !empty(trim((string) $candidature->getCvFilename()));
    }

    private function detectLettre(Candidature $candidature): bool
    {
        foreach ($candidature->getPiecesJointes() as $pj) {
            if ($this->isLettreType($pj->getTypeDocument())) {
                return true;
            }
        }

        return !empty(trim((string) $candidature->getLettreMotivationFilename()));
    }

    private function isCvType(?string $type): bool
    {
        if ($type === null) {
            return false;
        }

        $normalized = strtolower(trim($type));

        return in_array($normalized, ['cv', 'curriculum vitae', 'curriculum_vitae'], true);
    }

    private function isLettreType(?string $type): bool
    {
        if ($type === null) {
            return false;
        }

        $normalized = strtolower(trim($type));

        return str_contains($normalized, 'lettre') || str_contains($normalized, 'motivation');
    }
}
