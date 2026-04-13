<?php

namespace App\Entity;

use App\Repository\UserLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Historique de connexion — visible par l'admin et l'utilisateur lui-même.
 * Distinct de LoginAttempt (brute force) : celui-ci est lié à l'entité User
 * et affiche l'historique "Mes connexions récentes" sur le profil.
 */
#[ORM\Entity(repositoryClass: UserLogRepository::class)]
#[ORM\Table(name: 'user_log')]
#[ORM\Index(name: 'idx_userlog_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_userlog_logged_at', columns: ['logged_at'])]
class UserLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userLogs')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 45)]
    private string $ipAddress = '0.0.0.0';

    /** Navigateur détecté (ex: Chrome 120, Firefox 121) */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $browser = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column]
    private \DateTimeImmutable $loggedAt;

    #[ORM\Column]
    private bool $successful = true;

    /** Email saisi lors de la tentative (utile pour les échecs sans user lié) */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    public function __construct()
    {
        $this->loggedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getIpAddress(): string { return $this->ipAddress; }
    public function setIpAddress(string $ipAddress): static { $this->ipAddress = $ipAddress; return $this; }

    public function getBrowser(): ?string { return $this->browser; }
    public function setBrowser(?string $browser): static { $this->browser = $browser; return $this; }

    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $userAgent): static { $this->userAgent = $userAgent; return $this; }

    public function getLoggedAt(): \DateTimeImmutable { return $this->loggedAt; }
    public function setLoggedAt(\DateTimeImmutable $loggedAt): static { $this->loggedAt = $loggedAt; return $this; }

    public function isSuccessful(): bool { return $this->successful; }
    public function setSuccessful(bool $successful): static { $this->successful = $successful; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }

    /**
     * Détecte le navigateur à partir du User-Agent.
     */
    public static function detectBrowser(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Inconnu';
        }
        if (str_contains($userAgent, 'Edg/')) {
            return 'Edge';
        }
        if (str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera')) {
            return 'Opera';
        }
        if (str_contains($userAgent, 'Chrome/')) {
            return 'Chrome';
        }
        if (str_contains($userAgent, 'Firefox/')) {
            return 'Firefox';
        }
        if (str_contains($userAgent, 'Safari/') && str_contains($userAgent, 'Version/')) {
            return 'Safari';
        }
        if (str_contains($userAgent, 'MSIE') || str_contains($userAgent, 'Trident/')) {
            return 'Internet Explorer';
        }
        return 'Autre';
    }
}
