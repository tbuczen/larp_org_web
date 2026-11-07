<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * A LARP's own tag must not have the same name as a shared tag of the same type
 * (the shared one should be used instead, so preferences work across LARPs).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NotShadowingSharedTag extends Constraint
{
    public string $message = 'tag.shadows_shared';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
