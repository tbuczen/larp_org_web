<?php

declare(strict_types=1);

namespace App\Block\Type;

use App\Block\AbstractBlockType;
use App\Block\LarpChoicesTrait;
use App\Entity\LarpEdition;
use App\Entity\PageBlock;
use App\Repository\LarpEditionRepository;
use App\Repository\LarpRepository;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * "Next LARP" widget: a countdown to the start of an edition, with a small month
 * calendar marking its days, and links to the edition, its calendar file and its
 * Facebook event. By default it picks the soonest upcoming edition of any LARP.
 * With nothing upcoming it is hidden, or shows the "no date yet" text if set.
 */
final class CountdownBlock extends AbstractBlockType
{
    use LarpChoicesTrait;

    /** The soonest upcoming edition of any LARP. */
    public const NEXT = 'next';

    public function __construct(
        private readonly LarpRepository $larps,
        private readonly LarpEditionRepository $editions,
    ) {
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
        return ['title' => '', 'larp' => self::NEXT, 'show_calendar' => true, 'empty_text' => ''];
    }

    /** @param FormBuilderInterface<array<string, mixed>|null> $builder */
    public function buildSettingsForm(FormBuilderInterface $builder): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'block.title', 'required' => false, 'help' => 'block.countdown.title_help'])
            ->add('larp', ChoiceType::class, [
                'label' => 'block.countdown.larp',
                'choices' => ['block.countdown.next' => self::NEXT] + $this->larpChoices($this->larps, 'block.auto_larp'),
                'help' => 'block.countdown.larp_help',
            ])
            ->add('show_calendar', CheckboxType::class, ['label' => 'block.countdown.show_calendar', 'required' => false])
            ->add('empty_text', TextType::class, ['label' => 'block.countdown.empty_text', 'required' => false, 'help' => 'block.countdown.empty_text_help']);
    }

    public function getContext(PageBlock $block, array $settings): array
    {
        $today = new \DateTimeImmutable('today');
        $edition = $this->findEdition($settings['larp'] ?? self::NEXT, $today);
        $start = $edition?->getStartDate();

        return [
            'larp' => $edition?->getLarp(),
            'edition' => $edition,
            'start' => null !== $start && $start > new \DateTimeImmutable() ? $start : null,
            'months' => null !== $edition && ($settings['show_calendar'] ?? true) ? self::calendar($edition, $today) : [],
        ];
    }

    private function findEdition(mixed $choice, \DateTimeImmutable $today): ?LarpEdition
    {
        if (self::NEXT === $choice) {
            foreach ($this->editions->findPublishedEvents($today)['upcoming'] as $edition) {
                if (null !== $edition->getStartDate() && $edition->getStartDate() >= $today) {
                    return $edition;
                }
            }

            return null;
        }
        $edition = $this->resolveLarp($this->larps, $choice)?->getCurrentEdition();

        return null !== $edition && !$edition->isPast($today) && null !== $edition->getStartDate() ? $edition : null;
    }

    /**
     * Month grids (Monday first) covering the edition: usually one month, two when
     * it crosses a month end.
     *
     * @return list<array{label: string, weeks: list<list<array{day: int, event: bool, start: bool, end: bool, today: bool}|null>>}>
     */
    public static function calendar(LarpEdition $edition, \DateTimeImmutable $today): array
    {
        $start = $edition->getStartDate();
        if (null === $start) {
            return [];
        }
        $end = $edition->getEndDate() ?? $start;
        $formatter = new \IntlDateFormatter('pl_PL', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'LLLL yyyy');

        $months = [];
        $month = $start->modify('first day of this month');
        $last = $end->modify('first day of this month');
        for ($n = 0; $month <= $last && $n < 2; ++$n, $month = $month->modify('+1 month')) {
            $weeks = [];
            $week = array_fill(0, (int) $month->format('N') - 1, null);
            $days = (int) $month->format('t');
            for ($d = 1; $d <= $days; ++$d) {
                $date = $month->setDate((int) $month->format('Y'), (int) $month->format('n'), $d);
                $key = $date->format('Y-m-d');
                $week[] = [
                    'day' => $d,
                    'event' => $key >= $start->format('Y-m-d') && $key <= $end->format('Y-m-d'),
                    'start' => $key === $start->format('Y-m-d'),
                    'end' => $key === $end->format('Y-m-d'),
                    'today' => $key === $today->format('Y-m-d'),
                ];
                if (7 === \count($week)) {
                    $weeks[] = $week;
                    $week = [];
                }
            }
            if ([] !== $week) {
                $weeks[] = array_pad($week, 7, null);
            }
            $months[] = ['label' => (string) $formatter->format($month), 'weeks' => $weeks];
        }

        return $months;
    }
}
