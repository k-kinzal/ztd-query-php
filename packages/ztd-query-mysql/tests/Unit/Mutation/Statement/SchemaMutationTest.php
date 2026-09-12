<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Mutation\Statement\SchemaMutation;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\AlterTableMutation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\ColumnAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\ColumnAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\ColumnDefinitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\CreateTableRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\OperationApplier::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\PrimaryKeyAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\StoredColumns::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Alter\UnsupportedKeyword::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlColumnTypeMapper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlForeignKeyDefinitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlPartitioningParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlSchemaParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Alter\OptionList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\OptionalInsertIntoNormalizer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\DefinitionBuilder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\ForeignKey\DefinitionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\ForeignKey\TokenReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Schema\Partition\PredicateCompiler::class)]
#[CoversClass(SchemaMutation::class)]
final class SchemaMutationTest extends TestCase
{
    public function testResolveCreateTable(): void
    {
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $schemaParser = new \ZtdQuery\Platform\MySql\MySqlSchemaParser(new \ZtdQuery\Platform\MySql\MySqlParser());
        $resolver = new SchemaMutation($registry, $schemaParser);
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
        $schemaParser = new \ZtdQuery\Platform\MySql\MySqlSchemaParser(new \ZtdQuery\Platform\MySql\MySqlParser());
        $resolver = new SchemaMutation($registry, $schemaParser);
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
        $schemaParser = new \ZtdQuery\Platform\MySql\MySqlSchemaParser(new \ZtdQuery\Platform\MySql\MySqlParser());
        $resolver = new SchemaMutation($registry, $schemaParser);
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
        $schemaParser = new \ZtdQuery\Platform\MySql\MySqlSchemaParser(new \ZtdQuery\Platform\MySql\MySqlParser());
        $resolver = new SchemaMutation($registry, $schemaParser);
        $statement = (new \PhpMyAdmin\SqlParser\Parser('SELECT id, 1 AS number, 1 + 2, * FROM users'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\SelectStatement::class, $statement);
        self::assertSame(['id', 'number', '1___2'], $resolver->extractSelectColumnNames($statement));
    }

}
