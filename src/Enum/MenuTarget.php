<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** What a menu item links to. */
enum MenuTarget: string implements TranslatableInterface
{
    case Page = 'page';
    case Larp = 'larp';
    case Route = 'route';
    case Url = 'url';
    case None = 'none';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('menu_target.'.$this->value, [], 'enums', $locale);
    }
}
