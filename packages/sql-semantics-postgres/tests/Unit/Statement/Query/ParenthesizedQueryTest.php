<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery::class)]
#[Medium]
final class ParenthesizedQueryTest extends TestCase
{
    public function testOutputNameIsThatOfTheQueryInside(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('(SELECT 1 AS a)');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery::class, $statement);
        self::assertSame('a', $statement->outputName()?->value);
    }

    public function testDeriveStatementRecordsTheRowsOfTheQueryInside(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('((SELECT 1 AS a))');
        self::assertSame('a', $query->field(0)->name?->value);
    }

    public function testDeriveQueryHasTheOutputOfTheQueryInside(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 UNION (SELECT 2 AS b)');
        self::assertSame('?column?', $query->field(0)->name?->value);
    }

    public function testRenderKeepsTheParentheses(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 UNION (SELECT 2 UNION SELECT 3)');
        self::assertSame('SELECT 1 UNION (SELECT 2 UNION SELECT 3)', $query->toString());
    }
}
