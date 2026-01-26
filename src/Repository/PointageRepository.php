<?php

namespace App\Repository;

use App\Entity\Pointage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PointageRepository extends ServiceEntityRepository {

    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Pointage::class);
    }

    /**
     * Retourne tous les pointages triés, filtrés et limités
     *
     * @param string $champ : champ pour le tri ('datePointage' ou 'utilisateur')
     * @param string $ordre : 'ASC' ou 'DESC'
     * @param string|null $username : filtre par utilisateur
     * @param int|null $limit : nombre maximum de résultats
     * @return Pointage[]
     */
    public function findAllOrderByField(string $champ, string $ordre, ?string $username = null, ?int $limit = null): array {
        $qb = $this->createQueryBuilder('p')
                ->join('p.utilisateur', 'u');

        // Appliquer le filtre utilisateur si présent
        if ($username) {
            $qb->where('u.username = :username')
                    ->setParameter('username', $username);
        }

        // Tri
        if ($champ === 'datePointage') {
            $qb->orderBy('p.datePointage', $ordre)
                    ->addOrderBy('p.heureEntree', $ordre); // tri secondaire sur l'heure
        } elseif ($champ === 'utilisateur') {
            $qb->orderBy('u.username', $ordre)
                    ->addOrderBy('p.datePointage', 'DESC')
                    ->addOrderBy('p.heureEntree', 'DESC');
        }

        // Limiter le nombre de résultats si demandé
        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Retourne tous les noms d'utilisateurs uniques
     *
     * @return array
     */
    public function getAllUsernames(): array {
        return $this->createQueryBuilder('p')
                        ->select('DISTINCT u.username')
                        ->join('p.utilisateur', 'u')
                        ->orderBy('u.username', 'ASC')
                        ->getQuery()
                        ->getResult();
    }

    /**
     * Supprime un pointage
     */
    public function remove(Pointage $pointage): void {
        $this->getEntityManager()->remove($pointage);
        $this->getEntityManager()->flush();
    }


    /**
     * Récupère tous les pointages triés par champ avec filtres utilisateur et dates
     * @param string $champ
     * @param string $ordre
     * @param string|null $userFilter
     * @param \DateTimeInterface|null $dateStart
     * @param \DateTimeInterface|null $dateEnd
     * @return Pointage[]
     */
    public function findAllOrderByFieldWithLimit(
            string $champ, string $ordre,
            ?string $userFilter = null,
            ?\DateTimeInterface $dateStart = null,
            ?\DateTimeInterface $dateEnd = null
    ): array {
        $qb = $this->createQueryBuilder('p')->join('p.utilisateur', 'u');

        if ($userFilter) {
            $users = explode(',', $userFilter);
            $qb->andWhere('u.username IN (:users)')
                    ->setParameter('users', $users);
        }

        if ($dateStart) {
            $qb->andWhere('p.datePointage >= :dateStart')
                    ->setParameter('dateStart', $dateStart->format('Y-m-d'));
        }

        if ($dateEnd) {
            $qb->andWhere('p.datePointage <= :dateEnd')
                    ->setParameter('dateEnd', $dateEnd->format('Y-m-d'));
        }

        if ($champ === 'datePointage') {
            $qb->orderBy('p.datePointage', $ordre)
                    ->addOrderBy('p.heureEntree', $ordre);
        } elseif ($champ === 'utilisateur') {
            $qb->orderBy('u.username', $ordre)
                    ->addOrderBy('p.datePointage', 'DESC')
                    ->addOrderBy('p.heureEntree', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * renvoie le nombre total de pointages correspondant à des filtres
     * sur l’utilisateur
     * @param string|null $userFilter
     * @param \DateTimeInterface|null $dateStart
     * @param \DateTimeInterface|null $dateEnd
     * @return int
     */
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
            $qb->andWhere('u.username IN (:users)')
                    ->setParameter('users', $users);
        }

        if ($dateStart) {
            $qb->andWhere('p.datePointage >= :dateStart')
                    ->setParameter('dateStart', $dateStart->format('Y-m-d'));
        }

        if ($dateEnd) {
            $qb->andWhere('p.datePointage <= :dateEnd')
                    ->setParameter('dateEnd', $dateEnd->format('Y-m-d'));
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
