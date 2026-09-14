<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation\Row;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Shadow\Mutation\Row\RowMutationResolver;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\DmlWhereClauseExtractor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\TargetName::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Cte\MySqlCteShadowComposer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\FullText\MySqlFullTextSearchRewriter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\GeneratedColumn\MySqlGeneratedColumnProjector::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\MySqlParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Partition\MySqlPartitionSelectionRewriter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Relation\MySqlSelectRelationParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Type\MySqlTypeSemantics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Upsert\MySqlUpsertAssignmentExtractor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Upsert\MySqlUpsertExpressionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\AssignmentExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Cte\HeaderParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Cte\IdentifierReferences::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\OptionalInsertIntoNormalizer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Relation\ExpressionNames::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Relation\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Upsert\AssignmentReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Upsert\ExpressionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Upsert\LiteralReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Shadow\Mutation\Upsert\StringLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\FullText\ExpressionEditor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Partition\SelectionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Partition\SourceProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Delete\ResultSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Delete\TargetProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Set\OrderRewriter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Set\ValueNormalizer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Shadow\CteRows::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Update\ResultSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Transformer\Update\TargetProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\CastTypeResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Rewrite\Type\RankEdits::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\ScalarExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\StringCoercion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\UpdateAssignmentExtractor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Sql\Dml\UpdateSourceExtractor::class)]
#[CoversClass(RowMutationResolver::class)]
final class RowMutationResolverTest extends TestCase
{
    public function testResolveInsert(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\Sql\MySqlParser();
        $select = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id', 'name'], [], ['id'], [], []));
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'old']]);
        $resolver = new RowMutationResolver(new \ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer($parser, $select), $registry, $store, new \ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer($parser, $select));
        $statement = (new \PhpMyAdmin\SqlParser\Parser('INSERT INTO users (id) VALUES (2)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\InsertStatement::class, $statement);
        $mutation = $resolver->resolveInsert($statement, $statement->build());
        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\Row\InsertMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveUpdate(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\Sql\MySqlParser();
        $select = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id', 'name'], [], ['id'], [], []));
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'old']]);
        $resolver = new RowMutationResolver(new \ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer($parser, $select), $registry, $store, new \ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer($parser, $select));
        $statement = (new \PhpMyAdmin\SqlParser\Parser('UPDATE users SET name = 7'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);
        $mutation = $resolver->resolveUpdate($statement, $statement->build());
        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\Row\UpdateMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveDelete(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\Sql\MySqlParser();
        $select = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id', 'name'], [], ['id'], [], []));
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'old']]);
        $resolver = new RowMutationResolver(new \ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer($parser, $select), $registry, $store, new \ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer($parser, $select));
        $statement = (new \PhpMyAdmin\SqlParser\Parser('DELETE FROM users'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);
        $mutation = $resolver->resolveDelete($statement, $statement->build());
        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\Row\DeleteMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveReplace(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\Sql\MySqlParser();
        $select = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id', 'name'], [], ['id'], [], []));
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'old']]);
        $resolver = new RowMutationResolver(new \ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer($parser, $select), $registry, $store, new \ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer($parser, $select));
        $statement = (new \PhpMyAdmin\SqlParser\Parser('REPLACE INTO users (id) VALUES (2)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\ReplaceStatement::class, $statement);
        $mutation = $resolver->resolveReplace($statement, $statement->build());
        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\Row\ReplaceMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }


    public function testMultiTableTargets(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\Sql\MySqlParser();
        $select = new \ZtdQuery\Platform\MySql\Rewrite\Transformer\SelectTransformer();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id', 'name'], [], ['id'], [], []));
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'old']]);
        $resolver = new RowMutationResolver(new \ZtdQuery\Platform\MySql\Rewrite\Transformer\DeleteTransformer($parser, $select), $registry, $store, new \ZtdQuery\Platform\MySql\Rewrite\Transformer\UpdateTransformer($parser, $select));
        $targets = $resolver->multiTableTargets(['users'], 'UPDATE users SET name = 7');
        self::assertCount(1, $targets);
        self::assertSame('users', $targets[0]->tableName());
        self::assertSame(['id', 'name'], $targets[0]->columns());
        self::assertSame(['id'], $targets[0]->primaryKeys());
    }

}
