<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Write;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
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
final class ConflictBinderTest extends TestCase
{
    public function testBindSeparatesConditionalConflictUpdate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER,n INTEGER)')))->bind('INSERT INTO t VALUES(1,2) ON CONFLICT(id) WHERE id>0 DO UPDATE SET n=excluded.n WHERE t.n<10 RETURNING id');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement);
        self::assertSame('update', $statement->conflicts[0]->action->value);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Conflict\IndexConflict::class, $statement->conflicts[0]->target);
        self::assertSame('id', $statement->conflicts[0]->target->keys[0]->columnBinding()?->column->name);
        self::assertSame('>', $statement->conflicts[0]->target->predicate?->spelling());
        self::assertInstanceOf(\SqlSemantics\Model\Write\Conflict\DoUpdate::class, $statement->conflicts[0]);
        self::assertSame('<', $statement->conflicts[0]->where?->spelling());
        self::assertSame('n', $statement->conflicts[0]->assignments[0]->destinations()[0]->column()->columnBinding()?->column->name);
    }
    public function testActionRetainsDoNothing(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('INSERT INTO t VALUES(1) ON CONFLICT DO NOTHING');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement);
        self::assertSame('nothing', $statement->conflicts[0]->action->value);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Conflict\DoNothing::class, $statement->conflicts[0]);
    }
    public function testPredicateRejectsNonBooleanUpdateCondition(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('boolean');
        $binder->bind('UPDATE t SET id=2 WHERE 1');
    }
    public function testPredicateRejectsNonBooleanDeleteCondition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('DELETE FROM t WHERE 1', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\DeleteStatement::class, $statement);
        self::assertSame('non-boolean-predicate', $statement->diagnostics[0]->reason);
        self::assertSame('integer', $statement->where?->type->name);
    }

    public function testPredicateRetainsCursorRowSelection(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('DELETE FROM t WHERE CURRENT OF cur');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\DeleteStatement::class, $statement);
        self::assertNotNull($statement->where);
        self::assertSame('current-row', $statement->where->kind->value);
        self::assertSame(['cur'], $statement->where->referenceParts());
        self::assertSame('boolean', $statement->where->type->name);
    }

    public function testActionRetainsNamedConstraintAndConditionalUpdate(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER,n INTEGER)'));
        $statement = $binder->bind('INSERT INTO t VALUES(1,2) ON CONFLICT ON CONSTRAINT t_key DO UPDATE SET n=excluded.n WHERE t.n<excluded.n');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement);
        $conflict = $statement->conflicts[0];
        self::assertInstanceOf(\SqlSemantics\Model\Write\Conflict\DoUpdate::class, $conflict);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Conflict\ConstraintConflict::class, $conflict->target);
        self::assertSame('t_key', $conflict->target->name);
        self::assertSame('<', $conflict->where?->spelling());
        self::assertSame('n', $conflict->assignments[0]->destinations()[0]->column()->columnBinding()?->column->name);
    }
    public function testActionSeparatesSqliteIndexAndUpdatePredicates(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER,n INTEGER)'));
        $statement = $binder->bind('INSERT INTO t VALUES(1,2) ON CONFLICT(id) WHERE id>0 DO UPDATE SET n=excluded.n WHERE n<3 ON CONFLICT DO NOTHING');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement);
        self::assertSame(['update','nothing'], array_map(static fn ($item) => $item->action->value, $statement->conflicts));
        self::assertInstanceOf(\SqlSemantics\Model\Write\Conflict\IndexConflict::class, $statement->conflicts[0]->target);
        self::assertSame('id', $statement->conflicts[0]->target->keys[0]->columnBinding()?->column->name);
        self::assertSame('>', $statement->conflicts[0]->target->predicate?->spelling());
        self::assertInstanceOf(\SqlSemantics\Model\Write\Conflict\DoUpdate::class, $statement->conflicts[0]);
        self::assertSame('<', $statement->conflicts[0]->where?->spelling());
        self::assertNotNull($statement->conflicts[0]->where);
        self::assertSame('3', $statement->conflicts[0]->where->inputs()[1]->spelling());
        self::assertInstanceOf(\SqlSemantics\Model\Write\Conflict\DoNothing::class, $statement->conflicts[1]);
    }
    public function testBindKeepsMysqlDuplicateKeyWritesSeparate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER,n INTEGER)')))->bind('INSERT INTO t VALUES(1,2) ON DUPLICATE KEY UPDATE n=3');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement);
        self::assertSame('update', $statement->conflicts[0]->action->value);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Conflict\DoUpdate::class, $statement->conflicts[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $statement->conflicts[0]->assignments[0]);
        self::assertSame('3', $statement->conflicts[0]->assignments[0]->value->spelling());
        self::assertInstanceOf(\SqlSemantics\Model\Expression::class, $statement->rows[0][0]);
        self::assertSame('1', $statement->rows[0][0]->spelling());
    }

    public function testBindDoesNotTurnSqliteReturningIntoAConflictAction(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)')))->bind('INSERT INTO t VALUES(1) RETURNING id AS saved');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement);
        self::assertSame([], $statement->conflicts);
        self::assertSame('saved', $statement->outputs[0]->name);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testBindKeepsEveryDuplicateKeyAssignmentAndItsProposedRowReference(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(n INTEGER, m INTEGER)'));
        $statement = $binder->bind('INSERT INTO t (n) VALUES (1) ON DUPLICATE KEY UPDATE n = VALUES(t.n), m = VALUES(m)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Conflict\DoUpdate::class, $statement->conflicts[0]);
        self::assertCount(2, $statement->conflicts[0]->assignments);
        self::assertSame('INSERT INTO `t`(`n`) VALUES (1) ON DUPLICATE KEY UPDATE `n` = VALUES (`t`.`n`), `m` = VALUES (`m`)', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerBindReadsEachConflictHandler(): array
    {
        return [
            [Dialect::Sqlite, null, 'INSERT INTO t VALUES (1, 2) ON CONFLICT (id) WHERE id > 0 DO UPDATE SET n = 1 WHERE n > 0', [\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, 'INSERT INTO "main"."t" VALUES (1, 2) ON CONFLICT("id") WHERE ("id" > 0) DO UPDATE SET "n" = 1 WHERE ("n" > 0)']],
            [Dialect::Sqlite, null, 'INSERT INTO t VALUES (1, 2) ON CONFLICT (id) WHERE id > 0 DO UPDATE SET n = 1', [\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, 'INSERT INTO "main"."t" VALUES (1, 2) ON CONFLICT("id") WHERE ("id" > 0) DO UPDATE SET "n" = 1']],
            [Dialect::Sqlite, null, 'insert into t values (1, 2) on conflict do nothing', [\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, 'INSERT INTO "main"."t" VALUES (1, 2) ON CONFLICT DO NOTHING']],
            [Dialect::Sqlite, null, 'insert into t values (1, 2) returning id', [\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, 'INSERT INTO "main"."t" VALUES (1, 2) RETURNING "id" AS "id"']],
            [Dialect::Sqlite, null, 'INSERT INTO t VALUES (1, 2) ON CONFLICT (id) DO NOTHING ON CONFLICT DO UPDATE SET n = 2', [\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, 'INSERT INTO "main"."t" VALUES (1, 2) ON CONFLICT("id") DO NOTHING ON CONFLICT DO UPDATE SET "n" = 2']],
            [Dialect::PostgreSql, null, 'insert into t values (1, 2) on conflict ((n + 1)) do nothing', [\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, 'INSERT INTO "public"."t" VALUES (1, 2) ON CONFLICT(("n" + 1)) DO NOTHING']],
            [Dialect::PostgreSql, null, 'insert into t values (1, 2) on conflict (id) where n > 0 do update set n = 1 where t.n > 1', [\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, 'INSERT INTO "public"."t" VALUES (1, 2) ON CONFLICT("id") WHERE ("n" > 0) DO UPDATE SET "n" = 1 WHERE ("t"."n" > 1)']],
            [Dialect::PostgreSql, null, 'insert into t values (1, 2) on conflict on constraint t_pkey do nothing', [\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, 'INSERT INTO "public"."t" VALUES (1, 2) ON CONFLICT ON CONSTRAINT "t_pkey" DO NOTHING']],
            [Dialect::MySql, null, 'insert into t values (1, 2) on duplicate key update n = 3, id = 4', [\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, 'INSERT INTO `t` VALUES (1, 2) ON DUPLICATE KEY UPDATE `n` = 3, `id` = 4']],
        ];
    }

    #[DataProvider('providerBindReadsEachConflictHandler')]
    public function testBindReadsEachConflictHandler(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(id INTEGER PRIMARY KEY, n INTEGER)')))->bind($sql, strict: false);
        self::assertSame($expected, [$statement::class, $statement->toString()]);
    }


    public function testUpsertPredicatesSeparatesTheTargetAndUpdateConditions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER PRIMARY KEY, n INT)'));
        $update = $binder->bind('INSERT INTO t VALUES (1, 2) ON CONFLICT (id) DO UPDATE SET n = 3 WHERE n > 0');
        $both = $binder->bind('INSERT INTO t VALUES (1, 2) ON CONFLICT (id) WHERE n > 1 DO UPDATE SET n = 3 WHERE n > 0');
        self::assertSame('INSERT INTO "main"."t" VALUES (1, 2) ON CONFLICT("id") DO UPDATE SET "n" = 3 WHERE ("n" > 0)', $update->toString());
        self::assertSame('INSERT INTO "main"."t" VALUES (1, 2) ON CONFLICT("id") WHERE ("n" > 1) DO UPDATE SET "n" = 3 WHERE ("n" > 0)', $both->toString());
        self::assertSame($update->toString(), $binder->bind($update->toString())->toString());
    }

    public function testActionReadsDoNothingFromItsKeywordsNotFromLiterals(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER PRIMARY KEY, n TEXT)'));
        $statement = $binder->bind("INSERT INTO t VALUES (1, 'a') ON CONFLICT (id) DO UPDATE SET n = 'DO NOTHING'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Conflict\DoUpdate::class, $statement->conflicts[0]);
    }

    public function testBindResolvesDuplicateKeyDestinationsOnlyInTheTargetBesideARowAlias(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind('INSERT INTO t VALUES (1, 2) AS r ON DUPLICATE KEY UPDATE n = r.n');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Conflict\DoUpdate::class, $statement->conflicts[0]);
        $destination = $statement->conflicts[0]->assignments[0]->destinations()[0]->column();
        self::assertSame($statement->insertion->target->id, $destination->columnBinding()?->relationId);
        self::assertSame('INSERT INTO `t` VALUES (1, 2) AS `r` ON DUPLICATE KEY UPDATE `n` = `r`.`n`', $statement->toString());
    }
}
