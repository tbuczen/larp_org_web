<?php

declare(strict_types=1);

namespace App\Block\Type;

use App\Block\AbstractBlockType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/** Call-to-action banner, e.g. "Recruitment is open!". */
final class CtaBlock extends AbstractBlockType
{
    public static function getKey(): string
    {
        return 'cta';
    }

    public function getIcon(): string
    {
        return 'fa fa-bullhorn';
    }

    public function getDefaultSettings(): array
    {
        return ['title' => '', 'text' => '', 'button_label' => '', 'button_url' => '', 'style' => 'primary'];
    }

    public function buildSettingsForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'block.title'])
            ->add('text', TextareaType::class, ['label' => 'block.text', 'required' => false])
            ->add('button_label', TextType::class, ['label' => 'block.button_label', 'required' => false])
            ->add('button_url', TextType::class, ['label' => 'block.button_url', 'required' => false])
            ->add('style', ChoiceType::class, [
                'label' => 'block.cta.style',
                'choices' => ['block.cta.style_primary' => 'primary', 'block.cta.style_accent' => 'accent'],
            ]);
    }
}
