<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Facts;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\QueryExpressionFacts::class)]
#[Medium]
final class QueryExpressionFactsTest extends TestCase
{
    public function testDeriveReportsLockingOfASetOperation(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 UNION SELECT 2 FOR UPDATE');
        self::assertSame('FOR UPDATE is not allowed with UNION/INTERSECT/EXCEPT', $query->facts->diagnostics[0]->message());
    }

    public function testMergedReportsASecondLimit(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('(SELECT 1 LIMIT 1) LIMIT 2');
        self::assertSame('multiple LIMIT clauses not allowed', $query->facts->diagnostics[0]->message());
    }

    public function testAnyTellsWhetherAnOptionMatches(): void
    {
        self::assertTrue((new \SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\QueryExpressionFacts())->any([null, new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions(readOnly: true)], static fn (\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions $options): bool => $options->readOnly));
    }
}
