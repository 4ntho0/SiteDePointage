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
     * Retourne tous les pointages triés et filtrés
     *
     * @param string $champ : champ pour le tri ('datePointage' ou 'utilisateur')
     * @param string $ordre : 'ASC' ou 'DESC'
     * @param string|null $username : filtre par utilisateur
     * @return Pointage[]
     */
    public function findAllOrderByField(string $champ, string $ordre, ?string $username = null): array {
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
                    ->addOrderBy('p.datePointage', 'DESC')   // tri secondaire pour cohérence
                    ->addOrderBy('p.heureEntree', 'DESC');   // tri secondaire
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
}
