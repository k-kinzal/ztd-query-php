<?php

declare(strict_types=1);

namespace Tests\Unit\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;

#[CoversClass(\SqlSemantics\Binding\Scope::class)]
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
#[CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Binding\IdentitySequence::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\Nullability::class)]
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
final class ScopeTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testColumnRejectsAmbiguousUnqualifiedNames(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('unambiguously');
        (new Binder($schema))->bind('SELECT id FROM users a JOIN users b ON a.id=b.id');
    }

    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testMatchesAliasHidesOriginalTableName(Dialect $dialect): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $this->expectException(SemanticException::class);
        (new Binder($schema))->bind('SELECT users.id FROM users AS child');
    }

    #[TestWith([Dialect::PostgreSql, 'A FROM clause cannot name two items the same'])]
    #[TestWith([Dialect::MySql, 'A FROM clause cannot name two items the same'])]
    #[TestWith([Dialect::Sqlite, 'Duplicate relation'])]
    public function testCombineRejectsDuplicateAliases(Dialect $dialect, string $message): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage($message);
        (new Binder($schema))->bind('SELECT a.id FROM users a, users a');
    }

    public function testRejectsReferencesOutsideJoinOperands(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $this->expectException(SemanticException::class);
        (new Binder($schema))->bind('SELECT a.id FROM users a, users b JOIN users c ON a.id = c.id');
    }

    public function testRelationColumnIgnoresStoredProgramVariables(): void
    {
        $variable = new \SqlSemantics\Model\Definition\Routine\Body\Declaration\LocalVariable('n', new \SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain(\SqlSemantics\Type\TypeDescriptor::builtin(Dialect::MySql, 'integer')));
        $tables = new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::MySql))->build(), new \SqlSemantics\Ast\Identifiers(Dialect::MySql), '', program: (new \SqlSemantics\Binding\Statement\Routine\Program\ProgramNamespace())->declare([$variable]));
        $scope = new \SqlSemantics\Binding\Scope($tables->identifiers, queries: new \SqlSemantics\Binding\Query\QueryContext($tables));
        $token = new \SqlParser\Lexer\Token(0, 'IDENT', 'n', 0);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\LocalVariableReference::class, $scope->column(['n'], $token));
        $this->expectException(SemanticException::class);
        $scope->relationColumn(['n'], $token);
    }

    public function testExtendDoesNotMutateTheInputScope(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL)');
        $statement = (new Binder($schema))->bind('SELECT id FROM users');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), $statement->relations);
        $extended = $scope->extend('j0');
        self::assertSame([], $scope->extensions);
        self::assertSame(['r0' => ['j0']], $extended->extensions);
        self::assertSame(['r0' => ['j0', 'j1']], $extended->extend('j1')->extensions);
    }
    public function testOutputColumnsKeepsUsingColumnsFirst(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE a (x INTEGER, id INTEGER)', 'CREATE TABLE b (id INTEGER, y INTEGER)');
        $query = (new Binder($schema))->bind('SELECT * FROM a JOIN b USING (id)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame(['id', 'x', 'y'], array_column($query->outputs, 'name'));
    }

    public function testExtendMakesMergedJoinValuesNullable(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE x (k INTEGER)', 'CREATE TABLE a (id INTEGER NOT NULL)', 'CREATE TABLE b (id INTEGER NOT NULL)');
        $query = (new Binder($schema))->bind('SELECT id FROM x LEFT JOIN (a JOIN b USING(id)) ON x.k=a.id');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        self::assertSame('maybe-null', $query->outputs[0]->expression->nullability->value);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\Joining\OnJoin::class, $query->from);
        self::assertSame([$query->from->id], $query->outputs[0]->expression->nullExtendedBy);
    }

    public function testDiagnosticsPropagatesThroughLexicalScopes(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $diagnostics = new \SqlSemantics\Binding\Analysis\Diagnostics(true);
        $ids = new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql);
        $tables = new \SqlSemantics\Binding\TableResolver($schema, $ids, 'public', $diagnostics);
        $parent = new \SqlSemantics\Binding\Scope($ids, queries: new \SqlSemantics\Binding\Query\QueryContext($tables));
        self::assertSame($diagnostics, $parent->diagnostics());
        self::assertSame($diagnostics, (new \SqlSemantics\Binding\Scope($ids, parent: $parent))->diagnostics());
        self::assertFalse((new \SqlSemantics\Binding\Scope($ids))->diagnostics()->collect);
    }


    public function testColumnResolvesSqliteBooleansAfterColumnNames(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t("TRUE" INTEGER)'));
        $boundQuery1 = $binder->bind('SELECT TRUE');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery1);
        self::assertSame('literal', $boundQuery1->outputs[0]->expression->kind->value);
        $boundQuery2 = $binder->bind('SELECT TRUE FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $boundQuery2);
        self::assertSame('column', $boundQuery2->outputs[0]->expression->kind->value);
        $this->expectException(SemanticException::class);
        $binder->bind('SELECT "FALSE"');
    }

    public function testMergedColumnResolvesSharedJoinColumnsUnderDialectNameRules(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)')))->bind('SELECT a FROM t JOIN u USING (a)');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $shared = $statement->outputs[0]->expression;
        $folding = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql), merged: ['A' => $shared]);
        self::assertSame($shared, $folding->mergedColumn('a'));
        self::assertNull($folding->mergedColumn('zz'));
        $exact = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), merged: ['A' => $shared]);
        self::assertNull($exact->mergedColumn('a'));
        self::assertSame($shared, $exact->mergedColumn('A'));
    }

    public function testMatchesASchemaQualifiedColumn(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE SCHEMA s; CREATE TABLE s.t(a int)')))->bind('SELECT s.t.a FROM s.t');
        self::assertSame('SELECT "s"."t"."a" AS "a" FROM "s"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['SELECT x.t.a FROM s.t'])]
    #[TestWith(['SELECT s.t.a FROM s.t AS z'])]
    public function testMatchesRejectsAnotherSchemaOrAnAliasedTable(string $sql): void
    {
        $this->expectException(SemanticException::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE SCHEMA s; CREATE TABLE s.t(a int)')))->bind($sql);
    }

    public function testColumnReportsTheUnresolvedName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a int)')))->bind('SELECT missing FROM t', strict: false);
        self::assertSame(['unknown-column', 'Cannot resolve column unambiguously: missing'], [$statement->diagnostics[0]->reason, $statement->diagnostics[0]->message]);
    }

    #[TestWith(['SELECT 1 FROM s.t JOIN u ON true JOIN u ON true'])]
    #[TestWith(['SELECT 1 FROM u JOIN (s.t JOIN u ON true) ON true'])]
    public function testCombineRejectsARepeatedNameBehindAnotherRelation(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE SCHEMA s; CREATE TABLE s.t(a int); CREATE TABLE u(b int)')))->bind($sql);
    }


    public function testRelationColumnLeavesADetachedNameUnresolvedWithoutADiagnostic(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver($schema, new \SqlSemantics\Ast\Identifiers(Dialect::MySql), ''));
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql), queries: $context, detached: true);
        $column = $scope->relationColumn(['id'], new \SqlParser\Parser\Node('expr', 0, []));
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $column);
        self::assertSame(['id'], $column->referenceParts());
    }


    public function testUnmatchedDiagnosesAnUnknownNameUnlessTheScopeIsDetached(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build();
        $identifiers = new \SqlSemantics\Ast\Identifiers(Dialect::MySql);
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver($schema, $identifiers, ''));
        $source = new \SqlParser\Parser\Node('expr', 0, []);
        $detached = new \SqlSemantics\Binding\Scope($identifiers, queries: $context, detached: true);
        self::assertSame(['a'], $detached->unmatched(['a'], false, $source)->referenceParts());
        $this->expectException(SemanticException::class);
        (new \SqlSemantics\Binding\Scope($identifiers, queries: $context))->unmatched(['a'], false, $source);
    }

    public function testReferenceBindsARepeatedColumnNameByPosition(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p (id int, v int)')))->bind('SELECT * FROM (p CROSS JOIN p AS q) AS j');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $relation = $query->from;
        self::assertInstanceOf(\SqlSemantics\Model\Relation\AliasedRelation::class, $relation);
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), [$relation]);
        $third = $scope->reference($relation, 2, ['j', 'id'], new \SqlParser\Parser\Node('expr', 0, []));
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $third);
        self::assertSame(2, $third->binding->column->ordinal);
        self::assertSame($relation->id, $third->binding->relationId);
        self::assertSame(['j', 'id'], $third->referenceParts());
        self::assertSame([$relation->resultExpressions()[2]], $third->inputs());
    }
}
