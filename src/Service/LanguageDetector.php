<?php

namespace App\Service;

class LanguageDetector
{
    private const LANG_LABELS = [
        'ar' => 'العربية',
        'fr' => 'Français',
        'en' => 'English',
    ];

    /**
     * Detect the primary language of a text string.
     * @return string 'ar', 'fr', or 'en'
     */
    public function detect(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return 'fr'; // default
        }

        // 1. Arabic: check for Arabic/Arabic-supplement Unicode characters
        $arabicCount = preg_match_all('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
        $totalChars = max(1, mb_strlen(preg_replace('/\s+/u', '', $text)));
        if ($arabicCount / $totalChars > 0.3) {
            return 'ar';
        }

        // 2. French vs English: score-based approach
        $lower = mb_strtolower($text);

        // French accent characters
        $frenchAccents = preg_match_all('/[àâäéèêëïîôùûüÿçœæ]/u', $lower);

        // Common French words (that are NOT English words)
        $frenchWords = [
            'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'et', 'est',
            'en', 'que', 'qui', 'dans', 'pour', 'sur', 'avec', 'son', 'ses',
            'ce', 'cette', 'ces', 'il', 'elle', 'nous', 'vous', 'ils', 'elles',
            'sont', 'ont', 'fait', 'pas', 'plus', 'aussi', 'mais', 'ou', 'donc',
            'ni', 'car', 'je', 'tu', 'mon', 'ton', 'notre', 'votre', 'leur',
            'très', 'bien', 'peut', 'tout', 'tous', 'toute', 'comme', 'ici',
            'avoir', 'être', 'faire', 'aller', 'voir', 'savoir', 'pouvoir',
            'bonjour', 'merci', 'oui', 'non', 'salut', 'alors', 'parce',
            "j'ai", "l'on", "c'est", "n'est", "qu'il", "d'un", "d'une",
        ];

        // Common English words (that are NOT French words)
        $englishWords = [
            'the', 'is', 'are', 'was', 'were', 'been', 'being', 'have', 'has',
            'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
            'can', 'may', 'might', 'shall', 'must', 'need', 'this', 'that',
            'these', 'those', 'what', 'which', 'who', 'whom', 'where', 'when',
            'how', 'why', 'there', 'their', 'they', 'them', 'she', 'her',
            'him', 'his', 'its', 'our', 'your', 'my', 'we', 'you', 'it',
            'not', 'but', 'and', 'from', 'with', 'into', 'about', 'been',
            'because', 'each', 'other', 'some', 'than', 'then', 'very',
            'just', 'also', 'hello', 'yes', 'please', 'thank', 'thanks',
        ];

        $words = preg_split('/[\s,.\-!?;:()]+/u', $lower, -1, PREG_SPLIT_NO_EMPTY);
        $frScore = $frenchAccents * 2; // accents weigh double
        $enScore = 0;

        foreach ($words as $w) {
            if (in_array($w, $frenchWords, true)) {
                $frScore++;
            }
            if (in_array($w, $englishWords, true)) {
                $enScore++;
            }
        }

        if ($frScore > $enScore) {
            return 'fr';
        }
        if ($enScore > $frScore) {
            return 'en';
        }

        // Default to French (forum default language)
        return 'fr';
    }

    /**
     * Get display label for a language code.
     */
    public function getLabel(string $langCode): string
    {
        return self::LANG_LABELS[$langCode] ?? $langCode;
    }

    /**
     * Get short display code.
     */
    public function getShortLabel(string $langCode): string
    {
        return match ($langCode) {
            'ar' => 'AR',
            'fr' => 'FR',
            'en' => 'EN',
            default => strtoupper($langCode),
        };
    }
}
