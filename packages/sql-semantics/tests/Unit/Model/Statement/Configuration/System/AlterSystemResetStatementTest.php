<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\ResetSetting;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Statement\Configuration\System\AlterSystemResetStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterSystemResetStatement::class)]
#[Medium]
final class AlterSystemResetStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRemovedParameter(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SYSTEM RESET timezone');
        self::assertInstanceOf(AlterSystemResetStatement::class, $statement);
        self::assertSame(['timezone'], $statement->withOrigin($statement->origin)->setting->name);
    }

    public function testWithSettingReplacesTheRemovedParameter(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SYSTEM RESET work_mem');
        self::assertInstanceOf(AlterSystemResetStatement::class, $statement);
        self::assertSame('ALTER SYSTEM RESET "a"."b"', $statement->withSetting(new ResetSetting(['a', 'b'], SettingScope::Session, $statement->source))->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withSetting(new ResetSetting(['a'], SettingScope::Session, $statement->source, true));
    }
}
