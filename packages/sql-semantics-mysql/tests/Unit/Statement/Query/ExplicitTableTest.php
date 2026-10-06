<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ExplicitTable::class)]
#[Medium]
final class ExplicitTableTest extends TestCase
{
    public function testNameAnswersTheTable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('TABLE shop.t');

        self::assertInstanceOf(ExplicitTable::class, $operation->statement);
        self::assertSame('t', $operation->statement->name()->name->value);
        self::assertSame('shop', $operation->statement->name()->schema?->value);
    }

    public function testAliasAnswersNoCorrelationName(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('TABLE t');

        self::assertInstanceOf(ExplicitTable::class, $operation->statement);
        self::assertNull($operation->statement->alias());
    }

    public function testDeriveStatementRecordsTheColumnsAsTheOutput(): void
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
        $operation = $semantics->analyze('TABLE t', [$t]);

        self::assertInstanceOf(ExplicitTable::class, $operation->statement);
        self::assertSame($operation->facts->query($operation->statement), $operation->facts->output);
        self::assertSame($t->columns[1], $operation->field('b')->column());
    }

    public function testDeriveQueryAnswersEveryColumnOfTheTable(): void
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
        $operation = $semantics->analyze('TABLE t', [$t]);

        self::assertSame(['a', 'b'], array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []));
        self::assertSame(Nullability::Nullable, $operation->field('b')->nullability);
    }

    public function testDeriveRelationDependsOnAnUndeclaredTable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('TABLE t');

        self::assertInstanceOf(ExplicitTable::class, $operation->statement);
        self::assertInstanceOf(UndeclaredTable::class, $operation->facts->relation($operation->statement)->table);
        self::assertNull($operation->fields());
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('TABLE shop.t', (new Semantics(Dialect::MySql))->analyze('table shop.t')->toString());
    }
}
