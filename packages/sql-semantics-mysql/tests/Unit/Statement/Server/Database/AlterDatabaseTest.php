<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase;

#[CoversClass(AlterDatabase::class)]
#[Medium]
final class AlterDatabaseTest extends TestCase
{
    public function testRenderWritesTheName(): void
    {
        self::assertSame('ALTER DATABASE d COLLATE utf8mb4_bin', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('alter schema d collate utf8mb4_bin')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('ALTER DATABASE d COLLATE utf8mb4_bin');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
