<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\ColumnResolver;
use SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(ColumnResolver::class)]
#[Medium]
final class ColumnResolverTest extends TestCase
{
    public function testFindPrefersAColumnOfTheFromClauseToAnAlias(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int)), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('SELECT b AS a FROM t GROUP BY a HAVING a > 0', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $group = $select->groupBy;
        self::assertNotNull($group);
        $resolution = $operation->facts->scalar($group->items[0]->expression)->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($table->columns[0], $resolution->declaration());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testFindFallsBackToTheAliasOfASelectListItem(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('SELECT a AS x FROM t GROUP BY x ORDER BY x + 1', [$table]);
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $group = $select->groupBy;
        self::assertNotNull($group);
        $resolution = $operation->facts->scalar($group->items[0]->expression)->resolution;

        self::assertInstanceOf(AliasTarget::class, $resolution);
        self::assertSame($operation->field('x'), $resolution->field);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testFindReportsAnAliasOfSeveralDifferentItems(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int)), new Column(new Name('b'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('SELECT a AS x, b AS x FROM t GROUP BY x', [$table]);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(AmbiguousAlias::class, $operation->facts->diagnostics[0]);
        self::assertSame('x', $operation->facts->diagnostics[0]->name->value);
    }

    public function testFindIsConditionalWhileAnUndeclaredRelationCouldOwnTheName(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 AS x FROM u GROUP BY x');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $group = $select->groupBy;
        self::assertNotNull($group);
        $resolution = $operation->facts->scalar($group->items[0]->expression)->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $resolution);
        self::assertSame([], $resolution->candidates);
    }

    public function testFindIgnoresAliasesForAQualifiedName(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int))]);
        $operation = $semantics->analyze('SELECT a AS x FROM t', [$table]);

        self::assertInstanceOf(MissingColumn::class, (new ColumnResolver())->find(new Environment($operation->context, null, [], [], [$operation->field('x')]), new Name('x'), new QualifiedName(new Name('t'))));
        self::assertInstanceOf(AliasTarget::class, (new ColumnResolver())->find(new Environment($operation->context, null, [], [], [$operation->field('x')]), new Name('X')));
    }

    public function testAliasTreatsItemsComputingTheSameExpressionAsOne(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 AS x, 1 AS x, 2 AS x', []);
        $fields = $operation->fields()->items ?? [];

        self::assertInstanceOf(AliasTarget::class, (new ColumnResolver())->alias(new Name('x'), [$fields[0], $fields[1]]));
        $single = (new ColumnResolver())->alias(new Name('x'), [$fields[2]]);
        self::assertInstanceOf(AliasTarget::class, $single);
        self::assertSame($fields[2], $single->field);
        $ambiguous = (new ColumnResolver())->alias(new Name('x'), [$fields[0], $fields[1], $fields[2]]);
        self::assertInstanceOf(AmbiguousAlias::class, $ambiguous);
        self::assertCount(3, $ambiguous->candidates);
    }
}
