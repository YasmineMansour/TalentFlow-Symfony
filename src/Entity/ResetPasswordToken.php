<?php

namespace App\Entity;

use App\Repository\ResetPasswordTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Jeton sécurisé à durée limitée pour la réinitialisation de mot de passe
 * et la vérification d'email.
 */
#[ORM\Entity(repositoryClass: ResetPasswordTokenRepository::class)]
#[ORM\Table(name: 'reset_password_token')]
#[ORM\Index(name: 'idx_token_hash', columns: ['token_hash'])]
class ResetPasswordToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * Hash SHA-256 du token (on ne stocke jamais le token en clair en BDD)
     */
    #[ORM\Column(length: 64, unique: true)]
    private ?string $tokenHash = null;

    #[ORM\Column(length: 20)]
    private ?string $type = 'password_reset';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    private bool $used = false;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getTokenHash(): ?string
    {
        return $this->tokenHash;
    }

    public function setTokenHash(string $tokenHash): static
    {
        $this->tokenHash = $tokenHash;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function setUsed(bool $used): static
    {
        $this->used = $used;
        return $this;
    }

    /**
     * Vérifie si le token est encore valide (non expiré et non utilisé).
     */
    public function isValid(): bool
    {
        return !$this->used && $this->expiresAt > new \DateTimeImmutable();
    }
}
