<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Column;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnConstraint;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultExpression;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Generated;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\NonConstantDefault;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversNothing]
#[Medium]
final class ColumnConstraintTest extends TestCase
{
    public function testDeriveConstraintDerivesEachKindOfOperandInItsOwnScope(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a CHECK (rowid > 0), b AS (rowid), c DEFAULT (a))', []);
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertContainsOnlyInstancesOf(ColumnConstraint::class, [$statement->columns[0]->constraints[0], $statement->columns[1]->constraints[0], $statement->columns[2]->constraints[0]]);
        self::assertInstanceOf(ColumnCheck::class, $statement->columns[0]->constraints[0]);
        self::assertInstanceOf(Generated::class, $statement->columns[1]->constraints[0]);
        self::assertInstanceOf(DefaultExpression::class, $statement->columns[2]->constraints[0]);
        self::assertCount(3, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[1]);
        self::assertInstanceOf(NonConstantDefault::class, $operation->facts->diagnostics[2]);
    }
}
