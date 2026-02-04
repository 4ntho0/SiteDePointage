<?php

namespace App\Repository;

use App\Entity\Pointage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PointageRepository extends ServiceEntityRepository {

    private const FORMAT_DATE_SQL = 'Y-m-d';
    private const CHAMP_DATE_POINTAGE = 'datePointage';
    private const CHAMP_UTILISATEUR = 'utilisateur';
    private const CHAMP_USERNAME = 'u.username';
    private const ORDRE_ASC = 'ASC';
    private const ORDRE_DESC = 'DESC';
    private const CONDITION_DATE_GE = 'p.datePointage >= :dateStart';
    private const CONDITION_DATE_LE = 'p.datePointage <= :dateEnd';
    private const CONDITION_DATE_BETWEEN = 'p.datePointage BETWEEN :start AND :end';
    private const CONDITION_USER_IN = 'u.username IN (:users)';
    private const CONDITION_USER_EQ = 'u.username = :username';

    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Pointage::class);
    }

    public function findAllOrderByField
    (string $champ, string $ordre, ?string $username = null, ?int $limit = null): array {
        $qb = $this->createQueryBuilder('p')
                ->join('p.utilisateur', 'u');

        if ($username) {
            $qb->where(self::CONDITION_USER_EQ)
                    ->setParameter('username', $username);
        }

        if ($champ === self::CHAMP_DATE_POINTAGE) {
            $qb->orderBy('p.datePointage', $ordre)
                    ->addOrderBy('p.heureEntree', $ordre);
        } elseif ($champ === self::CHAMP_UTILISATEUR) {
            $qb->orderBy(self::CHAMP_USERNAME, $ordre)
                    ->addOrderBy('p.datePointage', self::ORDRE_DESC)
                    ->addOrderBy('p.heureEntree', self::ORDRE_DESC);
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function getAllUsernames(): array {
        return $this->createQueryBuilder('p')
                        ->select('DISTINCT u.username')
                        ->join('p.utilisateur', 'u')
                        ->orderBy(self::CHAMP_USERNAME, self::ORDRE_ASC)
                        ->getQuery()
                        ->getResult();
    }

    public function remove(Pointage $pointage): void {
        $this->getEntityManager()->remove($pointage);
        $this->getEntityManager()->flush();
    }

    public function findAllOrderByFieldWithLimit(
            string $champ,
            string $ordre,
            ?string $userFilter = null,
            ?\DateTimeInterface $dateStart = null,
            ?\DateTimeInterface $dateEnd = null
    ): array {
        $qb = $this->createQueryBuilder('p')->join('p.utilisateur', 'u');

        if ($userFilter) {
            $users = explode(',', $userFilter);
            $qb->andWhere(self::CONDITION_USER_IN)
                    ->setParameter('users', $users);
        }

        if ($dateStart) {
            $qb->andWhere(self::CONDITION_DATE_GE)
                    ->setParameter('dateStart', $dateStart->format(self::FORMAT_DATE_SQL));
        }

        if ($dateEnd) {
            $qb->andWhere(self::CONDITION_DATE_LE)
                    ->setParameter('dateEnd', $dateEnd->format(self::FORMAT_DATE_SQL));
        }

        if ($champ === self::CHAMP_DATE_POINTAGE) {
            $qb->orderBy('p.datePointage', $ordre)
                    ->addOrderBy('p.heureEntree', $ordre);
        } elseif ($champ === self::CHAMP_UTILISATEUR) {
            $qb->orderBy(self::CHAMP_USERNAME, $ordre)
                    ->addOrderBy('p.datePointage', self::ORDRE_DESC)
                    ->addOrderBy('p.heureEntree', self::ORDRE_DESC);
        }

        return $qb->getQuery()->getResult();
    }

    public function countFiltered(
            ?string $userFilter = null,
            ?\DateTimeInterface $dateStart = null,
            ?\DateTimeInterface $dateEnd = null
    ): int {
        $qb = $this->createQueryBuilder('p')
                ->select('COUNT(p.id)');

        if ($userFilter) {
            $qb->join('p.utilisateur', 'u');
            $users = explode(',', $userFilter);
            $qb->andWhere(self::CONDITION_USER_IN)
                    ->setParameter('users', $users);
        }

        if ($dateStart) {
            $qb->andWhere(self::CONDITION_DATE_GE)
                    ->setParameter('dateStart', $dateStart->format(self::FORMAT_DATE_SQL));
        }

        if ($dateEnd) {
            $qb->andWhere(self::CONDITION_DATE_LE)
                    ->setParameter('dateEnd', $dateEnd->format(self::FORMAT_DATE_SQL));
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getRecapParUtilisateur(
            ?\DateTimeInterface $dateStart,
            ?\DateTimeInterface $dateEnd,
            ?string $userFilter = null
    ): array {
        $qb = $this->createQueryBuilder('p')
                ->join('p.utilisateur', 'u')
                ->addSelect('u')
                ->orderBy(self::CHAMP_USERNAME, self::ORDRE_ASC);

        if ($dateStart && $dateEnd) {
            $qb->where(self::CONDITION_DATE_BETWEEN)
                    ->setParameter('start', $dateStart->format(self::FORMAT_DATE_SQL))
                    ->setParameter('end', $dateEnd->format(self::FORMAT_DATE_SQL));
        }

        if ($userFilter && $userFilter !== 'all') {
            $userFilters = explode(',', $userFilter);
            $qb->andWhere(self::CONDITION_USER_IN)
                    ->setParameter('users', $userFilters);
        }

        $pointages = $qb->getQuery()->getResult();

        $result = [];
        foreach ($pointages as $pointage) {
            /** @var \App\Entity\Pointage $pointage */
            $user = $pointage->getUtilisateur();
            if (!$user) {
                continue;
            }

            $userId = $user->getId();
            if (!isset($result[$userId])) {
                $result[$userId] = [
                    'user' => $user,
                    'totalSeconds' => 0,
                    'joursTravailles' => [],
                ];
            }

            $seconds = $pointage->getTotalTravailSeconds();
            if ($seconds !== null) {
                $result[$userId]['totalSeconds'] += $seconds;
            }

            $datePointage = $pointage->getDatePointage();
            if ($datePointage) {
                $result[$userId]['joursTravailles'][] = $datePointage->format(self::FORMAT_DATE_SQL);
            }
        }

        foreach ($result as &$row) {
            $row['joursTravailles'] = count(array_unique($row['joursTravailles']));

            $seconds = $row['totalSeconds'];
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;

            $row['totalFormatted'] = sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }
        unset($row);

        return array_values($result);
    }
}
