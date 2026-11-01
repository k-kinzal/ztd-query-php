<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\PrimaryKeyProblem;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableUnique;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(TableUnique::class)]
#[Medium]
final class TableUniqueTest extends TestCase
{
    public function testDeriveConstraintResolvesTheKeyColumns(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, b, UNIQUE (a, b COLLATE nocase))', []);
        $statement = $operation;

        self::assertInstanceOf(CreateTable::class, $statement);
        $unique = $statement->constraints[0]->items[0];
        self::assertInstanceOf(TableUnique::class, $unique);
        self::assertTrue($unique->terms[1]->expression->hasBinding());
        self::assertSame([], $operation->diagnostics());
    }

    public function testDeriveConstraintReportsAMissingColumnAndAnExpressionTerm(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, UNIQUE (zz, a + 1))', []);

        self::assertCount(2, $operation->diagnostics());
        self::assertInstanceOf(MissingColumn::class, $operation->diagnostics()[0]);
        self::assertInstanceOf(PrimaryKeyProblem::class, $operation->diagnostics()[1]);
    }

    public function testRenderKeepsTheTermsAndTheConflictResolution(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create table t (a, b, unique (a asc, b) on conflict ignore)');
        $statement = $operation;

        self::assertInstanceOf(CreateTable::class, $statement);
        $unique = $statement->constraints[0]->items[0];
        self::assertInstanceOf(TableUnique::class, $unique);
        self::assertSame(ConflictResolution::Ignore, $unique->conflict);
        self::assertSame('CREATE TABLE t (a, b, UNIQUE (a ASC, b) ON CONFLICT IGNORE)', $operation->toString());
    }

    public function testRefusesAStringLiteralTermThatNamesAColumn(): void
    {
        $this->expectExceptionMessage('A string literal written as a key term names a column: write it as a LiteralColumn.');

        new TableUnique([new \SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm(new \SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped(new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral('a')))]);
    }
}
