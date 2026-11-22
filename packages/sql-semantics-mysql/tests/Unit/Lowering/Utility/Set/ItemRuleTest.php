<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Utility\Set\ItemRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ItemRule::class)]
#[Medium]
final class ItemRuleTest extends TestCase
{
    public function testPasswordTellsAPasswordItem(): void
    {
        self::assertSame("SET @a = 1, PASSWORD FOR u = 'x'", (new Inspection('mysql-5.6.51'))->inspect("set @a = 1, password for u = 'x'")->toString());
    }

    public function testUnscopedLowersEveryVariableForm(): void
    {
        self::assertSame('SET @@GLOBAL.a.b = 1, @c = 2, d = 3', (new Inspection('mysql-5.6.51'))->inspect('set @@global.a.b = 1, @c = 2, d = 3')->toString());
        self::assertSame('SET @@PERSIST_ONLY.a = 1', (new Inspection())->inspect('set @@persist_only.a = 1')->toString());
    }

    public function testConnectionLowersTheCharacterSets(): void
    {
        self::assertSame('SET NAMES DEFAULT, CHARSET utf8mb4', (new Inspection())->inspect('set names default, character set utf8mb4')->toString());
    }

    public function testScopedLowersANameAfterAScope(): void
    {
        self::assertSame('SET SESSION a.b = 1', (new Inspection())->inspect('set session a.b = 1')->toString());
    }

    public function testNamedLowersTheValue(): void
    {
        self::assertSame('SET a = DEFAULT', (new Inspection())->inspect('set a = default')->toString());
    }

    public function testVariableLowersTheDefaultInstance(): void
    {
        self::assertSame('SET `default`.key_buffer_size = 1', (new Inspection('mysql-5.7.44'))->inspect('set default .key_buffer_size = 1')->toString());
    }
}
