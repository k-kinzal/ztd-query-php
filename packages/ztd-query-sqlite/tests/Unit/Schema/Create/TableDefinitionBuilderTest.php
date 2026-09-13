<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Create;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\Schema\Create\TableDefinitionBuilder;

#[CoversClass(TableDefinitionBuilder::class)]
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
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\ForeignKey\ForeignKeyEntryParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\Schema\ForeignKey\ForeignKeyTokens::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteColumnTypeMapper::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteForeignKeyDefinitionParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteLexerProfile::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteLexicalMasker::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteParser::class)]
#[UsesClass(\ZtdQuery\Platform\Sqlite\SqliteSelectRelationParser::class)]
final class TableDefinitionBuilderTest extends TestCase
{
    public function testAddDefinitionCollectsTypedColumnsAndPrimaryKeys(): void
    {
        $builder = new TableDefinitionBuilder();
        $builder->addDefinition('id INTEGER PRIMARY KEY');
        $builder->addDefinition('name TEXT NOT NULL');
        $builder->addDefinition('');
        $definition = $builder->build('CREATE TABLE users(id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        self::assertNotNull($definition);
        self::assertSame(['id', 'name'], $definition->columns);
        self::assertSame(['id'], $definition->primaryKeys);
        self::assertSame(['id', 'name'], $definition->notNullColumns);
        self::assertSame(\ZtdQuery\Schema\IdentityGenerationStrategy::MaxValue, $definition->identityStrategies['id']);
    }

    public function testAddConstraintPreservesCompositeAndUniqueKeys(): void
    {
        $builder = new TableDefinitionBuilder();
        $builder->addDefinition('a INTEGER');
        $builder->addDefinition('b INTEGER');
        $builder->addConstraint('CONSTRAINT pk PRIMARY KEY(a, b)');
        $builder->addConstraint('UNIQUE(b)');
        $definition = $builder->build('CREATE TABLE t(a INTEGER, b INTEGER, PRIMARY KEY(a, b))');
        self::assertNotNull($definition);
        self::assertSame(['a', 'b'], $definition->primaryKeys);
        self::assertSame(['unique_0' => ['b']], $definition->uniqueConstraints);
        self::assertSame([], $definition->identityStrategies);
    }

    public function testAddColumnPreservesDefaultsAndGeneratedExpressions(): void
    {
        $builder = new TableDefinitionBuilder();
        $builder->addColumn([
            'name' => 'total', 'type' => 'INTEGER', 'notNull' => false, 'primaryKey' => false,
            'unique' => true, 'default' => '0', 'generatedExpression' => '(2 + 3)',
        ]);
        $definition = $builder->build('CREATE TABLE t(total INTEGER)');
        self::assertNotNull($definition);
        self::assertSame(['total_UNIQUE' => ['total']], $definition->uniqueConstraints);
        self::assertSame(['total' => '0'], $definition->columnDefaults);
        self::assertSame(['total' => '(2 + 3)'], $definition->generatedExpressions);
    }

    public function testBuildRejectsMissingColumnsAndUnknownUniqueColumns(): void
    {
        $builder = new TableDefinitionBuilder();
        self::assertNull($builder->build('CREATE TABLE t()'));
        $builder->addDefinition('id INTEGER');
        $builder->addConstraint('UNIQUE(missing)');
        self::assertNull($builder->build('CREATE TABLE t(id INTEGER, UNIQUE(missing))'));
    }

    public function testBuildDisablesIdentityForWithoutRowid(): void
    {
        $builder = new TableDefinitionBuilder();
        $builder->addDefinition('id INTEGER PRIMARY KEY');
        self::assertSame([], $builder->build('CREATE TABLE t(id INTEGER PRIMARY KEY) WITHOUT ROWID')?->identityStrategies);
    }

}
