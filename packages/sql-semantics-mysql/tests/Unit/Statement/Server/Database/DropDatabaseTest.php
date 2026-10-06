<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DropDatabase;

#[CoversClass(DropDatabase::class)]
#[Medium]
final class DropDatabaseTest extends TestCase
{
    public function testRenderWritesIfExists(): void
    {
        self::assertSame('DROP DATABASE IF EXISTS d', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('drop database if exists d')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DROP DATABASE d');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
