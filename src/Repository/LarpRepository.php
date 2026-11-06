<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Larp;
use App\Enum\LarpStatus;
use App\Enum\ProjectType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Larp>
 */
class LarpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Larp::class);
    }

    /**
     * Published LARPs grouped the way the public list shows them.
     *
     * @return array{current: list<Larp>, past: list<Larp>, initiatives: list<Larp>}
     */
    public function findPublishedSections(): array
    {
        /** @var list<Larp> $larps */
        $larps = $this->createQueryBuilder('l')
            ->addSelect('e')
            ->leftJoin('l.editions', 'e')
            ->andWhere('l.status != :draft')
            ->setParameter('draft', LarpStatus::Draft)
            ->orderBy('l.position', 'ASC')
            ->addOrderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();

        $sections = ['current' => [], 'past' => [], 'initiatives' => []];
        foreach ($larps as $larp) {
            $key = match (true) {
                ProjectType::Initiative === $larp->getProjectType() => 'initiatives',
                LarpStatus::Current === $larp->getStatus() => 'current',
                default => 'past',
            };
            $sections[$key][] = $larp;
        }

        // Most recent previous projects first.
        usort($sections['past'], static fn (Larp $a, Larp $b): int => ($b->getYearRange() ?? '') <=> ($a->getYearRange() ?? ''));

        return $sections;
    }

    public function findPublishedBySlug(string $slug): ?Larp
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.slug = :slug')
            ->andWhere('l.status != :draft')
            ->setParameter('slug', $slug)
            ->setParameter('draft', LarpStatus::Draft)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
