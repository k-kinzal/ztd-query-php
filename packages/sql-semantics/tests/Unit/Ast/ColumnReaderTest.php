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

#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
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
#[CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
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
final class ColumnReaderTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testReadPreservesDeclaredNullabilityIndependentlyOfUsage(Dialect $dialect): void
    {
        $table = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)')->tables[0];
        self::assertSame(Nullability::MaybeNull, $table->columns[1]->nullability);
        self::assertSame(Nullability::NotNull, $table->columns[2]->nullability);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\SuppliedColumn::class, $table->columns[1]->generation);
        self::assertNull($table->columns[1]->generation->default);
    }
    public function testReadPreservesNamedNullabilityIdentityAndCollation(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('create table t (id integer generated always as identity, value text constraint required not null, optional text collate "C")');
        self::assertSame('not-null', $schema->tables[0]->columns[0]->nullability->value);
        self::assertSame('not-null', $schema->tables[0]->columns[1]->nullability->value);
        self::assertSame('maybe-null', $schema->tables[0]->columns[2]->nullability->value);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\SuppliedColumn::class, $schema->tables[0]->columns[2]->generation);
        self::assertSame(['C'], $schema->tables[0]->columns[2]->attributes->collation?->parts);
    }

    public function testReadSqliteTypelessGeneratedColumn(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('create table t (id, value as (id + 1))');
        self::assertSame('', $schema->tables[0]->columns[0]->type->name);
        self::assertSame('blob', $schema->tables[0]->columns[0]->type->affinity?->value);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\ComputedColumn::class, $schema->tables[0]->columns[1]->generation);
        self::assertSame('id + 1', $schema->tables[0]->columns[1]->generation->expression->source->toString());
    }



    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testReadRetainsGeneratedExpressionBoundaries(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('create table t (n integer default 1, computed integer generated always as (n + 2) stored)');
        $base = $schema->tables[0]->columns[0];
        $generated = $schema->tables[0]->columns[1];
        self::assertInstanceOf(\SqlSemantics\Schema\Column\SuppliedColumn::class, $base->generation);
        self::assertNotNull($base->generation->default);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\ComputedColumn::class, $generated->generation);
        self::assertSame('+', $generated->generation->expression->spelling());
        self::assertSame('n', $generated->generation->expression->lineage()[0]->column->name);
    }


    public function testReadIgnoresKeywordsSpelledInsideExpressionsAndLiterals(): void
    {
        $postgres = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (identity int, a int CHECK (identity > 0))')->tables[0];
        $mysql = (new SchemaBuilder(Dialect::MySql))->build("CREATE TABLE t (b INT COMMENT 'IDENTITY')")->tables[0];
        self::assertSame(Nullability::MaybeNull, $postgres->columns[1]->nullability);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\SuppliedColumn::class, $postgres->columns[1]->generation);
        self::assertSame(Nullability::MaybeNull, $mysql->columns[0]->nullability);
    }

    public function testReadsAMySql57GeneratedColumn(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build());
        $statement = $binder->bind("CREATE TABLE t (a INT, d INT GENERATED ALWAYS AS (a) STORED NOT NULL COMMENT 'x')");
        self::assertSame("CREATE TABLE `t`(`a` integer, `d` integer GENERATED ALWAYS AS(`a`) STORED COMMENT 'x' NOT NULL)", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }


    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testGeneratedExpressionFindsTheExpressionAfterTheType(string $version): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, $version))->parse('CREATE TABLE t (a INT, b INT AS (a + 1) STORED)');
        $columns = \SqlSemantics\Ast\Tree::outer($tree, ['column_def']);
        self::assertNull(\SqlSemantics\Ast\ColumnReader::generatedExpression($columns[0]));
        self::assertSame('a + 1', \SqlSemantics\Ast\Tree::text(\SqlSemantics\Ast\ColumnReader::generatedExpression($columns[1]) ?? $columns[1]));
    }

    public function testAttributeWordsDropsTheConstraintNameAndExpressions(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('CREATE TABLE t (a int CONSTRAINT identity CHECK (a > 0))');
        self::assertSame(['CHECK', '(', ')'], \SqlSemantics\Ast\ColumnReader::attributeWords(\SqlSemantics\Ast\Tree::outer($tree, ['ColConstraint'])[0]));
    }

    #[TestWith(['mysql-5.6.51', 'c INT NOT NULL NULL', 'maybe-null'])]
    #[TestWith(['mysql-5.7.44', 'c INT NULL NOT NULL', 'not-null'])]
    #[TestWith(['mysql-8.0.44', 'c INT NOT NULL NULL NOT NULL', 'not-null'])]
    #[TestWith(['mysql-8.4.7', 'c INT AUTO_INCREMENT NULL, KEY (c)', 'maybe-null'])]
    #[TestWith(['mysql-8.4.7', 'c INT NULL AUTO_INCREMENT, KEY (c)', 'not-null'])]
    #[TestWith(['mysql-8.4.7', 'c SERIAL', 'not-null'])]
    #[TestWith(['mysql-8.4.7', 'c SERIAL NULL', 'maybe-null'])]
    #[TestWith(['mysql-5.6.51', 'c INT NULL SERIAL DEFAULT VALUE', 'not-null'])]
    #[TestWith(['mysql-9.1.0', 'c INT SERIAL DEFAULT VALUE NULL', 'maybe-null'])]
    public function testNullabilityTakesTheLastMySqlNullOrNotNullAttribute(string $version, string $column, string $expected): void
    {
        $table = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (' . $column . ')')->tables[0];
        self::assertSame(Nullability::from($expected), $table->columns[0]->nullability);
    }

    #[TestWith(['c int NOT NULL NULL'])]
    #[TestWith(['c int NULL NOT NULL'])]
    #[TestWith(['c int NOT NULL CONSTRAINT n NULL'])]
    #[TestWith(['c serial NULL'])]
    #[TestWith(['c bigint NULL GENERATED ALWAYS AS IDENTITY'])]
    #[TestWith(['c int GENERATED BY DEFAULT AS IDENTITY NULL'])]
    public function testNullabilityDiagnosesConflictingPostgreSqlDeclarations(string $column): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::ConflictingNullability->message());
        (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (' . $column . ')');
    }

    public function testNullabilityDiagnosesAConflictingPostgreSqlStatement(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a int)'));
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::ConflictingNullability->message());
        $binder->bind('ALTER TABLE t ADD COLUMN c int NULL NOT NULL');
    }

    #[TestWith(['c int NULL NULL', 'maybe-null'])]
    #[TestWith(['c int NOT NULL NOT NULL', 'not-null'])]
    #[TestWith(['c serial', 'not-null'])]
    #[TestWith(['c smallserial NOT NULL', 'not-null'])]
    #[TestWith(['c int NULL PRIMARY KEY', 'not-null'])]
    #[TestWith(['c int PRIMARY KEY NULL', 'not-null'])]
    #[TestWith(['c int NULL UNIQUE', 'maybe-null'])]
    public function testNullabilityAcceptsTheRepeatedPostgreSqlDeclarations(string $column, string $expected): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (' . $column . ')')->tables[0];
        self::assertSame(Nullability::from($expected), $table->columns[0]->nullability);
    }

    #[TestWith(['c int NOT NULL NULL', 'not-null'])]
    #[TestWith(['c int NULL NOT NULL', 'not-null'])]
    #[TestWith(['c int NULL NULL', 'maybe-null'])]
    #[TestWith(['c int NULL PRIMARY KEY', 'maybe-null'])]
    public function testNullabilityLetsAnySqliteNotNullDecide(string $column, string $expected): void
    {
        $table = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (' . $column . ')')->tables[0];
        self::assertSame(Nullability::from($expected), $table->columns[0]->nullability);
    }

    #[TestWith(['mysql', 'CREATE TABLE t (c INT NOT NULL NULL, d INT NULL NOT NULL)', 'CREATE TABLE `t`(`c` integer NULL, `d` integer NOT NULL)'])]
    #[TestWith(['mysql', 'CREATE TABLE t (c INT AUTO_INCREMENT NULL, KEY (c))', 'CREATE TABLE `t`(`c` integer AUTO_INCREMENT NULL, INDEX(`c`))'])]
    #[TestWith(['mysql', 'CREATE TABLE t (c SERIAL NULL, d SERIAL)', 'CREATE TABLE `t`(`c` serial NULL, `d` serial NOT NULL)'])]
    #[TestWith(['postgresql', 'CREATE TABLE t (c int NULL NULL, d serial)', 'CREATE TABLE "public"."t"("c" integer NULL, "d" serial NOT NULL)'])]
    #[TestWith(['postgresql', 'CREATE TABLE t (c int NULL, d int NULL, CONSTRAINT pk PRIMARY KEY (c, d))', 'CREATE TABLE "public"."t"("c" integer NOT NULL, "d" integer NOT NULL, CONSTRAINT "pk" PRIMARY KEY("c", "d"))'])]
    #[TestWith(['sqlite', 'CREATE TABLE t (c int NOT NULL NULL)', 'CREATE TABLE "main"."t"("c" "int" NOT NULL)'])]
    public function testNullabilitySurvivesSimpleSerialization(string $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::from($dialect)))->build());
        $written = (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($sql));
        self::assertSame($expected, $written);
        self::assertSame($written, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($written)));
    }
}
