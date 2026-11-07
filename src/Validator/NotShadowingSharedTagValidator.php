<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\Tag;
use App\Repository\TagRepository;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class NotShadowingSharedTagValidator extends ConstraintValidator
{
    public function __construct(
        private readonly TagRepository $tags,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotShadowingSharedTag) {
            throw new UnexpectedTypeException($constraint, NotShadowingSharedTag::class);
        }
        if (!$value instanceof Tag || $value->isShared() || '' === trim($value->getName())) {
            return;
        }

        $slug = $value->getSlug() ?: $this->slugger->slug($value->getName())->lower()->toString();
        $shared = $this->tags->findOneBy(['type' => $value->getType(), 'slug' => $slug, 'larp' => null]);
        if (null !== $shared) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('%tag%', $shared->getName())
                ->atPath('name')
                ->addViolation();
        }
    }
}
