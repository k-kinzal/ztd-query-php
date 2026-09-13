<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Mutation\DdlResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Conflict\ColumnSet::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\FromClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\RelationReference::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\Classification::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\ConflictClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\InsertSource::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\SelectColumns::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\TableDefinitionClauses::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlColumnTypeMapper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlConflictTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlForeignKeyDefinitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlPartitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlSchemaParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlSelectRelationParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PostgreSqlLexicalMasker::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Definition\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Definition\ColumnTypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Definition\TableBody::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Definition\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Definition\TableFields::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\ForeignKey\DefinitionEntry::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\ForeignKey\DefinitionTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Partition\BoundPredicate::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Partition\ClauseTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\Lexing\CommentSpan::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\Lexing\QuotedSpan::class)]
final class DdlResolverTest extends TestCase
{
    public function testResolveCreateTableRegistersItsSchemaOnlyWhenApplied(): void
    {
        $parser = new \ZtdQuery\Platform\Postgres\PgSqlParser();
        $schemaParser = new \ZtdQuery\Platform\Postgres\PgSqlSchemaParser();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $resolver = new \ZtdQuery\Platform\Postgres\Mutation\DdlResolver($parser, new \ZtdQuery\Platform\Postgres\PgSqlPartitionParser(), $registry, $schemaParser);
        $mutation = $resolver->resolveCreateTable('CREATE TABLE events (id INTEGER PRIMARY KEY)');
        self::assertFalse($registry->has('events'));
        $mutation->apply($store, []);
        self::assertSame('events', $mutation->tableName());
        self::assertTrue($registry->has('events'));
        self::assertSame([], $store->get('events'));
    }

    public function testResolveDropTableRemovesOnlyTheTarget(): void
    {
        $parser = new \ZtdQuery\Platform\Postgres\PgSqlParser();
        $schemaParser = new \ZtdQuery\Platform\Postgres\PgSqlSchemaParser();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $resolver = new \ZtdQuery\Platform\Postgres\Mutation\DdlResolver($parser, new \ZtdQuery\Platform\Postgres\PgSqlPartitionParser(), $registry, $schemaParser);
        $store->set('users', [['id' => 1, 'name' => 'Ada']]);
        $mutation = $resolver->resolveDropTable('DROP TABLE users');
        self::assertTrue($registry->has('users'));
        $mutation->apply($store, []);
        self::assertFalse($registry->has('users'));
        self::assertFalse($store->has('users'));
    }

    public function testResolveAlterTableRejectsUnsupportedSchemaChanges(): void
    {
        $parser = new \ZtdQuery\Platform\Postgres\PgSqlParser();
        $schemaParser = new \ZtdQuery\Platform\Postgres\PgSqlSchemaParser();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $resolver = new \ZtdQuery\Platform\Postgres\Mutation\DdlResolver($parser, new \ZtdQuery\Platform\Postgres\PgSqlPartitionParser(), $registry, $schemaParser);
        $this->expectException(\ZtdQuery\Exception\UnsupportedSqlException::class);
        $resolver->resolveAlterTable('ALTER TABLE users ADD COLUMN age INTEGER');
    }

    public function testResolvePartitionInheritsItsParentDefinition(): void
    {
        $parser = new \ZtdQuery\Platform\Postgres\PgSqlParser();
        $schemaParser = new \ZtdQuery\Platform\Postgres\PgSqlSchemaParser();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $resolver = new \ZtdQuery\Platform\Postgres\Mutation\DdlResolver($parser, new \ZtdQuery\Platform\Postgres\PgSqlPartitionParser(), $registry, $schemaParser);
        $registry->register('users', $definition->withPartitionKey(new \ZtdQuery\Schema\TablePartitionKey(\ZtdQuery\Schema\TablePartitionStrategy::Range, ['id'])));
        $mutation = $resolver->resolvePartition('CREATE TABLE users_low PARTITION OF users FOR VALUES FROM (0) TO (10)', 'users_low', 'users', false);
        $mutation->apply($store, []);
        $child = $registry->get('users_low');
        self::assertNotNull($child);
        self::assertSame(['id', 'name'], $child->columns);
        self::assertSame('users', $child->partitionRelation?->parentTable);
    }
}
