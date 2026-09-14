<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Shadow\Mutation\Table\TableMutationResolver;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\AlterTableMutation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\ColumnAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\ColumnAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\ColumnDefinitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\CreateTableRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\OperationApplier::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\PrimaryKeyAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\StoredColumns::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\UnsupportedKeyword::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\MySqlColumnTypeMapper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\Key\MySqlForeignKeyDefinitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\Partition\MySqlPartitioningParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\MySqlSchemaParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Alter\OptionList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\OptionalInsertIntoNormalizer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\DefinitionBuilder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\Key\DefinitionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\Key\TokenReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\Partition\PredicateCompiler::class)]
#[CoversClass(TableMutationResolver::class)]
final class TableMutationResolverTest extends TestCase
{
    public function testResolveCreateTable(): void
    {
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $schemaParser = new \ZtdQuery\Platform\MySql\Schema\MySqlSchemaParser(new \ZtdQuery\Platform\MySql\Sql\MySqlParser());
        $resolver = new TableMutationResolver($registry, $schemaParser);
        $statement = (new \PhpMyAdmin\SqlParser\Parser('CREATE TABLE users (id INT PRIMARY KEY)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $statement);
        $mutation = $resolver->resolveCreateTable($statement, $statement->build());
        $mutation->apply(new \ZtdQuery\Shadow\ShadowStore(), []);
        $definition = $registry->get('users');
        self::assertNotNull($definition);
        self::assertSame(['id'], $definition->columns);
        self::assertSame(['id'], $definition->primaryKeys);
    }

    public function testResolveDropTable(): void
    {
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $schemaParser = new \ZtdQuery\Platform\MySql\Schema\MySqlSchemaParser(new \ZtdQuery\Platform\MySql\Sql\MySqlParser());
        $resolver = new TableMutationResolver($registry, $schemaParser);
        $statement = (new \PhpMyAdmin\SqlParser\Parser('DROP TABLE users'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DropStatement::class, $statement);
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id'], [], ['id'], [], []));
        $mutation = $resolver->resolveDropTable($statement, $statement->build());
        $mutation->apply(new \ZtdQuery\Shadow\ShadowStore(), []);
        self::assertFalse($registry->has('users'));
    }

    public function testResolveAlterTable(): void
    {
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $schemaParser = new \ZtdQuery\Platform\MySql\Schema\MySqlSchemaParser(new \ZtdQuery\Platform\MySql\Sql\MySqlParser());
        $resolver = new TableMutationResolver($registry, $schemaParser);
        $statement = (new \PhpMyAdmin\SqlParser\Parser('ALTER TABLE users ADD COLUMN name TEXT'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\AlterStatement::class, $statement);
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id'], ['id' => 'INT'], ['id'], [], []));
        $mutation = $resolver->resolveAlterTable($statement, $statement->build());
        $mutation->apply(new \ZtdQuery\Shadow\ShadowStore(), []);
        $definition = $registry->get('users');
        self::assertNotNull($definition);
        self::assertSame(['id', 'name'], $definition->columns);
    }

    public function testExtractSelectColumnNames(): void
    {
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $schemaParser = new \ZtdQuery\Platform\MySql\Schema\MySqlSchemaParser(new \ZtdQuery\Platform\MySql\Sql\MySqlParser());
        $resolver = new TableMutationResolver($registry, $schemaParser);
        $statement = (new \PhpMyAdmin\SqlParser\Parser('SELECT id, 1 AS number, 1 + 2, * FROM users'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\SelectStatement::class, $statement);
        self::assertSame(['id', 'number', '1___2'], $resolver->extractSelectColumnNames($statement));
    }

    public function testResolveTruncate(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\Sql\MySqlParser();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id', 'name'], [], ['id'], [], []));
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'old']]);
        $resolver = new TableMutationResolver($registry, new \ZtdQuery\Platform\MySql\Schema\MySqlSchemaParser($parser));
        $statement = (new \PhpMyAdmin\SqlParser\Parser('TRUNCATE TABLE users'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\TruncateStatement::class, $statement);
        $mutation = $resolver->resolveTruncate($statement, $statement->build());
        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\Table\TruncateMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
        $mutation->apply($store, []);
        self::assertSame([], $store->get('users'));
    }

}
