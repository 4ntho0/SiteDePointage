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
     * Retourne tous les pointages triés
     * @param string $champ : champ principal pour le tri ('datePointage' ou 'utilisateur')
     * @param string $ordre : 'ASC' ou 'DESC'
     * @return Pointage[]
     */
    public function findAllOrderBy(string $champ, string $ordre): array {
        $qb = $this->createQueryBuilder('p');

        if ($champ === 'datePointage') {
            $qb->orderBy('p.datePointage', $ordre)
                    ->addOrderBy('p.heureEntree', $ordre);
        } elseif ($champ === 'utilisateur') {
            $qb->join('p.utilisateur', 'u')
                    ->orderBy('u.username', $ordre)
                    ->addOrderBy('p.datePointage', $ordre)
                    ->addOrderBy('p.heureEntree', $ordre);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Supprime un pointage
     */
    public function remove(Pointage $pointage): void {
        $this->getEntityManager()->remove($pointage);
        $this->getEntityManager()->flush();
    }
}
