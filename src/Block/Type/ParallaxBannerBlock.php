<?php

declare(strict_types=1);

namespace App\Block\Type;

use App\Block\AbstractBlockType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Full-width photo between sections, moving slower than the page (parallax), with
 * an optional quote or headline over it. Good for breaking up long pages.
 */
final class ParallaxBannerBlock extends AbstractBlockType
{
    public static function getKey(): string
    {
        return 'parallax_banner';
    }

    public function getIcon(): string
    {
        return 'fa fa-mountain-sun';
    }

    public function getDefaultSettings(): array
    {
        return ['image' => null, 'eyebrow' => '', 'title' => '', 'text' => '', 'button_label' => '', 'button_url' => '', 'height' => 'large', 'align' => 'center'];
    }

    /** @param FormBuilderInterface<array<string, mixed>|null> $builder */
    public function buildSettingsForm(FormBuilderInterface $builder): void
    {
        $this->addImage($builder, 'image', 'block.image', 'block.parallax_banner.image_help');
        $builder
            ->add('eyebrow', TextType::class, ['label' => 'block.parallax_banner.eyebrow', 'required' => false])
            ->add('title', TextType::class, ['label' => 'block.title', 'required' => false])
            ->add('text', TextareaType::class, ['label' => 'block.parallax_banner.text', 'required' => false])
            ->add('button_label', TextType::class, ['label' => 'block.button_label', 'required' => false])
            ->add('button_url', TextType::class, ['label' => 'block.button_url', 'required' => false])
            ->add('height', ChoiceType::class, [
                'label' => 'block.hero.height',
                'choices' => ['block.hero.height_full' => 'full', 'block.hero.height_large' => 'large', 'block.hero.height_medium' => 'medium'],
            ])
            ->add('align', ChoiceType::class, [
                'label' => 'block.parallax_banner.align',
                'choices' => ['block.parallax_banner.align_left' => 'left', 'block.parallax_banner.align_center' => 'center'],
            ]);
    }
}
