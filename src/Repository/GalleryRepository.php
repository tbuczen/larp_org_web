<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Gallery;
use App\Entity\Larp;
use App\Entity\LarpEdition;
use App\Enum\LarpStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Gallery>
 */
class GalleryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gallery::class);
    }

    /** @return list<Gallery> published galleries, newest event first */
    public function findVisible(?Larp $larp = null): array
    {
        $qb = $this->visible();
        if (null !== $larp) {
            $qb->andWhere('g.larp = :larp')->setParameter('larp', $larp);
        }

        /* @var list<Gallery> */
        return $qb->getQuery()->getResult();
    }

    /** @return list<Gallery> published galleries of one edition */
    public function findVisibleForEdition(LarpEdition $edition): array
    {
        /* @var list<Gallery> */
        return $this->visible()->andWhere('g.edition = :edition')->setParameter('edition', $edition)->getQuery()->getResult();
    }

    public function findVisibleBySlug(string $slug): ?Gallery
    {
        /* @var Gallery|null */
        return $this->visible()->andWhere('g.slug = :slug')->setParameter('slug', $slug)->getQuery()->getOneOrNullResult();
    }

    private function visible(): QueryBuilder
    {
        return $this->createQueryBuilder('g')
            ->addSelect('l', 'e')
            ->leftJoin('g.larp', 'l')
            ->leftJoin('g.edition', 'e')
            ->andWhere('g.published = true')
            ->andWhere('l.id IS NULL OR l.status != :draft')
            ->setParameter('draft', LarpStatus::Draft)
            ->addSelect('COALESCE(g.eventDate, e.startDate) AS HIDDEN eventDay')
            ->orderBy('eventDay', 'DESC')
            ->addOrderBy('g.id', 'DESC');
    }
}
