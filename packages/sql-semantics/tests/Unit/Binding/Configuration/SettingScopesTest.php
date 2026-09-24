<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Configuration\SettingScopes;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\AssignedSetting;
use SqlSemantics\Model\Configuration\SettingScope;
use SqlSemantics\Model\Scalar\Value\ConfigurationIdentifier;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SettingScopes::class)]
#[Medium]
final class SettingScopesTest extends TestCase
{
    public function testCarryGivesUnscopedItemsTheLatestScopeKeyword(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SET GLOBAL a = 1, b = 2, @@c = 3, SESSION d = 4, e = 5');
        self::assertInstanceOf(SetStatement::class, $statement);
        self::assertSame([SettingScope::Global, SettingScope::Global, SettingScope::Session, SettingScope::Session, SettingScope::Session], array_map(static fn ($setting): ?SettingScope => $setting instanceof AssignedSetting ? $setting->scope : null, $statement->settings));
        self::assertSame('SET GLOBAL `a` = 1, GLOBAL `b` = 2, SESSION `c` = 3, `d` = 4, `e` = 5', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    #[TestWith(['SET GLOBAL.x = 1'])]
    #[TestWith(['SET @@GLOBAL.session.x = 1'])]
    public function testNameRejectsAScopeKeywordAsPrefix(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
    }

    public function testNameAcceptsAComponentPrefix(): void
    {
        SettingScopes::name(['keycache', 'key_buffer_size'], new Node('empty', 0, []));
        $this->expectException(InvalidSql::class);
        SettingScopes::name(['a', 'b', 'c'], new Node('empty', 0, []));
    }

    #[TestWith(['SET PERSIST_ONLY x = ROW', 'SET PERSIST_ONLY `x` = `ROW`'])]
    #[TestWith(['SET @@PERSIST.k.x = `on`', 'SET PERSIST `k`.`x` = `on`'])]
    #[TestWith(['SET x = c', 'SET `x` = `c`'])]
    public function testIdentifierBindsANameValueWhetherQuotedOrNot(string $sql, string $written): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(SetStatement::class, $statement);
        self::assertInstanceOf(AssignedSetting::class, $statement->settings[0]);
        self::assertInstanceOf(ConfigurationIdentifier::class, $statement->settings[0]->values[0]);
        self::assertSame($written, $statement->toString());
        self::assertSame($written, $binder->bind($written)->toString());
    }

    #[TestWith(['SET GLOBAL a = DEFAULT, b = DEFAULT', 'SET GLOBAL `a` = DEFAULT, GLOBAL `b` = DEFAULT'])]
    #[TestWith(['SET GLOBAL a = 1, @u = 2, b = 3', 'SET GLOBAL `a` = 1, @`u` = 2, GLOBAL `b` = 3'])]
    #[TestWith(['SET global a = 1, b = 2', 'SET GLOBAL `a` = 1, GLOBAL `b` = 2'])]
    #[TestWith(['SET PERSIST a = 1, b = 2', 'SET PERSIST `a` = 1, PERSIST `b` = 2'])]
    #[TestWith(['SET GLOBAL a = 1, @@b = 2', 'SET GLOBAL `a` = 1, SESSION `b` = 2'])]
    public function testCarryAppliesTheScopeToLaterDefaultsAndAssignments(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        self::assertSame($expected, $binder->bind($sql)->toString());
    }
}
