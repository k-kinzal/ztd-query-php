<?php

declare(strict_types=1);

namespace Tests\Unit\Rewriting\Mutation\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\Mutation\AlterTableMutation;
use ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Alter\AddColumnResolver;
use ZtdQuery\Platform\Sqlite\SqliteSchemaParser;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(AddColumnResolver::class)]
#[UsesClass(AlterTableMutation::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Alter\AlteredTableProjection::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Rewriting\Mutation\Resolution\MutationTableLookup::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Expression\AssignmentColumnParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Expression\AssignmentParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Expression\ValueListParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Insert\InsertClauseParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\ExpressionSpan::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\IdentifierDecoder::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\LiteralMasker::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\OpaqueSqlSpan::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\QuotedSpan::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Lexing\TopLevelKeywordScanner::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Relation\RelationSourceParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Statement\StatementClassifier::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Statement\StatementStructure::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Statement\TargetTableParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Parsing\Update\UpdateClauseParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\Create\ColumnDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\Create\TableBodyParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\Create\TableDefinitionBuilder::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\Create\VirtualTableParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\ForeignKey\ForeignKeyEntryParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\ForeignKey\ForeignKeyTokens::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteColumnTypeMapper::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteForeignKeyDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteLexerProfile::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteLexicalMasker::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteParser::class)]
#[UsesClass(SqliteSchemaParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteSelectRelationParser::class)]
final class AddColumnResolverTest extends TestCase
{
    public function testResolveAlterAddColumnProjectsDefaultAndRegistersNewSchema(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id', 'name'], ['id' => 'INTEGER', 'name' => 'TEXT'], ['id'], ['id'], []);
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);
        $mutation = (new AddColumnResolver($registry, new SqliteSchemaParser()))->resolveAlterAddColumn('ALTER TABLE users ADD score INTEGER DEFAULT 0', 'users', 'score INTEGER DEFAULT 0');
        self::assertInstanceOf(AlterTableMutation::class, $mutation);
        self::assertSame('SELECT "id", "name", 0 AS "score" FROM "users"', $mutation->resultSelect());
        self::assertSame($definition, $registry->get('users'));
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice', 'score' => 0]]);
        self::assertSame(['id', 'name', 'score'], $registry->get('users')->columns);
        self::assertSame([['id' => 1, 'name' => 'Alice', 'score' => 0]], $store->get('users'));
    }

    public function testResolveAlterAddColumnRejectsDuplicateColumnIgnoringCase(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id', 'name'], ['id' => 'INTEGER', 'name' => 'TEXT'], ['id'], ['id'], []);
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);
        $this->expectException(\ZtdQuery\Exception\ColumnAlreadyExistsException::class);
        (new AddColumnResolver($registry, new SqliteSchemaParser()))->resolveAlterAddColumn('ALTER TABLE users ADD NAME TEXT', 'users', 'NAME TEXT');
    }

    public function testResolveAlterAddColumnRejectsMultipleColumns(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id', 'name'], ['id' => 'INTEGER', 'name' => 'TEXT'], ['id'], ['id'], []);
        $registry->register('users', $definition);
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);
        $this->expectException(\ZtdQuery\Exception\UnsupportedSqlException::class);
        (new AddColumnResolver($registry, new SqliteSchemaParser()))->resolveAlterAddColumn('ALTER TABLE users ADD a TEXT, b TEXT', 'users', 'a TEXT, b TEXT');
    }

}
