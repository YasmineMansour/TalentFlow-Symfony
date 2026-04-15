<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\PostReport;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PostReport>
 */
class PostReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostReport::class);
    }

    /**
     * @return PostReport[]
     */
    public function findPendingOrdered(): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.post', 'p')->addSelect('p')
            ->leftJoin('r.reportedBy', 'u')->addSelect('u')
            ->where('r.status = :status')
            ->setParameter('status', PostReport::STATUS_PENDING)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function hasPendingReport(Post $post, User $user): bool
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.post = :post')
            ->andWhere('r.reportedBy = :user')
            ->andWhere('r.status = :status')
            ->setParameter('post', $post)
            ->setParameter('user', $user)
            ->setParameter('status', PostReport::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function resolvePendingForPost(Post $post, string $status): void
    {
        $this->createQueryBuilder('r')
            ->update()
            ->set('r.status', ':status')
            ->where('r.post = :post')
            ->andWhere('r.status = :pending')
            ->setParameter('status', $status)
            ->setParameter('post', $post)
            ->setParameter('pending', PostReport::STATUS_PENDING)
            ->getQuery()
            ->execute();
    }
}
