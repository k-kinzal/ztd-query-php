<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Query\RowSubquery;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(RowSubquery::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class RowSubqueryTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testComparisonKeepsTheWholeResultRow(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $query = $binder->bind('SELECT (1,2)=(SELECT 1,2)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $query->outputs[0]->expression);
        $value = $query->outputs[0]->expression->right;
        self::assertInstanceOf(RowSubquery::class, $value);
        self::assertCount(2, $value->query->resultColumns());
        self::assertSame('record', $value->type->name);
        $rebound = $binder->bind($query->toString());
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $rebound);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $rebound->outputs[0]->expression);
        self::assertInstanceOf(RowSubquery::class, $rebound->outputs[0]->expression->right);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testDoesNotTreatMultipleColumnsAsAScalar(Dialect $dialect): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT (SELECT 1,2)', strict: false);
    }

    public function testComparisonRequiresTheSameNumberOfFields(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (1,2)=(SELECT 1,2,3)', strict: false);
    }

    public function testACompositeValueRemainsOneQueryOutput(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT (SELECT ROW(1,2))=ROW(1,2)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $query->outputs[0]->expression);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Query\ScalarSubquery::class, $query->outputs[0]->expression->left);
        self::assertCount(1, $query->outputs[0]->expression->left->query->resultColumns());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }


    public function testInputsAreEveryResultColumn(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (1,2)=(SELECT 3,4)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $query->outputs[0]->expression);
        $row = $query->outputs[0]->expression->right;
        self::assertInstanceOf(RowSubquery::class, $row);
        self::assertSame(array_map(static fn ($output): \SqlSemantics\Model\Expression => $output->expression, $row->query->resultColumns()), $row->inputs());
        self::assertSame(['3', '4'], array_map(static fn (\SqlSemantics\Model\Expression $input): ?string => $input->spelling(), $row->inputs()));
    }

    public function testSpellingIsRow(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (1,2)=(SELECT 3,4)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $query->outputs[0]->expression);
        $row = $query->outputs[0]->expression->right;
        self::assertInstanceOf(RowSubquery::class, $row);
        self::assertSame('ROW', $row->spelling());
        self::assertSame(ExpressionKind::RowSubquery, $row->kind);
    }

    public function testWithFactsKeepsTheQuery(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (1,2)=(SELECT 3,4)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $query->outputs[0]->expression);
        $row = $query->outputs[0]->expression->right;
        self::assertInstanceOf(RowSubquery::class, $row);
        $changed = $row->withFacts(new ExpressionFacts($row->type, Nullability::MaybeNull, ['j0']));
        self::assertNotSame($row, $changed);
        self::assertSame(['j0'], $changed->nullExtendedBy);
        self::assertSame([], $row->nullExtendedBy);
        self::assertSame($row->query, $changed->query);
    }

    public function testSubqueryReturnsTheBoundQuery(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT (1,2)=(SELECT 3,4)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $query->outputs[0]->expression);
        $row = $query->outputs[0]->expression->right;
        self::assertInstanceOf(RowSubquery::class, $row);
        self::assertSame($row->query, $row->subquery());
        self::assertCount(2, $row->subquery()->resultColumns());
    }

    public function testRejectsASingleColumnQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT (1,2)=(SELECT 3,4)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $query->outputs[0]->expression);
        $row = $query->outputs[0]->expression->right;
        self::assertInstanceOf(RowSubquery::class, $row);
        $single = $binder->bind('SELECT 8');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $single);
        $this->expectException(InvalidStructure::class);
        new RowSubquery($row->facts, $row->source, $single);
    }

    public function testRejectsFactsWithoutTheRecordType(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT (SELECT 5)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $scalar = $query->outputs[0]->expression;
        $wide = $binder->bind('SELECT 8, 9');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $wide);
        $this->expectException(InvalidStructure::class);
        new RowSubquery($scalar->facts, $scalar->source, $wide);
    }
}
