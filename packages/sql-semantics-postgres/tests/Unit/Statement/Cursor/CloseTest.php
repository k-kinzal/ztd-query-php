<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Cursor\Close::class)]
#[Medium]
final class CloseTest extends TestCase
{
    public function testDeriveStatementDerivesNothing(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('CLOSE ALL');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Cursor\Close::class, $statement);
        self::assertSame([null, null], [$query->facts->output, $statement->cursor]);
    }

    public function testRenderWritesTheCursor(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('CLOSE c');
        self::assertSame('CLOSE c', $query->toString());
    }
}
