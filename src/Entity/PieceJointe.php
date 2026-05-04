<?php

namespace App\Entity;

use App\Repository\PieceJointeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[Vich\Uploadable]
#[ORM\Entity(repositoryClass: PieceJointeRepository::class)]
#[ORM\Table(name: 'piece_jointe')]
class PieceJointe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du fichier ne peut pas être vide.')]
    #[Assert\Length(max: 255)]
    private ?string $nomFichier = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le type de document ne peut pas être vide.')]
    #[Assert\Choice(
        choices: ['CV', 'Lettre de motivation', 'Diplôme', 'Certificat', 'Autre'],
        message: 'Type de document invalide.'
    )]
    private ?string $typeDocument = null;

    #[ORM\Column(length: 500)]
    #[Assert\Length(max: 500)]
    private ?string $cheminFichier = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private ?int $tailleFichier = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $uploadedAt = null;

    #[Vich\UploadableField(mapping: 'candidature_piece_jointe', fileNameProperty: 'cheminFichier', size: 'tailleFichier')]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
        ],
        mimeTypesMessage: 'Formats acceptés : PDF, DOC, DOCX, JPG, PNG (max 5 Mo).'
    )]
    private ?File $fichierFile = null;

    #[ORM\ManyToOne(targetEntity: Candidature::class, inversedBy: 'piecesJointes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'La candidature est obligatoire.')]
    private ?Candidature $candidature = null;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getNomFichier(): ?string { return $this->nomFichier; }
    public function setNomFichier(string $nomFichier): static { $this->nomFichier = $nomFichier; return $this; }

    public function getTypeDocument(): ?string { return $this->typeDocument; }
    public function setTypeDocument(string $typeDocument): static { $this->typeDocument = $typeDocument; return $this; }

    public function getCheminFichier(): ?string { return $this->cheminFichier; }
    public function setCheminFichier(string $cheminFichier): static { $this->cheminFichier = $cheminFichier; return $this; }

    public function getTailleFichier(): ?int { return $this->tailleFichier; }
    public function setTailleFichier(int $tailleFichier): static { $this->tailleFichier = $tailleFichier; return $this; }

    public function getUploadedAt(): ?\DateTimeImmutable { return $this->uploadedAt; }
    public function setUploadedAt(\DateTimeImmutable $uploadedAt): static { $this->uploadedAt = $uploadedAt; return $this; }

    public function getFichierFile(): ?File
    {
        return $this->fichierFile;
    }

    public function setFichierFile(?File $fichierFile): static
    {
        $this->fichierFile = $fichierFile;

        if ($fichierFile instanceof UploadedFile) {
            $this->uploadedAt = new \DateTimeImmutable();
            $this->nomFichier = $fichierFile->getClientOriginalName();
        }

        return $this;
    }

    public function getCandidature(): ?Candidature { return $this->candidature; }
    public function setCandidature(?Candidature $candidature): static { $this->candidature = $candidature; return $this; }

    public function getTailleFormatee(): string
    {
        $taille = $this->tailleFichier;
        if ($taille >= 1048576) {
            return round($taille / 1048576, 2) . ' Mo';
        }
        if ($taille >= 1024) {
            return round($taille / 1024, 2) . ' Ko';
        }
        return $taille . ' octets';
    }

    public function getExtension(): string
    {
        return strtolower(pathinfo($this->nomFichier, PATHINFO_EXTENSION));
    }
}
