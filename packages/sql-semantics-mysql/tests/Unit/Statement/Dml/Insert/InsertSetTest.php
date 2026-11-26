<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ColumnList;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\DuplicateColumn;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(InsertSet::class)]
#[Medium]
final class InsertSetTest extends TestCase
{
    public function testDeriveStatementReportsAColumnAssignedTwice(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull), new Column(new Name('b'), new Integral(IntegralKind::BigInt))]);
        $operation = $semantics->diagnose('INSERT INTO t SET a = 1, b = a, a = 2', [$t]);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(DuplicateColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testRenderWritesTheAssignments(): void
    {
        self::assertSame('REPLACE INTO t SET a = 1', (new Inspection('mysql-5.6.51'))->diagnose('replace t set a = 1')->toString());
    }

    public function testRenderRejectsAColumnList(): void
    {
        $this->expectExceptionMessage('INSERT ... SET names its columns in the assignments.');

        new InsertSet(new InsertInto(false, null, false, new WriteTarget(new QualifiedName(new Name('t'))), new ColumnList([])), [new Assignment(new ColumnUse(new Name('a')), new NumberLiteral('1'))]);
    }
}
