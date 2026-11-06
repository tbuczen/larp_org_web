<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum LarpStatus: string implements TranslatableInterface
{
    case Draft = 'draft';
    case Current = 'current';
    case Past = 'past';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('larp_status.'.$this->value, [], 'enums', $locale);
    }
}
