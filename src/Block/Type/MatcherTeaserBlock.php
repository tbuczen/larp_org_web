<?php

declare(strict_types=1);

namespace App\Block\Type;

use App\Block\AbstractBlockType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/** Invitation to the character matcher (/postacie). */
final class MatcherTeaserBlock extends AbstractBlockType
{
    public static function getKey(): string
    {
        return 'matcher_teaser';
    }

    public function getIcon(): string
    {
        return 'fa fa-masks-theater';
    }

    public function getDefaultSettings(): array
    {
        return ['title' => '', 'text' => '', 'button_label' => ''];
    }

    /** @param FormBuilderInterface<array<string, mixed>|null> $builder */
    public function buildSettingsForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'block.title', 'required' => false, 'help' => 'block.default_help'])
            ->add('text', TextareaType::class, ['label' => 'block.text', 'required' => false])
            ->add('button_label', TextType::class, ['label' => 'block.button_label', 'required' => false]);
    }
}
