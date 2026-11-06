<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum CharacterCasting: string implements TranslatableInterface
{
    case Any = 'any';
    case Female = 'female';
    case Male = 'male';
    case NonBinary = 'non_binary';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('casting.'.$this->value, [], 'enums', $locale);
    }
}
