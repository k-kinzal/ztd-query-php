<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\StoredSettings;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Database\AlterDatabaseResetAllStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Database\AlterDatabaseSetStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(StoredSettings::class)]
#[Medium]
final class StoredSettingsTest extends TestCase
{
    public function testAssignmentReadsNamesAsTheClientEncodingDefault(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app SET NAMES');
        self::assertInstanceOf(AlterDatabaseSetStatement::class, $statement);
        self::assertSame(['client_encoding'], $statement->setting->name);
    }

    #[TestWith(['ALTER DATABASE app SET TRANSACTION READ ONLY'])]
    #[TestWith(['ALTER DATABASE app SET SESSION CHARACTERISTICS AS TRANSACTION READ WRITE'])]
    public function testAssignmentDiagnosesSessionOnlyCommands(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::StoredSetting->message());
        $binder->bind($sql);
    }

    public function testResetSeparatesTheFullReset(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app RESET ALL');
        self::assertInstanceOf(AlterDatabaseResetAllStatement::class, $statement);
    }

    #[TestWith(["ALTER DATABASE app SET work_mem = '1MB'", 'ALTER DATABASE "app" SET "work_mem" = \'1MB\''])]
    #[TestWith(['ALTER DATABASE app SET work_mem TO DEFAULT', 'ALTER DATABASE "app" SET "work_mem" = DEFAULT'])]
    #[TestWith(['ALTER DATABASE app SET work_mem FROM CURRENT', 'ALTER DATABASE "app" SET "work_mem" FROM CURRENT'])]
    #[TestWith(['ALTER DATABASE app SET SESSION AUTHORIZATION r', 'ALTER DATABASE "app" SET "session_authorization" = "r"'])]
    #[TestWith(['ALTER DATABASE app RESET work_mem', 'ALTER DATABASE "app" RESET "work_mem"'])]
    #[TestWith(['ALTER ROLE r SET search_path = a, b', 'ALTER ROLE "r" SET "search_path" = "a", "b"'])]
    public function testAssignmentAndResetReadOneStoredParameter(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertSame($expected, $binder->bind($sql)->toString());
    }
}
