<?php

namespace App\Entity;

use App\Repository\OffreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Entreprise;
use App\Entity\Categorie;

#[ORM\Entity(repositoryClass: OffreRepository::class)]
#[ORM\Table(name: 'offre')]
class Offre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        min: 10,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'La localisation doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'La localisation ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $localisation = null;

    #[ORM\Column(name: 'type_contrat', length: 50, options: ['default' => 'CDI'])]
    #[Assert\Choice(
        choices: ['CDI', 'CDD', 'Stage', 'Freelance', 'Alternance'],
        message: 'Type de contrat invalide.'
    )]
    private ?string $typeContrat = 'CDI';

    #[ORM\Column(name: 'mode_travail', length: 50, options: ['default' => 'ON_SITE'])]
    #[Assert\Choice(
        choices: ['ON_SITE', 'REMOTE', 'HYBRID'],
        message: 'Mode de travail invalide.'
    )]
    private ?string $modeTravail = 'ON_SITE';

    #[ORM\Column(name: 'salaire_min', options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'Le salaire minimum doit être positif ou zéro.')]
    private ?float $salaireMin = 0;

    #[ORM\Column(name: 'salaire_max', options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'Le salaire maximum doit être positif ou zéro.')]
    #[Assert\GreaterThanOrEqual(
        propertyPath: 'salaireMin',
        message: 'Le salaire maximum doit être supérieur ou égal au salaire minimum.'
    )]
    private ?float $salaireMax = 0;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private ?bool $active = true;

    #[ORM\Column(length: 50, options: ['default' => 'PUBLISHED'])]
    #[Assert\Choice(
        choices: ['PUBLISHED', 'DRAFT', 'CLOSED'],
        message: 'Statut invalide.'
    )]
    private ?string $statut = 'PUBLISHED';

    #[ORM\ManyToOne(targetEntity: Entreprise::class, inversedBy: 'offres')]
    #[ORM\JoinColumn(name: 'entreprise_id', nullable: true)]
    private ?Entreprise $entreprise = null;

    #[ORM\ManyToOne(targetEntity: Categorie::class, inversedBy: 'offres')]
    #[ORM\JoinColumn(name: 'categorie_id', nullable: true)]
    private ?Categorie $categorie = null;

    #[ORM\OneToMany(mappedBy: 'offre', targetEntity: Avantage::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $avantages;

    public function __construct()
    {
        $this->avantages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(?string $localisation): static
    {
        $this->localisation = $localisation;
        return $this;
    }

    public function getTypeContrat(): ?string
    {
        return $this->typeContrat;
    }

    public function setTypeContrat(string $typeContrat): static
    {
        $this->typeContrat = $typeContrat;
        return $this;
    }

    public function getModeTravail(): ?string
    {
        return $this->modeTravail;
    }

    public function setModeTravail(string $modeTravail): static
    {
        $this->modeTravail = $modeTravail;
        return $this;
    }

    public function getSalaireMin(): ?float
    {
        return $this->salaireMin;
    }

    public function setSalaireMin(float $salaireMin): static
    {
        $this->salaireMin = $salaireMin;
        return $this;
    }

    public function getSalaireMax(): ?float
    {
        return $this->salaireMax;
    }

    public function setSalaireMax(float $salaireMax): static
    {
        $this->salaireMax = $salaireMax;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    /**
     * @return Collection<int, Avantage>
     */
    public function getAvantages(): Collection
    {
        return $this->avantages;
    }

    public function addAvantage(Avantage $avantage): static
    {
        if (!$this->avantages->contains($avantage)) {
            $this->avantages->add($avantage);
            $avantage->setOffre($this);
        }
        return $this;
    }

    public function removeAvantage(Avantage $avantage): static
    {
        if ($this->avantages->removeElement($avantage)) {
            if ($avantage->getOffre() === $this) {
                $avantage->setOffre(null);
            }
        }
        return $this;
    }

    /**
     * Métier de base : fourchette salariale formatée
     */
    public function getEntreprise(): ?Entreprise
    {
        return $this->entreprise;
    }

    public function setEntreprise(?Entreprise $entreprise): static
    {
        $this->entreprise = $entreprise;
        return $this;
    }

    public function getCategorie(): ?Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(?Categorie $categorie): static
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getSalaireRange(): string
    {
        if ($this->salaireMin == 0 && $this->salaireMax == 0) {
            return 'Non précisé';
        }
        return number_format($this->salaireMin, 0, ',', ' ') . ' - ' . number_format($this->salaireMax, 0, ',', ' ') . ' DT';
    }

    public function __toString(): string
    {
        return $this->titre ?? '';
    }
}
