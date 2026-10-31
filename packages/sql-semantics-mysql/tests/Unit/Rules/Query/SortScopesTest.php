<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\SortScopes;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(SortScopes::class)]
#[Medium]
final class SortScopesTest extends TestCase
{
    public function testItemsRetainsTheDeferredSortRole(): void
    {
        $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $item = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem($row);
        self::assertSame([$item], (new SortScopes())->items([$item]));
        self::assertNull((new SortScopes())->word($row));
    }

    public function testItemsRefusesAnUnrelatedNode(): void
    {
        $this->expectExceptionMessage('A query ordering retains scalar, ordinal or deferred operands.');
        (new SortScopes())->items([new NumberLiteral('1')]);
    }

    public function testCheckRefusesAnIntegerItem(): void
    {
        $this->expectExceptionMessage('An integer in ORDER BY or GROUP BY is a select list position.');

        (new SortScopes())->check([new OrderItem(new NumberLiteral('2'))]);
    }

    public function testCheckRefusesAGroupedUnaryIntegerItem(): void
    {
        $this->expectExceptionMessage('An integer in ORDER BY or GROUP BY is a select list position.');
        (new SortScopes())->check([new OrderItem(new Grouped(new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Plus, new NumberLiteral('2'))))]);
    }

    public function testProblemDistinguishesFixedAndCatalogDependentPositions(): void
    {
        $item = new OrderItem(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new NumberLiteral('2')));
        $fixed = new Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new NumberLiteral('1'))], orderBy: [$item]);
        self::assertSame('2', (new SortScopes())->problem($fixed)?->name());
        $open = new Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t'))), orderBy: [$item]);
        self::assertNull((new SortScopes())->problem($open));
        $zero = new Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('t'))), groupBy: new \SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping([new OrderItem(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new NumberLiteral('0')))]));
        self::assertTrue((new SortScopes())->problem($zero)?->grouped);
    }

    public function testBoundedUsesTheWidthOfTheLexicalProjection(): void
    {
        $ordinal = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new NumberLiteral('2'));
        $query = new Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()], new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new QualifiedName(new Name('c'))), orderBy: [new OrderItem($ordinal)]);
        self::assertSame($ordinal, (new SortScopes())->bounded($query, 1)?->ordinal);
        self::assertNull((new SortScopes())->bounded($query, 2));
        self::assertNull((new SortScopes())->bounded($query, null));
    }

    public function testPositionPreservesOnlyUnsignedIntegerOperands(): void
    {
        $literal = new NumberLiteral('02');
        self::assertSame($literal, (new SortScopes())->position(new Grouped(new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Plus, $literal))));
        self::assertNull((new SortScopes())->position(new Grouped(new NumberLiteral('2.0'))));
        self::assertNull((new SortScopes())->position(new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Minus, $literal)));
        self::assertNull((new SortScopes())->position(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal($literal)));
    }

    public function testDeriveResolvesOrdinalsAliasesAndColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('SELECT b AS a FROM t ORDER BY a, 1, b', [$t]);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(Select::class, $operation->query);
        self::assertInstanceOf(AliasTarget::class, $operation->query->orderBy[0]->expression->scalarInformation()->resolution);
        self::assertInstanceOf(AliasTarget::class, $operation->query->orderBy[1]->expression->scalarInformation()->resolution);
        $resolution = $operation->query->orderBy[2]->expression->scalarInformation()->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($t->columns[1], $resolution->declaration());
    }

    public function testWordAnswersABareUnqualifiedName(): void
    {
        self::assertSame('a', (new SortScopes())->word(new Grouped(new ColumnUse(new Name('a'))))?->value);
        self::assertNull((new SortScopes())->word(new ColumnUse(new Name('a'), new QualifiedName(new Name('t')))));
        self::assertNull((new SortScopes())->word(new NumberLiteral('1')));
    }
}
