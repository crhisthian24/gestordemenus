<?php

namespace App\Repository;

use App\Entity\ArchivoLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ArchivoLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArchivoLog::class);
    }

    public function findByEmpresaYArchivo(string $empresa, string $archivo): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.empresa = :empresa')
            ->andWhere('a.archivoOriginal = :archivo')
            ->setParameter('empresa', $empresa)
            ->setParameter('archivo', $archivo)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByEmpresa(string $empresa): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}