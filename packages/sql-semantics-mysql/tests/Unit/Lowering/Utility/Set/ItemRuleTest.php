<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Utility\Set\ItemRule;

#[CoversClass(ItemRule::class)]
#[Medium]
final class ItemRuleTest extends TestCase
{
    public function testPasswordTellsAPasswordItem(): void
    {
        self::assertSame("SET @a = 1, PASSWORD FOR u = 'x'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("set @a = 1, password for u = 'x'")->toString());
    }

    public function testUnscopedLowersEveryVariableForm(): void
    {
        self::assertSame('SET @@GLOBAL.a.b = 1, @c = 2, d = 3', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('set @@global.a.b = 1, @c = 2, d = 3')->toString());
        self::assertSame('SET @@PERSIST_ONLY.a = 1', (new Semantics(Dialect::MySql))->analyze('set @@persist_only.a = 1')->toString());
    }

    public function testConnectionLowersTheCharacterSets(): void
    {
        self::assertSame('SET NAMES DEFAULT, CHARSET utf8mb4', (new Semantics(Dialect::MySql))->analyze('set names default, character set utf8mb4')->toString());
    }

    public function testScopedLowersANameAfterAScope(): void
    {
        self::assertSame('SET SESSION a.b = 1', (new Semantics(Dialect::MySql))->analyze('set session a.b = 1')->toString());
    }

    public function testNamedLowersTheValue(): void
    {
        self::assertSame('SET a = DEFAULT', (new Semantics(Dialect::MySql))->analyze('set a = default')->toString());
    }

    public function testVariableLowersTheDefaultInstance(): void
    {
        self::assertSame('SET `default`.key_buffer_size = 1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('set default .key_buffer_size = 1')->toString());
    }
}
