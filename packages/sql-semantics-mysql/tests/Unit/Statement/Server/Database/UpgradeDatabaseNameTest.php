<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(UpgradeDatabaseName::class)]
#[Medium]
final class UpgradeDatabaseNameTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('ALTER DATABASE `#mysql50#x` UPGRADE DATA DIRECTORY NAME', (new Inspection('mysql-5.6.51'))->inspect('alter database `#mysql50#x` upgrade data directory name')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection('mysql-5.7.44'))->inspect('ALTER DATABASE d UPGRADE DATA DIRECTORY NAME');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
