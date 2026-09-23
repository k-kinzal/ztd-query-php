<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Conditional\InList;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InList::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class InListTest extends TestCase
{
    public function testInputsRetainsScalarCandidateOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 NOT IN (2, 3)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $membership = $statement->outputs[0]->expression;
        self::assertInstanceOf(InList::class, $membership);
        self::assertSame('1', $membership->value->spelling());
        self::assertSame('2', $membership->choices[0]->spelling());
        self::assertSame('3', $membership->choices[1]->spelling());
        self::assertSame([$membership->value, ...$membership->choices], $membership->inputs());
        self::assertTrue($membership->negated);
        self::assertSame('NOT IN', $membership->spelling());
    }

    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::PostgreSql])]
    public function testInputsRequiresCandidatesOutsideSqlite(Dialect $dialect): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1 IN (2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $membership = $statement->outputs[0]->expression;
        self::assertInstanceOf(InList::class, $membership);
        $this->expectException(InvalidStructure::class);
        new InList($membership->facts, $membership->source, $membership->value, [], false);
    }

    public function testInputsRejectsAContradictoryRowWidth(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT ROW(1,2) IN (ROW(2,3))');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $membership = $statement->outputs[0]->expression;
        self::assertInstanceOf(InList::class, $membership);
        $this->expectException(InvalidStructure::class);
        new InList($membership->facts, $membership->source, $membership->value, [Expression::literal(1, Dialect::PostgreSql)], false);
    }

    public function testWithFactsPreservesEmptySqliteCandidates(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind('SELECT NULL IN ()');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $membership = $statement->outputs[0]->expression;
        self::assertInstanceOf(InList::class, $membership);
        $copy = $membership->withFacts($membership->facts);
        self::assertSame([], $copy->choices);
        self::assertSame($membership->value, $copy->value);
        self::assertSame('SELECT (NULL IN ())', $binder->bind($statement->toString())->toString());
    }
}
