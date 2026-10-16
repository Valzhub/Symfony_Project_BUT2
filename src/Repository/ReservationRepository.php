<?php

namespace App\Repository;

// ReservationRepository.php — Contient les requêtes pour vérifier les disponibilités et trouver les véhicules réservables sur une période.


use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findAvailableVehicles(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?int $vehicleTypeId = null,
        ?int $minCapacity = null,
        array $featureIds = []
    ): array {
        $subQb = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(r.vehicle)')
            ->from('App\Entity\Reservation', 'r')
            ->where('r.startDate < :endDate')
            ->andWhere('r.endDate > :startDate');

        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->select('v')
            ->from('App\Entity\Vehicle', 'v')
            ->where($qb->expr()->notIn('v.id', $subQb->getDQL()))
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        if ($vehicleTypeId !== null) {
            $qb->andWhere('v.typeVehicle = :typeId')
               ->setParameter('typeId', $vehicleTypeId);
        }

        if ($minCapacity !== null) {
            $qb->andWhere('v.capacity >= :capacity')
               ->setParameter('capacity', $minCapacity);
        }

        if (!empty($featureIds)) {
            foreach ($featureIds as $i => $featureId) {
                $qb->andWhere(':feature' . $i . ' MEMBER OF v.features')
                   ->setParameter('feature' . $i, $featureId);
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function isVehicleAvailable(int $vehicleId, \DateTimeInterface $startDate, \DateTimeInterface $endDate): bool
    {
        $count = (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from('App\Entity\Reservation', 'r')
            ->where('r.vehicle = :vehicleId')
            ->andWhere('r.startDate < :endDate')
            ->andWhere('r.endDate > :startDate')
            ->setParameter('vehicleId', $vehicleId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getSingleScalarResult();

        return $count === 0;
    }
    public function findPastReservations(User $user, \DateTimeInterface $now): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.endDate < :now')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->orderBy('r.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findFutureReservations(User $user, \DateTimeInterface $now): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.startDate > :now')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->orderBy('r.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function isVehicleAvailableExcept(int $vehicleId, \DateTimeInterface $startDate, \DateTimeInterface $endDate, int $excludeId): bool
    {
        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.vehicle = :vehicleId')
            ->andWhere('r.startDate < :endDate')
            ->andWhere('r.endDate > :startDate')
            ->andWhere('r.id != :excludeId')
            ->setParameter('vehicleId', $vehicleId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('excludeId', $excludeId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count === 0;
    }
}