<?php

namespace App\Repository;

use App\Entity\Categorie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CategorieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Categorie::class);
    }

    public function findByFilters(
        string $search = '',
        string $tri = 'id',
        string $ordre = 'DESC'
    ): array {
        $qb = $this->createQueryBuilder('c');

        if ($search !== '') {
            $qb->andWhere('c.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        $allowedTri = ['id', 'nom'];
        $triColumn = in_array($tri, $allowedTri, true) ? $tri : 'id';
        $ordreDir = strtoupper($ordre) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy('c.' . $triColumn, $ordreDir);

        return $qb->getQuery()->getResult();
    }
}
