<?php

namespace App\Repository;

use App\Entity\Candidature;
use App\Entity\CandidatureStatusHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CandidatureStatusHistory>
 */
class CandidatureStatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CandidatureStatusHistory::class);
    }

    /**
     * @return CandidatureStatusHistory[]
     */
    public function findByCandidatureOrdered(Candidature $candidature): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.candidature = :candidature')
            ->setParameter('candidature', $candidature)
            ->orderBy('h.changedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAcceptedTransition(Candidature $candidature): ?CandidatureStatusHistory
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.candidature = :candidature')
            ->andWhere('h.toStatus = :status')
            ->setParameter('candidature', $candidature)
            ->setParameter('status', 'Acceptée')
            ->orderBy('h.changedAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function computeAverageTimeToHireDays(): ?float
    {
        $rows = $this->createQueryBuilder('h')
            ->select('h.changedAt AS acceptedAt, c.createdAt AS createdAt')
            ->join('h.candidature', 'c')
            ->andWhere('h.toStatus = :status')
            ->setParameter('status', 'Acceptée')
            ->getQuery()
            ->getArrayResult();

        if (count($rows) === 0) {
            return null;
        }

        $sum = 0.0;
        foreach ($rows as $row) {
            $acceptedAt = $row['acceptedAt'];
            $createdAt = $row['createdAt'];

            if (!$acceptedAt instanceof \DateTimeInterface || !$createdAt instanceof \DateTimeInterface) {
                continue;
            }

            $diffSeconds = $acceptedAt->getTimestamp() - $createdAt->getTimestamp();
            if ($diffSeconds > 0) {
                $sum += $diffSeconds / 86400;
            }
        }

        return round($sum / max(1, count($rows)), 2);
    }
}
