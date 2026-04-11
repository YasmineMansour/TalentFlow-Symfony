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
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Post[]
     */
    public function findByFilters(string $search = '', ?string $auteur = null, string $tri = 'createdAt', string $ordre = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a');

        if ($search !== '') {
            $qb->andWhere('p.title LIKE :q OR p.content LIKE :q OR a.nom LIKE :q OR a.prenom LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        if ($auteur !== null && $auteur !== '') {
            $qb->andWhere('a.id = :auteurId')
               ->setParameter('auteurId', (int) $auteur);
        }

        $allowedTri = ['createdAt', 'title', 'upvotes'];
        $triField = in_array($tri, $allowedTri) ? $tri : 'createdAt';
        $ordreField = strtoupper($ordre) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy('p.' . $triField, $ordreField);

        return $qb->getQuery()->getResult();
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
     * Public: all posts ordered by date, with optional search.
     * @return Post[]
     */
    public function findPublicPosts(string $search = '', int $limit = 30): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($search !== '') {
            $qb->andWhere('p.title LIKE :q OR p.content LIKE :q OR a.nom LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
