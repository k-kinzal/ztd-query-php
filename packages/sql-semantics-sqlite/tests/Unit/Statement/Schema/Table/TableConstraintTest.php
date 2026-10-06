<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Table;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableConstraint;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TablePrimaryKey;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversNothing]
#[Medium]
final class TableConstraintTest extends TestCase
{
    public function testDeriveConstraintDerivesTheOperandsOfEachConstraint(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, PRIMARY KEY (a), CHECK (b > 0))', []);
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertContainsOnlyInstancesOf(TableConstraint::class, [$statement->constraints[0]->items[0], $statement->constraints[1]->items[0]]);
        self::assertInstanceOf(TablePrimaryKey::class, $statement->constraints[0]->items[0]);
        self::assertInstanceOf(TableCheck::class, $statement->constraints[1]->items[0]);
        self::assertTrue($operation->facts->covers($statement->constraints[0]->items[0]->terms[0]->expression));
        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }
}
