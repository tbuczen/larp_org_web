<?php

declare(strict_types=1);

namespace App\Command;

use App\Theme\ThemeManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'app:theme:create', description: 'Creates a new theme that extends an existing one')]
final class CreateThemeCommand extends Command
{
    public function __construct(
        private readonly ThemeManager $themes,
        #[Autowire('%kernel.project_dir%/themes')]
        private readonly string $themesDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Folder name, e.g. "my-org" (lowercase letters, digits, - and _)')
            ->addOption('extends', null, InputOption::VALUE_REQUIRED, 'Parent theme', ThemeManager::BASE)
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Human-readable name shown in the admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');
        $parent = (string) $input->getOption('extends');

        if (1 !== preg_match('/^[a-z0-9_-]+$/', $name)) {
            $io->error('Use lowercase letters, digits, "-" and "_" only.');

            return Command::INVALID;
        }
        if (isset($this->themes->all()[$name])) {
            $io->error(sprintf('Theme "%s" already exists.', $name));

            return Command::FAILURE;
        }
        if (!isset($this->themes->all()[$parent])) {
            $io->error(sprintf('Parent theme "%s" does not exist.', $parent));

            return Command::FAILURE;
        }

        $label = (string) ($input->getOption('label') ?? ucfirst($name));
        $dir = $this->themesDir.'/'.$name;
        $fs = new Filesystem();
        $fs->mkdir([$dir.'/templates', $dir.'/assets/styles', $dir.'/assets/scripts', $dir.'/assets/images']);

        $fs->dumpFile($dir.'/theme.yaml', <<<YAML
            name: {$label}
            description: ''
            extends: {$parent}

            # Loaded after the parent theme's files, in this order.
            styles:
                - tokens.css
                - components.css
                - effects.css

            scripts: []

            YAML);
        $fs->dumpFile($dir.'/assets/styles/tokens.css', <<<'CSS'
            /* Override only the design tokens you want to change (see themes/base/assets/styles/tokens.css). */
            :root {
                /* --color-primary: #e3b45c; */
            }

            CSS);
        $fs->dumpFile($dir.'/assets/styles/components.css', "/* Restyle components here. */\n");
        $fs->dumpFile($dir.'/assets/styles/effects.css', "/* Animations and decorations. Respect prefers-reduced-motion. */\n");
        $fs->dumpFile($dir.'/templates/.gitkeep', '');
        $fs->dumpFile($dir.'/README.md', <<<MD
            # {$label} theme

            Extends `{$parent}`. Read `themes/base/README.md` first: it lists every template and
            design token you can override.

            - Change colours and fonts in `assets/styles/tokens.css`.
            - Override a template by copying it from the parent theme into `templates/` (same path).

            MD);

        $io->success(sprintf('Created themes/%s (extends %s). Choose it in Admin → Site settings → Appearance.', $name, $parent));

        return Command::SUCCESS;
    }
}
