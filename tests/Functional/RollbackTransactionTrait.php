<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Wraps each test in a database transaction that is rolled back afterwards, so
 * tests that save data don't leak it into other tests.
 *
 * Call startTransaction($client) right after createClient(). Kernel reboots are
 * disabled so every request in the test uses the same connection.
 */
trait RollbackTransactionTrait
{
    private ?Connection $rollbackConnection = null;

    private function startTransaction(KernelBrowser $client): void
    {
        $client->disableReboot();
        $this->rollbackConnection = self::getContainer()->get(Connection::class);
        $this->rollbackConnection->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (null !== $this->rollbackConnection && $this->rollbackConnection->isTransactionActive()) {
            $this->rollbackConnection->rollBack();
        }
        $this->rollbackConnection = null;

        parent::tearDown();
    }
}
