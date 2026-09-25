<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;

#[CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[CoversClass(\SqlSemantics\Binding\TypeResolution::class)]
#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[CoversClass(\SqlSemantics\Binding\Scope::class)]
#[CoversClass(\SqlSemantics\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Model\ColumnBinding::class)]
#[CoversClass(\SqlSemantics\Model\Expression::class)]
#[CoversClass(\SqlSemantics\Model\Join::class)]
#[CoversClass(\SqlSemantics\Model\Ordering::class)]
#[CoversClass(\SqlSemantics\Model\OutputColumn::class)]
#[CoversClass(\SqlSemantics\Model\BoundQuery::class)]
#[CoversClass(\SqlSemantics\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Schema::class)]
#[CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexKeys::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\FunctionSignature::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\Builtins::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\BuiltinResult::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\SignatureInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexElement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Serializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\StatementFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SimpleSerializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
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
final class SchemaReaderTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testReadDeclaredKeysDefaultsAndChecks(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL DEFAULT 1, UNIQUE(score), FOREIGN KEY (parent_id) REFERENCES users(id), CHECK (score > 0))');
        $table = $schema->tables[0];
        self::assertSame(['id', 'parent_id', 'score'], array_column($table->columns, 'name'));
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\SuppliedColumn::class, $table->columns[2]->generation);
        self::assertNotNull($table->columns[2]->generation->default);
        self::assertSame(4, count($table->constraints));
        self::assertSame(['parent_id'], $table->constraints[2]->localColumns());
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\ForeignKey::class, $table->constraints[2]);
        self::assertSame(['users'], $table->constraints[2]->referencedTable->parts);
        self::assertSame(['id'], $table->constraints[2]->referencedColumns);
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\Check::class, $table->constraints[3]);
        self::assertSame('>', $table->constraints[3]->predicate->spelling());
    }

    public function testTableRejectsDuplicateDeclarations(): void
    {
        $builder = new SchemaBuilder(Dialect::PostgreSql);
        $sql = 'CREATE TABLE users (id INTEGER)';
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Duplicate table');
        $builder->build($sql, $sql);
    }

    public function testPrimaryKeysRejectsMissingColumn(): void
    {
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('unknown column');
        (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER, PRIMARY KEY (missing))');
    }

    public function testColumnNodesPreservesSqliteDeclarationOrder(): void
    {
        $table = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE users (z INTEGER, a TEXT, m REAL)')->tables[0];
        self::assertSame(['z', 'a', 'm'], array_column($table->columns, 'name'));
    }

    public function testPrimaryNotNullRespectsSqliteDescException(): void
    {
        $table = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE users (id INTEGER PRIMARY KEY DESC)')->tables[0];
        self::assertSame(Nullability::MaybeNull, $table->columns[0]->nullability);
    }

    public function testPrimaryNotNullAliasesTheRowidForATableLevelDescendingKey(): void
    {
        $table = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE users (id INTEGER, CONSTRAINT pk PRIMARY KEY (id DESC))')->tables[0];
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
    }

    public function testCreateAsSelectExtractsResultColumns(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE users AS SELECT 1 AS id');
        self::assertSame('id', $schema->tables[0]->columns[0]->name);
        self::assertSame('integer', $schema->tables[0]->columns[0]->type->name);
    }

    public function testPrimaryKeysResolvesCaseInsensitiveSqliteConstraintColumns(): void
    {
        $table = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE users (id INTEGER, PRIMARY KEY (ID))')->tables[0];
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
    }

    #[TestWith(['strict'])]
    #[TestWith(['without rowid'])]
    public function testPrimaryKeysPromotesOnlyTheSqliteKey(string $option): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('create table t (ID text primary key, value text) ' . $option);
        self::assertSame('not-null', $schema->tables[0]->columns[0]->nullability->value);
        self::assertSame('maybe-null', $schema->tables[0]->columns[1]->nullability->value);
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    public function testPrimaryKeysPreservesIdentifierCaseRules(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE t (ID INTEGER PRIMARY KEY, value TEXT)');
        self::assertSame('not-null', $schema->tables[0]->columns[0]->nullability->value);
        self::assertSame('maybe-null', $schema->tables[0]->columns[1]->nullability->value);
    }

    public function testReadCanReadMultipleDeclarationTreesDirectly(): void
    {
        $parser = new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql);
        $reader = new \SqlSemantics\Ast\SchemaReader(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public');
        $tree = $parser->parse('CREATE TABLE a (id INTEGER); CREATE TABLE b (name TEXT)');
        $tables = $reader->read([$tree]);
        self::assertSame(['a','b'], array_column($tables, 'name'));
        self::assertSame('id', $tables[0]->columns[0]->name);
        self::assertSame('name', $tables[1]->columns[0]->name);
        self::assertCount(1, $reader->columnNodes($tree->find('CreateStmt')[0]));
    }

    public function testReportAllowsDeclarationDiagnosticsWithoutDroppingStructure(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t (id INTEGER, id TEXT, PRIMARY KEY (missing))', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateTableStatement::class, $statement);
        self::assertSame(['duplicate-column', 'unknown-column', 'unknown-column'], array_column($statement->diagnostics, 'reason'));
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\PrimaryKey::class, $statement->definition->table->constraints[0]);
        self::assertSame(\SqlSemantics\Model\ExpressionKind::UnresolvedColumn, $statement->definition->table->constraints[0]->keys[0]->value()->kind);
        self::assertSame('t', $statement->definition->table->name);
        self::assertSame(['id', 'id'], array_column($statement->definition->table->columns, 'name'));
        self::assertSame('text', $statement->definition->table->columns[1]->type->name);
        self::assertSame(['missing'], $statement->definition->table->constraints[0]->localColumns());
    }


    public function testNamespacePlacesTemporaryTablesInTheTemporarySchema(): void
    {
        $postgres = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TEMP TABLE a(x INT)', 'CREATE TEMP TABLE pg_temp.b(x INT)', 'CREATE UNLOGGED TABLE c(x INT)');
        self::assertSame(['pg_temp', 'pg_temp', 'public'], array_map(static fn ($table): string => $table->schema, $postgres->tables));
        $sqlite = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TEMP TABLE a(x INT)', 'CREATE TABLE b(x INT)');
        self::assertSame(['temp', 'main'], array_map(static fn ($table): string => $table->schema, $sqlite->tables));
    }

    #[TestWith([Dialect::PostgreSql, 'CREATE TEMP TABLE public.t(a INT)'])]
    #[TestWith([Dialect::PostgreSql, 'CREATE LOCAL TEMPORARY TABLE s.t AS SELECT 1'])]
    #[TestWith([Dialect::Sqlite, 'CREATE TEMP TABLE main.t(a INT)'])]
    public function testNamespaceRejectsATemporaryTableInANamedSchema(Dialect $dialect, string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::TemporaryTableSchema->message());
        (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql);
    }


    #[TestWith([Dialect::PostgreSql, 'CREATE TABLE IF NOT EXISTS t (a int)', true])]
    #[TestWith([Dialect::PostgreSql, "CREATE TABLE t (a text DEFAULT 'IF NOT EXISTS')", false])]
    #[TestWith([Dialect::MySql, 'CREATE TEMPORARY TABLE IF NOT EXISTS t (a INT)', true])]
    #[TestWith([Dialect::Sqlite, "CREATE TABLE t (a TEXT CHECK (a <> 'IF NOT EXISTS'))", false])]
    public function testIfNotExistsReadsOnlyTheWordsBeforeTheName(Dialect $dialect, string $sql, bool $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateTableStatement::class, $statement);
        self::assertSame($expected, $statement->ifNotExists);
    }

    public function testTableKeepsTheCatalogOfAThreePartPostgreSqlName(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE TABLE c.s.t (a int)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateTableStatement::class, $statement);
        self::assertSame('CREATE TABLE "c"."s"."t"("a" integer)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame('s', $statement->definition->table->schema);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testTableRejectsAFourPartPostgreSqlName(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::RelationName->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE x.c.s.t (a int)');
    }

    public function testPrimaryKeysReadsSqliteTableOptionsNotLiterals(): void
    {
        $literal = (new SchemaBuilder(Dialect::Sqlite))->build("CREATE TABLE t (a TEXT DEFAULT 'STRICT' PRIMARY KEY)")->tables[0];
        $strict = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a TEXT PRIMARY KEY) WITHOUT ROWID')->tables[0];
        $named = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a INTEGER CONSTRAINT desc_key PRIMARY KEY)')->tables[0];
        self::assertSame(Nullability::MaybeNull, $literal->columns[0]->nullability);
        self::assertSame(Nullability::NotNull, $strict->columns[0]->nullability);
        self::assertSame(Nullability::NotNull, $named->columns[0]->nullability);
    }


    public function testAutoIncrementCarriesATableLevelKeyToItsColumn(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind('CREATE TABLE u (a INTEGER, b INT, PRIMARY KEY (a AUTOINCREMENT) ON CONFLICT IGNORE)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateTableStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\AutoIncrementColumn::class, $statement->definition->table->columns[0]->generation);
        $expected = 'CREATE TABLE "main"."u"("a" "integer" NOT NULL PRIMARY KEY ON CONFLICT IGNORE AUTOINCREMENT, "b" "int")';
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    #[TestWith(['CREATE TABLE u (a INT, b INT, PRIMARY KEY (a AUTOINCREMENT))'])]
    #[TestWith(['CREATE TABLE u (a INTEGER, b INT, PRIMARY KEY (a, b AUTOINCREMENT))'])]
    public function testAutoIncrementOutsideAnIntegerKeyIsInvalidSql(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::AutoIncrementKey->message());
        (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind($sql);
    }

    #[TestWith(['mysql-8.4.7', 0])]
    #[TestWith(['mysql-9.0.1', 2])]
    #[TestWith(['mysql-9.1.0', 2])]
    public function testColumnNodesEnforceInlineReferencesFromMySql9(string $release, int $count): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE p (x INT PRIMARY KEY)', 'CREATE TABLE u (a INT REFERENCES p (x) ON DELETE CASCADE, b INT REFERENCES p)');
        self::assertCount($count, $schema->tables[1]->constraints);
        $binder = new Binder($schema);
        $statement = $binder->bind('CREATE TABLE v (a INT REFERENCES p (x))');
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
