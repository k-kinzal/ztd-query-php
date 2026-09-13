<?php

declare(strict_types=1);

namespace Tests\Unit\Rewriting\Mutation\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\Mutation\AlterTableMutation;
use ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Alter\DropColumnResolver;
use ZtdQuery\Schema\ForeignKeyDefinition;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(DropColumnResolver::class)]
#[UsesClass(AlterTableMutation::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Alter\AlteredTableProjection::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Alter\ColumnDefinitionEditor::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Resolution\MutationTableLookup::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Alter\AlterOperationParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteLexerProfile::class)]
final class DropColumnResolverTest extends TestCase
{
    public function testResolveAlterDropColumnProjectsRemainingRows(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id', 'name'], ['id' => 'INTEGER', 'name' => 'TEXT'], ['id'], ['id'], []);
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);
        $mutation = (new DropColumnResolver($registry))->resolveAlterDropColumn('ALTER TABLE users DROP COLUMN NAME', 'users', 'NAME');
        self::assertInstanceOf(AlterTableMutation::class, $mutation);
        self::assertSame('SELECT "id" FROM "users"', $mutation->resultSelect());
        $mutation->apply($store, [['id' => 1]]);
        self::assertSame(['id'], $registry->get('users')?->columns);
        self::assertSame([['id' => 1]], $store->get('users'));
    }

    public function testResolveAlterDropColumnRejectsMissingColumn(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id', 'name'], ['id' => 'INTEGER', 'name' => 'TEXT'], ['id'], ['id'], []);
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);
        $this->expectException(\ZtdQuery\Exception\ColumnNotFoundException::class);
        (new DropColumnResolver($registry))->resolveAlterDropColumn('ALTER TABLE users DROP COLUMN missing', 'users', 'missing');
    }

    public function testDefinitionWithoutColumnRemovesAllDependentMetadata(): void
    {
        $definition = new TableDefinition(
            ['id', 'parent'],
            ['id' => 'INTEGER', 'parent' => 'INTEGER'],
            ['id'],
            ['parent'],
            ['parent_key' => ['parent'], 'composite' => ['id', 'parent']],
            ['parent' => new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::INTEGER, 'INTEGER')],
            ['parent' => '0'],
            ['parent' => \ZtdQuery\Schema\IdentityGenerationStrategy::MaxValue],
            ['parent' => '(id * 2)'],
            ['fk' => new ForeignKeyDefinition(['parent'], 'other', ['id'])],
        );
        $result = (new DropColumnResolver(new TableDefinitionRegistry()))->definitionWithoutColumn($definition, 'parent');
        self::assertSame(['id'], $result->columns);
        self::assertSame(['id' => 'INTEGER'], $result->columnTypes);
        self::assertSame(['id'], $result->primaryKeys);
        self::assertSame([], $result->notNullColumns);
        self::assertSame(['composite' => ['id']], $result->uniqueConstraints);
        self::assertSame([], $result->typedColumns);
        self::assertSame([], $result->columnDefaults);
        self::assertSame([], $result->identityStrategies);
        self::assertSame([], $result->generatedExpressions);
        self::assertSame([], $result->foreignKeys);
    }

}
