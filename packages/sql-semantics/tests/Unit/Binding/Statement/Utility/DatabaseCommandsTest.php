<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\DatabaseCommands;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseOption;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseParameter;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Database as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DatabaseCommands::class)]
#[Medium]
final class DatabaseCommandsTest extends TestCase
{
    #[TestWith(['CREATE DATABASE app', Statement\CreateDatabaseStatement::class])]
    #[TestWith(['DROP DATABASE app', Statement\DropDatabaseStatement::class])]
    #[TestWith(['ALTER DATABASE app SET work_mem TO 1', Statement\AlterDatabaseSetStatement::class])]
    #[TestWith(['ALTER DATABASE app RESET work_mem', Statement\AlterDatabaseResetStatement::class])]
    #[TestWith(['ALTER DATABASE app IS_TEMPLATE on', Statement\AlterDatabaseOptionsStatement::class])]
    public function testBindSelectsTheConcreteOperation(string $sql, string $class): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertSame($class, $statement::class);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    #[TestWith(['CREATE DATABASE app WITH OWNER x OWNER y'])]
    #[TestWith(['CREATE DATABASE app "OWNER" x'])]
    #[TestWith(['CREATE DATABASE app LOCALE a LC_CTYPE b'])]
    #[TestWith(['CREATE DATABASE app CONNECTION LIMIT 1.5'])]
    #[TestWith(['CREATE DATABASE app OID 100'])]
    #[TestWith(['CREATE DATABASE app STRATEGY copy'])]
    #[TestWith(['ALTER DATABASE app OWNER = alice'])]
    #[TestWith(['ALTER DATABASE app ALLOW_CONNECTIONS maybe'])]
    public function testBindDiagnosesImpossibleProperties(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::DatabaseOption->message());
        $binder->bind($sql);
    }

    #[TestWith(['ALTER DATABASE app SET TABLESPACE fast', Statement\SetDatabaseTablespaceStatement::class])]
    #[TestWith(['ALTER DATABASE app WITH TABLESPACE = fast', Statement\SetDatabaseTablespaceStatement::class])]
    #[TestWith(['ALTER DATABASE app REFRESH COLLATION VERSION', Statement\RefreshDatabaseCollationStatement::class])]
    #[TestWith(['ALTER DATABASE app', Statement\AlterDatabaseOptionsStatement::class])]
    public function testAlterationSeparatesMovesAndRefreshes(string $sql, string $class): void
    {
        self::assertSame($class, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)::class);
    }

    #[TestWith(['ALTER DATABASE app TABLESPACE fast CONNECTION LIMIT 1'])]
    #[TestWith(['ALTER DATABASE app TABLESPACE DEFAULT'])]
    public function testAlterationRequiresASoleNamedTablespace(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $binder->bind($sql);
    }

    public function testSettingKeepsTheStoredParameter(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app SET TIME ZONE UTC');
        self::assertInstanceOf(Statement\AlterDatabaseSetStatement::class, $statement);
        self::assertSame(['timezone'], $statement->setting->name);
    }

    public function testOptionsConvertEachArgumentToItsDomain(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE DATABASE app WITH OWNER = \"Bob\" ENCODING 6 IS_TEMPLATE 1 ALLOW_CONNECTIONS off LOCALE E'C' LOCATION 1e2 OID 4294967295");
        self::assertInstanceOf(Statement\CreateDatabaseStatement::class, $statement);
        self::assertEquals([
            new DatabaseOption(DatabaseParameter::Owner, 'Bob'),
            new DatabaseOption(DatabaseParameter::Encoding, 6),
            new DatabaseOption(DatabaseParameter::IsTemplate, true),
            new DatabaseOption(DatabaseParameter::AllowConnections, false),
            new DatabaseOption(DatabaseParameter::Locale, 'C'),
            new DatabaseOption(DatabaseParameter::Location, '1e2'),
            new DatabaseOption(DatabaseParameter::Oid, 4294967295),
        ], $statement->options);
    }

    #[TestWith([DatabaseParameter::IsTemplate, 'on', true])]
    #[TestWith([DatabaseParameter::IsTemplate, 'sometimes', 'sometimes'])]
    #[TestWith([DatabaseParameter::ConnectionLimit, 7, 7])]
    #[TestWith([DatabaseParameter::Oid, '3000000000', 3000000000])]
    #[TestWith([DatabaseParameter::Owner, 5, '5'])]
    public function testValueConvertsAsTheServerOptionReaders(DatabaseParameter $parameter, string|int $raw, string|int|bool $expected): void
    {
        self::assertSame($expected, DatabaseCommands::value($parameter, $raw));
    }
}
