<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps the admin panel, the login page and live-component updates out of search
 * results even when something links to them (robots.txt only stops crawling,
 * not indexing).
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class NoIndexListener
{
    private const PREFIXES = ['/admin', '/login', '/_components'];

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $path = $event->getRequest()->getPathInfo();
        foreach (self::PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                $event->getResponse()->headers->set('X-Robots-Tag', 'noindex, nofollow');

                return;
            }
        }
    }
}
