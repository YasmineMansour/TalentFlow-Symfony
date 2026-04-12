<?php

namespace App\Service;

class ContentValidator
{
    // ── Bad words list (FR + EN) ──
    private const BAD_WORDS = [
        // French
        'merde', 'putain', 'connard', 'connasse', 'salaud', 'salope',
        'enculé', 'enculer', 'nique', 'niquer', 'ntm', 'fdp', 'fils de pute',
        'bordel', 'batard', 'bâtard', 'couille', 'bite', 'chier', 'foutre',
        'pétasse', 'petasse', 'enfoiré', 'enfoire', 'casse-toi', 'ta gueule',
        'ferme ta gueule', 'ftg', 'pd', 'tg', 'branleur', 'branleuse',
        'abruti', 'clochard', 'pouffiasse', 'trouduc', 'trou du cul',
        // English
        'fuck', 'shit', 'bitch', 'asshole', 'bastard', 'dick', 'pussy',
        'cunt', 'whore', 'slut', 'nigger', 'nigga', 'faggot', 'retard',
        'motherfucker', 'bullshit', 'stfu', 'wtf', 'damn', 'crap',
        'dumbass', 'jackass', 'piss', 'cock', 'wanker', 'twat',
    ];

    /**
     * Validate a post title.
     * @return string[] List of error messages (empty = valid)
     */
    public function validatePostTitle(string $title): array
    {
        $errors = [];

        if (mb_strlen($title) < 3) {
            $errors[] = 'Le titre doit contenir au moins 3 caractères.';
        }
        if (mb_strlen($title) > 255) {
            $errors[] = 'Le titre ne doit pas dépasser 255 caractères.';
        }
        if ($this->isRepetitive($title)) {
            $errors[] = 'Le titre semble répétitif ou contient du spam.';
        }
        if ($badWords = $this->detectBadWords($title)) {
            $errors[] = 'Le titre contient du langage inapproprié : ' . implode(', ', $badWords) . '.';
        }

        return $errors;
    }

    /**
     * Validate post content.
     * @return string[] List of error messages (empty = valid)
     */
    public function validatePostContent(string $content, bool $hasImage): array
    {
        $errors = [];

        if (!$hasImage && mb_strlen($content) < 10) {
            $errors[] = 'Le contenu doit contenir au moins 10 caractères (ou ajoutez une image).';
        }
        if (mb_strlen($content) > 5000) {
            $errors[] = 'Le contenu ne doit pas dépasser 5000 caractères.';
        }
        if (mb_strlen($content) > 0 && $this->isRepetitive($content)) {
            $errors[] = 'Le contenu semble répétitif ou contient du spam.';
        }
        if ($badWords = $this->detectBadWords($content)) {
            $errors[] = 'Le contenu contient du langage inapproprié : ' . implode(', ', $badWords) . '.';
        }

        return $errors;
    }

    /**
     * Validate a comment.
     * @return string[] List of error messages (empty = valid)
     */
    public function validateComment(string $content): array
    {
        $errors = [];

        if (mb_strlen($content) < 2) {
            $errors[] = 'Le commentaire doit contenir au moins 2 caractères.';
        }
        if (mb_strlen($content) > 2000) {
            $errors[] = 'Le commentaire ne doit pas dépasser 2000 caractères.';
        }
        if (mb_strlen($content) > 0 && $this->isRepetitive($content)) {
            $errors[] = 'Le commentaire semble répétitif ou contient du spam.';
        }
        if ($badWords = $this->detectBadWords($content)) {
            $errors[] = 'Le commentaire contient du langage inapproprié : ' . implode(', ', $badWords) . '.';
        }

        return $errors;
    }

    /**
     * Detect bad words in text. Returns matched words (masked).
     * @return string[]
     */
    public function detectBadWords(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $normalized = $this->normalize($text);
        $found = [];

        foreach (self::BAD_WORDS as $word) {
            $pattern = '/\b' . preg_quote($word, '/') . '\b/iu';
            if (preg_match($pattern, $normalized)) {
                // Mask the word: keep first and last char, stars in between
                $masked = $this->maskWord($word);
                if (!in_array($masked, $found, true)) {
                    $found[] = $masked;
                }
            }
        }

        return $found;
    }

    /**
     * Check if text is repetitive/spam-like.
     */
    public function isRepetitive(string $text): bool
    {
        if (mb_strlen($text) < 4) {
            return false;
        }

        // 1. Same character repeated 5+ times: "aaaaaa", "!!!!!!"
        if (preg_match('/(.)\1{4,}/u', $text)) {
            return true;
        }

        // 2. Same short word/pattern repeated 3+ times: "test test test"
        if (preg_match('/\b(\w{2,})\s+(\1\s*){2,}/iu', $text)) {
            return true;
        }

        // 3. Entire text is just one or two chars repeated
        $unique = count(array_unique(mb_str_split(preg_replace('/\s+/', '', $text))));
        $len = mb_strlen(preg_replace('/\s+/', '', $text));
        if ($len >= 6 && $unique <= 2) {
            return true;
        }

        return false;
    }

    /**
     * Normalize text for matching: lowercase, strip accents, common leet-speak.
     */
    private function normalize(string $text): string
    {
        $text = mb_strtolower($text);

        // Common leet-speak substitutions
        $leet = [
            '@' => 'a', '4' => 'a', '3' => 'e', '1' => 'i', '!' => 'i',
            '0' => 'o', '$' => 's', '5' => 's', '7' => 't',
        ];
        $text = strtr($text, $leet);

        // Remove repeated special chars used to bypass filters: f.u.c.k → fuck
        $text = preg_replace('/(?<=\w)[.\-_*]+(?=\w)/u', '', $text);

        return $text;
    }

    private function maskWord(string $word): string
    {
        $len = mb_strlen($word);
        if ($len <= 2) {
            return str_repeat('*', $len);
        }
        return mb_substr($word, 0, 1) . str_repeat('*', $len - 2) . mb_substr($word, -1);
    }
}
