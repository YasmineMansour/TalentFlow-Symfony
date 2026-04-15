<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cet email.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom ne peut pas être vide.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-]+$/u',
        message: 'Le nom ne doit contenir que des lettres, espaces ou tirets (pas de chiffres).'
    )]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom ne peut pas être vide.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-]+$/u',
        message: 'Le prénom ne doit contenir que des lettres, espaces ou tirets (pas de chiffres).'
    )]
    private ?string $prenom = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'email ne peut pas être vide.')]
    #[Assert\Email(
        message: 'L\'email "{{ value }}" n\'est pas valide.',
        mode: 'strict'
    )]
    #[Assert\Length(max: 180, maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^(\+216|00216)?[2459][0-9]{7}$/',
        message: 'Le numéro doit être au format tunisien : (+216|00216) suivi de 8 chiffres commençant par 2, 4, 5 ou 9.'
    )]
    private ?string $telephone = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Indique si le compte est bloqué (protection Brute Force).
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $blocked = false;

    /**
     * Indique si le compte est actif. Contrôlé par l'administrateur.
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    /**
     * Code d'authentification à deux facteurs envoyé par email.
     */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $authCode = null;

    /**
     * Indique si la 2FA par email est activée pour cet utilisateur.
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $twoFactorEnabled = true;

    /** Titre professionnel (ex: Développeur PHP, Chef de projet) */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $titrePoste = null;

    /** Biographie courte visible sur le profil */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    /** @var Collection<int, Post> */
    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'author')]
    private Collection $posts;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'author')]
    private Collection $comments;

    #[ORM\ManyToOne(targetEntity: Entreprise::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Entreprise $entreprise = null;

    /** @var Collection<int, UserLog> */
    #[ORM\OneToMany(targetEntity: UserLog::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $userLogs;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->posts = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->userLogs = new ArrayCollection();
    }

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
        // Transformation automatique du nom en MAJUSCULES
        $this->nom = mb_strtoupper($nom, 'UTF-8');

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
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

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get the full name of the user.
     */
    public function getFullName(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    /**
     * Get a human-readable role label.
     */
    public function getRoleLabel(): string
    {
        if (in_array('ROLE_ADMIN', $this->roles)) {
            return 'Administrateur';
        }
        if (in_array('ROLE_RH', $this->roles)) {
            return 'Recruteur RH';
        }
        if (in_array('ROLE_CANDIDAT', $this->roles)) {
            return 'Candidat';
        }

        return 'Utilisateur';
    }

    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    public function setBlocked(bool $blocked): static
    {
        $this->blocked = $blocked;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    // ─── Two-Factor Authentication (Email) ──────────────────────────

    /**
     * Vérifie si l'authentification à deux facteurs par email est activée.
     */
    public function isEmailAuthEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    /**
     * Retourne l'adresse email sur laquelle envoyer le code 2FA.
     */
    public function getEmailAuthRecipient(): string
    {
        return $this->email;
    }

    /**
     * Retourne le code d'authentification courant.
     */
    public function getEmailAuthCode(): string
    {
        if (null === $this->authCode) {
            throw new \LogicException('Le code d\'authentification n\'a pas été défini.');
        }

        return $this->authCode;
    }

    /**
     * Définit le code d'authentification (appelé automatiquement par le bundle).
     */
    public function setEmailAuthCode(string $authCode): void
    {
        $this->authCode = $authCode;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    public function setTwoFactorEnabled(bool $twoFactorEnabled): static
    {
        $this->twoFactorEnabled = $twoFactorEnabled;
        return $this;
    }

    /**
     * Lifecycle callback : transforme automatiquement le nom en majuscules et
     * met à jour le timestamp avant chaque persistance.
     */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function onPrePersist(): void
    {
        if ($this->nom) {
            $this->nom = mb_strtoupper($this->nom, 'UTF-8');
        }
    }

    /** @return Collection<int, Post> */
    public function getPosts(): Collection { return $this->posts; }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection { return $this->comments; }

    public function getEntreprise(): ?Entreprise
    {
        return $this->entreprise;
    }

    public function setEntreprise(?Entreprise $entreprise): static
    {
        $this->entreprise = $entreprise;
        return $this;
    }

    public function getTitrePoste(): ?string { return $this->titrePoste; }
    public function setTitrePoste(?string $titrePoste): static { $this->titrePoste = $titrePoste; return $this; }

    public function getBio(): ?string { return $this->bio; }
    public function setBio(?string $bio): static { $this->bio = $bio; return $this; }

    /** @return Collection<int, UserLog> */
    public function getUserLogs(): Collection { return $this->userLogs; }
}
