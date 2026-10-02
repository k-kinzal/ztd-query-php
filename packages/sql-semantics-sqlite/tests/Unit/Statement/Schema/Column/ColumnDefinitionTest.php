<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Generated;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\NotNull;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(ColumnDefinition::class)]
#[Medium]
final class ColumnDefinitionTest extends TestCase
{
    public function testNotNullTellsWhetherTheConstraintIsWritten(): void
    {
        self::assertTrue((new ColumnDefinition(new Name('a'), null, [new NotNull()]))->notNull());
        self::assertFalse((new ColumnDefinition(new Name('a')))->notNull());
    }

    public function testPrimaryKeyAnswersTheFirstWrittenKeyConstraint(): void
    {
        $first = new ColumnPrimaryKey();
        $column = new ColumnDefinition(new Name('a'), null, [new NotNull(), $first, new ColumnPrimaryKey(null, null, true)]);

        self::assertSame($first, $column->primaryKey());
        self::assertNull((new ColumnDefinition(new Name('a')))->primaryKey());
    }

    public function testGeneratedAnswersTheGeneratedColumnExpression(): void
    {
        $generated = new Generated(new IntegerLiteral('1'));

        self::assertSame($generated, (new ColumnDefinition(new Name('a'), null, [$generated]))->generated());
        self::assertNull((new ColumnDefinition(new Name('a')))->generated());
    }

    public function testDeriveColumnDerivesTheTypeArgumentsAndTheConstraints(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a DECIMAL(10, 2) DEFAULT 0)', []);
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $type = $statement->columns[0]->type;
        self::assertNotNull($type);
        self::assertEquals(new Known(Storage::Integer), $operation->facts->scalar($type->arguments[1]->number)->type);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheNameTheTypeAndTheConstraintsInOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('create table t ("my col" unsigned big int default 1 not null)');

        self::assertSame('CREATE TABLE t (`my col` unsigned big int DEFAULT 1 NOT NULL)', $operation->toString());
    }

    public function testRenderKeepsTheQuotingOfTypeWords(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a "big int", b [x] INT, c \'text\')');

        self::assertSame('CREATE TABLE t (a "big int", b [x] INT, c \'text\')', $operation->toString());
    }
}
