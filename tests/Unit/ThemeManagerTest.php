<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\SiteSettings;
use App\Repository\SiteSettingsRepository;
use App\Theme\ThemeManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class ThemeManagerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/themes-'.bin2hex(random_bytes(4));
        $fs = new Filesystem();
        $fs->dumpFile($this->dir.'/base/theme.yaml', "name: Base\nstyles: [tokens.css, base.css]\n");
        $fs->dumpFile($this->dir.'/base/assets/styles/tokens.css', '');
        $fs->dumpFile($this->dir.'/base/assets/styles/base.css', '');
        $fs->dumpFile($this->dir.'/base/assets/images/hero.svg', '');
        $fs->mkdir($this->dir.'/base/templates');
        $fs->dumpFile($this->dir.'/org/theme.yaml', "name: Org\nextends: base\nstyles: [tokens.css, missing.css]\nscripts: [fx.js]\npreload: [fonts/head.woff2, fonts/gone.woff2]\n");
        $fs->dumpFile($this->dir.'/org/assets/fonts/head.woff2', '');
        $fs->dumpFile($this->dir.'/org/assets/styles/tokens.css', '');
        $fs->dumpFile($this->dir.'/org/assets/scripts/fx.js', '');
        $fs->dumpFile($this->dir.'/org/assets/images/hero.svg', '');
        $fs->mkdir($this->dir.'/org/templates');
        $fs->dumpFile($this->dir.'/loop/theme.yaml', "name: Loop\nextends: loop\n");
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testChainStylesScriptsAndAssets(): void
    {
        $themes = $this->manager('org');

        self::assertSame(['org', 'base'], array_map(static fn ($t) => $t->name, $themes->getChain()));
        self::assertSame([
            'themes/base/assets/styles/tokens.css',
            'themes/base/assets/styles/base.css',
            'themes/org/assets/styles/tokens.css',
        ], $themes->getStylesheets(), 'parent first; missing files skipped');
        self::assertSame(['themes/org/assets/scripts/fx.js'], $themes->getScripts());
        self::assertSame(['themes/org/assets/fonts/head.woff2'], $themes->getPreloads(), 'missing fonts skipped');
        self::assertSame('themes/org/assets/images/hero.svg', $themes->findAsset('images/hero.svg'), 'child wins');
        self::assertSame([$this->dir.'/org/templates', $this->dir.'/base/templates'], $themes->getTemplateDirs());
    }

    public function testUnknownThemeFallsBackToBase(): void
    {
        self::assertSame('base', $this->manager('does-not-exist')->getActive()->name);
    }

    public function testSelfReferencingThemeDoesNotLoop(): void
    {
        self::assertSame(['loop'], array_map(static fn ($t) => $t->name, $this->manager('loop')->getChain()));
    }

    public function testOverrideForOneRequest(): void
    {
        $themes = $this->manager('org');
        $themes->useTheme('base');

        self::assertSame('base', $themes->getActive()->name);
        $themes->reset();
        self::assertSame('org', $themes->getActive()->name);
    }

    private function manager(string $active): ThemeManager
    {
        $repo = $this->createStub(SiteSettingsRepository::class);
        $repo->method('findOneBy')->willReturn((new SiteSettings())->setTheme($active));

        return new ThemeManager($this->dir, $repo);
    }
}
