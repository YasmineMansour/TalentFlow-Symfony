<?php

namespace App\Service;

use App\Entity\Candidature;

class CandidatureMatchingService
{
    public function computeScore(Candidature $candidature): int
    {
        $offre = $candidature->getOffre();
        if ($offre === null) {
            return 0;
        }

        $candidateText = implode(' ', array_filter([
            $candidature->getTitrePoste(),
            $candidature->getDescription(),
            $candidature->getCompetences(),
            $candidature->getNotes(),
        ]));

        $offerText = implode(' ', array_filter([
            $offre->getTitre(),
            $offre->getDescription(),
            $offre->getTypeContrat(),
            $offre->getLocalisation(),
            $offre->getCategorie()?->getNom(),
        ]));

        $candidateTokens = $this->tokenize($candidateText);
        $offerTokens = $this->tokenize($offerText);

        if (count($candidateTokens) === 0 || count($offerTokens) === 0) {
            return 0;
        }

        $common = array_intersect($candidateTokens, $offerTokens);
        $keywordScore = (int) round((count($common) / max(1, count(array_unique($offerTokens)))) * 100);

        $similarity = 0.0;
        similar_text(mb_strtolower($candidateText), mb_strtolower($offerText), $similarity);
        $similarityScore = (int) round($similarity);

        // Weighted blend: keyword overlap is primary signal.
        $final = (int) round(($keywordScore * 0.7) + ($similarityScore * 0.3));

        return max(0, min(100, $final));
    }

    /**
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $normalized = mb_strtolower($text);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized) ?? '';
        $parts = preg_split('/\s+/u', trim($normalized)) ?: [];

        $stopWords = [
            'le', 'la', 'les', 'de', 'du', 'des', 'un', 'une', 'et', 'ou', 'en', 'dans',
            'pour', 'par', 'sur', 'avec', 'sans', 'the', 'and', 'for', 'with', 'to',
            'a', 'an', 'of', 'is', 'are', 'vos', 'votre', 'notre',
        ];

        $tokens = [];
        foreach ($parts as $part) {
            if (mb_strlen($part) < 3) {
                continue;
            }
            if (in_array($part, $stopWords, true)) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }
}
