<?php

declare(strict_types=1);

namespace Tests\Unit\Mutation\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Mutation\Statement\RowMutation;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\DmlWhereClauseExtractor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Mutation\Statement\TargetName::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlCastRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlCteShadowComposer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlFullTextSearchRewriter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlGeneratedColumnProjector::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlPartitionSelectionRewriter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlSelectRelationParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlTypeSemantics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlUpsertAssignmentExtractor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlUpsertExpressionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\MySqlValueRenderer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\AssignmentExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Cte\HeaderParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Cte\IdentifierReferences::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\OptionalInsertIntoNormalizer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Relation\ExpressionNames::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Relation\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\AssignmentReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\ExpressionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\LiteralReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Parsing\Upsert\StringLiteral::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\FullText\ExpressionEditor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\Partition\SelectionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Projection\Partition\SourceProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\DeleteTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Delete\ResultSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Delete\TargetProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\SelectTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Set\OrderRewriter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Set\ValueNormalizer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Shadow\CteRows::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\UpdateTransformer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Update\ResultSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Transformer\Update\TargetProjection::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Type\CastTypeResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Type\Enum\RankEdits::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Type\Value\ScalarExpression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Type\Value\StringCoercion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\UpdateAssignmentExtractor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\UpdateSourceExtractor::class)]
#[CoversClass(RowMutation::class)]
final class RowMutationTest extends TestCase
{
    public function testResolveInsert(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\MySqlParser();
        $select = new \ZtdQuery\Platform\MySql\Transformer\SelectTransformer();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id', 'name'], [], ['id'], [], []));
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'old']]);
        $resolver = new RowMutation(new \ZtdQuery\Platform\MySql\Transformer\DeleteTransformer($parser, $select), $registry, $store, new \ZtdQuery\Platform\MySql\Transformer\UpdateTransformer($parser, $select));
        $statement = (new \PhpMyAdmin\SqlParser\Parser('INSERT INTO users (id) VALUES (2)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\InsertStatement::class, $statement);
        $mutation = $resolver->resolveInsert($statement, $statement->build());
        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\InsertMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveUpdate(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\MySqlParser();
        $select = new \ZtdQuery\Platform\MySql\Transformer\SelectTransformer();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id', 'name'], [], ['id'], [], []));
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'old']]);
        $resolver = new RowMutation(new \ZtdQuery\Platform\MySql\Transformer\DeleteTransformer($parser, $select), $registry, $store, new \ZtdQuery\Platform\MySql\Transformer\UpdateTransformer($parser, $select));
        $statement = (new \PhpMyAdmin\SqlParser\Parser('UPDATE users SET name = 7'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\UpdateStatement::class, $statement);
        $mutation = $resolver->resolveUpdate($statement, $statement->build());
        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\UpdateMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveDelete(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\MySqlParser();
        $select = new \ZtdQuery\Platform\MySql\Transformer\SelectTransformer();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id', 'name'], [], ['id'], [], []));
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'old']]);
        $resolver = new RowMutation(new \ZtdQuery\Platform\MySql\Transformer\DeleteTransformer($parser, $select), $registry, $store, new \ZtdQuery\Platform\MySql\Transformer\UpdateTransformer($parser, $select));
        $statement = (new \PhpMyAdmin\SqlParser\Parser('DELETE FROM users'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\DeleteStatement::class, $statement);
        $mutation = $resolver->resolveDelete($statement, $statement->build());
        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\DeleteMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveReplace(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\MySqlParser();
        $select = new \ZtdQuery\Platform\MySql\Transformer\SelectTransformer();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id', 'name'], [], ['id'], [], []));
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'old']]);
        $resolver = new RowMutation(new \ZtdQuery\Platform\MySql\Transformer\DeleteTransformer($parser, $select), $registry, $store, new \ZtdQuery\Platform\MySql\Transformer\UpdateTransformer($parser, $select));
        $statement = (new \PhpMyAdmin\SqlParser\Parser('REPLACE INTO users (id) VALUES (2)'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\ReplaceStatement::class, $statement);
        $mutation = $resolver->resolveReplace($statement, $statement->build());
        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\ReplaceMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
    }

    public function testResolveTruncate(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\MySqlParser();
        $select = new \ZtdQuery\Platform\MySql\Transformer\SelectTransformer();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id', 'name'], [], ['id'], [], []));
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'old']]);
        $resolver = new RowMutation(new \ZtdQuery\Platform\MySql\Transformer\DeleteTransformer($parser, $select), $registry, $store, new \ZtdQuery\Platform\MySql\Transformer\UpdateTransformer($parser, $select));
        $statement = (new \PhpMyAdmin\SqlParser\Parser('TRUNCATE TABLE users'))->statements[0];
        self::assertInstanceOf(\PhpMyAdmin\SqlParser\Statements\TruncateStatement::class, $statement);
        $mutation = $resolver->resolveTruncate($statement, $statement->build());
        self::assertInstanceOf(\ZtdQuery\Shadow\Mutation\TruncateMutation::class, $mutation);
        self::assertSame('users', $mutation->tableName());
        $mutation->apply($store, []);
        self::assertSame([], $store->get('users'));
    }

    public function testMultiTableTargets(): void
    {
        $parser = new \ZtdQuery\Platform\MySql\MySqlParser();
        $select = new \ZtdQuery\Platform\MySql\Transformer\SelectTransformer();
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        $registry->register('users', new \ZtdQuery\Schema\TableDefinition(['id', 'name'], [], ['id'], [], []));
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'old']]);
        $resolver = new RowMutation(new \ZtdQuery\Platform\MySql\Transformer\DeleteTransformer($parser, $select), $registry, $store, new \ZtdQuery\Platform\MySql\Transformer\UpdateTransformer($parser, $select));
        $targets = $resolver->multiTableTargets(['users'], 'UPDATE users SET name = 7');
        self::assertCount(1, $targets);
        self::assertSame('users', $targets[0]->tableName());
        self::assertSame(['id', 'name'], $targets[0]->columns());
        self::assertSame(['id'], $targets[0]->primaryKeys());
    }

}
