<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(WriteTarget::class)]
#[Medium]
final class WriteTargetTest extends TestCase
{
    public function testNameAnswersTheTableName(): void
    {
        $target = new WriteTarget(new QualifiedName(new Name('t'), new Name('shop')));

        self::assertSame('shop', $target->name()->schema?->value);
    }

    public function testAliasAnswersTheCorrelationName(): void
    {
        $target = new WriteTarget(new QualifiedName(new Name('t')), new Name('x'));

        self::assertSame('x', $target->alias()?->value);
    }

    public function testDeriveRelationResolvesTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('DELETE FROM t WHERE a = 1', [$t]);
        self::assertInstanceOf(Delete::class, $operation->statement);
        $fact = $operation->facts->relation($operation->statement->table);

        self::assertCount(2, $fact->shape->slots);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveRelationReportsACommonTable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT 1) DELETE FROM c');

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertSame('The target table is a common table expression, which is not updatable', $operation->facts->diagnostics[0]->message());
    }

    public function testRenderWritesNameAliasAndPartitions(): void
    {
        self::assertSame('DELETE FROM shop.t x PARTITION (p0, p1) WHERE x.a = 1', (new Semantics(Dialect::MySql))->analyze('delete from shop.t x partition (p0, p1) where x.a = 1')->toString());
    }

    public function testRenderRejectsACatalog(): void
    {
        $this->expectExceptionMessage('A table is qualified by at most a database.');

        new WriteTarget(new QualifiedName(new Name('t'), new Name('s'), new Name('c')));
    }
}
