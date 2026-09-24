<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Write;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;

#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Binder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scope::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TypeResolution::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Expression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Join::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Ordering::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\TableUse::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaBuilder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\Medium]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
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
final class AssignmentBinderTest extends TestCase
{
    public function testBindKeepsQualifiedTargetsAndJoin(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE u(id INTEGER)');
        $statement = (new Binder($schema))->bind('UPDATE t a JOIN u b ON a.id=b.id SET a.id=b.id WHERE b.id=1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateJoinedStatement::class, $statement);
        self::assertSame('t', $statement->writes[0]->destinations()[0]->column()->columnBinding()?->table->name);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $statement->writes[0]);
        self::assertSame('u', $statement->writes[0]->value->columnBinding()?->table->name);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\Joining\OnJoin::class, $statement->from);
        self::assertNotNull($statement->writes[0]->value->columnBinding());
        self::assertSame('u', $statement->writes[0]->value->columnBinding()->table->name);
    }
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, null, 'WITH c AS MATERIALIZED (UPDATE t SET a = DEFAULT) UPDATE t SET b = DEFAULT', 'WITH "c" AS MATERIALIZED(UPDATE "public"."t" SET "a" = DEFAULT) UPDATE "public"."t" SET "b" = DEFAULT'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'mysql-8.4.7', 'WITH c AS (SELECT 1) UPDATE t SET b = DEFAULT', 'WITH `c` AS (SELECT 1) UPDATE `t` SET `b` = DEFAULT'])]
    public function testBindKeepsTheAssignmentsOfACommonTableExpressionWithItsOwnStatement(Dialect $dialect, ?string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(a INT, b INT)'));
        $statement = $binder->bind($sql);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testAssignmentRetainsTupleCorrespondence(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER,b TEXT)')))->bind("UPDATE t SET (a,b)=(1,'x')");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\UpdateStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\TupleRowAssignment::class, $statement->writes[0]);
        self::assertSame(['a','b'], array_map(static fn ($target) => $target->column()->columnBinding()?->column->name, $statement->writes[0]->targets));
        self::assertInstanceOf(\SqlSemantics\Model\Expression::class, $statement->writes[0]->row->items[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Expression::class, $statement->writes[0]->row->items[1]);
        self::assertSame(['1', "'x'"], [$statement->writes[0]->row->items[0]->spelling(), $statement->writes[0]->row->items[1]->spelling()]);
    }
    public function testTargetResolvesOnlyMutationDestinations(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE u(id INTEGER)');
        $statement = (new Binder($schema))->bind('UPDATE t SET id=u.id FROM u WHERE t.id=u.id');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\UpdateStatement::class, $statement);
        self::assertSame('t', $statement->writes[0]->destinations()[0]->column()->columnBinding()?->table->name);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $statement->writes[0]);
        self::assertSame('u', $statement->writes[0]->value->columnBinding()?->table->name);
    }

    public function testTargetTreatsKeywordColumnNamesAsStorageReferences(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(xmlnamespaces INTEGER[])'));
        $statement = $binder->bind('UPDATE t SET xmlnamespaces[1]=2');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\UpdateStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $statement->writes[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Storage\ElementPath::class, $statement->writes[0]->target);
        self::assertSame('xmlnamespaces', $statement->writes[0]->target->column()->columnBinding()?->column->name);
        self::assertSame('1', $statement->writes[0]->target->index->spelling());
    }
    public function testAssignmentChecksSubqueryValuesAgainstTupleDestinations(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER,b TEXT)'));
        $statement = $binder->bind("UPDATE t SET (a,b)=(SELECT 1,'x')");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\UpdateStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\TupleQueryAssignment::class, $statement->writes[0]);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement->writes[0]->query);
        self::assertSame(['a','b'], array_map(static fn ($target) => $target->column()->columnBinding()?->column->name, $statement->writes[0]->targets));
        self::assertSame('1', $statement->writes[0]->query->outputs[0]->expression->spelling());
        self::assertSame('implicit', $statement->writes[0]->query->outputs[1]->expression->spelling());
        self::assertSame("'x'", $statement->writes[0]->query->outputs[1]->expression->inputs()[0]->spelling());
    }
    public function testAssignmentDiagnosesKnownTypeMismatch(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER,b TEXT)'));
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Cannot assign boolean to integer');
        $binder->bind("UPDATE t SET (a,b)=(TRUE,'x')");
    }

    public function testFormBuildsScalarAssignmentsForSingleDestinations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('UPDATE t SET a = 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $statement);
        $write = $statement->writes[0];
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $write);
        $binder = new \SqlSemantics\Binding\Write\AssignmentBinder();
        $postgres = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), [$statement->target]);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $binder->form($write->source, false, $write->value, [$write->target], $postgres));
        $sqlite = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::Sqlite), [$statement->target]);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $binder->form($write->source, true, $write->value, [$write->target], $sqlite));
    }

    public function testFormBuildsTupleAssignmentsFromRowsAndQueries(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)'));
        $statement = $binder->bind('UPDATE t SET a = 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $statement);
        $write = $statement->writes[0];
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $write);
        $values = $binder->bind('SELECT ROW(1, 2), (SELECT 1), (a, a) = (SELECT 1, 2) FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $values);
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), [$statement->target]);
        $forms = new \SqlSemantics\Binding\Write\AssignmentBinder();
        $row = $forms->form($write->source, true, $values->outputs[0]->expression, [$write->target, $write->target], $scope);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\TupleRowAssignment::class, $row);
        self::assertCount(2, $row->row->items);
        $scalar = $forms->form($write->source, true, $values->outputs[1]->expression, [$write->target], $scope);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\TupleQueryAssignment::class, $scalar);
        $comparison = $values->outputs[2]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $comparison);
        $wide = $forms->form($write->source, true, $comparison->right, [$write->target, $write->target], $scope);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\TupleQueryAssignment::class, $wide);
        self::assertCount(2, $wide->targets);
    }

    public function testFormRejectsATupleSourceThatIsNeitherARowNorAQuery(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('UPDATE t SET a = 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $statement);
        $write = $statement->writes[0];
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $write);
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), [$statement->target]);
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::TupleSource->message());
        (new \SqlSemantics\Binding\Write\AssignmentBinder())->form($write->source, true, $write->value, [$write->target], $scope);
    }

    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'UPDATE t SET (a, b) = (1, 2)', 'UPDATE "public"."t" SET ("a", "b") = ROW(1, 2)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'UPDATE t SET (a, b) = (SELECT 1, 2)', 'UPDATE "public"."t" SET ("a", "b") = (SELECT 1, 2)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'UPDATE t SET (a, b) = ROW(1, 2)', 'UPDATE "public"."t" SET ("a", "b") = ROW(1, 2)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'UPDATE t SET (a, b) = (SELECT a, b FROM t)', 'UPDATE "public"."t" SET ("a", "b") = (SELECT "a" AS "a", "b" AS "b" FROM "public"."t")'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'UPDATE t SET c[1] = 2', 'UPDATE "public"."t" SET "c"[1] = 2'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'UPDATE t SET a = DEFAULT, b = 2', 'UPDATE "public"."t" SET "a" = DEFAULT, "b" = 2'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'UPDATE t SET a = 1, b = 2, c = 3', 'UPDATE "main"."t" SET "a" = 1, "b" = 2, "c" = 3'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'UPDATE t SET (a) = (1)', 'UPDATE "main"."t" SET "a" = 1'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'UPDATE t SET (a, b) = (SELECT 1, 2)', 'UPDATE "main"."t" SET ("a", "b") = (SELECT 1, 2)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'UPDATE t SET a = DEFAULT, b = 1', 'UPDATE `t` SET `a` = DEFAULT, `b` = 1'])]
    public function testBindSpellsEveryAssignmentForm(Dialect $dialect, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(a INT, b INT, c ' . ($dialect === Dialect::PostgreSql ? 'INT[]' : 'INT') . ')')))->bind($sql);
        self::assertSame($expected, $statement->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'UPDATE t SET (a, b) = (1, 2, 3)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'UPDATE t SET (a, b) = (SELECT 1)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'UPDATE t SET (a, b) = (SELECT 1, 2, 3)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'UPDATE t SET (a, b) = (SELECT *, a FROM t)'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::Sqlite, 'UPDATE t SET (a, b) = (1, 2, 3)'])]
    public function testBindRejectsAssignmentsOfAnotherWidth(Dialect $dialect, string $sql): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(a INT, b INT)'));
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('Assignment destinations and values must have the same width.');
        $binder->bind($sql);
    }
}
