<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList::class)]
#[Small]
final class ValuesListTest extends TestCase
{
    public function testOutputNameIsColumn1(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('VALUES (1)');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList::class, $statement);
        self::assertSame('column1', $statement->outputName()->value);
    }

    public function testDeriveStatementRecordsTheRows(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("VALUES (1, 'a')");
        self::assertSame('column2', $query->field(1)->name?->value);
    }

    public function testDeriveQueryReportsRowsOfDifferentLengths(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('VALUES (1), (1, 2)');
        self::assertSame('VALUES lists must all be the same length', $query->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheRows(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('VALUES (1), (2)');
        self::assertSame('VALUES (1), (2)', $query->toString());
    }
}
