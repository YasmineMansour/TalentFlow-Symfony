<?php

namespace App\Entity;

use App\Repository\LoginAttemptRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité pour tracer chaque tentative de connexion (réussie ou échouée).
 * Utilisée par le LoginTrackerService pour la protection Brute Force.
 */
#[ORM\Entity(repositoryClass: LoginAttemptRepository::class)]
#[ORM\Table(name: 'login_attempt')]
#[ORM\Index(name: 'idx_login_email', columns: ['email'])]
#[ORM\Index(name: 'idx_login_ip', columns: ['ip_address'])]
class LoginAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 45)]
    private ?string $ipAddress = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $attemptedAt = null;

    #[ORM\Column]
    private bool $successful = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $failureReason = null;

    public function __construct()
    {
        $this->attemptedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getAttemptedAt(): ?\DateTimeImmutable
    {
        return $this->attemptedAt;
    }

    public function setAttemptedAt(\DateTimeImmutable $attemptedAt): static
    {
        $this->attemptedAt = $attemptedAt;
        return $this;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function setSuccessful(bool $successful): static
    {
        $this->successful = $successful;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): static
    {
        $this->failureReason = $failureReason;
        return $this;
    }
}
