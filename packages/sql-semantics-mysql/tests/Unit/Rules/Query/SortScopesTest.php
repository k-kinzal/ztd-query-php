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
    public function testCheckRefusesAnIntegerItem(): void
    {
        $this->expectExceptionMessage('An integer in ORDER BY or GROUP BY is a select list position.');

        (new SortScopes())->check([new OrderItem(new NumberLiteral('2'))]);
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

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(AliasTarget::class, $operation->facts->scalar($operation->statement->orderBy[0]->expression)->resolution);
        self::assertInstanceOf(AliasTarget::class, $operation->facts->scalar($operation->statement->orderBy[1]->expression)->resolution);
        $resolution = $operation->facts->scalar($operation->statement->orderBy[2]->expression)->resolution;
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
