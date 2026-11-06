<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum CharacterAvailability: string implements TranslatableInterface
{
    case Available = 'available';
    case Taken = 'taken';
    case Hidden = 'hidden';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('availability.'.$this->value, [], 'enums', $locale);
    }
}
