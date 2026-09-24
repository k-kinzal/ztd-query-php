<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Configuration\ResetBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ResetBinder::class)]
#[Medium]
final class ResetBinderTest extends TestCase
{
    public function testBindSeparatesResetAllFromANamedReset(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\ResetAllSettingsStatement::class, $binder->bind('RESET ALL'));
        $named = $binder->bind('RESET my.setting');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\ResetSettingStatement::class, $named);
        self::assertSame(['my', 'setting'], $named->setting->name);
        self::assertSame(\SqlSemantics\Model\Configuration\SettingScope::Session, $named->setting->scope);
        self::assertFalse($named->setting->ifExists);
    }

    #[TestWith(['RESET TIME ZONE', 'timezone'])]
    #[TestWith(['RESET TRANSACTION ISOLATION LEVEL', 'transaction_isolation'])]
    #[TestWith(['RESET SESSION AUTHORIZATION', 'session_authorization'])]
    public function testBindMapsSpecialPostgreSqlResetsToTheirParameterNames(string $sql, string $name): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\ResetSettingStatement::class, $statement);
        self::assertSame([$name], $statement->setting->name);
    }

    public function testBindDistinguishesMySqlPersistedResets(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\ResetAllPersistedVariablesStatement::class, $binder->bind('RESET PERSIST'));
        $named = $binder->bind('RESET PERSIST IF EXISTS max_connections');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\ResetSettingStatement::class, $named);
        self::assertSame(['max_connections'], $named->setting->name);
        self::assertSame(\SqlSemantics\Model\Configuration\SettingScope::Persist, $named->setting->scope);
        self::assertTrue($named->setting->ifExists);
        self::assertSame('RESET PERSIST IF EXISTS `max_connections`', $named->toString());
    }

    public function testBindHandsMySqlServerStateResetsToTheServerCommands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('RESET REPLICA ALL');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Server\Administration\ResetServerStatement::class, $statement);
    }
}
