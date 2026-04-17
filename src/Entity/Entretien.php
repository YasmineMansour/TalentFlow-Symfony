<?php

namespace App\Entity;

use App\Repository\EntretienRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EntretienRepository::class)]
#[ORM\Table(name: 'entretien')]
class Entretien
{
    public const TYPES = ['EN_LIGNE', 'PRESENTIEL', 'TELEPHONIQUE'];
    public const STATUTS = ['PLANIFIE', 'REALISE', 'ANNULE'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'candidature_id')]
    #[Assert\NotNull(message: 'La candidature est obligatoire.')]
    #[Assert\Positive(message: 'L\'identifiant de candidature doit être positif.')]
    private ?int $candidatureId = null;

    #[ORM\Column(name: 'date_heure', type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date et l\'heure sont obligatoires.')]
    private ?\DateTimeInterface $dateHeure = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\NotBlank(message: 'Le type est obligatoire.')]
    #[Assert\Choice(choices: self::TYPES, message: 'Le type est invalide.')]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Le lieu ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $lieu = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Le lien doit être une URL valide.')]
    private ?string $lien = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(choices: self::STATUTS, message: 'Le statut est invalide.')]
    private ?string $statut = null;

    #[ORM\Column(name: 'note_technique', nullable: true)]
    #[Assert\Range(min: 0, max: 20, notInRangeMessage: 'La note technique doit être entre {{ min }} et {{ max }}.')]
    private ?int $noteTechnique = null;

    #[ORM\Column(name: 'note_communication', nullable: true)]
    #[Assert\Range(min: 0, max: 20, notInRangeMessage: 'La note de communication doit être entre {{ min }} et {{ max }}.')]
    private ?int $noteCommunication = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000, maxMessage: 'Le commentaire est trop long.')]
    private ?string $commentaire = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'reminder_24h_sent_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $reminder24hSentAt = null;

    #[ORM\Column(name: 'reminder_1h_sent_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $reminder1hSentAt = null;

    #[ORM\OneToOne(mappedBy: 'entretien', targetEntity: DecisionFinale::class)]
    private ?DecisionFinale $decisionFinale = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCandidatureId(): ?int
    {
        return $this->candidatureId;
    }

    public function setCandidatureId(int $candidatureId): static
    {
        $this->candidatureId = $candidatureId;

        return $this;
    }

    public function getDateHeure(): ?\DateTimeInterface
    {
        return $this->dateHeure;
    }

    public function setDateHeure(?\DateTimeInterface $dateHeure): static
    {
        $this->dateHeure = $dateHeure;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type !== null ? strtoupper(trim($type)) : null;

        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu !== null ? trim($lieu) : null;

        return $this;
    }

    public function getLien(): ?string
    {
        return $this->lien;
    }

    public function setLien(?string $lien): static
    {
        $this->lien = $lien !== null ? trim($lien) : null;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut !== null ? strtoupper(trim($statut)) : null;

        return $this;
    }

    public function getNoteTechnique(): ?int
    {
        return $this->noteTechnique;
    }

    public function setNoteTechnique(?int $noteTechnique): static
    {
        $this->noteTechnique = $noteTechnique;

        return $this;
    }

    public function getNoteCommunication(): ?int
    {
        return $this->noteCommunication;
    }

    public function setNoteCommunication(?int $noteCommunication): static
    {
        $this->noteCommunication = $noteCommunication;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire !== null ? trim($commentaire) : null;

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

    public function getDecisionFinale(): ?DecisionFinale
    {
        return $this->decisionFinale;
    }

    public function setDecisionFinale(?DecisionFinale $decisionFinale): static
    {
        $this->decisionFinale = $decisionFinale;

        return $this;
    }

    public function getReminder24hSentAt(): ?\DateTimeInterface
    {
        return $this->reminder24hSentAt;
    }

    public function setReminder24hSentAt(?\DateTimeInterface $reminder24hSentAt): static
    {
        $this->reminder24hSentAt = $reminder24hSentAt;

        return $this;
    }

    public function getReminder1hSentAt(): ?\DateTimeInterface
    {
        return $this->reminder1hSentAt;
    }

    public function setReminder1hSentAt(?\DateTimeInterface $reminder1hSentAt): static
    {
        $this->reminder1hSentAt = $reminder1hSentAt;

        return $this;
    }

    public function getScoreFinal(): ?float
    {
        if ($this->noteTechnique === null || $this->noteCommunication === null) {
            return null;
        }

        return round(($this->noteTechnique * 0.7) + ($this->noteCommunication * 0.3), 2);
    }

    public function getNiveau(): string
    {
        $score = $this->getScoreFinal();

        if ($score === null) {
            return '-';
        }

        return match (true) {
            $score >= 16 => 'EXCELLENT',
            $score >= 12 => 'BON',
            $score >= 10 => 'MOYEN',
            default => 'INSUFFISANT',
        };
    }

    public function getDisplayLabel(): string
    {
        $date = $this->dateHeure?->format('d/m/Y H:i') ?? 'Date inconnue';

        return sprintf('Entretien #%d | Candidature #%d | %s', $this->id ?? 0, $this->candidatureId ?? 0, $date);
    }

    /**
     * Retourne l'URL Jitsi Meet pour cet entretien (type EN_LIGNE uniquement).
     */
    public function getMeetUrl(): string
    {
        return 'https://meet.jit.si/talentflow-entretien-' . ($this->id ?? 'preview');
    }

    /**
     * Retourne la décision suggérée basée sur le score calculé.
     */
    public function getDecisionSuggestion(): string
    {
        $score = $this->getScoreFinal();

        if ($score === null) {
            return 'Notes manquantes';
        }

        return match (true) {
            $score >= 14 => 'ACCEPTE',
            $score < 10  => 'REFUSE',
            default      => 'EN_ATTENTE',
        };
    }
}