<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\ResetSettingStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ResetSettingStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ResetSettingStatementTest extends TestCase
{
    public function testWithOriginPreservesRequiredOperandsAndSerialization(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('RESET PERSIST IF EXISTS max_connections', strict: false);
        self::assertInstanceOf(ResetSettingStatement::class, $statement);
        self::assertSame(['max_connections'], $statement->setting->name);
        self::assertTrue($statement->setting->ifExists);
        self::assertSame(\SqlSemantics\Model\Configuration\SettingScope::Persist, $statement->setting->scope);
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('new-scope', $statement->source, Dialect::MySql));
        self::assertSame('new-scope', $changed->scopeId);
        self::assertNotSame($statement, $changed);
        self::assertSame('RESET PERSIST IF EXISTS `max_connections`', $changed->toString());
        self::assertSame($changed->toString(), $binder->bind($changed->toString(), strict: false)->toString());
    }

    public function testSettingAcceptsAPostgreSqlSessionReset(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('RESET work_mem');
        self::assertInstanceOf(ResetSettingStatement::class, $statement);
        $rebuilt = new ResetSettingStatement($statement->origin, new \SqlSemantics\Model\Configuration\ResetSetting(['work_mem'], \SqlSemantics\Model\Configuration\SettingScope::Session, $statement->setting->source));
        self::assertSame('RESET "work_mem"', $rebuilt->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'RESET work_mem', 'persist', false])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'RESET work_mem', 'session', true])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'RESET PERSIST max_connections', 'session', false])]
    public function testSettingRejectsAnotherResetScope(Dialect $dialect, string $sql, string $scope, bool $ifExists): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql, strict: false);
        self::assertInstanceOf(ResetSettingStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new ResetSettingStatement($statement->origin, new \SqlSemantics\Model\Configuration\ResetSetting(['x'], \SqlSemantics\Model\Configuration\SettingScope::from($scope), $statement->setting->source, $ifExists));
    }

    public function testSettingRejectsSqliteResets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('RESET work_mem');
        self::assertInstanceOf(ResetSettingStatement::class, $statement);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new ResetSettingStatement(new \SqlSemantics\Model\Statement\Origin('s', $statement->source, Dialect::Sqlite), $statement->setting);
    }
}
