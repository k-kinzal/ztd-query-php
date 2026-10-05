<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Manipulation\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\Fetch::class)]
#[Medium]
final class FetchTest extends TestCase
{
    public function testDeriveStatementRecordsTheRowsOfTheCursor(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('FETCH NEXT c');
        self::assertSame('the session state: the cursor c', $query->facts->output?->shape->missing[0]->describe());
    }

    public function testDeriveStatementRecordsNoRowsForMove(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('MOVE LAST IN c');
        self::assertNull($query->facts->output);
    }

    public function testRenderWritesTheMovementAndTheCount(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('FETCH RELATIVE 2 FROM c');
        self::assertSame('FETCH RELATIVE 2 c', $query->toString());
    }
}
