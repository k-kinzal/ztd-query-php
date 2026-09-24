<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\DefaultSetting;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Statement\Configuration\System\AlterSystemSetStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterSystemSetStatement::class)]
#[Medium]
final class AlterSystemSetStatementTest extends TestCase
{
    public function testWithOriginRetainsTheAssignment(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER SYSTEM SET work_mem = '64MB'");
        self::assertInstanceOf(AlterSystemSetStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame(['work_mem'], $copy->setting->name);
        self::assertSame(StatementKind::Alter, $copy->kind);
    }

    public function testWithSettingReplacesTheAssignment(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER SYSTEM SET work_mem = '64MB'");
        self::assertInstanceOf(AlterSystemSetStatement::class, $statement);
        self::assertSame('ALTER SYSTEM SET "a"."b" = DEFAULT', $statement->withSetting(new DefaultSetting(['a', 'b'], SettingScope::Session, $statement->source))->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withSetting(new DefaultSetting(['a'], SettingScope::Global, $statement->source));
    }
}
