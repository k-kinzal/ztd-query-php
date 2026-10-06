<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(DefaultRequest::class)]
#[Medium]
final class DefaultRequestTest extends TestCase
{
    public function testDeriveScalarTakesTheAssignedColumn(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->analyze('INSERT INTO t (b, a) VALUES (DEFAULT, DEFAULT)', [$t]);
        self::assertInstanceOf(InsertRows::class, $operation->statement);
        $fact = $operation->facts->scalar($operation->statement->rows[0]->values[1]);

        self::assertEquals(new Known(new Integral(IntegralKind::Int)), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarReportsDefaultOutsideAnAssignment(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('VALUES ROW(DEFAULT)');

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(WriteMisuse::class, $operation->facts->diagnostics[0]);
        self::assertSame(WriteRule::DefaultOutsideInsert, $operation->facts->diagnostics[0]->rule);
    }

    public function testRenderWritesTheKeyword(): void
    {
        self::assertSame('UPDATE t SET a = DEFAULT', (new Semantics(Dialect::MySql))->analyze('update t set a = default')->toString());
    }
}
