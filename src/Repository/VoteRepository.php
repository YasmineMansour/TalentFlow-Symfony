<?php

namespace App\Repository;

use App\Entity\Vote;
use App\Entity\Post;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vote>
 */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    public function findByUserAndPost(User $user, Post $post): ?Vote
    {
        return $this->findOneBy(['user' => $user, 'post' => $post]);
    }
}
