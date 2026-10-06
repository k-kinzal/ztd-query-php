<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\DescribeTable;

#[CoversClass(DescribeTable::class)]
#[Medium]
final class DescribeTableTest extends TestCase
{
    public function testDeriveStatementResolvesTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $describe = $semantics->analyze('DESCRIBE t a', [$table]);
        self::assertInstanceOf(DescribeTable::class, $describe->statement);
        self::assertSame([], $describe->facts->diagnostics);
        self::assertSame('Field', $describe->field(0)->name?->value);
    }

    public function testRenderWritesTheColumn(): void
    {
        self::assertSame('DESCRIBE db.t `a%`', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('explain db.t `a%`')->toString());
    }
}
