<?php

declare(strict_types=1);

namespace App\Block\Type;

use App\Block\AbstractBlockType;
use App\Entity\Larp;
use App\Entity\PageBlock;
use App\Repository\LarpRepository;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/** LARP cards: current, previous, initiatives or featured. */
final class LarpShowcaseBlock extends AbstractBlockType
{
    public function __construct(private readonly LarpRepository $larps)
    {
    }

    public static function getKey(): string
    {
        return 'larp_showcase';
    }

    public function getIcon(): string
    {
        return 'fa fa-dragon';
    }

    public function getDefaultSettings(): array
    {
        return ['title' => '', 'intro' => '', 'mode' => 'current', 'limit' => 6];
    }

    /** @param FormBuilderInterface<array<string, mixed>|null> $builder */
    public function buildSettingsForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'block.title', 'required' => false])
            ->add('intro', TextareaType::class, ['label' => 'block.intro', 'required' => false])
            ->add('mode', ChoiceType::class, [
                'label' => 'block.larp_showcase.mode',
                'choices' => [
                    'block.larp_showcase.mode_current' => 'current',
                    'block.larp_showcase.mode_past' => 'past',
                    'block.larp_showcase.mode_initiatives' => 'initiatives',
                    'block.larp_showcase.mode_featured' => 'featured',
                ],
            ])
            ->add('limit', IntegerType::class, ['label' => 'block.limit', 'attr' => ['min' => 1, 'max' => 24]]);
    }

    public function getContext(PageBlock $block, array $settings): array
    {
        $sections = $this->larps->findPublishedSections();
        $mode = (string) ($settings['mode'] ?? 'current');
        $larps = match ($mode) {
            'past' => $sections['past'],
            'initiatives' => $sections['initiatives'],
            'featured' => array_values(array_filter(array_merge($sections['current'], $sections['past']), static fn (Larp $l): bool => $l->isFeatured())),
            default => $sections['current'],
        };

        return ['larps' => \array_slice($larps, 0, max(1, (int) ($settings['limit'] ?? 6)))];
    }
}
