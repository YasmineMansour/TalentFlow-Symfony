<?php

namespace App\Entity;

use App\Repository\AvantageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AvantageRepository::class)]
#[ORM\Table(name: 'avantage')]
class Avantage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de l\'avantage est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $nom = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        min: 5,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(length: 50, options: ['default' => 'AUTRE'])]
    #[Assert\Choice(
        choices: ['Financier', 'Bien-être', 'Matériel', 'AUTRE'],
        message: 'Type d\'avantage invalide.'
    )]
    private ?string $type = 'AUTRE';

    #[ORM\ManyToOne(targetEntity: Offre::class, inversedBy: 'avantages')]
    #[ORM\JoinColumn(name: 'offre_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'L\'offre associée est obligatoire.')]
    private ?Offre $offre = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getOffre(): ?Offre
    {
        return $this->offre;
    }

    public function setOffre(?Offre $offre): static
    {
        $this->offre = $offre;
        return $this;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }
}
