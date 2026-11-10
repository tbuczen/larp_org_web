<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Character;
use App\Entity\Larp;
use App\Entity\LarpEdition;
use App\Enum\CharacterAvailability;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Character>
 */
class CharacterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Character::class);
    }

    /**
     * Visible (not hidden) characters of an edition with their tags, in one query.
     *
     * @return list<Character>
     */
    public function findVisibleForEdition(LarpEdition $edition): array
    {
        /* @var list<Character> */
        return $this->createQueryBuilder('c')
            ->addSelect('ct', 't', 'tc')
            ->leftJoin('c.characterTags', 'ct')
            ->leftJoin('ct.tag', 't')
            ->leftJoin('t.category', 'tc')
            ->andWhere('c.edition = :edition')
            ->andWhere('c.availability != :hidden')
            ->setParameter('edition', $edition)
            ->setParameter('hidden', CharacterAvailability::Hidden)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Visible characters of several editions with their tags and LARP, in one query.
     *
     * @param list<LarpEdition> $editions
     *
     * @return list<Character>
     */
    public function findVisibleForEditions(array $editions): array
    {
        if ([] === $editions) {
            return [];
        }

        /* @var list<Character> */
        return $this->createQueryBuilder('c')
            ->addSelect('e', 'l', 'ct', 't', 'tc')
            ->join('c.edition', 'e')
            ->join('e.larp', 'l')
            ->leftJoin('c.characterTags', 'ct')
            ->leftJoin('ct.tag', 't')
            ->leftJoin('t.category', 'tc')
            ->andWhere('c.edition IN (:editions)')
            ->andWhere('c.availability != :hidden')
            ->setParameter('editions', $editions)
            ->setParameter('hidden', CharacterAvailability::Hidden)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Number of visible characters per edition of a LARP.
     *
     * @return array<int, int> edition id => count
     */
    public function countVisibleByEdition(Larp $larp): array
    {
        /** @var list<array{edition: int|string, n: int|string}> $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.edition) AS edition', 'COUNT(c.id) AS n')
            ->join('c.edition', 'e')
            ->andWhere('e.larp = :larp')
            ->andWhere('c.availability != :hidden')
            ->setParameter('larp', $larp)
            ->setParameter('hidden', CharacterAvailability::Hidden)
            ->groupBy('c.edition')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['edition']] = (int) $row['n'];
        }

        return $counts;
    }

    public function findVisibleBySlug(LarpEdition $edition, string $slug): ?Character
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.edition = :edition')
            ->andWhere('c.slug = :slug')
            ->andWhere('c.availability != :hidden')
            ->setParameter('edition', $edition)
            ->setParameter('slug', $slug)
            ->setParameter('hidden', CharacterAvailability::Hidden)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
