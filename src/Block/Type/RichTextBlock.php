<?php

declare(strict_types=1);

namespace App\Block\Type;

use App\Block\AbstractBlockType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\TextEditorType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class RichTextBlock extends AbstractBlockType
{
    public static function getKey(): string
    {
        return 'rich_text';
    }

    public function getIcon(): string
    {
        return 'fa fa-align-left';
    }

    public function getDefaultSettings(): array
    {
        return ['title' => '', 'content' => '', 'width' => 'narrow'];
    }

    /** @param FormBuilderInterface<array<string, mixed>|null> $builder */
    public function buildSettingsForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'block.title', 'required' => false])
            ->add('content', TextEditorType::class, ['label' => 'block.content', 'required' => false])
            ->add('width', ChoiceType::class, [
                'label' => 'block.width',
                'choices' => ['block.width_narrow' => 'narrow', 'block.width_wide' => 'wide'],
            ]);
    }
}
