<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Sql;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\Statement\TableStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Serializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaBuilder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\StatementFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SimpleSerializer::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\FunctionSignature::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Expression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Join::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\TableUse::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Ordering::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(OutputColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Transformation\Context::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\InsertStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ConfigurationStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\DeleteStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\MergeStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ValuesStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\UpdateStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CompoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CreateIndexStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CreateTableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(InvalidStructure::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Literal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\ExpressionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Build::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Parts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Atom::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Source::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Format::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexKeys::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexReader::class)]
final class TableStatementTest extends TestCase
{
    public function testWithTableRefreshesDependentFacts(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE TABLE u(n TEXT)');
        $statement = (new Binder($schema))->bind('TABLE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $statement);
        $changed = $statement->withTable($schema->tables[1]);
        self::assertSame(['n'], array_column($changed->outputs, 'name'));
        self::assertSame('text', $changed->outputs[0]->expression->type->name);
        self::assertSame('u', $changed->from->declaration->name);
        self::assertSame('t', $statement->from->declaration->name);
    }

    public function testWithTableRejectsAnInvalidTarget(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('TABLE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $statement);
        $missing = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE missing(id INTEGER)')->tables[0];
        $this->expectException(\SqlSemantics\SemanticException::class);
        $statement->withTable($missing);
    }

    public function testWithTableDerivesColumnsFromTheReplacementDeclaration(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE u(name TEXT, active BOOLEAN)');
        $binder = new Binder($schema);
        $before = $binder->bind('TABLE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $before);
        $other = $binder->bind('TABLE u');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $other);


        $changed = new \SqlSemantics\Model\Statement\TableStatement($before->origin, $other->from);
        self::assertSame(['name', 'active'], array_column($changed->outputs, 'name'));
        self::assertSame([$other->from], $changed->relations);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($changed), (new \SqlSemantics\SimpleSerializer())->serialize($before->withTable($other->from->declaration)));
    }
    public function testWithTablePreservesExcludedDescendants(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE u(name TEXT)');
        $statement = (new Binder($schema))->bind('TABLE ONLY t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $statement);
        $changed = $statement->withTable($schema->tables[1]);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\OnlyTableReference::class, $changed->from);
        self::assertSame(['name'], array_column($changed->outputs, 'name'));
        self::assertSame('TABLE ONLY "public"."u"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('TABLE ONLY "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginKeepsTheSourceTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('TABLE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $statement);
        $changed = $statement->withOrigin(new \SqlSemantics\Model\Statement\Origin('other', $statement->source, Dialect::PostgreSql, [], $statement->origin->context));
        self::assertSame('other', $changed->scopeId);
        self::assertSame($statement->from, $changed->from);
        self::assertSame('TABLE "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('s0', $statement->scopeId);
    }

    public function testResultColumnsAreTheDeclaredTableColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('TABLE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $statement);
        self::assertSame($statement->outputs, $statement->resultColumns());
        self::assertSame(['id', 'n'], array_column($statement->resultColumns(), 'name'));
    }

    public function testWithOrderBySortsTheTableOutput(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('TABLE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $statement);
        $changed = $statement->withOrderBy([new \SqlSemantics\Model\Ordering(Expression::reference(['n'], Dialect::PostgreSql), true)]);
        self::assertSame('TABLE "public"."t" ORDER BY "n" DESC', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('TABLE "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame([], $changed->withOrderBy([])->orderBy);
    }

    /**
     * @param list<string> $parts
     */
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, ['u']])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, ['public', 'u']])]
    public function testWithTableNamesTheReplacementWithItsSchema(Dialect $dialect, array $parts): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(b INT)');
        $statement = (new Binder($schema))->bind('TABLE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $statement);
        $name = $statement->withTable($schema->tables[1])->from->name;
        self::assertInstanceOf(\SqlSemantics\Model\Relation\QualifiedName::class, $name);
        self::assertSame($parts, $name->parts);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.0.44'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.1.0'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.2.0'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.3.0'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-9.0.1'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-9.1.0'])]
    public function testKeepsMySqlRowLocksThroughStructuralSerialization(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t(id INT)'));
        $shared = $binder->bind('TABLE t LOCK IN SHARE MODE');
        $update = $binder->bind('TABLE t ORDER BY id LIMIT 1 FOR UPDATE OF t NOWAIT');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $shared);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $update);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockStrength::Share, $shared->locks[0]->strength);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Locking\NamedRowLock::class, $update->locks[0]);
        self::assertSame($update->from, $update->locks[0]->relations[0]);
        self::assertSame('TABLE `t` LOCK IN SHARE MODE', (new \SqlSemantics\SimpleSerializer())->serialize($shared));
        self::assertSame('TABLE `t` ORDER BY `id` ASC LIMIT 1 FOR UPDATE OF `t` NOWAIT', (new \SqlSemantics\SimpleSerializer())->serialize($update));
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($update));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $rebound);
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockWait::NoWait, $rebound->locks[0]->wait);
    }

    public function testKeepsPostgreSqlRowLocksInAnInsertedQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE u(id INTEGER)'));
        $statement = $binder->bind('INSERT INTO u TABLE t FOR KEY SHARE SKIP LOCKED');
        self::assertSame('INSERT INTO "public"."u" TABLE "public"."t" FOR KEY SHARE SKIP LOCKED', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithLocksReplacesTheRowLocksAndLeavesTheOriginalUnchanged(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('TABLE t FOR UPDATE');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $statement);
        $changed = $statement->withLocks([new \SqlSemantics\Model\Query\Locking\AllRowLock(\SqlSemantics\Model\Query\Locking\LockStrength::NoKeyUpdate)]);
        self::assertNotSame($statement, $changed);
        self::assertSame('TABLE "public"."t" FOR NO KEY UPDATE', $changed->toString());
        self::assertSame(\SqlSemantics\Model\Query\Locking\LockStrength::Update, $statement->locks[0]->strength);
        self::assertSame([], $changed->withLocks([])->locks);
    }

    public function testRejectsAPostgreSqlLockStrengthOnMySql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('TABLE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new \SqlSemantics\Model\Statement\TableStatement($statement->origin, $statement->from, locks: [new \SqlSemantics\Model\Query\Locking\AllRowLock(\SqlSemantics\Model\Query\Locking\LockStrength::KeyShare)]);
    }
}
