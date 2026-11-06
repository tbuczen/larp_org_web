<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ProjectType: string implements TranslatableInterface
{
    case Larp = 'larp';
    case Initiative = 'initiative';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('project_type.'.$this->value, [], 'enums', $locale);
    }
}
