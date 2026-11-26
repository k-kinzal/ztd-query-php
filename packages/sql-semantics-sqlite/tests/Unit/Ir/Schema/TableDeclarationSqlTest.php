<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\ColumnGeneration;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\Storage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyTerm;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\UniqueKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\NotNullConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\CheckConstraint;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ComparisonSpelling;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\DefinitionSource;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate\ImplicitCheckLabel;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableDeclarationSql;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;

#[CoversClass(TableDeclarationSql::class)]
#[Small]
final class TableDeclarationSqlTest extends TestCase
{
    public function testAdditionNamesBothPriorAndAppendedSlotsInConstraintOrder(): void
    {
        $prior = new Identifier('operand');
        $column = new TableColumn(new Identifier('appended'), DeclaredDatatypeName::Integer);
        $first = new Comparison(ComparisonOperator::Greater, new ColumnReference(1), new ColumnReference(0));
        $checks = [
            new CheckConstraint(new Identifier('minimum'), $first, 1),
            new CheckConstraint(new Identifier('upper"bound'), new ColumnReference(1), 1),
        ];

        self::assertSame(
            '"appended" INTEGER CONSTRAINT "minimum" CHECK (("appended" > "operand")) CONSTRAINT "upper""bound" CHECK ("appended")',
            (new TableDeclarationSql())->addition($column, [$prior, $column->name], $checks),
        );
    }

    public function testDeclarationsPlaceOrderedKeysAndCheckedExpressions(): void
    {
        $column = new TableColumn(new Identifier('code'), collation: Collation::NoCase);
        $inline = new UniqueKey([new KeyTerm(0, collation: Collation::NoCase)], inline: true);
        $table = new UniqueKey([new KeyTerm(0)], conflict: KeyConflict::Ignore);
        $check = new CheckConstraint(new Identifier('a"b'), new ColumnReference(0), 0);

        self::assertSame(
            ['"code" COLLATE NOCASE UNIQUE CONSTRAINT "a""b" CHECK ("code")', 'UNIQUE ("code" COLLATE BINARY) ON CONFLICT IGNORE'],
            (new TableDeclarationSql())->declarations([$column], [$inline, $table], [$check])
        );
    }

    public function testCheckClauseRetainsImplicitRenameBehavior(): void
    {
        $column = new Identifier('Id');
        $condition = new Comparison(ComparisonOperator::Equal, new ColumnReference(0), new IntegerLiteral('1'));
        $label = new ImplicitCheckLabel($column, ComparisonSpelling::DoubleEqual, false, ' ', '', 1);
        $check = new CheckConstraint($label, $condition);

        self::assertSame('CHECK (Id ==01)', (new TableDeclarationSql())->checkClause($check, new SelectSql(), new DefinitionSource([$column])));
    }

    public function testColumnWritesTypeDefaultAndConflictPolicy(): void
    {
        $name = new Identifier('c');
        $column = new TableColumn($name, DeclaredDatatypeName::Blob, NotNullConflict::Replace, new NullLiteral());

        self::assertSame(
            '"c" BLOB NOT NULL ON CONFLICT REPLACE DEFAULT NULL',
            (new TableDeclarationSql())->column($column, new SelectSql(), new DefinitionSource([$name])),
        );
    }

    public function testColumnWritesTheExpressionOverItsResolvedSlot(): void
    {
        $name = new Identifier('derived');
        $value = new ColumnGeneration(Storage::Stored, new ColumnReference(1));
        $column = new TableColumn($name, DeclaredDatatypeName::Integer, generated: $value);

        self::assertSame(
            '"derived" INTEGER GENERATED ALWAYS AS ("source") STORED',
            (new TableDeclarationSql())->column($column, new SelectSql(), new DefinitionSource([$name, new Identifier('source')])),
        );
    }
}
