<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\AlterKinds;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnActionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\NamedActionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\OwnerTo;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableAction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\TableActionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\AlterAction;
use SqlSemantics\Statement\Declaration\RelationKind;

#[CoversClass(AlterKinds::class)]
#[Medium]
final class AlterKindsTest extends TestCase
{
    public function testCheckReportsEachActionTheKindDoesNotAccept(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE VIEW v AS SELECT 1 AS a')];
        self::assertSame([
            'ALTER action ADD COLUMN cannot be performed on relation "v"',
            'ALTER action ALTER COLUMN ... SET NOT NULL cannot be performed on relation "v"',
        ], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('ALTER TABLE v ADD COLUMN b int, ALTER a SET DEFAULT 1, ALTER a SET NOT NULL, OWNER TO r', $context)->facts->diagnostics));
    }

    public function testCheckReportsTheTargetKindFirst(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE TABLE t (a int)')];
        self::assertSame(['"t" is not a view'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('ALTER VIEW t ALTER a SET NOT NULL', $context)->facts->diagnostics));
    }

    public function testParentReportsAParentThatCannotBeInherited(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE TABLE t (a int)'), $semantics->analyze('CREATE SEQUENCE s')];
        self::assertSame(['ALTER action INHERIT cannot be performed on relation "s"'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('ALTER TABLE t INHERIT s', $context)->facts->diagnostics));
    }

    public function testForeignReportsKeysAddedToAForeignTable(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE FOREIGN TABLE f (a int) SERVER x')];
        self::assertSame([
            'unique constraints are not supported on foreign tables',
            'primary key constraints are not supported on foreign tables',
        ], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('ALTER TABLE f ADD COLUMN b int UNIQUE, ADD PRIMARY KEY (a), ADD CHECK (a > 0)', $context)->facts->diagnostics));
    }

    public function testAcceptedAnswersNullForOwnerTo(): void
    {
        $statement = (new Semantics(Dialect::PostgreSql))->analyze('ALTER TABLE t OWNER TO r')->statement;
        self::assertInstanceOf(AlterTable::class, $statement);
        self::assertInstanceOf(OwnerTo::class, $statement->commands[0]);
        self::assertNull((new AlterKinds())->accepted($statement->commands[0]));
    }

    public function testOtherAnswersTheActionOfAColumnChange(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t ALTER a SET STORAGE plain, ALTER a SET COMPRESSION pglz')->statement;
        self::assertInstanceOf(AlterTable::class, $statement);
        self::assertSame([
            [AlterAction::SetStorage, [RelationKind::BaseTable, RelationKind::MaterializedView, RelationKind::ForeignTable]],
            [AlterAction::SetCompression, [RelationKind::BaseTable, RelationKind::MaterializedView]],
        ], [(new AlterKinds())->other($statement->commands[0]), (new AlterKinds())->other($statement->commands[1] ?? $statement->commands[0])]);
    }

    public function testRelationWideAnswersTheActionOfARelationOption(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t RESET (fillfactor)')->statement;
        self::assertInstanceOf(AlterTable::class, $statement);
        self::assertSame([AlterAction::ResetRelationOptions, [RelationKind::BaseTable, RelationKind::View, RelationKind::MaterializedView]], (new AlterKinds())->relationWide($statement->commands[0]));
    }

    public function testColumnAnswersTheActionOfAColumnAction(): void
    {
        self::assertSame([AlterAction::SetDefault, [RelationKind::BaseTable, RelationKind::View, RelationKind::ForeignTable]], (new AlterKinds())->column(ColumnActionKind::DropDefault));
    }

    public function testTableAnswersTheActionOfATableAction(): void
    {
        self::assertSame([AlterAction::SetLogged, [RelationKind::BaseTable, RelationKind::Sequence]], (new AlterKinds())->table(TableActionKind::SetLogged));
        self::assertSame([AlterAction::NotOf, [RelationKind::BaseTable]], (new AlterKinds())->accepted(new TableAction(TableActionKind::NotOf)));
    }

    public function testNamedAnswersTheActionOfANamedAction(): void
    {
        self::assertSame([AlterAction::ClusterOn, [RelationKind::BaseTable, RelationKind::MaterializedView]], (new AlterKinds())->named(NamedActionKind::ClusterOn));
    }
}
