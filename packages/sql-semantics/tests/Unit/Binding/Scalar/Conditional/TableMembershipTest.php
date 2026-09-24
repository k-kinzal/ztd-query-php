<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Conditional\TableMembership;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Query\InSubquery;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableMembership::class)]
#[Medium]
final class TableMembershipTest extends TestCase
{
    #[TestWith(["SELECT 'a' IN b", false, 'SELECT (\'a\' IN (SELECT "b"."x" AS "x" FROM "main"."b"))'])]
    #[TestWith(["SELECT 'a' IN 'b'", false, 'SELECT (\'a\' IN (SELECT "b"."x" AS "x" FROM "main"."b"))'])]
    #[TestWith(['SELECT 1 NOT IN main.b', true, 'SELECT (1 NOT IN(SELECT "b"."x" AS "x" FROM "main"."b"))'])]
    public function testBindReadsMembershipInANamedTable(string $sql, bool $negated, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE b(x)'));
        $query = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        $expression = $query->outputs[0]->expression;
        self::assertInstanceOf(InSubquery::class, $expression);
        self::assertSame($negated, $expression->negated);
        self::assertSame('x', $expression->query->resultColumns()[0]->name);
        self::assertSame($expected, $query->toString());
        self::assertSame($expected, $binder->bind($query->toString())->toString());
    }

    public function testBindReadsTheInOperandOfPosition(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE b(x)'));
        $query = $binder->bind("SELECT position('a' in 'b')");
        self::assertSame('SELECT position((\'a\' IN (SELECT "b"."x" AS "x" FROM "main"."b")))', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testBindKeepsTheArgumentsOfATableValuedFunction(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ComparisonWidth->message());
        (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind("SELECT 1 IN json_each('[1]')");
    }

    public function testBindLeavesAnExpressionListToTheOperatorRules(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1 IN (1, 2)');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Conditional\InList::class, $query->outputs[0]->expression);
    }
}
