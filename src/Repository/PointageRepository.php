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

    public function findAllOrderByFieldWithLimit(string $champ, string $ordre, ?string $username = null, ?int $limit = null, ?int $offset = null): array {
        $qb = $this->createQueryBuilder('p')
                ->join('p.utilisateur', 'u');

        if ($username) {
            $qb->where('u.username = :username')
                    ->setParameter('username', $username);
        }

        // Tri
        if ($champ === 'datePointage') {
            $qb->orderBy('p.datePointage', $ordre)
                    ->addOrderBy('p.heureEntree', $ordre);
        } elseif ($champ === 'utilisateur') {
            $qb->orderBy('u.username', $ordre)
                    ->addOrderBy('p.datePointage', 'DESC')
                    ->addOrderBy('p.heureEntree', 'DESC');
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }
}
