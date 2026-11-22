<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Delete::class)]
#[Medium]
final class DeleteTest extends TestCase
{
    public function testDeriveStatementSeesTheTableUnderItsAlias(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->inspect('DELETE FROM t AS x WHERE x.a = 1 ORDER BY b LIMIT 1', [$t]);
        self::assertInstanceOf(Delete::class, $operation->statement);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('DELETE LOW_PRIORITY QUICK IGNORE FROM t PARTITION (p) WHERE a = 1 ORDER BY b LIMIT 5', (new Inspection('mysql-5.6.51'))->inspect('delete low_priority quick ignore from t partition (p) where a = 1 order by b limit 5')->toString());
    }
}
