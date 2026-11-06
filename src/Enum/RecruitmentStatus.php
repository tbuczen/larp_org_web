<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum RecruitmentStatus: string implements TranslatableInterface
{
    case NotStarted = 'not_started';
    case Open = 'open';
    case Closed = 'closed';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('recruitment.'.$this->value, [], 'enums', $locale);
    }
}
