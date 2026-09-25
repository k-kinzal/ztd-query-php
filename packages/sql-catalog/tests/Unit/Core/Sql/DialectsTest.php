<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Sql;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Core\Sql\Dialects::class)]
final class DialectsTest extends TestCase
{
    public function testFindSupportsApplicationIdentities(): void
    {
        $policy = self::createStub(\SqlCatalog\Core\Sql\Dialect::class);
        $registry = new \SqlCatalog\Core\Sql\Dialects(['application' => $policy]);
        self::assertSame($policy, $registry->find('application'));
        self::assertNull($registry->find('unknown'));
        self::assertNull($registry->find(null));
    }

    public function testHasConnectionUsesRegisteredClasses(): void
    {
        $registry = new \SqlCatalog\Core\Sql\Dialects(connections: ['App\Connection' => 'application']);
        self::assertTrue($registry->hasConnection('App\Connection'));
        self::assertFalse($registry->hasConnection('Unknown'));
        self::assertFalse($registry->hasConnection(null));
    }

    public function testConnectionUsesTheConfiguredFallback(): void
    {
        $registry = new \SqlCatalog\Core\Sql\Dialects(connections: ['App\Connection' => 'application']);
        self::assertSame('application', $registry->connection('App\Connection', 'default'));
        self::assertSame('default', $registry->connection('Unknown', 'default'));
        self::assertNull($registry->connection(null, null));
    }
}
