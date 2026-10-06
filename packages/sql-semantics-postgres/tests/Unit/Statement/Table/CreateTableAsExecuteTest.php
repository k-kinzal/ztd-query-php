<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTableAsExecute::class)]
#[Medium]
final class CreateTableAsExecuteTest extends TestCase
{
    public function testDeriveStatementDeclaresAnIncompleteTable(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n AS EXECUTE q', []);
        self::assertSame([
          0 => false,
          1 => 6,
        ], [$statement->declarations()[0]->complete, count($statement->declarations()[0]->implicit)]);
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TEMP TABLE IF NOT EXISTS n (x) AS EXECUTE q (1, 2) WITH DATA', []);
        self::assertSame('CREATE TEMP TABLE IF NOT EXISTS n (x) AS EXECUTE q (1, 2) WITH DATA', $statement->toString());
    }
}
