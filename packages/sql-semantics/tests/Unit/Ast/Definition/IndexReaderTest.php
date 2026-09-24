<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Definition;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\Definition\IndexReader;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\FunctionSignature;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaBuilder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Binder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TypeResolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scope::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(FunctionSignature::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexElement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\BuiltinResult::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\SignatureInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\Builtins::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Expression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Join::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\TableUse::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Ordering::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(DialectParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexKeys::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(IndexReader::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Serializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\StatementFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SimpleSerializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Transformation\Context::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\InsertStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ConfigurationStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\TableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\DeleteStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\MergeStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ValuesStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\UpdateStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CompoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CreateIndexStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CreateTableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Literal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\ExpressionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Build::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Parts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Atom::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Source::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Format::class)]
final class IndexReaderTest extends TestCase
{
    public function testReadNamedAndUnnamedQualifiedIndexes(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE app.t(id INTEGER); CREATE INDEX ON app.t(id)');
        $index = $schema->tables[0]->indexes[0];
        self::assertNull($index->name);
        self::assertSame('app', $index->schema);
        self::assertSame(['app', 't'], $index->table);
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE aux.t(id INTEGER); CREATE INDEX aux.ix ON t(id)');
        self::assertSame(['aux', 't'], $schema->tables[0]->indexes[0]->table);
    }

    public function testTableReadsInlineMysqlIndexes(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, name TEXT, KEY ix (id), FULLTEXT INDEX words (name))')->tables[0];
        self::assertSame(['ix', 'words'], array_column($table->indexes, 'name'));
        self::assertSame('fulltext', $table->indexes[1]->properties->kind->value);
    }

    public function testDefinitionRetainsIncludesPredicateAndFlags(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, name TEXT); CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS ix ON t USING btree(name) INCLUDE(id) NULLS NOT DISTINCT WITH(fillfactor=80) WHERE id>0');
        $index = $schema->tables[0]->indexes[0];
        self::assertSame('btree', $index->method);
        self::assertSame(['id'], $index->include);
        self::assertTrue($index->unique);
        $statement = (new Binder($schema))->bind($index->source->toString());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateIndexStatement::class, $statement);
        self::assertTrue($statement->concurrently);
        self::assertFalse($index->properties->nullsDistinct);
        self::assertTrue($statement->ifNotExists);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $index->properties->storageParameters[0]->value);
        self::assertSame('80', $index->properties->storageParameters[0]->value->spelling());
        self::assertNotNull($index->predicate);
    }


    public function testReadLowercaseMysqlHeaderMethodAndOptions(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, 'app'))->build('create table t(name varchar(10))', "create index ix using HASH on app.t(name(5) asc) comment 'key' visible");
        $index = $schema->tables[0]->indexes[0];
        self::assertSame('ix', $index->name);
        self::assertSame('app', $index->schema);
        self::assertSame(['app', 't'], $index->table);
        self::assertFalse($index->unique);
        self::assertSame('hash', $index->method);
        self::assertSame('key', $index->properties->comment);
        self::assertTrue($index->properties->visible);
        self::assertSame('ASC', $index->elements[0]->direction?->value);
        self::assertSame([], $index->include);
        self::assertNull($index->predicate);
    }

    public function testTableRetainsNamedConstraintsAfterAnUnrelatedConstraint(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('create table t(id integer, foreign key(id) references p(id), constraint uq unique(id), constraint pk primary key(id))')->tables[0];
        self::assertSame(['uq', 'pk'], [$table->constraints[1]->name, $table->constraints[2]->name]);
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\UniqueKey::class, $table->constraints[1]);
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\PrimaryKey::class, $table->constraints[2]);
        self::assertSame('public', $table->schema);
        self::assertSame(['id'], $table->constraints[2]->localColumns());
    }

    public function testReadSpatialAndFulltextKindsDoNotImplyUniqueness(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(g GEOMETRY, name TEXT, SPATIAL INDEX shape(g), FULLTEXT KEY words(name))')->tables[0];
        self::assertSame('spatial', $table->indexes[0]->properties->kind->value);
        self::assertSame('fulltext', $table->indexes[1]->properties->kind->value);
        self::assertSame([false, false], array_column($table->indexes, 'unique'));
        self::assertSame(['shape', 'words'], array_column($table->indexes, 'name'));
    }

    public function testReadPostgresHeaderAndSqliteSchemaQuoting(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('create table app.t(id integer); create unique index concurrently if not exists ix on only app.t using btree(id) nulls distinct');
        $index = $schema->tables[0]->indexes[0];
        self::assertSame(['app', 't'], $index->table);
        self::assertTrue($index->properties->nullsDistinct);
        $statement = (new Binder($schema))->bind($index->source->toString());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateIndexStatement::class, $statement);
        self::assertTrue($statement->concurrently);
        self::assertTrue($statement->ifNotExists);
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('create table "aux".t(id integer); create index if not exists "aux"."i.x" on t(id)');
        self::assertSame('i.x', $schema->tables[0]->indexes[0]->name);
        self::assertSame('aux', $schema->tables[0]->indexes[0]->schema);
        $sqlite = (new Binder($schema))->bind($schema->tables[0]->indexes[0]->source->toString());
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateIndexStatement::class, $sqlite);
        self::assertTrue($sqlite->ifNotExists);
    }

    public function testTargetReadsTheTableFollowingOnUnderEachGrammar(): void
    {
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n VARCHAR(10))')))->bind('CREATE INDEX ix ON t (n(5))');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateIndexStatement::class, $mysql);
        self::assertSame(['t'], $mysql->index->definition->table);
        self::assertSame(['t'], $mysql->table->name->parts);
        $postgres = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE app.t(id INT)')))->bind('CREATE INDEX ix ON app.t USING btree (id)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateIndexStatement::class, $postgres);
        self::assertSame(['app', 't'], $postgres->index->definition->table);
        self::assertSame(['app', 't'], $postgres->table->name->parts);
        self::assertSame('btree', $postgres->index->definition->method);
    }

    public function testReadNamesAMySqlIndexWrittenWithATypeClause(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('CREATE INDEX type TYPE BTREE ON t (id)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateIndexStatement::class, $statement);
        self::assertSame(['type', 'btree'], [$statement->index->definition->name, $statement->index->definition->method]);
    }

    public function testDefinitionReadsTheTypeOfATableIndex(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, KEY ix USING HASH (id))')->tables[0];
        self::assertSame('hash', $table->indexes[0]->method);
    }

    /**
     * @return array<string, array{Dialect, string, string, ?list<mixed>}>
     */
    public static function providerReadStatements(): array
    {
        return [
            'postgresql full header' => [Dialect::PostgreSql, 'dflt', 'CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS ix ON app.t USING btree (a) INCLUDE (b, c) WHERE a > 0', ['app', 'ix', ['app', 't'], true, 'btree', ['b', 'c'], 'a > 0', 'IndexStmt', ['concurrently' => true, 'if_not_exists' => true]]],
            'postgresql unnamed' => [Dialect::PostgreSql, 'dflt', 'CREATE INDEX ON t (a)', ['dflt', null, ['dflt', 't'], false, null, [], null, 'IndexStmt', []]],
            'postgresql without default schema' => [Dialect::PostgreSql, '', 'create index ix on t using gin (a)', ['', 'ix', ['t'], false, 'gin', [], null, 'IndexStmt', []]],
            'mysql unique' => [Dialect::MySql, 'dflt', 'CREATE UNIQUE INDEX ix ON t (a)', ['dflt', 'ix', ['dflt', 't'], true, null, [], null, 'create_index_stmt', []]],
            'mysql fulltext' => [Dialect::MySql, 'dflt', 'CREATE FULLTEXT INDEX ix ON t (a)', ['dflt', 'ix', ['dflt', 't'], false, null, [], null, 'create_index_stmt', ['kind' => 'fulltext']]],
            'mysql spatial' => [Dialect::MySql, 'dflt', 'CREATE SPATIAL INDEX ix ON db.t (a)', ['db', 'ix', ['db', 't'], false, null, [], null, 'create_index_stmt', ['kind' => 'spatial']]],
            'mysql two methods' => [Dialect::MySql, 'dflt', 'create index ix using btree on t (a) using hash', ['dflt', 'ix', ['dflt', 't'], false, 'btree', [], null, 'create_index_stmt', ['using' => 'hash']]],
            'sqlite qualified name' => [Dialect::Sqlite, 'dflt', 'CREATE UNIQUE INDEX IF NOT EXISTS aux.ix ON t (a) WHERE a > 0', ['aux', 'ix', ['aux', 't'], true, null, [], 'a > 0', 'input', ['if_not_exists' => true]]],
            'mysql table' => [Dialect::MySql, 'dflt', 'CREATE TABLE t (a int)', null],
            'postgresql view mentioning index and on' => [Dialect::PostgreSql, 'dflt', 'CREATE VIEW v AS SELECT 1 AS index FROM t JOIN u ON true', null],
            'mysql spatial reference system' => [Dialect::MySql, 'dflt', "CREATE SPATIAL REFERENCE SYSTEM 4326 NAME 'x' DEFINITION 'y'", null],
        ];
    }

    /**
     * @param ?list<mixed> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerReadStatements')]
    public function testReadReturnsTheDeclaredIndex(Dialect $dialect, string $defaultSchema, string $sql, ?array $expected): void
    {
        $index = (new IndexReader(new Identifiers($dialect), $defaultSchema))->read((new DialectParser($dialect))->parse($sql));
        self::assertSame($expected, $index === null ? null : [$index->schema, $index->name, $index->table, $index->unique, $index->method, $index->include, $index->predicate === null ? null : Tree::text($index->predicate), $index->source->name, $index->options]);
    }

    public function testTableReadsEveryKeyAfterOtherConstraints(): void
    {
        $indexes = (new IndexReader(new Identifiers(Dialect::MySql), 'dflt'))->table((new DialectParser(Dialect::MySql))->parse('CREATE TABLE t(a INT, PRIMARY KEY (a), key ix using hash (a), KEY jx (a) USING HASH, KEY (a))'), ['app', 't']);
        self::assertSame([['app', 'ix', ['app', 't'], 'hash', []], ['app', 'jx', ['app', 't'], 'hash', ['using' => 'HASH']], ['app', null, ['app', 't'], null, []]], array_map(static fn (\SqlSemantics\Ast\Declaration\IndexDefinition $index): array => [$index->schema, $index->name, $index->table, $index->method, $index->options], $indexes));
    }

    public function testTargetReadsTheTokensFollowingOn(): void
    {
        $reader = new IndexReader(new Identifiers(Dialect::MySql), 'dflt');
        $root = (new DialectParser(Dialect::MySql))->parse('CREATE INDEX ix ON db.t (a)');
        self::assertSame(['db', 't'], $reader->target($root, array_slice($root->tokens(), 4)));
    }

    /**
     * @return array<string, array{Dialect, string, ?string, array<string, string|bool|list<string>>, bool}>
     */
    public static function providerDefinitionWords(): array
    {
        return [
            'if not exists alone' => [Dialect::Sqlite, 'CREATE TABLE IF NOT EXISTS t(a)', 'ifnotexists', ['if_not_exists' => true], false],
            'if not exists ending at the ninth word' => [Dialect::PostgreSql, 'SELECT 1; CREATE UNIQUE INDEX IF NOT EXISTS ix ON t(a)', null, ['if_not_exists' => true], false],
            'if not exists ending at the tenth word' => [Dialect::PostgreSql, 'SELECT 1, 2; CREATE INDEX IF NOT EXISTS ix ON t(a)', null, [], false],
            'fulltext as third word' => [Dialect::Sqlite, 'SELECT 1 fulltext', null, ['kind' => 'fulltext'], false],
            'fulltext as fourth word' => [Dialect::Sqlite, 'SELECT a, fulltext', null, [], false],
            'spatial as third word' => [Dialect::Sqlite, 'SELECT 1 spatial', null, ['kind' => 'spatial'], false],
            'spatial as fourth word' => [Dialect::Sqlite, 'SELECT a, spatial', null, [], false],
            'spatial before fulltext' => [Dialect::Sqlite, 'SELECT spatial fulltext', null, ['kind' => 'fulltext'], false],
            'fulltext after the kind window' => [Dialect::Sqlite, 'SELECT spatial, fulltext', null, ['kind' => 'spatial'], false],
            'no kind' => [Dialect::Sqlite, 'SELECT 1', null, [], false],
            'unique alone' => [Dialect::Sqlite, 'CREATE UNIQUE INDEX i ON t(a)', 'uniqueflag', [], true],
            'unique as fourth word' => [Dialect::Sqlite, 'CREATE TABLE t(a INT NULL UNIQUE)', 'columnlist', [], true],
            'unique as fifth word' => [Dialect::Sqlite, 'CREATE TABLE t(a INT NOT NULL UNIQUE)', 'columnlist', [], false],
            'primary first' => [Dialect::Sqlite, 'CREATE TABLE t(a INT PRIMARY KEY)', 'ccons', [], true],
            'primary as fourth word' => [Dialect::Sqlite, 'CREATE TABLE t(a INT NULL PRIMARY KEY)', 'columnlist', [], true],
            'primary as fifth word' => [Dialect::Sqlite, 'CREATE TABLE t(a INT NOT NULL PRIMARY KEY)', 'columnlist', [], false],
        ];
    }

    /**
     * @param array<string, string|bool|list<string>> $options
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDefinitionWords')]
    public function testDefinitionReadsFlagsFromTheLeadingWords(Dialect $dialect, string $sql, ?string $node, array $options, bool $unique): void
    {
        $root = (new DialectParser($dialect))->parse($sql);
        $index = (new IndexReader(new Identifiers($dialect), 'dflt'))->definition(Tree::outer($root, [$node ?? 'none'])[0] ?? $root, 'main', 'ix', ['main', 't'], []);
        self::assertSame([$options, $unique], [$index->options, $index->unique]);
    }
}
