<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ColumnUse::class)]
#[Medium]
final class ColumnUseTest extends TestCase
{
    public function testDeriveScalarAnswersTheTypeAndNullFactOfTheResolvedSlot(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('SELECT t.a, b FROM t', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $first = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $first);
        $second = $select->items[1];
        self::assertInstanceOf(SelectExpression::class, $second);
        $qualified = $operation->facts->scalar($first->expression);
        $bare = $operation->facts->scalar($second->expression);

        self::assertInstanceOf(Known::class, $qualified->type);
        self::assertInstanceOf(Integral::class, $qualified->type->descriptor);
        self::assertSame(IntegralKind::Int, $qualified->type->descriptor->kind);
        self::assertSame(Nullability::NotNull, $qualified->nullability);
        self::assertInstanceOf(ResolvedColumn::class, $qualified->resolution);
        self::assertSame('a', $qualified->resolution->slot->column?->name->value);
        self::assertInstanceOf(Known::class, $bare->type);
        self::assertInstanceOf(Integral::class, $bare->type->descriptor);
        self::assertSame(IntegralKind::BigInt, $bare->type->descriptor->kind);
        self::assertSame(Nullability::Nullable, $bare->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarResolvesWithoutRegardToLetterCase(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull)]);
        $operation = $semantics->analyze('SELECT A FROM t', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $fact = $operation->facts->scalar($item->expression);

        self::assertInstanceOf(ResolvedColumn::class, $fact->resolution);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertSame('A', $operation->field(0)->name?->value);
    }

    public function testDeriveScalarDependsOnTheDeclarationOfAnUndeclaredRelation(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $fact = $operation->facts->scalar($item->expression);

        self::assertInstanceOf(Dependent::class, $fact->type);
        self::assertCount(1, $fact->type->missing);
        self::assertSame('the declaration of relation t', $fact->type->missing[0]->describe());
        self::assertSame(Nullability::Dependent, $fact->nullability);
        self::assertInstanceOf(ConditionalColumn::class, $fact->resolution);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarReportsAMissingColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('SELECT c FROM t', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $fact = $operation->facts->scalar($item->expression);

        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertInstanceOf(MissingColumn::class, $fact->type->cause);
        self::assertSame('c', $fact->type->cause->name->value);
        self::assertSame(Nullability::Dependent, $fact->nullability);
        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveScalarReportsAQualifierThatNamesNoRelationEvenInAnOpenContext(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT x.a FROM t');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $fact = $operation->facts->scalar($item->expression);

        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertInstanceOf(MissingColumn::class, $fact->type->cause);
        self::assertSame('x', $fact->type->cause->qualifier?->name->value);
        self::assertCount(1, $operation->facts->diagnostics);
    }

    public function testDeriveScalarReportsAnAmbiguousColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int)), new Column(new Name('a'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('SELECT a FROM t', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $fact = $operation->facts->scalar($item->expression);

        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertInstanceOf(AmbiguousColumn::class, $fact->type->cause);
        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(AmbiguousColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveScalarAnswersTheFactsOfTheItemAnAliasNames(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull)]);
        $operation = $semantics->analyze('SELECT a + 1 AS x FROM t HAVING x > 0', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $having = $select->having;
        self::assertInstanceOf(Comparison::class, $having);
        $fact = $operation->facts->scalar($having->left);

        self::assertInstanceOf(AliasTarget::class, $fact->resolution);
        self::assertSame($operation->field('x'), $fact->resolution->field);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheQualifiersAndTheNameAsWritten(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('select shop.T.A from shop.t');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $use = $item->expression;
        self::assertInstanceOf(ColumnUse::class, $use);

        self::assertSame('A', $use->name->value);
        self::assertSame('T', $use->qualifier?->name->value);
        self::assertSame('shop', $use->qualifier->schema?->value);
        self::assertSame('SELECT shop.T.A FROM shop.t', $operation->toString());
        self::assertSame('SELECT `a b` FROM t', (new Semantics(Dialect::MySql))->analyze('SELECT `a b` FROM t')->toString());
    }

    public function testRejectsACatalogQualifier(): void
    {
        $this->expectExceptionMessage('A column is qualified by a table and at most a database.');

        new ColumnUse(new Name('a'), new QualifiedName(new Name('t'), new Name('db'), new Name('catalog')));
    }
}
