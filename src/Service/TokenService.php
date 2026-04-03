<?php

namespace App\Service;

use App\Entity\ResetPasswordToken;
use App\Entity\User;
use App\Repository\ResetPasswordTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Générateur de Jetons Sécurisés (Token Service)
 * 
 * Génère des liens sécurisés à durée limitée pour :
 * - Réinitialisation de mot de passe oublié
 * - Vérification d'email
 * 
 * Le token est généré en clair, mais seul le hash SHA-256 est stocké en BDD
 * (même principe que les mots de passe — ne jamais stocker en clair).
 */
class TokenService
{
    /**
     * Durée de validité du token en heures.
     */
    private const TOKEN_EXPIRY_HOURS = 1;

    /**
     * Longueur du token en bytes (32 bytes = 64 chars hex).
     */
    private const TOKEN_LENGTH = 32;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ResetPasswordTokenRepository $tokenRepository,
    ) {
    }

    /**
     * Génère un token sécurisé pour un utilisateur.
     * 
     * @return string Le token en clair (à envoyer par email, ne sera plus accessible après)
     */
    public function generateToken(User $user, string $type = 'password_reset', int $expiryHours = self::TOKEN_EXPIRY_HOURS): string
    {
        // Invalider tous les anciens tokens du même type pour cet utilisateur
        $this->tokenRepository->invalidateAllForUser($user, $type);

        // Générer un token cryptographiquement sécurisé
        $plainToken = bin2hex(random_bytes(self::TOKEN_LENGTH));

        // Stocker uniquement le hash en BDD
        $token = new ResetPasswordToken();
        $token->setUser($user);
        $token->setTokenHash(hash('sha256', $plainToken));
        $token->setType($type);
        $token->setExpiresAt(new \DateTimeImmutable("+{$expiryHours} hours"));

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return $plainToken;
    }

    /**
     * Valide un token et retourne l'entité associée si valide.
     */
    public function validateToken(string $plainToken): ?ResetPasswordToken
    {
        $hash = hash('sha256', $plainToken);
        return $this->tokenRepository->findValidByHash($hash);
    }

    /**
     * Consomme un token (le marque comme utilisé).
     */
    public function consumeToken(ResetPasswordToken $token): void
    {
        $token->setUsed(true);
        $this->entityManager->flush();
    }

    /**
     * Génère un token et retourne l'URL complète de réinitialisation.
     */
    public function generatePasswordResetUrl(User $user, string $baseUrl): string
    {
        $token = $this->generateToken($user, 'password_reset');
        return $baseUrl . '?token=' . $token;
    }

    /**
     * Nettoie les tokens expirés (maintenance).
     */
    public function purgeExpiredTokens(): int
    {
        return $this->tokenRepository->purgeExpired();
    }
}
