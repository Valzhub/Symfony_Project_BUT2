<?php

namespace App\Purger;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\Purger\ORMPurgerInterface;
use Doctrine\ORM\EntityManagerInterface;

class TruncatePurger implements ORMPurgerInterface
{

    private readonly ORMPurgerInterface $ormPurger;

    public function __construct(?EntityManagerInterface $entityManager = null, array $excluded = [])
    {
        $this->ormPurger = new ORMPurger($entityManager, $excluded);
    }

    public function setEntityManager(EntityManagerInterface $em): void
    {
        $this->ormPurger->setEntityManager($em);
    }

    public function purge(): void
    {
        $this->ormPurger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);
        $connection = $this->ormPurger->getObjectManager()->getConnection();

        $pdo = $connection->getNativeConnection();

        $wasInTransaction = $pdo->inTransaction();

        // Désactiver les triggers (contraintes FK avec PostgreSQL) pour toutes les tables
        $connection->executeStatement("SET session_replication_role = 'replica';");

        // Effectue la purge
        $this->ormPurger->purge();

        // Récupère et reset toutes les séquences associées à des colonnes SERIAL/IDENTITY
        $sqlSeq = "
        SELECT sequence_name 
        FROM information_schema.sequences 
        WHERE sequence_schema = 'public'
        AND sequence_name LIKE '%_seq'
        ";
        $sequences = $connection->fetchAllAssociative($sqlSeq);

        foreach ($sequences as $sequence) {
            $sequenceName = $sequence['sequence_name'];
            $connection->executeStatement(sprintf('ALTER SEQUENCE "%s" RESTART WITH 1', $sequenceName));
        }

        // Réactive triggers
        $connection->executeStatement("SET session_replication_role = 'origin';");

        if ($wasInTransaction && !$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
    }
}
