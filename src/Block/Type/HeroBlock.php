<?php

declare(strict_types=1);

namespace App\Block\Type;

use App\Block\AbstractBlockType;
use App\Block\LarpChoicesTrait;
use App\Entity\PageBlock;
use App\Repository\LarpRepository;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Large opening section: background image, headline, buttons. Can feature a LARP
 * (its cover, dates, recruitment status and a link to its characters).
 */
final class HeroBlock extends AbstractBlockType
{
    use LarpChoicesTrait;

    public function __construct(private readonly LarpRepository $larps)
    {
    }

    public static function getKey(): string
    {
        return 'hero';
    }

    public function getIcon(): string
    {
        return 'fa fa-panorama';
    }

    public function getDefaultSettings(): array
    {
        return ['title' => '', 'subtitle' => '', 'larp' => '', 'image' => null, 'primary_label' => '', 'primary_url' => '', 'secondary_label' => '', 'secondary_url' => '', 'height' => 'large'];
    }

    public function buildSettingsForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'block.hero.title', 'required' => false, 'help' => 'block.hero.title_help'])
            ->add('subtitle', TextareaType::class, ['label' => 'block.hero.subtitle', 'required' => false])
            ->add('larp', ChoiceType::class, [
                'label' => 'block.hero.larp',
                'required' => false,
                'placeholder' => 'block.none',
                'choices' => $this->larpChoices($this->larps, 'block.auto_larp'),
                'help' => 'block.hero.larp_help',
            ]);
        $this->addImage($builder, 'image', 'block.hero.image', 'block.hero.image_help');
        $builder
            ->add('primary_label', TextType::class, ['label' => 'block.button_label', 'required' => false])
            ->add('primary_url', TextType::class, ['label' => 'block.button_url', 'required' => false])
            ->add('secondary_label', TextType::class, ['label' => 'block.hero.secondary_label', 'required' => false])
            ->add('secondary_url', TextType::class, ['label' => 'block.hero.secondary_url', 'required' => false])
            ->add('height', ChoiceType::class, [
                'label' => 'block.hero.height',
                'choices' => ['block.hero.height_full' => 'full', 'block.hero.height_large' => 'large', 'block.hero.height_medium' => 'medium'],
            ]);
    }

    public function getContext(PageBlock $block, array $settings): array
    {
        $larp = ($settings['larp'] ?? '') === '' ? null : $this->resolveLarp($this->larps, $settings['larp']);

        return ['larp' => $larp, 'edition' => $larp?->getCurrentEdition()];
    }
}
