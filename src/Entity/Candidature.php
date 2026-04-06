<?php

namespace App\Entity;

use App\Repository\CandidatureRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CandidatureRepository::class)]
#[ORM\Table(name: 'candidature')]
class Candidature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le titre du poste ne peut pas être vide.')]
    #[Assert\Length(
        min: 3,
        max: 150,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $titrePoste = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le nom de l\'entreprise ne peut pas être vide.')]
    #[Assert\Length(
        min: 2,
        max: 150,
        minMessage: 'Le nom de l\'entreprise doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $entreprise = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 3000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le type de contrat ne peut pas être vide.')]
    #[Assert\Choice(
        choices: ['CDI', 'CDD', 'Stage', 'Alternance', 'Freelance'],
        message: 'Type de contrat invalide.'
    )]
    private ?string $typeContrat = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: 'Le statut ne peut pas être vide.')]
    #[Assert\Choice(
        choices: ['En attente', 'Acceptée', 'Refusée', 'Entretien'],
        message: 'Statut invalide.'
    )]
    private ?string $statut = 'En attente';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date de candidature ne peut pas être vide.')]
    private ?\DateTimeImmutable $dateCandidature = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\GreaterThanOrEqual(
        propertyPath: 'dateCandidature',
        message: 'La date d\'entretien doit être postérieure à la date de candidature.'
    )]
    private ?\DateTimeImmutable $dateEntretien = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $lieu = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le salaire doit être un nombre positif.')]
    #[Assert\LessThanOrEqual(value: 999999.99, message: 'Le salaire ne peut pas dépasser {{ compared_value }}.')]
    private ?string $salaireSouhaite = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000, maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères.')]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, PieceJointe> */
    #[ORM\OneToMany(targetEntity: PieceJointe::class, mappedBy: 'candidature', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $piecesJointes;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->dateCandidature = new \DateTimeImmutable();
        $this->piecesJointes = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitrePoste(): ?string { return $this->titrePoste; }
    public function setTitrePoste(string $titrePoste): static { $this->titrePoste = $titrePoste; return $this; }

    public function getEntreprise(): ?string { return $this->entreprise; }
    public function setEntreprise(string $entreprise): static { $this->entreprise = $entreprise; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getTypeContrat(): ?string { return $this->typeContrat; }
    public function setTypeContrat(string $typeContrat): static { $this->typeContrat = $typeContrat; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getDateCandidature(): ?\DateTimeImmutable { return $this->dateCandidature; }
    public function setDateCandidature(\DateTimeImmutable $dateCandidature): static { $this->dateCandidature = $dateCandidature; return $this; }

    public function getDateEntretien(): ?\DateTimeImmutable { return $this->dateEntretien; }
    public function setDateEntretien(?\DateTimeImmutable $dateEntretien): static { $this->dateEntretien = $dateEntretien; return $this; }

    public function getLieu(): ?string { return $this->lieu; }
    public function setLieu(?string $lieu): static { $this->lieu = $lieu; return $this; }

    public function getSalaireSouhaite(): ?string { return $this->salaireSouhaite; }
    public function setSalaireSouhaite(?string $salaireSouhaite): static { $this->salaireSouhaite = $salaireSouhaite; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function getStatutBadgeClass(): string
    {
        return match ($this->statut) {
            'Acceptée' => 'success',
            'Refusée' => 'danger',
            'Entretien' => 'warning',
            default => 'secondary',
        };
    }

    /** @return Collection<int, PieceJointe> */
    public function getPiecesJointes(): Collection { return $this->piecesJointes; }

    public function addPieceJointe(PieceJointe $pieceJointe): static
    {
        if (!$this->piecesJointes->contains($pieceJointe)) {
            $this->piecesJointes->add($pieceJointe);
            $pieceJointe->setCandidature($this);
        }
        return $this;
    }

    public function removePieceJointe(PieceJointe $pieceJointe): static
    {
        if ($this->piecesJointes->removeElement($pieceJointe)) {
            if ($pieceJointe->getCandidature() === $this) {
                $pieceJointe->setCandidature(null);
            }
        }
        return $this;
    }
}
