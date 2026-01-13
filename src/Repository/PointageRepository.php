<?php

namespace App\Repository;

use App\Entity\Pointage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pointage>
 */
class PointageRepository extends ServiceEntityRepository {

    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Pointage::class);
    }

    /**
     * Tri les pointage par date
     * @param string $field
     * @param string $order
     * @return type
     */
    public function findAllOrderBy(string $field, string $order) {
        return $this->createQueryBuilder('p')
                        ->orderBy('p.' . $field, $order)
                        ->getQuery()
                        ->getResult();
    }

    /**
     * Supprime un pointage
     * @param Pointage $pointage
     * @return void
     */
    public function remove(Pointage $pointage):void
    {
        $this->getEntityManager()->remove($pointage);
        $this->getEntityManager()->flush();
    }
}
