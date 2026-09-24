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
}
