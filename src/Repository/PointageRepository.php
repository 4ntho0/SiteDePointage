<?php

namespace App\Repository;

use App\Entity\Pointage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pointage>
 */
class PointageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pointage::class);
    }
public function findAllOrderBy(string $field, string $order = 'ASC')
{
    return $this->createQueryBuilder('p')
        ->orderBy('p.' . $field, $order)
        ->getQuery()
        ->getResult();
}
}
