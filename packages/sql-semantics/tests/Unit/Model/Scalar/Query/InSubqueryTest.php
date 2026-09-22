<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Query\InSubquery;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InSubquery::class)]
final class InSubqueryTest extends TestCase
{
    public function testRejectsAQueryWithTheWrongOperandWidth(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT 1 IN (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $query = $binder->bind('SELECT 1, 2');
        self::assertInstanceOf(BoundSelect::class, $query);


        $expression = $statement->outputs[0]->expression;
        self::assertInstanceOf(InSubquery::class, $expression);
        $this->expectException(InvalidStructure::class);
        new InSubquery($expression->facts, $expression->source, $expression->value, $query, false);
    }

    public function testSubqueryRetainsTheComparisonInput(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 IN (SELECT 1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $expression = $statement->outputs[0]->expression;
        self::assertInstanceOf(InSubquery::class, $expression);
        self::assertSame($expression->query, $expression->subquery());
        self::assertSame('1', $expression->inputs()[0]->spelling());
        self::assertSame($expression->type, $expression->withFacts($expression->facts)->type);
    }
}
