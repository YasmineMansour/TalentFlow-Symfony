<?php

namespace App\Service;

use App\Entity\LoginAttempt;
use App\Repository\LoginAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service d'Audit de Sécurité — Login Tracker
 * 
 * Enregistre chaque tentative de connexion (réussie ou échouée) avec IP et heure.
 * Bloque l'utilisateur après MAX_ATTEMPTS tentatives infructueuses dans un intervalle
 * de LOCKOUT_MINUTES (protection Brute Force).
 */
class LoginTrackerService
{
    /**
     * Nombre maximum de tentatives échouées avant blocage.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Fenêtre de temps en minutes pour compter les tentatives.
     */
    private const LOCKOUT_MINUTES = 15;

    /**
     * Nombre max de tentatives par IP (contre les attaques distribuées).
     */
    private const MAX_ATTEMPTS_PER_IP = 20;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoginAttemptRepository $loginAttemptRepository,
    ) {
    }

    /**
     * Enregistre une tentative de connexion.
     */
    public function recordAttempt(
        string $email,
        string $ipAddress,
        bool $successful,
        ?string $userAgent = null,
        ?string $failureReason = null
    ): LoginAttempt {
        $attempt = new LoginAttempt();
        $attempt->setEmail($email);
        $attempt->setIpAddress($ipAddress);
        $attempt->setSuccessful($successful);
        $attempt->setUserAgent($userAgent ? mb_substr($userAgent, 0, 255) : null);
        $attempt->setFailureReason($failureReason);

        $this->entityManager->persist($attempt);
        $this->entityManager->flush();

        return $attempt;
    }

    /**
     * Vérifie si un email est actuellement bloqué (trop de tentatives échouées).
     */
    public function isBlocked(string $email): bool
    {
        $failedCount = $this->loginAttemptRepository
            ->countRecentFailedAttempts($email, self::LOCKOUT_MINUTES);

        return $failedCount >= self::MAX_ATTEMPTS;
    }

    /**
     * Vérifie si une IP est bloquée (attaque distribuée).
     */
    public function isIpBlocked(string $ip): bool
    {
        $failedCount = $this->loginAttemptRepository
            ->countRecentFailedAttemptsByIp($ip, self::LOCKOUT_MINUTES);

        return $failedCount >= self::MAX_ATTEMPTS_PER_IP;
    }

    /**
     * Retourne le nombre de tentatives restantes avant blocage.
     */
    public function getRemainingAttempts(string $email): int
    {
        $failedCount = $this->loginAttemptRepository
            ->countRecentFailedAttempts($email, self::LOCKOUT_MINUTES);

        return max(0, self::MAX_ATTEMPTS - $failedCount);
    }

    /**
     * Retourne le temps restant avant déblocage (en minutes).
     */
    public function getLockoutRemainingMinutes(string $email): int
    {
        if (!$this->isBlocked($email)) {
            return 0;
        }

        // Trouve la dernière tentative échouée
        $recentAttempts = $this->loginAttemptRepository->findRecentByEmail($email, 1);
        if (empty($recentAttempts)) {
            return 0;
        }

        $lastAttempt = $recentAttempts[0];
        $unblockAt = $lastAttempt->getAttemptedAt()->modify('+' . self::LOCKOUT_MINUTES . ' minutes');
        $now = new \DateTimeImmutable();

        if ($unblockAt <= $now) {
            return 0;
        }

        return (int) ceil(($unblockAt->getTimestamp() - $now->getTimestamp()) / 60);
    }

    /**
     * Récupère l'historique des connexions pour l'audit.
     *
     * @return LoginAttempt[]
     */
    public function getAuditLog(int $hours = 24): array
    {
        return $this->loginAttemptRepository->findAllRecent($hours);
    }

    /**
     * Récupère l'historique pour un email spécifique.
     *
     * @return LoginAttempt[]
     */
    public function getUserLoginHistory(string $email, int $limit = 10): array
    {
        return $this->loginAttemptRepository->findRecentByEmail($email, $limit);
    }
}
