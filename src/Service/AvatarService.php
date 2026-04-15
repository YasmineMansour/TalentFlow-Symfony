<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Générateur d'avatars dynamiques.
 *
 * Stratégie (par ordre de priorité) :
 *   1. Gravatar (si l'email a un compte Gravatar)
 *   2. Avatar initiales SVG généré côté serveur (fond doré, lettres sombres)
 *
 * Utilisé dans les templates Twig via le service injecté.
 */
class AvatarService
{
    /** Taille par défaut en pixels */
    private const DEFAULT_SIZE = 80;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Retourne l'URL de l'avatar pour un utilisateur.
     * Utilise Gravatar avec fallback sur l'URL de l'avatar initiales.
     */
    public function getAvatarUrl(User $user, int $size = self::DEFAULT_SIZE): string
    {
        $hash = md5(strtolower(trim($user->getEmail() ?? '')));
        $fallbackUrl = $this->urlGenerator->generate(
            'app_avatar_initials',
            ['initials' => $this->getInitials($user), 'size' => $size],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Gravatar avec fallback 404 → si pas de Gravatar, redirige vers notre URL
        return sprintf(
            'https://www.gravatar.com/avatar/%s?s=%d&d=%s',
            $hash,
            $size,
            urlencode($fallbackUrl)
        );
    }

    /**
     * Génère un SVG inline avec les initiales de l'utilisateur.
     * Utilisé directement dans les templates pour éviter une requête HTTP.
     */
    public function generateInitialsSvg(User $user, int $size = self::DEFAULT_SIZE): string
    {
        return $this->buildSvg($this->getInitials($user), $size);
    }

    /**
     * Génère un SVG brut pour les initiales données (utilisé par le contrôleur d'avatar).
     */
    public function buildSvg(string $initials, int $size = self::DEFAULT_SIZE): string
    {
        $bgColors = ['#D4A843', '#c9993a', '#e0b84d', '#b8882e'];
        // Couleur déterministe basée sur les initiales
        $colorIndex = abs(crc32($initials)) % count($bgColors);
        $bgColor = $bgColors[$colorIndex];

        $fontSize = (int) ($size * 0.38);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">'
            . '<rect width="%d" height="%d" rx="%d" fill="%s"/>'
            . '<text x="50%%" y="50%%" dominant-baseline="central" text-anchor="middle" '
            . 'font-family="Segoe UI,Arial,sans-serif" font-size="%d" font-weight="700" fill="#1e2a3a">%s</text>'
            . '</svg>',
            $size, $size, $size, $size,
            $size, $size, (int)($size / 2),
            $bgColor,
            $fontSize,
            htmlspecialchars($initials, ENT_XML1)
        );
    }

    /**
     * Retourne les initiales (1 à 2 caractères) d'un utilisateur.
     */
    public function getInitials(User $user): string
    {
        $prenom = mb_strtoupper(mb_substr($user->getPrenom() ?? '', 0, 1, 'UTF-8'), 'UTF-8');
        $nom    = mb_strtoupper(mb_substr($user->getNom() ?? '', 0, 1, 'UTF-8'), 'UTF-8');

        return $prenom . $nom ?: '?';
    }
}
