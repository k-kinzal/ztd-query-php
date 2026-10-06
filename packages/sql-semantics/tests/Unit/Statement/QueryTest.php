<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(Query::class)]
#[Medium]
final class QueryTest extends TestCase
{
    public function testDeriveQueryRecordsTheOutputOfANestedQueryAtItsPosition(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL)');
        $operation = $semantics->analyze('SELECT (SELECT t.a FROM t AS inner_t) FROM t', [$table]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        $subquery = $statement->columns[0]->expression;
        self::assertInstanceOf(ScalarSubquery::class, $subquery);
        $fields = $operation->facts->query($subquery->query)->fields();
        self::assertNotNull($fields);
        self::assertSame('a', $fields->at(0)->name?->value);
        $resolution = $fields->at(0)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame(1, $resolution->depth);
        self::assertSame($statement->from, $resolution->relation);
    }

    public function testDeriveQueryOfTheRootIsTheOutputOfTheOperation(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS one');
        $statement = $operation->statement;

        self::assertInstanceOf(Query::class, $statement);
        self::assertSame($operation->facts->output, $operation->facts->query($statement));
    }
}
