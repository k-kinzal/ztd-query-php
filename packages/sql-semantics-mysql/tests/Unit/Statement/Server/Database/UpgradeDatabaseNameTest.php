<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName;

#[CoversClass(UpgradeDatabaseName::class)]
#[Medium]
final class UpgradeDatabaseNameTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('ALTER DATABASE `#mysql50#x` UPGRADE DATA DIRECTORY NAME', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('alter database `#mysql50#x` upgrade data directory name')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER DATABASE d UPGRADE DATA DIRECTORY NAME');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
