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
    public function testResolvedPositionMapsIndependentSnapshotsToTheirQualifiedStoredTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (a INT, b INT); CREATE TABLE d.u (a INT, b INT); CREATE DATABASE e; CREATE TABLE e.t (a INT, b INT)');
        $statement = $session->analyze('SELECT target.a FROM d.t AS target');
        $resolution = $statement->field('a')->resolution;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\ResolvedColumn::class, $resolution);
        $session->query('ALTER TABLE d.t MODIFY a INT AFTER b');
        $table = $session->instance->dictionary->table('d', 't');
        $other = $session->instance->dictionary->table('d', 'u');
        $otherSchema = $session->instance->dictionary->table('e', 't');
        self::assertNotNull($table);
        self::assertNotNull($other);
        self::assertNotNull($otherSchema);
        self::assertNotSame($resolution->declaration(), $table->definition->columns[1]->declaration);
        self::assertSame(1, $table->definition->resolvedPosition($resolution));
        self::assertNull($other->definition->resolvedPosition($resolution));
        self::assertNull($otherSchema->definition->resolvedPosition($resolution));
        $session->query('ALTER TABLE d.t DROP COLUMN a');
        self::assertNull($table->definition->resolvedPosition($resolution));
    }

    public function testWithColumnDisplaysUpdatesRuntimeAndDeclaredColumnsWithoutLosingKeyIdentity(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (a DOUBLE PRIMARY KEY, b INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $original = $table->definition;
        $changed = $original->withColumnDisplays(['a' => 12]);
        $domain = $changed->declaration->columns[0]->type;

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::class, $domain);
        self::assertSame(12, $domain->display);
        self::assertSame(12, $changed->columns[0]->domain->display);
        self::assertSame($changed->declaration->columns[0], $changed->declaration->keys[0]->columns[0]);
        self::assertSame($original->declaration->columns[1], $changed->declaration->columns[1]);
        self::assertSame($original, $original->withColumnDisplays([]));
    }

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
        self::assertSame([16898, 20484, 16392, 16, 8192], [$table->definition->flags(0), $table->definition->flags(1), $table->definition->flags(2), $table->definition->flags(3), $table->definition->flags(4)]);
    }

    public function testZeroFillPreservesDeclaredNumericAttributesInResultFlags(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (i INT(2) ZEROFILL, d DECIMAL(7,2) ZEROFILL, f FLOAT ZEROFILL, u INT UNSIGNED, s VARCHAR(4))');
        $table = $session->instance->dictionary->table('d', 't');

        self::assertNotNull($table);
        self::assertTrue($table->definition->zeroFill(0));
        self::assertFalse($table->definition->zeroFill(3));
        self::assertFalse($table->definition->zeroFill(4));
        self::assertSame([64, 64, 64, 0, 0], [$table->definition->flags(0), $table->definition->flags(1), $table->definition->flags(2), $table->definition->flags(3), $table->definition->flags(4)]);
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

    public function testStatementHoldsTheStatementThatDeclaresTheTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (a INT)');

        self::assertSame('t', $session->instance->dictionary->table('d', 't')?->definition->statement?->name->name->value);
    }

    public function testWithColumnsAnswersTheDefinitionWithOtherColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, b INT, CHECK (a > 0))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $definition = $table->definition->withColumns([$table->definition->columns[1]]);

        self::assertSame([['b'], 1], [array_map(static fn ($column): string => $column->name, $definition->columns), count($definition->checks)]);
    }

    public function testWithConstraintsAnswersTheDefinitionWithOtherConstraints(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT, CHECK (a > 0))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([], $table->definition->withConstraints([], [])->checks);
    }

    public function testWithPartitioningAnswersTheDefinitionWithAnotherPartitioning(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT) PARTITION BY HASH (a)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame([true, null], [$table->definition->partitioning !== null, $table->definition->withPartitioning(null)->partitioning]);
    }

    public function testWithRowFormatSurvivesAllDefinitionCopies(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t(a INT PRIMARY KEY)');
        $definition = $session->instance->dictionary->table('d', 't')?->definition;
        self::assertNotNull($definition);
        $format = \SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat::Redundant;
        $changed = $definition->withRowFormat($format)->withColumns($definition->columns)->withColumnDisplays(['a' => 12])->withConstraints([], [])->withPartitioning(null);
        self::assertSame($format, $changed->rowFormat);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat::Dynamic, $definition->rowFormat);
    }

}
