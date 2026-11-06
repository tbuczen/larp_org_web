<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Character;
use App\Entity\Larp;
use App\Entity\LarpEdition;
use App\Entity\Tag;
use App\Entity\TagCategory;
use App\Enum\CharacterAvailability;
use App\Enum\CharacterCasting;
use App\Enum\LarpStatus;
use App\Enum\ProjectType;
use App\Enum\RecruitmentStatus;
use App\Enum\TagType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * LARPs from the current Las Tajemnic site (issue #18), plus a tag dictionary
 * based on the categories used by zapisy.krak-on.info.
 *
 * The characters and their tags are invented demo data.
 */
class LarpFixtures extends Fixture
{
    /** Trigger categories and their tags. */
    private const TRIGGERS = [
        'Śmierć i żałoba' => ['Śmierć', 'Żałoba', 'Śmierć bliskiej osoby', 'Samobójstwo'],
        'Przemoc' => ['Przemoc fizyczna', 'Przemoc psychiczna', 'Tortury', 'Broń palna', 'Gore'],
        'Wojna' => ['Wojna', 'Ludobójstwo', 'Represje polityczne'],
        'Zdrowie psychiczne i trauma' => ['Trauma / PTSD', 'Kryzys psychiczny', 'Szaleństwo', 'Uzależnienia'],
        'Relacje i zdrada' => ['Zdrada', 'Toksyczne relacje', 'Manipulacja', 'Porzucenie / odrzucenie'],
        'Dyskryminacja' => ['Rasizm', 'Seksizm', 'Dehumanizacja'],
        'Substancje' => ['Alkohol / alkoholizm', 'Narkotyki / używki'],
        'Treści seksualne i cielesne' => ['Bliskość fizyczna / dotyk', 'Treści erotyczne / zmysłowe'],
        'Religia i nadprzyrodzone' => ['Religia', 'Okultyzm', 'Opętanie'],
        'Ciemność i doznania sensoryczne' => ['Ciemność', 'Krzyk', 'Głośne dźwięki'],
    ];

    /** Gameplay categories and their tags. */
    private const GAMEPLAY = [
        'Emocje i relacje' => ['Silne emocje / dramat', 'Wątki romantyczne', 'Relacje i więzi', 'Trudne wybory / dylematy moralne'],
        'Intryga i tajemnica' => ['Polityka / intryga', 'Tajemnica / śledztwo', 'Handel i negocjacje'],
        'Akcja' => ['Konflikt / walka', 'Napięcie / akcja', 'Przetrwanie'],
        'Klimat' => ['Groza / horror', 'Komedia', 'Refleksja / filozofia', 'Duchowość / religia', 'Folklor / obrzędy'],
    ];

    /** @var array<string, Tag> */
    private array $tags = [];

    public function load(ObjectManager $manager): void
    {
        $this->loadTags($manager, TagType::Trigger, self::TRIGGERS);
        $this->loadTags($manager, TagType::Gameplay, self::GAMEPLAY);

        // Current LARPs
        $cybercrush = $this->larp($manager, 'Cybercrush', LarpStatus::Current, 'Larp chamber', 'Shadowrun',
            'Larp chamber inspirowany uniwersum Shadowrun.', 1);
        $cyberEdition = $this->edition($cybercrush, '1. edycja', '2026-07-17', '2026-07-18', 'Dąbrowa Górnicza', RecruitmentStatus::Closed);

        $zwoje = $this->larp($manager, 'Pradawne Zwoje', LarpStatus::Current, 'Larp terenowy', 'Skyrim',
            'Larp terenowy w świecie Skyrim.', 2)->setFeatured(true);
        $this->edition($zwoje, '1. edycja', '2024-08-08', '2024-08-11', 'Czyżowice', RecruitmentStatus::Closed);
        $this->edition($zwoje, '2. edycja', '2025-08-07', '2025-08-10', 'Czyżowice', RecruitmentStatus::Closed);
        $zwojeEdition = $this->edition($zwoje, '3. edycja', '2026-08-06', '2026-08-09', 'Czyżowice', RecruitmentStatus::Open)
            ->setRegistrationUrl('https://fb.me/e/69mffUNUK');

        // Previous LARPs
        $vault = $this->larp($manager, 'Vault 47', LarpStatus::Past, 'Larp terenowy', 'Fallout',
            'Larp w świecie Fallouta, przedstawiający historię mieszkańców Krypty 47, którzy dzięki mocy GECKa stworzyli niepowtarzalne miejsce, ściągając na siebie uwagę całego rejonu.', 3);
        foreach (['2023', '2024', '2025'] as $i => $year) {
            $this->edition($vault, ($i + 1).'. edycja', $year.'-06-01', null, null, RecruitmentStatus::Closed);
        }

        $pogarda = $this->larp($manager, 'Czasy Pogardy', LarpStatus::Past, null, 'Wiedźmin',
            'Larp inspirowany światem Wiedźmina.', 4);
        $this->edition($pogarda, '1. edycja', '2023-09-01', null, null, RecruitmentStatus::Closed);

        // Other initiatives
        $this->larp($manager, 'Wyprawa Rycerska Talabeclandu', LarpStatus::Current, 'Grupa larpowa', 'Warhammer Fantasy',
            'Wyjazdy na larpy terenowe i bitewne w świecie Warhammer Fantasy.', 5)
            ->setProjectType(ProjectType::Initiative);

        // A draft that must stay hidden on the public site.
        $this->larp($manager, 'Projekt w przygotowaniu', LarpStatus::Draft, null, null, 'Szkic — niewidoczny publicznie.', 9);

        $this->characters($manager, $zwojeEdition, [
            ['Jarl Ulfgar', CharacterCasting::Male, 'Władca twierdzy, który musi wybrać między honorem a przetrwaniem klanu.',
                ['Polityka / intryga' => 50, 'Trudne wybory / dylematy moralne' => 30, 'Konflikt / walka' => 20],
                ['Zdrada' => 60, 'Wojna' => 40, 'Śmierć' => 30]],
            ['Kapłanka Mary', CharacterCasting::Female, 'Strażniczka świątyni, która słyszy głosy dawnych bogów.',
                ['Duchowość / religia' => 50, 'Tajemnica / śledztwo' => 30, 'Relacje i więzi' => 20],
                ['Religia' => 70, 'Opętanie' => 40, 'Ciemność' => 20]],
            ['Najemniczka Hrafna', CharacterCasting::Female, 'Wojowniczka do wynajęcia z długiem wobec niewłaściwych ludzi.',
                ['Konflikt / walka' => 60, 'Napięcie / akcja' => 30, 'Wątki romantyczne' => 10],
                ['Przemoc fizyczna' => 60, 'Śmierć' => 40]],
            ['Kupiec Brennik', CharacterCasting::Any, 'Handlarz, który zna każdą plotkę w Skyrim i sprzeda ją każdemu.',
                ['Handel i negocjacje' => 60, 'Komedia' => 25, 'Polityka / intryga' => 15],
                ['Alkohol / alkoholizm' => 30, 'Manipulacja' => 40]],
            ['Uczeń maga Eirik', CharacterCasting::NonBinary, 'Adept, który otworzył księgę, której nie powinien był otwierać.',
                ['Tajemnica / śledztwo' => 50, 'Groza / horror' => 30, 'Refleksja / filozofia' => 20],
                ['Okultyzm' => 70, 'Szaleństwo' => 40, 'Krzyk' => 20], CharacterAvailability::Taken],
            ['Zwiadowca Torvi', CharacterCasting::Male, 'Tropiciel, który wrócił z gór jako jedyny z oddziału.',
                ['Przetrwanie' => 50, 'Silne emocje / dramat' => 30, 'Napięcie / akcja' => 20],
                ['Trauma / PTSD' => 60, 'Śmierć bliskiej osoby' => 50]],
            ['Tajna postać', CharacterCasting::Any, 'Ukryta — nie powinna być widoczna publicznie.', [], [], CharacterAvailability::Hidden],
        ]);

        $this->characters($manager, $cyberEdition, [
            ['Decker „Ghost”', CharacterCasting::Any, 'Hakerka, która włamała się o jeden system za daleko.',
                ['Tajemnica / śledztwo' => 50, 'Napięcie / akcja' => 30, 'Trudne wybory / dylematy moralne' => 20],
                ['Manipulacja' => 40, 'Narkotyki / używki' => 30, 'Głośne dźwięki' => 20]],
            ['Fixer Mama Lou', CharacterCasting::Female, 'Pośredniczka, która zna cenę każdego w tym mieście.',
                ['Handel i negocjacje' => 50, 'Polityka / intryga' => 40, 'Komedia' => 10],
                ['Zdrada' => 50, 'Przemoc psychiczna' => 30]],
            ['Samuraj ulicy Kenji', CharacterCasting::Male, 'Najemnik z wszczepami, który zaczyna zapominać, kim był.',
                ['Konflikt / walka' => 50, 'Silne emocje / dramat' => 30, 'Refleksja / filozofia' => 20],
                ['Przemoc fizyczna' => 70, 'Broń palna' => 60, 'Dehumanizacja' => 40]],
        ]);

        $manager->flush();
    }

    /** @param array<string, list<string>> $categories */
    private function loadTags(ObjectManager $manager, TagType $type, array $categories): void
    {
        $position = 0;
        foreach ($categories as $categoryName => $tagNames) {
            $category = (new TagCategory())->setName($categoryName)->setType($type)->setPosition($position++);
            $manager->persist($category);
            foreach ($tagNames as $name) {
                $tag = (new Tag())->setName($name)->setType($type)->setCategory($category);
                $manager->persist($tag);
                $this->tags[$name] = $tag;
            }
        }
    }

    private function larp(ObjectManager $manager, string $name, LarpStatus $status, ?string $format, ?string $setting, string $teaser, int $position): Larp
    {
        $larp = (new Larp())
            ->setName($name)
            ->setStatus($status)
            ->setFormat($format)
            ->setSetting($setting)
            ->setTeaser($teaser)
            ->setPosition($position);
        $manager->persist($larp);

        return $larp;
    }

    private function edition(Larp $larp, string $name, string $start, ?string $end, ?string $location, RecruitmentStatus $recruitment): LarpEdition
    {
        $edition = (new LarpEdition())
            ->setName($name)
            ->setStartDate(new \DateTimeImmutable($start))
            ->setEndDate(null === $end ? null : new \DateTimeImmutable($end))
            ->setLocation($location)
            ->setRecruitment($recruitment);
        $larp->addEdition($edition);

        return $edition;
    }

    /**
     * @param list<array{0: string, 1: CharacterCasting, 2: string, 3: array<string, int>, 4: array<string, int>, 5?: CharacterAvailability}> $rows
     */
    private function characters(ObjectManager $manager, LarpEdition $edition, array $rows): void
    {
        foreach ($rows as $position => $row) {
            $character = (new Character())
                ->setEdition($edition)
                ->setName($row[0])
                ->setCasting($row[1])
                ->setTeaser($row[2])
                ->setAvailability($row[5] ?? CharacterAvailability::Available)
                ->setPosition($position);
            foreach ($row[3] + $row[4] as $tagName => $percentage) {
                $character->tag($this->tags[$tagName], $percentage);
            }
            $manager->persist($character);
        }
    }
}
