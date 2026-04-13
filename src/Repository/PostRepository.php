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
