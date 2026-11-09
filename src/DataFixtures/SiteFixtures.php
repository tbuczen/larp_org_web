<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Larp;
use App\Entity\Menu;
use App\Entity\MenuItem;
use App\Entity\Page;
use App\Entity\PageBlock;
use App\Entity\SiteSettings;
use App\Enum\MenuTarget;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Pages, blocks and menus recreating the current Las Tajemnic site (issue #18),
 * with the Las Tajemnic theme switched on.
 */
class SiteFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [AppFixtures::class, LarpFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $settings = $manager->getRepository(SiteSettings::class)->findOneBy([]);
        $settings?->setTheme('lastajemnic');

        $home = (new Page())->setTitle('Strona główna')->setIsHome(true)
            ->setMetaDescription('Stowarzyszenie Larpowe „Las Tajemnic” — larpy fantasy, science fiction i postapo: Pradawne Zwoje, Cybercrush, Vault 47.');
        $this->blocks($home, [
            ['hero', ['title' => 'Przeżyj prawdziwą przygodę', 'subtitle' => 'Od dawnych legend po ruiny przyszłości — tworzymy larpy, w które się wchodzi jak w las: bez mapy i z dreszczem.', 'larp' => '', 'primary_label' => 'Nasze larpy', 'primary_url' => '/larpy', 'secondary_label' => 'Znajdź swoją postać', 'secondary_url' => '/postacie', 'height' => 'full']],
            ['larp_showcase', ['title' => 'Aktualnie organizowane larpy — 2026', 'intro' => 'Zapisy, daty i miejsca naszych najbliższych gier.', 'mode' => 'current', 'limit' => 6]],
            ['countdown', ['title' => '', 'larp' => 'auto']],
            ['matcher_teaser', []],
            ['larp_showcase', ['title' => 'Poprzednie projekty larpowe', 'intro' => '', 'mode' => 'past', 'limit' => 6]],
            ['larp_showcase', ['title' => 'Inne inicjatywy', 'intro' => '', 'mode' => 'initiatives', 'limit' => 6]],
            ['social_links', ['title' => 'Dołącz do naszej społeczności', 'text' => 'Śledź postępy nad naszymi projektami i bądź na bieżąco z zapisami.', 'show_email' => false]],
        ]);
        $manager->persist($home);

        $association = (new Page())->setTitle('Stowarzyszenie')->setSlug('stowarzyszenie');
        $this->blocks($association, [
            ['rich_text', ['title' => 'Kim jesteśmy', 'content' => '<p>Stowarzyszenie Larpowe „Las Tajemnic” organizuje larpy terenowe i chamberowe w światach fantasy, science fiction i postapokalipsy.</p><p><em>Tu pojawi się pełny opis stowarzyszenia i informacje formalne.</em></p>', 'width' => 'narrow']],
            ['larp_showcase', ['title' => 'Nasze projekty', 'intro' => '', 'mode' => 'past', 'limit' => 6]],
        ]);
        $manager->persist($association);

        $contact = (new Page())->setTitle('Kontakt')->setSlug('kontakt');
        $this->blocks($contact, [
            ['social_links', ['title' => 'Masz pytania?', 'text' => 'Zapraszamy do kontaktu — mailowo albo przez nasze kanały społecznościowe.', 'show_email' => true]],
        ]);
        $manager->persist($contact);

        $larp = static fn (string $slug): ?Larp => $manager->getRepository(Larp::class)->findOneBy(['slug' => $slug]);

        $main = new Menu(Menu::MAIN, 'Menu główne');
        $main->addItem($this->item('Stowarzyszenie', MenuTarget::Page, page: $association));
        $ourLarps = $this->item('Nasze larpy', MenuTarget::Route, route: 'app_larp_index');
        $main->addItem($ourLarps);
        foreach (['cybercrush', 'pradawne-zwoje', 'vault-47', 'czasy-pogardy'] as $slug) {
            if (null !== $found = $larp($slug)) {
                $ourLarps->addChild($this->item($found->getName(), MenuTarget::Larp, larp: $found));
            }
        }
        $initiatives = $this->item('Inne inicjatywy', MenuTarget::None);
        $main->addItem($initiatives);
        if (null !== $found = $larp('wyprawa-rycerska-talabeclandu')) {
            $initiatives->addChild($this->item($found->getName(), MenuTarget::Larp, larp: $found));
        }
        $main->addItem($this->item('Postacie', MenuTarget::Route, route: 'app_characters'));
        $main->addItem($this->item('Kontakt', MenuTarget::Page, page: $contact));
        $this->positions($main);
        $manager->persist($main);

        $footer = new Menu(Menu::FOOTER, 'Stopka');
        $footer->addItem($this->item('Stowarzyszenie', MenuTarget::Page, page: $association));
        $footer->addItem($this->item('Larpy', MenuTarget::Route, route: 'app_larp_index'));
        $footer->addItem($this->item('Kontakt', MenuTarget::Page, page: $contact));
        $this->positions($footer);
        $manager->persist($footer);

        $manager->flush();
    }

    /** @param list<array{0: string, 1: array<string, mixed>}> $blocks */
    private function blocks(Page $page, array $blocks): void
    {
        foreach ($blocks as $position => [$type, $settings]) {
            $page->addBlock((new PageBlock($type, $settings))->setPosition($position));
        }
    }

    private function item(string $label, MenuTarget $target, ?Page $page = null, ?Larp $larp = null, ?string $route = null): MenuItem
    {
        return (new MenuItem())->setLabel($label)->setTarget($target)->setPage($page)->setLarp($larp)->setRoute($route);
    }

    /** Adds submenu items to the menu (so they're saved) and numbers each list from 0. */
    private function positions(Menu $menu): void
    {
        foreach ($menu->getItems()->toArray() as $item) {
            foreach ($item->getChildren() as $child) {
                $menu->addItem($child);
            }
        }
        $counters = [];
        foreach ($menu->getItems() as $item) {
            $key = spl_object_id($item->getParent() ?? $menu);
            $item->setPosition($counters[$key] = ($counters[$key] ?? -1) + 1);
        }
    }
}
