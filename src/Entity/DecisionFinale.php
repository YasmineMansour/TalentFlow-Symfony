<?php

namespace App\Entity;

use App\Repository\DecisionFinaleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DecisionFinaleRepository::class)]
#[ORM\Table(name: 'decision_finale')]
class DecisionFinale
{
    public const DECISIONS = ['ACCEPTE', 'REFUSE', 'EN_ATTENTE'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'decisionFinale', targetEntity: Entretien::class)]
    #[ORM\JoinColumn(name: 'entretien_id', referencedColumnName: 'id', nullable: false, unique: true)]
    #[Assert\NotNull(message: 'L\'entretien est obligatoire.')]
    private ?Entretien $entretien = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'La décision est obligatoire.')]
    #[Assert\Choice(choices: self::DECISIONS, message: 'La décision est invalide.')]
    private ?string $decision = 'EN_ATTENTE';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Le motif est trop long.')]
    private ?string $motif = null;

    #[ORM\Column(name: 'date_decision', type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date de décision est obligatoire.')]
    private ?\DateTimeInterface $dateDecision = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 20, notInRangeMessage: 'Le score doit être entre {{ min }} et {{ max }}.')]
    private ?float $score = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntretien(): ?Entretien
    {
        return $this->entretien;
    }

    public function setEntretien(?Entretien $entretien): static
    {
        $this->entretien = $entretien;

        return $this;
    }

    public function getDecision(): ?string
    {
        return $this->decision;
    }

    public function setDecision(string $decision): static
    {
        $this->decision = strtoupper(trim($decision));

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif !== null ? trim($motif) : null;

        return $this;
    }

    public function getDateDecision(): ?\DateTimeInterface
    {
        return $this->dateDecision;
    }

    public function setDateDecision(\DateTimeInterface $dateDecision): static
    {
        $this->dateDecision = $dateDecision;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getScore(): ?float
    {
        return $this->score;
    }

    public function setScore(?float $score): static
    {
        $this->score = $score !== null ? round($score, 2) : null;

        return $this;
    }
}