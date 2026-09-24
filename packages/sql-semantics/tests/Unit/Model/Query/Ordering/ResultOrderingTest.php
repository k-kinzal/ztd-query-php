<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Ordering\OutputAlias;
use SqlSemantics\Model\Query\Ordering\OutputPosition;
use SqlSemantics\Model\Query\Ordering\ResultOrdering;
use SqlSemantics\Model\Statement\CompoundStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ResultOrdering::class)]
#[Medium]
final class ResultOrderingTest extends TestCase
{
    public function testBindRebindsAliasesAndPositionsToTheNewOutputs(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 AS a, 2 AS b UNION SELECT 3, 4 ORDER BY b DESC, 1');
        self::assertInstanceOf(CompoundStatement::class, $statement);
        $ordering = ResultOrdering::bind($statement->orderBy, $statement->outputs);
        self::assertCount(2, $ordering);
        self::assertInstanceOf(OutputAlias::class, $ordering[0]->key);
        self::assertSame($statement->outputs[1], $ordering[0]->key->output);
        self::assertTrue($ordering[0]->descending);
        self::assertInstanceOf(OutputPosition::class, $ordering[1]->key);
        self::assertSame($statement->outputs[0], $ordering[1]->key->output);
        self::assertFalse($ordering[1]->descending);
    }

    public function testBindPassesExpressionKeysThrough(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t ORDER BY id + 1 DESC NULLS FIRST');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $ordering = ResultOrdering::bind($statement->orderBy, []);
        self::assertSame($statement->orderBy[0]->key, $ordering[0]->key);
        self::assertTrue($ordering[0]->descending);
        self::assertTrue($ordering[0]->nullsFirst);
    }

    public function testBindRejectsAnAliasWithoutExactlyOneMatch(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 AS a, 2 AS b UNION SELECT 3, 4 ORDER BY b');
        self::assertInstanceOf(CompoundStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        ResultOrdering::bind($statement->orderBy, [$statement->outputs[0]]);
    }

    public function testBindRejectsAPositionBeyondTheResult(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 UNION SELECT 2 ORDER BY 1');
        self::assertInstanceOf(CompoundStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        ResultOrdering::bind($statement->orderBy, []);
    }
}
