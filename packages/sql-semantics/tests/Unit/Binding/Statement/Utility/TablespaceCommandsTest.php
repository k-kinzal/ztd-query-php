<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\TablespaceCommands;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Tablespace as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TablespaceCommands::class)]
#[Medium]
final class TablespaceCommandsTest extends TestCase
{
    #[TestWith(["CREATE TABLESPACE t LOCATION '/x'", Statement\CreateTablespaceStatement::class])]
    #[TestWith(['ALTER TABLESPACE t SET (seq_page_cost = 1)', Statement\SetTablespaceOptionsStatement::class])]
    #[TestWith(['ALTER TABLESPACE t RESET (seq_page_cost)', Statement\ResetTablespaceOptionsStatement::class])]
    #[TestWith(['DROP TABLESPACE t', Statement\DropTablespaceStatement::class])]
    public function testBindSelectsTheConcreteOperation(string $sql, string $class): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertSame($class, $statement::class);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testCreateReadsTheOwnerAndAcceptsDriveLetterPaths(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE TABLESPACE t OWNER CURRENT_USER LOCATION 'C:\\data'");
        self::assertInstanceOf(Statement\CreateTablespaceStatement::class, $statement);
        self::assertSame(SessionRole::CurrentUser, $statement->owner);
    }

    #[TestWith(["CREATE TABLESPACE t LOCATION 'relative'"])]
    #[TestWith(["CREATE TABLESPACE t LOCATION '/it''s'"])]
    public function testCreateDiagnosesImpossibleLocations(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::TablespaceOption->message());
        $binder->bind($sql);
    }

    #[TestWith(['ALTER TABLESPACE t SET (fillfactor = 1)'])]
    #[TestWith(['ALTER TABLESPACE t SET (seq_page_cost)'])]
    #[TestWith(['ALTER TABLESPACE t SET (seq_page_cost = -1)'])]
    #[TestWith(['ALTER TABLESPACE t SET (seq_page_cost = on)'])]
    #[TestWith(['ALTER TABLESPACE t SET (a.seq_page_cost = 1)'])]
    public function testParametersDiagnosesUnknownOrNonConstantOverrides(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::TablespaceOption->message());
        $binder->bind($sql);
    }

    public function testNamesRejectsValues(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ResetParameterValue->message());
        $binder->bind('ALTER TABLESPACE t RESET (seq_page_cost = 1)');
    }
}
