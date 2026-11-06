<?php

declare(strict_types=1);

namespace App\Block\Type;

use App\Block\AbstractBlockType;
use App\Block\LarpChoicesTrait;
use App\Entity\PageBlock;
use App\Repository\LarpRepository;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/** Countdown to the start of a LARP's next edition. Hidden once it has started. */
final class CountdownBlock extends AbstractBlockType
{
    use LarpChoicesTrait;

    public function __construct(private readonly LarpRepository $larps)
    {
    }

    public static function getKey(): string
    {
        return 'countdown';
    }

    public function getIcon(): string
    {
        return 'fa fa-hourglass-half';
    }

    public function getDefaultSettings(): array
    {
        return ['title' => '', 'larp' => self::AUTO];
    }

    public function buildSettingsForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'block.title', 'required' => false, 'help' => 'block.default_help'])
            ->add('larp', ChoiceType::class, [
                'label' => 'block.countdown.larp',
                'choices' => $this->larpChoices($this->larps, 'block.auto_larp'),
            ]);
    }

    public function getContext(PageBlock $block, array $settings): array
    {
        $larp = $this->resolveLarp($this->larps, $settings['larp'] ?? self::AUTO);
        $edition = $larp?->getCurrentEdition();
        $start = $edition?->getStartDate();

        return [
            'larp' => $larp,
            'edition' => $edition,
            'start' => null !== $start && $start > new \DateTimeImmutable() ? $start : null,
        ];
    }
}
