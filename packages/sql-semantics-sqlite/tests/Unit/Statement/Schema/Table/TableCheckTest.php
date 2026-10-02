<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableCheck;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(TableCheck::class)]
#[Medium]
final class TableCheckTest extends TestCase
{
    public function testDeriveConstraintResolvesColumnsAndTheRowIdentifier(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, CHECK (a < rowid))', []);
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $check = $statement->constraints[0]->items[0];
        self::assertInstanceOf(TableCheck::class, $check);
        self::assertInstanceOf(Binary::class, $check->expression);
        self::assertInstanceOf(ResolvedColumn::class, $operation->facts->scalar($check->expression->left)->resolution);
        self::assertInstanceOf(ResolvedColumn::class, $operation->facts->scalar($check->expression->right)->resolution);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveConstraintReportsANameThatIsNoColumn(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, CHECK (zz > 0))', []);

        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testRenderKeepsTheIgnoredConflictResolution(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create table t (a, check (a > 0) on conflict fail)');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $check = $statement->constraints[0]->items[0];
        self::assertInstanceOf(TableCheck::class, $check);
        self::assertSame(ConflictResolution::Fail, $check->conflict);
        self::assertSame('CREATE TABLE t (a, CHECK (a > 0) ON CONFLICT FAIL)', $operation->toString());
    }
}
