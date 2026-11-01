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
        self::assertSame('SET @@GLOBAL.hot_cache.key_buffer_size = 1, @c = 2, wait_timeout = 3', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('set @@global.hot_cache.key_buffer_size = 1, @c = 2, wait_timeout = 3')->toString());
        self::assertSame('SET @@PERSIST_ONLY.autocommit = 1', (new Semantics(Dialect::MySql))->analyze('set @@persist_only.autocommit = 1')->toString());
    }

    public function testConnectionLowersTheCharacterSets(): void
    {
        self::assertSame('SET NAMES DEFAULT, CHARACTER SET utf8mb4', (new Semantics(Dialect::MySql))->analyze('set names default, character set utf8mb4')->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerMismatchedCollations(): iterable
    {
        yield '8.4 after an earlier item' => ['mysql-8.4.7', 'SET @v = (SELECT 1 FROM missing_t), NAMES utf8mb4 COLLATE latin1_bin'];
        yield '5.7 in a stored program body' => ['mysql-5.7.44', 'CREATE PROCEDURE p() BEGIN SET NAMES utf8mb4 COLLATE latin1_bin; SELECT 1; END'];
        yield '5.6 quoted' => ['mysql-5.6.51', "SET NAMES 'utf8mb4' COLLATE 'latin1_bin'"];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerMismatchedCollations')]
    public function testConnectionRefusesACollationOfAnotherCharacterSetWhileReading(string $release, string $sql): void
    {
        try {
            (new Semantics(Dialect::MySql, $release))->analyze($sql);
            self::fail('The collation belongs to another character set.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch::class, $failure->problem);
            self::assertSame("COLLATION 'latin1_bin' is not valid for CHARACTER SET 'utf8mb4'", $failure->problem->message());
            self::assertTrue($failure->aborts);
        }
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
        self::assertSame('SET DEFAULT .key_buffer_size = 1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('set default .key_buffer_size = 1')->toString());
    }
}
