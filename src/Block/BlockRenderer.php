<?php

declare(strict_types=1);

namespace App\Block;

use App\Entity\PageBlock;
use Psr\Log\LoggerInterface;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * Renders page blocks: {{ render_block(block) }}.
 * The template gets "block", "settings" (defaults merged in) and the block type's context.
 */
final class BlockRenderer
{
    public function __construct(
        private readonly BlockTypeRegistry $types,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly bool $debug = false,
    ) {
    }

    #[AsTwigFunction('render_block', isSafe: ['html'])]
    public function render(PageBlock $block): string
    {
        $type = $this->types->get($block->getType());
        if (null === $type) {
            $this->logger->warning('Unknown block type "{type}" (block {id}).', ['type' => $block->getType(), 'id' => $block->getId()]);

            return '';
        }

        $settings = array_replace($type->getDefaultSettings(), $block->getSettings());

        try {
            return $this->twig->render($type->getTemplate(), [
                'block' => $block,
                'settings' => $settings,
                ...$type->getContext($block, $settings),
            ]);
        } catch (\Throwable $e) {
            // One broken block must not take the whole page down in production.
            if ($this->debug) {
                throw $e;
            }
            $this->logger->error('Block {id} ({type}) failed to render: {message}', ['id' => $block->getId(), 'type' => $block->getType(), 'message' => $e->getMessage(), 'exception' => $e]);

            return '';
        }
    }
}
