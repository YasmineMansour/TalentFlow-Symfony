<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'candidature')]
class Candidature
{
    public const STATUTS = ['EN_ATTENTE', 'NOUVEAU', 'ACCEPTE', 'REFUSE'];
    public const LANGUES = ['FR', 'EN', 'AR', 'DA', 'INCONNU'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Offre::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Offre $offre = null;

    #[ORM\Column(length: 50)]
    private string $langue = 'INCONNU';

    #[ORM\Column(length: 30)]
    private string $statut = 'EN_ATTENTE';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $cvUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motivation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $datePostulation = null;

    #[ORM\Column(length: 180)]
    #[Assert\Email]
    private ?string $email = null;

    /** @var Collection<int, PieceJointe> */
    #[ORM\OneToMany(targetEntity: PieceJointe::class, mappedBy: 'candidature', cascade: ['remove'], orphanRemoval: true)]
    private Collection $piecesJointes;

    public function __construct()
    {
        $this->datePostulation = new \DateTime();
        $this->piecesJointes = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getOffre(): ?Offre { return $this->offre; }
    public function setOffre(?Offre $offre): static { $this->offre = $offre; return $this; }

    public function getLangue(): string { return $this->langue; }
    public function setLangue(string $langue): static { $this->langue = $langue; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getCvUrl(): ?string { return $this->cvUrl; }
    public function setCvUrl(?string $cvUrl): static { $this->cvUrl = $cvUrl; return $this; }

    public function getMotivation(): ?string { return $this->motivation; }
    public function setMotivation(?string $motivation): static { $this->motivation = $motivation; return $this; }

    public function getDatePostulation(): ?\DateTimeInterface { return $this->datePostulation; }
    public function setDatePostulation(\DateTimeInterface $datePostulation): static { $this->datePostulation = $datePostulation; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    /** @return Collection<int, PieceJointe> */
    public function getPiecesJointes(): Collection { return $this->piecesJointes; }

    public function addPieceJointe(PieceJointe $pj): static
    {
        if (!$this->piecesJointes->contains($pj)) {
            $this->piecesJointes->add($pj);
            $pj->setCandidature($this);
        }
        return $this;
    }

    public function removePieceJointe(PieceJointe $pj): static
    {
        if ($this->piecesJointes->removeElement($pj)) {
            if ($pj->getCandidature() === $this) {
                $pj->setCandidature(null);
            }
        }
        return $this;
    }
}
