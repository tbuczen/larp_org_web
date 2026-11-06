<?php

declare(strict_types=1);

namespace App\Block\Type;

use App\Block\AbstractBlockType;
use App\Entity\PageBlock;
use App\Repository\SiteSettingsRepository;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/** Social media buttons and contact e-mail from Site settings ("join us" / contact). */
final class SocialLinksBlock extends AbstractBlockType
{
    public function __construct(private readonly SiteSettingsRepository $settings)
    {
    }

    public static function getKey(): string
    {
        return 'social_links';
    }

    public function getIcon(): string
    {
        return 'fa fa-share-nodes';
    }

    public function getDefaultSettings(): array
    {
        return ['title' => '', 'text' => '', 'show_email' => true];
    }

    public function buildSettingsForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'block.title', 'required' => false])
            ->add('text', TextareaType::class, ['label' => 'block.text', 'required' => false])
            ->add('show_email', CheckboxType::class, ['label' => 'block.social_links.show_email', 'required' => false]);
    }

    public function getContext(PageBlock $block, array $settings): array
    {
        return ['site' => $this->settings->get()];
    }
}
