<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\MultiplePrimaryKeys;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CreateTable::class)]
#[Medium]
final class CreateTableTest extends TestCase
{
    public function testTemporaryTellsWhetherTemporaryIsWritten(): void
    {
        $twice = (new Inspection('5.7.44'))->diagnose('CREATE TEMPORARY TEMPORARY TABLE t (a INT)')->statement;
        $never = (new Inspection())->diagnose('CREATE TABLE t (a INT)')->statement;
        self::assertInstanceOf(CreateTable::class, $twice);
        self::assertInstanceOf(CreateTable::class, $never);

        self::assertTrue($twice->temporary());
        self::assertSame(2, $twice->temporaryWords);
        self::assertFalse($never->temporary());
    }

    public function testDeriveStatementDerivesTheElementsInTheTable(): void
    {
        $create = (new Inspection())->diagnose('CREATE TABLE t (a INT, b INT, CHECK (a < b))');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $check = $statement->elements[2];

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint::class, $check);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Comparison::class, $check->condition);
        $resolution = $create->facts->scalar($check->condition->right)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($create->declarations()[0]->columns[1], $resolution->slot->column);
    }

    public function testDeriveStatementReportsTheProblems(): void
    {
        $create = (new Inspection())->diagnose('CREATE TABLE t ENGINE = InnoDB');
        $keys = (new Inspection())->diagnose('CREATE TABLE t (a INT PRIMARY KEY, PRIMARY KEY (a))');

        self::assertInstanceOf(NoColumns::class, $create->facts->diagnostics[0]);
        self::assertInstanceOf(MultiplePrimaryKeys::class, $keys->facts->diagnostics[0]);
    }

    public function testDeriveRelationProvidesTheDeclaration(): void
    {
        $create = (new Inspection())->diagnose('CREATE TABLE t (b INT NOT NULL, c TEXT) SELECT 1 AS a, 2 AS b');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $table = $create->declarations()[0];
        $fact = $create->facts->relation($statement);

        self::assertInstanceOf(DeclaredTable::class, $fact->table);
        self::assertSame($table, $fact->table->table);
        self::assertSame(['c', 'a', 'b'], [$table->columns[0]->name->value, $table->columns[1]->name->value, $table->columns[2]->name->value]);
        self::assertSame(Nullability::NotNull, $table->columns[2]->nullability);
    }

    public function testRenderWritesTheDefinition(): void
    {
        self::assertSame('CREATE TEMPORARY TABLE IF NOT EXISTS db.t (a INT) ENGINE InnoDB COMMENT \'x\' REPLACE AS SELECT 1 AS a', (new Inspection())->diagnose('CREATE TEMPORARY TABLE IF NOT EXISTS db.t (a INT) ENGINE = InnoDB COMMENT = \'x\' REPLACE AS SELECT 1 AS a')->toString());
    }
}
