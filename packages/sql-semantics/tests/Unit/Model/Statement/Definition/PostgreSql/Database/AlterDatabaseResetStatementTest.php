<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\ResetSetting;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Database\AlterDatabaseResetStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterDatabaseResetStatement::class)]
#[Medium]
final class AlterDatabaseResetStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRemovedDefault(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app RESET SESSION AUTHORIZATION');
        self::assertInstanceOf(AlterDatabaseResetStatement::class, $statement);
        self::assertSame(['session_authorization'], $statement->withOrigin($statement->origin)->setting->name);
    }

    public function testWithNameReplacesTheDatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app RESET a.b');
        self::assertInstanceOf(AlterDatabaseResetStatement::class, $statement);
        self::assertSame('ALTER DATABASE "other" RESET "a"."b"', $statement->withName('other')->toString());
    }

    public function testWithSettingRequiresASessionParameter(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app RESET work_mem');
        self::assertInstanceOf(AlterDatabaseResetStatement::class, $statement);
        self::assertSame('ALTER DATABASE "app" RESET "timezone"', $statement->withSetting(new ResetSetting(['timezone'], SettingScope::Session, $statement->source))->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withSetting(new ResetSetting(['timezone'], SettingScope::Persist, $statement->source));
    }
}
