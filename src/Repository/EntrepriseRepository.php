<?php

namespace App\Repository;

use App\Entity\Entreprise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EntrepriseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entreprise::class);
    }

    public function findByFilters(
        string $search = '',
        ?string $secteur = null,
        string $tri = 'id',
        string $ordre = 'DESC',
        $entreprise = null
    ): array {
        $qb = $this->createQueryBuilder('e');

        if ($search !== '') {
            $qb->andWhere('e.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($secteur !== null && $secteur !== '') {
            $qb->andWhere('e.secteur = :secteur')
               ->setParameter('secteur', $secteur);
        }

        if ($entreprise !== null) {
            $qb->andWhere('e.id = :entrepriseId')
               ->setParameter('entrepriseId', $entreprise->getId());
        }

        $allowedTri = ['id', 'nom', 'secteur'];
        $triColumn = in_array($tri, $allowedTri, true) ? $tri : 'id';
        $ordreDir = strtoupper($ordre) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy('e.' . $triColumn, $ordreDir);

        return $qb->getQuery()->getResult();
    }

    public function findDistinctSecteurs(): array
    {
        return $this->createQueryBuilder('e')
            ->select('DISTINCT e.secteur')
            ->where('e.secteur IS NOT NULL')
            ->orderBy('e.secteur', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }
}
