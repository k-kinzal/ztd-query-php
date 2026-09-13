<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Mutation\DmlResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Conflict\ColumnSet::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\HeaderParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\IdentifierReferences::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\PrefixMerge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Cte\ShadowDependencies::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Expression\ComparisonOperator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Expression\ExpressionCursor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Expression\ExpressionLexeme::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Expression\PrecedenceParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Expression\PrimaryParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\ActionClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\BranchTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\RelationTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Merge\StatementParts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\FromClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\RelationReference::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\Classification::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\ConflictClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\InsertSource::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Statement\TableDefinitionClauses::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlColumnTypeMapper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlConflictTarget::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlCteShadowComposer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlForeignKeyDefinitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeActionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeMatchKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlMergeStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlPartitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlSchemaParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlSelectRelationParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlUpsertExpressionParser::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Partition\StorageTable::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\Lexing\CommentSpan::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\Lexing\QuotedSpan::class)]
final class DmlResolverTest extends TestCase
{
    public function testResolveInsertAppliesOnlyReturnedRows(): void
    {
        $parser = new \ZtdQuery\Platform\Postgres\PgSqlParser();
        $schemaParser = new \ZtdQuery\Platform\Postgres\PgSqlSchemaParser();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $resolver = new \ZtdQuery\Platform\Postgres\Mutation\DmlResolver($parser, $registry, $store);
        $mutation = $resolver->resolveInsert("INSERT INTO users VALUES (1, 'Ada')");
        $mutation->apply($store, [['id' => 1, 'name' => 'Ada']]);
        self::assertSame('users', $mutation->tableName());
        self::assertSame([['id' => 1, 'name' => 'Ada']], $store->get('users'));
    }

    public function testResolveUpdatePreservesTheTargetIdentity(): void
    {
        $parser = new \ZtdQuery\Platform\Postgres\PgSqlParser();
        $schemaParser = new \ZtdQuery\Platform\Postgres\PgSqlSchemaParser();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $resolver = new \ZtdQuery\Platform\Postgres\Mutation\DmlResolver($parser, $registry, $store);
        $store->set('users', [['id' => 1, 'name' => 'before'], ['id' => 2, 'name' => 'untouched']]);
        $mutation = $resolver->resolveUpdate("UPDATE users SET name = 'after' WHERE id = 1");
        $mutation->apply($store, [['id' => 1, 'name' => 'after']]);
        self::assertSame([['id' => 1, 'name' => 'after'], ['id' => 2, 'name' => 'untouched']], $store->get('users'));
    }

    public function testResolveDeleteMatchesThePrimaryKey(): void
    {
        $parser = new \ZtdQuery\Platform\Postgres\PgSqlParser();
        $schemaParser = new \ZtdQuery\Platform\Postgres\PgSqlSchemaParser();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $resolver = new \ZtdQuery\Platform\Postgres\Mutation\DmlResolver($parser, $registry, $store);
        $store->set('users', [['id' => 1, 'name' => 'removed'], ['id' => 2, 'name' => 'kept']]);
        $mutation = $resolver->resolveDelete('DELETE FROM users WHERE id = 1');
        $mutation->apply($store, [['id' => 1, 'name' => 'removed']]);
        self::assertSame([['id' => 2, 'name' => 'kept']], $store->get('users'));
    }

    public function testResolveMergeSynchronizesTheProjectedTarget(): void
    {
        $parser = new \ZtdQuery\Platform\Postgres\PgSqlParser();
        $schemaParser = new \ZtdQuery\Platform\Postgres\PgSqlSchemaParser();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $resolver = new \ZtdQuery\Platform\Postgres\Mutation\DmlResolver($parser, $registry, $store);
        $mutation = $resolver->resolveMerge('MERGE INTO users u USING source s ON u.id = s.id WHEN MATCHED THEN DELETE');
        $mutation->apply($store, [['id' => 2, 'name' => 'kept']]);
        self::assertSame('users', $mutation->tableName());
        self::assertSame([['id' => 2, 'name' => 'kept']], $store->get('users'));
    }

    public function testResolveTruncateIncludesEveryExplicitTarget(): void
    {
        $parser = new \ZtdQuery\Platform\Postgres\PgSqlParser();
        $schemaParser = new \ZtdQuery\Platform\Postgres\PgSqlSchemaParser();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $resolver = new \ZtdQuery\Platform\Postgres\Mutation\DmlResolver($parser, $registry, $store);
        $store->set('users', [['id' => 1]]);
        $store->set('events', [['id' => 2]]);
        $mutation = $resolver->resolveTruncate('TRUNCATE users, events');
        $mutation->apply($store, []);
        self::assertSame([], $store->get('users'));
        self::assertSame([], $store->get('events'));
    }

    public function testResolveUpsertEvaluatesAssignmentsWithoutNativeCandidateKeys(): void
    {
        $parser = new \ZtdQuery\Platform\Postgres\PgSqlParser();
        $schemaParser = new \ZtdQuery\Platform\Postgres\PgSqlSchemaParser();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $definition = $schemaParser->parse('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        self::assertNotNull($definition);
        $registry->register('users', $definition);
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $resolver = new \ZtdQuery\Platform\Postgres\Mutation\DmlResolver($parser, $registry, $store);
        $store->set('users', [['id' => 1, 'name' => 'before']]);
        $mutation = $resolver->resolveUpsert("INSERT INTO users VALUES (1, 'incoming') ON CONFLICT (id) DO UPDATE SET name = 'after'", 'users', 'users', ['id'], ['columns' => ['name'], 'values' => ['name' => "'after'"]], null, null);
        $mutation->apply($store, [['id' => 1, 'name' => 'incoming']]);
        self::assertSame([['id' => 1, 'name' => 'after']], $store->get('users'));
    }
}
