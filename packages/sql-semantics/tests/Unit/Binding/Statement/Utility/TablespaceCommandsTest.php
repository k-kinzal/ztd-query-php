<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Utility\TablespaceCommands;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Tablespace as Statement;
use SqlSemantics\Model\Statement\Origin;
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
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
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
    #[TestWith(["ALTER TABLESPACE t SET (seq_page_cost = '-1')"])]
    #[TestWith(["ALTER TABLESPACE t SET (seq_page_cost = 'abc')"])]
    #[TestWith(["ALTER TABLESPACE t SET (random_page_cost = 'NaN')"])]
    #[TestWith(["ALTER TABLESPACE t SET (random_page_cost = 'infinity')"])]
    #[TestWith(['ALTER TABLESPACE t SET (random_page_cost = 1e400)'])]
    #[TestWith(['CREATE TABLESPACE t LOCATION \'/x\' WITH (effective_io_concurrency = 1001)'])]
    #[TestWith(["ALTER TABLESPACE t SET (maintenance_io_concurrency = '08')"])]
    #[TestWith(["ALTER TABLESPACE t SET (maintenance_io_concurrency = '1000.6')"])]
    #[TestWith(['ALTER TABLESPACE t SET (seq_page_cost = 1, seq_page_cost = 2)'])]
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

    #[TestWith(['DROP TABLESPACE IF EXISTS t', true])]
    #[TestWith(['DROP TABLESPACE t', false])]
    public function testBindReadsIfExistsOfADrop(string $sql, bool $ifExists): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(Statement\DropTablespaceStatement::class, $statement);
        self::assertSame($ifExists, $statement->ifExists);
    }

    public function testCreateReadsTheLocationOwnerAndParameters(): void
    {
        $node = (new DialectParser(Dialect::PostgreSql))->parse("CREATE TABLESPACE t OWNER u LOCATION '/x' WITH (seq_page_cost = 2, random_page_cost = 3)")->find('CreateTableSpaceStmt')[0];
        $statement = TablespaceCommands::create(new Origin('s0', $node, Dialect::PostgreSql), $node, 't', new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertSame('CREATE TABLESPACE "t" OWNER "u" LOCATION \'/x\' WITH ("seq_page_cost" = 2, "random_page_cost" = 3)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(["CREATE TABLESPACE t LOCATION 'data/x'"])]
    #[TestWith(["CREATE TABLESPACE t LOCATION 'data\\x'"])]
    public function testCreateDiagnosesRelativeLocationsWithSeparators(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::TablespaceOption->message());
        $binder->bind($sql);
    }

    public function testParametersReadsEverySignedOrQuotedConstant(): void
    {
        $node = (new DialectParser(Dialect::PostgreSql))->parse("ALTER TABLESPACE t SET (seq_page_cost = +1, random_page_cost = 2.5, effective_io_concurrency = '3')")->find('AlterTblSpcStmt')[0];
        $parameters = TablespaceCommands::parameters($node, new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertSame([['seq_page_cost'], ['random_page_cost'], ['effective_io_concurrency']], array_map(static fn ($parameter): array => $parameter->name->parts, $parameters));
        self::assertSame('ALTER TABLESPACE "t" SET ("seq_page_cost" = +1, "random_page_cost" = 2.5, "effective_io_concurrency" = \'3\')', (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TABLESPACE t SET (seq_page_cost = +1, random_page_cost = 2.5, effective_io_concurrency = '3')")));
    }

    public function testNamesReadsEveryResetParameter(): void
    {
        $node = (new DialectParser(Dialect::PostgreSql))->parse('ALTER TABLESPACE t RESET (seq_page_cost, random_page_cost)')->find('AlterTblSpcStmt')[0];
        self::assertSame([['seq_page_cost'], ['random_page_cost']], array_map(static fn ($name): array => $name->parts, TablespaceCommands::names($node, new Scope(new Identifiers(Dialect::PostgreSql)))));
    }

    public function testParametersAcceptsEveryValueThePostgreSqlReaderAccepts(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $sql = "ALTER TABLESPACE t SET (seq_page_cost = -0, random_page_cost = ' 1e3 ', effective_io_concurrency = '0x3E8', maintenance_io_concurrency = '1000.5')";
        $statement = $binder->bind($sql);
        self::assertInstanceOf(Statement\SetTablespaceOptionsStatement::class, $statement);
        self::assertSame('ALTER TABLESPACE "t" SET ("seq_page_cost" = -0, "random_page_cost" = \' 1e3 \', "effective_io_concurrency" = \'0x3E8\', "maintenance_io_concurrency" = \'1000.5\')', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
