<?php

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * @return Post[]
     */
    public function findAllOrderedByDate(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Post[]
     */
    public function findByAuthor(int $authorId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.author = :authorId')
            ->setParameter('authorId', $authorId)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

        /**
        * @return Post[]
        */
    public function findPublicPosts(string $search = ''): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->orderBy('p.createdAt', 'DESC');

        if ($search !== '') {
            $qb
                ->andWhere('p.title LIKE :search OR p.content LIKE :search OR a.nom LIKE :search OR a.prenom LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Post[]
     */
    public function searchAndFilter(string $query = '', string $sort = 'recent', string $author = ''): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a');

        if ($query !== '') {
            $qb->andWhere('p.title LIKE :q OR p.content LIKE :q OR a.nom LIKE :q OR a.prenom LIKE :q')
               ->setParameter('q', '%' . $query . '%');
        }

        if ($author !== '') {
            $qb->andWhere('CONCAT(a.prenom, \' \', a.nom) LIKE :author')
               ->setParameter('author', '%' . $author . '%');
        }

        match ($sort) {
            'top' => $qb->orderBy('p.upvotes', 'DESC'),
            'oldest' => $qb->orderBy('p.createdAt', 'ASC'),
            'comments' => $qb->leftJoin('p.comments', 'c')
                             ->groupBy('p.id')
                             ->addGroupBy('a.id')
                             ->orderBy('COUNT(c.id)', 'DESC'),
            default => $qb->orderBy('p.createdAt', 'DESC'),
        };

        return $qb->getQuery()->getResult();
    }
}
