<?php

declare(strict_types=1);

namespace App\Block\Type;

use App\Block\AbstractBlockType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\TextEditorType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/** Image beside text, e.g. "About us" with a group photo. */
final class ImageTextBlock extends AbstractBlockType
{
    public static function getKey(): string
    {
        return 'image_text';
    }

    public function getIcon(): string
    {
        return 'fa fa-image';
    }

    public function getDefaultSettings(): array
    {
        return ['title' => '', 'content' => '', 'image' => null, 'image_alt' => '', 'image_side' => 'left', 'button_label' => '', 'button_url' => ''];
    }

    /** @param FormBuilderInterface<array<string, mixed>|null> $builder */
    public function buildSettingsForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'block.title', 'required' => false])
            ->add('content', TextEditorType::class, ['label' => 'block.content', 'required' => false]);
        $this->addImage($builder, 'image', 'block.image');
        $builder
            ->add('image_alt', TextType::class, ['label' => 'block.image_alt', 'required' => false, 'help' => 'block.image_alt_help'])
            ->add('image_side', ChoiceType::class, [
                'label' => 'block.image_text.side',
                'choices' => ['block.image_text.side_left' => 'left', 'block.image_text.side_right' => 'right'],
            ])
            ->add('button_label', TextType::class, ['label' => 'block.button_label', 'required' => false])
            ->add('button_url', TextType::class, ['label' => 'block.button_url', 'required' => false]);
    }
}
