<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum TagType: string implements TranslatableInterface
{
    case Trigger = 'trigger';
    case Gameplay = 'gameplay';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('tag_type.'.$this->value, [], 'enums', $locale);
    }
}
