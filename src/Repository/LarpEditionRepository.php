<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LarpEdition;
use App\Enum\LarpStatus;
use App\Enum\ProjectType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LarpEdition>
 */
class LarpEditionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LarpEdition::class);
    }

    /**
     * Editions of published LARPs (not initiatives), each an event of its own,
     * split into upcoming (soonest first; undated ones last) and past (newest first).
     *
     * @return array{upcoming: list<LarpEdition>, past: list<LarpEdition>}
     */
    public function findPublishedEvents(?\DateTimeImmutable $today = null): array
    {
        /** @var list<LarpEdition> $editions */
        $editions = $this->createQueryBuilder('e')
            ->addSelect('l')
            ->join('e.larp', 'l')
            ->andWhere('l.status != :draft')
            ->andWhere('l.projectType != :initiative')
            ->setParameter('draft', LarpStatus::Draft)
            ->setParameter('initiative', ProjectType::Initiative)
            ->getQuery()
            ->getResult();

        $today ??= new \DateTimeImmutable('today');
        $events = ['upcoming' => [], 'past' => []];
        foreach ($editions as $edition) {
            $events[$edition->isPast($today) ? 'past' : 'upcoming'][] = $edition;
        }

        // Soonest first, undated last; past: most recent first.
        $key = static fn (LarpEdition $e): array => [null === $e->getStartDate(), $e->getStartDate(), $e->getLarp()?->getName()];
        usort($events['upcoming'], static fn (LarpEdition $a, LarpEdition $b): int => $key($a) <=> $key($b));
        usort($events['past'], static fn (LarpEdition $a, LarpEdition $b): int => $key($b) <=> $key($a));

        return $events;
    }
}
