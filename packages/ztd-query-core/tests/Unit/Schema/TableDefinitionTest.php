<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Schema\Key\CandidateKeySet;
use ZtdQuery\Schema\Key\ForeignKeyDefinition;
use ZtdQuery\Schema\Key\IdentityGenerationStrategy;
use ZtdQuery\Schema\Key\PartialUniqueIndex;
use ZtdQuery\Schema\Partition\TablePartitioning;
use ZtdQuery\Schema\Partition\TablePartitionKey;
use ZtdQuery\Schema\Partition\TablePartitionRelation;
use ZtdQuery\Schema\Partition\TablePartitionStrategy;
use ZtdQuery\Schema\TableDefinition;

#[UsesClass(ColumnDeclaration::class)]
#[UsesClass(CandidateKeySet::class)]
#[UsesClass(ForeignKeyDefinition::class)]
#[UsesClass(PartialUniqueIndex::class)]
#[UsesClass(TablePartitioning::class)]
#[UsesClass(TablePartitionKey::class)]
#[UsesClass(TablePartitionRelation::class)]
#[CoversClass(TableDefinition::class)]
final class TableDefinitionTest extends TestCase
{
    public function testKeepsEverythingATableDeclaresAboutItself(): void
    {
        $typedColumns = ['id' => new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INT')];

        $definition = new TableDefinition(
            ['id', 'name'],
            ['id' => 'INT', 'name' => 'VARCHAR(255)'],
            ['id'],
            ['id'],
            ['unique_name' => ['name']],
            $typedColumns,
            ['name' => "'anonymous'"],
            ['id' => IdentityGenerationStrategy::MaxValue],
            ['name' => "CONCAT('user-', id)"],
            ['fk_parent' => new ForeignKeyDefinition(['id'], 'parents', ['id'])],
            new TablePartitioning(['p0' => 'id < 10']),
        );

        self::assertSame(['id', 'name'], $definition->columns);
        self::assertSame(['id' => 'INT', 'name' => 'VARCHAR(255)'], $definition->columnTypes);
        self::assertSame(['id'], $definition->primaryKeys);
        self::assertSame(['id'], $definition->notNullColumns);
        self::assertSame(['unique_name' => ['name']], $definition->uniqueConstraints);
        self::assertSame($typedColumns, $definition->typedColumns);
        self::assertSame(['name' => "'anonymous'"], $definition->columnDefaults);
        self::assertSame(['id' => IdentityGenerationStrategy::MaxValue], $definition->identityStrategies);
        self::assertSame(['name' => "CONCAT('user-', id)"], $definition->generatedExpressions);
        self::assertSame(['fk_parent'], array_keys($definition->foreignKeys));
        self::assertSame(['id < 10'], $definition->partitioning?->predicatesFor(['p0']));
    }

    public function testTypedColumnsDefaultsToEmpty(): void
    {
        $definition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            [],
            [],
            [],
        );

        self::assertSame([], $definition->typedColumns);
        self::assertSame([], $definition->columnDefaults);
        self::assertSame([], $definition->identityStrategies);
        self::assertSame([], $definition->generatedExpressions);
        self::assertSame([], $definition->foreignKeys);
        self::assertNull($definition->partitioning);
        self::assertNull($definition->partitionKey);
        self::assertNull($definition->partitionRelation);
        self::assertSame([], $definition->partialUniqueIndexes);
    }

    public function testWithPartitioningPreservesSchemaAndReturnsNewDefinition(): void
    {
        $definition = new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []);
        $partitioning = new TablePartitioning(['p0' => 'id < 10']);

        $partitioned = $definition->withPartitioning($partitioning);

        self::assertNotSame($definition, $partitioned);
        self::assertSame($definition->columns, $partitioned->columns);
        self::assertSame($definition->primaryKeys, $partitioned->primaryKeys);
        self::assertSame($partitioning, $partitioned->partitioning);
    }

    public function testCandidateKeysWithPartitionKeyCandidateKeysPartitionMetadataCopiesPreserveOtherSchemaState(): void
    {
        $definition = new TableDefinition(['id'], ['id' => 'INT'], ['id'], ['id'], []);
        $key = new TablePartitionKey(TablePartitionStrategy::Range, ['id']);
        $relation = new TablePartitionRelation('events', 'id >= 10');

        $partition = $definition->withPartitionKey($key)->withPartitionRelation($relation);

        self::assertSame($key, $partition->partitionKey);
        self::assertSame($relation, $partition->partitionRelation);
        self::assertSame($definition->columns, $partition->columns);
        self::assertSame($definition->candidateKeys()->keys(), $partition->candidateKeys()->keys());
    }

    public function testWithPartialUniqueIndexPreservesSchemaAndIndexesByName(): void
    {
        $definition = new TableDefinition(['email', 'status'], ['email' => 'TEXT', 'status' => 'TEXT'], [], [], []);
        $index = new PartialUniqueIndex('users_active_email', ['email'], "status = 'active'");

        $indexed = $definition->withPartialUniqueIndex($index);

        self::assertNotSame($definition, $indexed);
        self::assertSame([], $definition->partialUniqueIndexes);
        self::assertSame($index, $indexed->partialUniqueIndexes['users_active_email']);
        self::assertSame($definition->columns, $indexed->columns);
    }

    public function testWithPartitionKeyAnswersATableDividedByThatKey(): void
    {
        $definition = new TableDefinition(['id'], [], ['id'], [], []);
        $key = new TablePartitionKey(TablePartitionStrategy::Range, ['id']);

        $divided = $definition->withPartitionKey($key);

        self::assertSame($key, $divided->partitionKey);
        self::assertNull($definition->partitionKey);
    }

    public function testWithPartitionRelationAnswersATableThatIsPartOfAnother(): void
    {
        $definition = new TableDefinition(['id'], [], ['id'], [], []);
        $relation = new TablePartitionRelation('parent', 'id < 10');

        $part = $definition->withPartitionRelation($relation);

        self::assertSame($relation, $part->partitionRelation);
        self::assertNull($definition->partitionRelation);
    }
}
