# Project foundation: Symfony 7.4 skeleton, Doctrine, AssetMapper, Symfony UX

## Description
Set up the base application everything else builds on.

## Scope
- `symfony/skeleton` 7.4 LTS + `webapp` pack (PHP 8.3)
- Doctrine ORM + Migrations. The default `.env` uses SQLite (`var/data.db`), and there is a commented MySQL example.
- AssetMapper, `symfony/stimulus-bundle`, `symfony/ux-turbo`, `symfony/ux-live-component`, `symfony/ux-twig-component`
- Image handling: `vich/uploader-bundle` for uploads, `liip/imagine-bundle` for thumbnails and responsive sizes
- Fixtures (`doctrine/doctrine-fixtures-bundle`) with demo data: 1 organization, 2 LARPs, about 10 characters, tags
- Code quality: PHPUnit, PHPStan, PHP-CS-Fixer, and a GitHub Actions workflow that runs them
- README covering setup, how to switch between SQLite and MySQL, and how to create the first admin user

## Acceptance criteria
- [ ] `composer install && bin/console doctrine:migrations:migrate && bin/console doctrine:fixtures:load` gives a working site on SQLite
- [ ] Changing `DATABASE_URL` to MySQL works with the same migrations (migrations are portable, with no SQL specific to one database)
- [ ] CI is green
