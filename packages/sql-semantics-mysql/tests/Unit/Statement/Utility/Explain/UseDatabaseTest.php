<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\UseDatabase;

#[CoversClass(UseDatabase::class)]
#[Medium]
final class UseDatabaseTest extends TestCase
{
    public function testDeriveStatementReturnsNoRows(): void
    {
        $use = (new Semantics(Dialect::MySql))->analyze('USE db');
        self::assertInstanceOf(UseDatabase::class, $use->statement);
        self::assertNull($use->shape());
    }

    public function testRenderWritesTheDatabase(): void
    {
        self::assertSame('USE db', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('use db')->toString());
    }
}
