<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Larp;
use App\Entity\Tag;
use App\Enum\TagType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * Tags a character of this LARP may use: shared tags plus the LARP's own,
     * ordered by category position, then name.
     */
    public function availableForQuery(TagType $type, ?Larp $larp): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')
            ->andWhere('t.type = :type')
            ->setParameter('type', $type)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->addOrderBy('t.name', 'ASC');

        if (null === $larp) {
            return $qb->andWhere('t.larp IS NULL');
        }

        return $qb->andWhere('t.larp IS NULL OR t.larp = :larp')->setParameter('larp', $larp);
    }

    /** @return list<Tag> */
    public function findAvailableFor(TagType $type, ?Larp $larp): array
    {
        /* @var list<Tag> */
        return $this->availableForQuery($type, $larp)->getQuery()->getResult();
    }

    /** @return list<Tag> the LARP's own tags of one type */
    public function findOwnedBy(Larp $larp, TagType $type): array
    {
        /* @var list<Tag> */
        return $this->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')
            ->addSelect('c')
            ->andWhere('t.larp = :larp')
            ->andWhere('t.type = :type')
            ->setParameters(new ArrayCollection([
                new Parameter('larp', $larp),
                new Parameter('type', $type),
            ]))
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
