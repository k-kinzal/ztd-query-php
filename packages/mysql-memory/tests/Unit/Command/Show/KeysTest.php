<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show;

use MySqlMemory\Command\Show\Keys;
use MySqlMemory\Dictionary\Key;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Keys::class)]
#[Small]
final class KeysTest extends TestCase
{
    public function testOrderedKeepsUniqueNotNullKeysFirstAndFullTextKeysLast(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE k1 (a INT, b INT NOT NULL, c INT, d TEXT, e INT NOT NULL, KEY ka (a), UNIQUE KEY uc (c), FULLTEXT KEY fd (d), UNIQUE KEY ub (b), UNIQUE KEY ue (e, b), KEY kab (a, b))');
        $table = $session->instance->dictionary->table('d', 'k1');

        self::assertNotNull($table);
        self::assertSame(['ub', 'ue', 'uc', 'ka', 'kab', 'fd'], array_map(static fn (Key $key): string => $key->name, (new Keys())->ordered($table->definition)));
    }

    public function testRankRanksTheKindsOfKeys(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT PRIMARY KEY, b INT, UNIQUE KEY (b), KEY (a, b))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame([0, 2, 3], array_map(static fn (Key $key): int => (new Keys())->rank($key, $table->definition), $table->definition->keys));
    }

    public function testNotNullTellsWhetherEveryColumnIsNotNull(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT NOT NULL, b INT, UNIQUE KEY ua (a), UNIQUE KEY uab (a, b))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertTrue((new Keys())->notNull($table->definition->keys[0], $table->definition));
        self::assertFalse((new Keys())->notNull($table->definition->keys[1], $table->definition));
    }

    public function testPrimaryUsesTheFirstUniqueKeyOfNotNullColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT NOT NULL, UNIQUE KEY ua (a), UNIQUE KEY ub (b))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame('ub', (new Keys())->primary($table->definition)?->name);
    }

    public function testRoleReportsPriUniAndMul(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE k1 (a INT, b INT NOT NULL, c INT, d TEXT, e INT NOT NULL, KEY ka (a), UNIQUE KEY uc (c), FULLTEXT KEY fd (d), UNIQUE KEY ub (b), UNIQUE KEY ue (e, b))');
        $table = $session->instance->dictionary->table('d', 'k1');

        self::assertNotNull($table);
        self::assertSame(['MUL', 'PRI', 'UNI', 'MUL', 'MUL'], array_map(static fn (int $position): string => (new Keys())->role($table->definition, $position), [0, 1, 2, 3, 4]));
    }
}
