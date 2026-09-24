<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\MySqlObject\Tablespaces;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Storage\TablespaceAccess;
use SqlSemantics\Model\Definition\Storage\UndoTablespaceState;
use SqlSemantics\Model\Statement\Definition\MySql\Storage as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Tablespaces::class)]
#[Medium]
final class TablespacesTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindCreatesATablespaceInEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("CREATE TABLESPACE `t s` ADD DATAFILE 'a''b' USE LOGFILE GROUP lg INITIAL_SIZE = 1M, ENGINE = NDB COMMENT 'x' WAIT");
        self::assertInstanceOf(Statement\CreateTablespaceStatement::class, $statement);
        self::assertSame(['t s', "a'b", 'lg', 1048576, 'NDB', 'x'], [$statement->name, $statement->datafile, $statement->logfileGroup, $statement->options->initialSize, $statement->options->engine, $statement->options->comment]);
        $expected = "CREATE TABLESPACE `t s` ADD DATAFILE 'a''b' USE LOGFILE GROUP `lg` INITIAL_SIZE = 1048576 ENGINE = `NDB` COMMENT = 'x' WAIT";
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindUndoTablespaceForms(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $create = $binder->bind("CREATE UNDO TABLESPACE u ADD DATAFILE 'u.ibu' ENGINE InnoDB");
        $alter = $binder->bind('ALTER UNDO TABLESPACE u SET INACTIVE');
        self::assertInstanceOf(Statement\CreateUndoTablespaceStatement::class, $create);
        self::assertInstanceOf(Statement\AlterUndoTablespaceStatement::class, $alter);
        self::assertSame(['u.ibu', 'InnoDB', UndoTablespaceState::Inactive], [$create->datafile, $create->engine, $alter->state]);
    }

    #[TestWith(['mysql-5.6.51', 'ALTER TABLESPACE ts READ_ONLY', TablespaceAccess::ReadOnly])]
    #[TestWith(['mysql-5.7.44', 'ALTER TABLESPACE ts NOT ACCESSIBLE', TablespaceAccess::NotAccessible])]
    public function testBindLegacyAccessModes(string $version, string $sql, TablespaceAccess $access): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql);
        self::assertInstanceOf(Statement\SetTablespaceAccessStatement::class, $statement);
        self::assertSame($access, $statement->access);
    }

    public function testBindRename(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER TABLESPACE a RENAME TO `b`');
        self::assertInstanceOf(Statement\RenameTablespaceStatement::class, $statement);
        self::assertSame(['a', 'b'], [$statement->name, $statement->newName]);
    }

    #[TestWith(['mysql-5.6.51', "ALTER TABLESPACE ts ADD DATAFILE 'f'", Statement\AddTablespaceDatafileStatement::class])]
    #[TestWith(['mysql-5.7.44', "ALTER TABLESPACE ts DROP DATAFILE 'f' MAX_SIZE 2", Statement\DropTablespaceDatafileStatement::class])]
    #[TestWith(['mysql-5.7.44', "ALTER TABLESPACE ts CHANGE DATAFILE 'f' MAX_SIZE 2", Statement\ChangeTablespaceDatafileStatement::class])]
    #[TestWith(['mysql-8.4.7', "ALTER TABLESPACE ts ENCRYPTION 'Y'", Statement\AlterTablespaceStatement::class])]
    public function testAlterSelectsTheDataFileAction(string $version, string $sql, string $class): void
    {
        self::assertSame($class, (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql)::class);
    }

    #[TestWith(['CREATE TABLESPACE ``'])]
    #[TestWith(['CREATE TABLESPACE ts USE LOGFILE GROUP ``'])]
    #[TestWith(['ALTER TABLESPACE ts RENAME TO ``'])]
    #[TestWith(['ALTER UNDO TABLESPACE `` SET ACTIVE'])]
    public function testNameRejectsAnEmptyName(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::StorageName->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
    }

    public function testDatafileDecodesTheStringAndReportsAbsence(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        self::assertSame('a\\b', Tablespaces::datafile($binder->bind("CREATE TABLESPACE ts ADD DATAFILE 'a\\\\b'")->source, new Identifiers(Dialect::MySql)));
        self::assertNull(Tablespaces::datafile($binder->bind('CREATE TABLESPACE ts')->source, new Identifiers(Dialect::MySql)));
    }

    #[TestWith(['mysql-8.4.7', 'alter undo tablespace u set inactive', 'ALTER UNDO TABLESPACE `u` SET INACTIVE'])]
    #[TestWith(['mysql-5.6.51', 'alter tablespace ts read_only', 'ALTER TABLESPACE `ts` READ_ONLY'])]
    #[TestWith(['mysql-5.7.44', 'alter tablespace ts not accessible', 'ALTER TABLESPACE `ts` NOT ACCESSIBLE'])]
    public function testBindReadsLowerCaseStates(string $version, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql)->toString());
    }

    public function testAlterAndNameReadParsedNodes(): void
    {
        $source = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("ALTER TABLESPACE ts ADD DATAFILE 'b.ibd'");
        $identifiers = new Identifiers(Dialect::MySql);
        $origin = new \SqlSemantics\Model\Statement\Origin('s0', $source, Dialect::MySql);
        self::assertInstanceOf(Statement\AddTablespaceDatafileStatement::class, Tablespaces::alter($origin, $source, ['ALTER', 'TABLESPACE', 'TS', 'ADD'], 'ts', $identifiers));
        self::assertSame('ts', Tablespaces::name($source->find('ident')[0], $identifiers));
    }
}
