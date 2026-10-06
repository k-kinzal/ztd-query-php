<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\ColumnRule;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Collation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnUnique;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ConstraintName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultExpression;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultWord;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Generated;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\NotNull;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\NullAllowed;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\Deferrability;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ForeignKeyClause;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;

#[CoversClass(ColumnRule::class)]
#[Medium]
final class ColumnRuleTest extends TestCase
{
    public function testColumnLowersTheNameTheTypeAndTheConstraints(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (price DECIMAL(10, 2) NOT NULL, plain)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertSame('price', $statement->columns[0]->name->value);
        self::assertCount(2, $statement->columns[0]->type->arguments ?? []);
        self::assertCount(1, $statement->columns[0]->constraints);
        self::assertNull($statement->columns[1]->type);
        self::assertSame([], $statement->columns[1]->constraints);
    }

    public function testConstraintsKeepTheirWrittenOrder(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a UNIQUE NOT NULL CONSTRAINT c CHECK (a > 0) COLLATE nocase)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertSame([ColumnUnique::class, NotNull::class, ConstraintName::class, ColumnCheck::class, Collation::class], array_map(static fn (object $constraint): string => $constraint::class, $statement->columns[0]->constraints));
    }

    public function testConstraintLowersEveryKindOfConstraint(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a NULL PRIMARY KEY REFERENCES p DEFERRABLE, b GENERATED ALWAYS AS (a), c AS (a))')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertSame([NullAllowed::class, ColumnPrimaryKey::class, ForeignKeyClause::class, Deferrability::class], array_map(static fn (object $constraint): string => $constraint::class, $statement->columns[0]->constraints));
        self::assertInstanceOf(Generated::class, $statement->columns[2]->constraints[0]);
    }

    public function testDefaultLowersEveryFormOfDefault(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a DEFAULT 1, b DEFAULT +1, c DEFAULT -1, d DEFAULT (1), e DEFAULT word)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $defaults = array_map(static fn (object $column): object => $column->constraints[0], $statement->columns);
        self::assertSame([DefaultLiteral::class, DefaultLiteral::class, DefaultLiteral::class, DefaultExpression::class, DefaultWord::class], array_map(static fn (object $default): string => $default::class, $defaults));
        self::assertSame([null, NumberSign::Plus, NumberSign::Minus], array_map(static fn (object $default): ?NumberSign => $default instanceof DefaultLiteral ? $default->sign : null, array_slice($defaults, 0, 3)));
    }

    public function testScannedAnswersWhatFollowsTheEmptyMarker(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("CREATE TABLE t (a DEFAULT 'x')");

        self::assertSame("CREATE TABLE t (a DEFAULT 'x')", $operation->toString());
    }

    public function testAutoincrementLowersTheOptionalKeyword(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a INTEGER PRIMARY KEY AUTOINCREMENT, b PRIMARY KEY)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertTrue($statement->columns[0]->primaryKey()?->autoincrement);
        self::assertFalse($statement->columns[1]->primaryKey()?->autoincrement);
    }

    public function testGeneratedLowersTheExpressionAndTheOptionalWord(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, b AS (a), c AS (a) STORED)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertNull($statement->columns[1]->generated()?->word);
        self::assertSame('STORED', $statement->columns[2]->generated()?->word?->name->value);
    }
}
