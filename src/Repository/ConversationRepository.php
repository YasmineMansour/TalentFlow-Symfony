<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.userOne = :user OR c.userTwo = :user')
            ->setParameter('user', $user)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findBetweenUsers(User $a, User $b): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->where('(c.userOne = :a AND c.userTwo = :b) OR (c.userOne = :b AND c.userTwo = :a)')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
