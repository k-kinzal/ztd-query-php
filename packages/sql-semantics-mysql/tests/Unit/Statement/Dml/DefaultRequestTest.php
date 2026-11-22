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
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DefaultRequest::class)]
#[Medium]
final class DefaultRequestTest extends TestCase
{
    public function testDeriveScalarTakesTheAssignedColumn(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->diagnose('INSERT INTO t (b, a) VALUES (DEFAULT, DEFAULT)', [$t]);
        self::assertInstanceOf(InsertRows::class, $operation->statement);
        $fact = $operation->facts->scalar($operation->statement->rows[0]->values[1]);

        self::assertEquals(new Known(new Integral(IntegralKind::Int)), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarReportsDefaultOutsideAnAssignment(): void
    {
        $operation = (new Inspection())->diagnose('VALUES ROW(DEFAULT)');

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(WriteMisuse::class, $operation->facts->diagnostics[0]);
        self::assertSame(WriteRule::DefaultOutsideInsert, $operation->facts->diagnostics[0]->rule);
    }

    public function testRenderWritesTheKeyword(): void
    {
        self::assertSame('UPDATE t SET a = DEFAULT', (new Inspection())->diagnose('update t set a = default')->toString());
    }
}
