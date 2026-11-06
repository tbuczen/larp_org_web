<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\SluggableInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Fills in an empty slug from the entity's name. Uniqueness is checked by
 * validation (UniqueEntity) on each entity, so admins see a readable error.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class SlugListener
{
    public function __construct(private readonly SluggerInterface $slugger)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->fill($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof SluggableInterface && $this->fill($entity)) {
            $args->setNewValue('slug', $entity->getSlug());
        }
    }

    private function fill(object $entity): bool
    {
        if (!$entity instanceof SluggableInterface || '' !== (string) $entity->getSlug()) {
            return false;
        }

        $entity->setSlug($this->slugger->slug($entity->getSlugSource())->lower()->toString());

        return true;
    }
}
