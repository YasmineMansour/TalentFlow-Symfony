<?php

namespace App\Repository;

use App\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * @return Comment[]
     */
    public function findByPost(int $postId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.post = :postId')
            ->setParameter('postId', $postId)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Comment[]
     */
    public function findByFilters(string $search = '', ?string $postId = null, ?string $auteur = null, string $tri = 'createdAt', string $ordre = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.author', 'a')
            ->leftJoin('c.post', 'p');

        if ($search !== '') {
            $qb->andWhere('c.content LIKE :q OR a.nom LIKE :q OR a.prenom LIKE :q OR p.title LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        if ($postId !== null && $postId !== '') {
            $qb->andWhere('p.id = :postId')
               ->setParameter('postId', (int) $postId);
        }

        if ($auteur !== null && $auteur !== '') {
            $qb->andWhere('a.id = :auteurId')
               ->setParameter('auteurId', (int) $auteur);
        }

        $allowedTri = ['createdAt', 'content'];
        $triField = in_array($tri, $allowedTri) ? $tri : 'createdAt';
        $ordreField = strtoupper($ordre) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy('c.' . $triField, $ordreField);

        return $qb->getQuery()->getResult();
    }
}
