<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\DescribeTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DescribeTable::class)]
#[Medium]
final class DescribeTableTest extends TestCase
{
    public function testDeriveStatementResolvesTheTable(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT)');
        $describe = $semantics->inspect('DESCRIBE t a', [$table]);
        self::assertInstanceOf(DescribeTable::class, $describe->statement);
        self::assertSame([], $describe->facts->diagnostics);
        self::assertSame('Field', $describe->field(0)->name?->value);
    }

    public function testRenderWritesTheColumn(): void
    {
        self::assertSame('DESCRIBE db.t `a%`', (new Inspection('mysql-5.6.51'))->inspect('explain db.t `a%`')->toString());
    }
}
