<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Clause;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions::class)]
#[Medium]
final class SelectOptionsTest extends TestCase
{
    public function testLocksTellsLockingAndReadOnly(): void
    {
        self::assertSame([true, false], [(new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions(readOnly: true))->locks(), (new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions([], new \SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\RowLimit(null, new \SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\Offset(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'))))))->locks()]);
    }

    public function testRenderKeepsTheOrderOfLimitAndLocking(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 FOR UPDATE LIMIT 1');
        self::assertSame('SELECT 1 FOR UPDATE LIMIT 1', $query->toString());
    }

    public function testWriteLockingWritesReadOnly(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 LIMIT 1 FOR READ ONLY');
        self::assertSame('SELECT 1 LIMIT 1 FOR READ ONLY', $query->toString());
    }
}
