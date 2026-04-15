<?php

namespace App\Entity;

use App\Repository\CandidatureStatusHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CandidatureStatusHistoryRepository::class)]
#[ORM\Table(name: 'candidature_status_history')]
class CandidatureStatusHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Candidature::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Candidature $candidature = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $changedBy = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $fromStatus = null;

    #[ORM\Column(length: 30)]
    private ?string $toStatus = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $transitionName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $changedAt = null;

    public function __construct()
    {
        $this->changedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getCandidature(): ?Candidature { return $this->candidature; }
    public function setCandidature(?Candidature $candidature): static { $this->candidature = $candidature; return $this; }

    public function getChangedBy(): ?User { return $this->changedBy; }
    public function setChangedBy(?User $changedBy): static { $this->changedBy = $changedBy; return $this; }

    public function getFromStatus(): ?string { return $this->fromStatus; }
    public function setFromStatus(?string $fromStatus): static { $this->fromStatus = $fromStatus; return $this; }

    public function getToStatus(): ?string { return $this->toStatus; }
    public function setToStatus(string $toStatus): static { $this->toStatus = $toStatus; return $this; }

    public function getTransitionName(): ?string { return $this->transitionName; }
    public function setTransitionName(?string $transitionName): static { $this->transitionName = $transitionName; return $this; }

    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): static { $this->note = $note; return $this; }

    public function getChangedAt(): ?\DateTimeImmutable { return $this->changedAt; }
    public function setChangedAt(\DateTimeImmutable $changedAt): static { $this->changedAt = $changedAt; return $this; }
}
