<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SettingScope::class)]
#[Medium]
final class SettingScopeTest extends TestCase
{
    public function testRepresentsEverySettingLifetime(): void
    {
        self::assertSame(['session', 'local', 'global', 'persist', 'persist-only', 'user', 'database'], array_column(SettingScope::cases(), 'value'));
    }

    #[TestWith([Dialect::PostgreSql, "SET SESSION work_mem = '4MB'", SettingScope::Session])]
    #[TestWith([Dialect::PostgreSql, 'SET LOCAL search_path TO public, other', SettingScope::Local])]
    #[TestWith([Dialect::MySql, 'SET GLOBAL max_connections = 10', SettingScope::Global])]
    #[TestWith([Dialect::MySql, 'SET PERSIST max_connections = 10', SettingScope::Persist])]
    #[TestWith([Dialect::MySql, 'SET PERSIST_ONLY sort_buffer_size = 2', SettingScope::PersistOnly])]
    #[TestWith([Dialect::MySql, 'SET @a = 1', SettingScope::User])]
    public function testClassifiesTheLifetimeOfEachAssignment(Dialect $dialect, string $sql, SettingScope $scope): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(SetStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Setting::class, $statement->settings[0]);
        self::assertSame($scope, $statement->settings[0]->scope);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
