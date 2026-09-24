<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\CurrentSetting;
use SqlSemantics\Model\Configuration\DefaultSetting;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Database\AlterDatabaseSetStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterDatabaseSetStatement::class)]
#[Medium]
final class AlterDatabaseSetStatementTest extends TestCase
{
    public function testWithOriginRetainsTheStoredDefault(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app SET work_mem FROM CURRENT');
        self::assertInstanceOf(AlterDatabaseSetStatement::class, $statement);
        self::assertInstanceOf(CurrentSetting::class, $statement->withOrigin($statement->origin)->setting);
    }

    public function testWithNameReplacesTheDatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app SET work_mem = 64');
        self::assertInstanceOf(AlterDatabaseSetStatement::class, $statement);
        self::assertSame('ALTER DATABASE "other" SET "work_mem" = 64', $statement->withName('other')->toString());
    }

    public function testWithSettingReplacesTheAssignmentInTheSessionScope(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DATABASE app SET work_mem = 64');
        self::assertInstanceOf(AlterDatabaseSetStatement::class, $statement);
        self::assertSame('ALTER DATABASE "app" SET "search_path" = DEFAULT', $statement->withSetting(new DefaultSetting(['search_path'], SettingScope::Session, $statement->source))->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withSetting(new DefaultSetting(['search_path'], SettingScope::Local, $statement->source));
    }
}
