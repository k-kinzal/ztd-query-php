<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableDefinition::class)]
#[Small]
final class TableDefinitionTest extends TestCase
{
    public function testPrimaryKeyAnswersThePrimaryKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT, b INT NOT NULL, KEY (a), PRIMARY KEY (b))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame(['PRIMARY', [1]], [$table->definition->primaryKey()?->name, $table->definition->primaryKey()?->columns]);
    }

    public function testPrimaryKeyAnswersNullWithoutOne(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT, UNIQUE KEY (a))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertNull($table->definition->primaryKey());
    }

    public function testPositionFindsAColumnWithoutRegardToCase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (id INT, Name VARCHAR(5))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame([1, 0, null], [$table->definition->position('NAME'), $table->definition->position('Id'), $table->definition->position('other')]);
    }

    public function testAutoIncrementColumnAnswersItsPosition(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT, id INT AUTO_INCREMENT PRIMARY KEY)');
        $session->query('CREATE TABLE d.u (a INT)');
        $table = $session->instance->dictionary->table('d', 't');
        $plain = $session->instance->dictionary->table('d', 'u');

        self::assertNotNull($table);
        self::assertNotNull($plain);
        self::assertSame([1, null], [$table->definition->autoIncrementColumn(), $plain->definition->autoIncrementColumn()]);
    }

    public function testClusterColumnsPreferThePrimaryKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT NOT NULL, b INT NOT NULL, c INT NOT NULL, UNIQUE KEY (a), PRIMARY KEY (c, b))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame([2, 1], $table->definition->clusterColumns());
    }

    public function testClusterColumnsTakeTheFirstUniqueKeyOverNotNullColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT, b VARCHAR(10) NOT NULL, c INT NOT NULL, UNIQUE KEY (a), UNIQUE KEY (b(3)), UNIQUE KEY (c))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame([2], $table->definition->clusterColumns());
    }

    public function testClusterColumnsAreEmptyWithoutAUsableKey(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT, b INT NOT NULL, UNIQUE KEY (a), KEY (b))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame([], $table->definition->clusterColumns());
    }

    public function testFlagsMarkKeysAutoIncrementAndMissingDefaults(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(10) NOT NULL, n INT, note TEXT, changed TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY (code), KEY (n))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame([514, 4100, 8, 16, 8192], [$table->definition->flags(0), $table->definition->flags(1), $table->definition->flags(2), $table->definition->flags(3), $table->definition->flags(4)]);
    }

    public function testFlagsMarkAYearColumnZerofill(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (y YEAR, z YEAR NOT NULL)');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertSame([64, 4160], [$table->definition->flags(0), $table->definition->flags(1)]);
    }
}
