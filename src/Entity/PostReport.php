<?php

namespace App\Entity;

use App\Repository\PostReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostReportRepository::class)]
#[ORM\Table(name: 'post_reports')]
#[ORM\Index(columns: ['status'], name: 'idx_post_reports_status')]
#[ORM\Index(columns: ['created_at'], name: 'idx_post_reports_created_at')]
#[ORM\HasLifecycleCallbacks]
class PostReport
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_RESOLVED = 'resolved';

    public const REASON_SPAM = 'Spam';
    public const REASON_INAPPROPRIATE = 'Inappropriate';
    public const REASON_MISINFORMATION = 'Misinformation';
    public const REASON_OTHER = 'Other';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Post $post = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reported_by', nullable: false, onDelete: 'CASCADE')]
    private ?User $reportedBy = null;

    #[ORM\Column(length: 50)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt ??= new \DateTimeImmutable();
    }

    public static function reasons(): array
    {
        return [
            self::REASON_SPAM,
            self::REASON_INAPPROPRIATE,
            self::REASON_MISINFORMATION,
            self::REASON_OTHER,
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getPost(): ?Post { return $this->post; }
    public function setPost(?Post $post): static { $this->post = $post; return $this; }
    public function getReportedBy(): ?User { return $this->reportedBy; }
    public function setReportedBy(?User $reportedBy): static { $this->reportedBy = $reportedBy; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(string $reason): static { $this->reason = $reason; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
