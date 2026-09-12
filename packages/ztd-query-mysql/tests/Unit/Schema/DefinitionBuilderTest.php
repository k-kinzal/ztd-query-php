<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Schema\DefinitionBuilder;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlColumnTypeMapper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\OptionalInsertIntoNormalizer::class)]
#[CoversClass(DefinitionBuilder::class)]
final class DefinitionBuilderTest extends TestCase
{
    public function testAddColumn(): void
    {
        $sql = 'CREATE TABLE t (id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(20))';
        $statement = (new \ZtdQuery\Platform\MySql\MySqlParser())->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $statement);
        self::assertIsArray($statement->fields);
        $builder = new DefinitionBuilder();
        $builder->addColumn($statement->fields[0]);
        $builder->addColumn($statement->fields[1]);
        $definition = $builder->build([], null);
        self::assertSame(['id', 'name'], $definition->columns);
        self::assertSame(['id' => 'BIGINT', 'name' => 'VARCHAR(20)'], $definition->columnTypes);
        self::assertSame(['id'], $definition->notNullColumns);
        self::assertSame(['id'], $definition->primaryKeys);
        self::assertSame(['id' => \ZtdQuery\Schema\IdentityGenerationStrategy::MaxValue], $definition->identityStrategies);
    }

    public function testAddColumnOptions(): void
    {
        $sql = 'CREATE TABLE t (id INT NOT NULL UNIQUE DEFAULT 7)';
        $statement = (new \ZtdQuery\Platform\MySql\MySqlParser())->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $statement);
        self::assertIsArray($statement->fields);
        $options = $statement->fields[0]->options;
        self::assertNotNull($options);
        $builder = new DefinitionBuilder();
        $builder->addColumnOptions('id', $options);
        $definition = $builder->build([], null);
        self::assertSame(['id'], $definition->notNullColumns);
        self::assertSame(['id_UNIQUE' => ['id']], $definition->uniqueConstraints);
        self::assertSame(['id' => '7'], $definition->columnDefaults);
    }

    public function testAddKey(): void
    {
        $sql = 'CREATE TABLE t (id INT, code INT, PRIMARY KEY (id, code), UNIQUE KEY uq (code))';
        $statement = (new \ZtdQuery\Platform\MySql\MySqlParser())->parseSingleLogicalStatement($sql);
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\CreateStatement::class, $statement);
        self::assertIsArray($statement->fields);
        $builder = new DefinitionBuilder();
        $builder->addKey($statement->fields[2]);
        $builder->addKey($statement->fields[3]);
        $definition = $builder->build([], null);
        self::assertSame(['id', 'code'], $definition->primaryKeys);
        self::assertSame(['uq' => ['code']], $definition->uniqueConstraints);
    }

    public function testBuild(): void
    {
        $foreignKey = new \ZtdQuery\Schema\ForeignKeyDefinition(['pid'], 'p', ['id']);
        $definition = (new DefinitionBuilder())->build(['fk' => $foreignKey], null);
        self::assertSame([], $definition->columns);
        self::assertSame(['fk' => $foreignKey], $definition->foreignKeys);
        self::assertNull($definition->partitioning);
    }

}
