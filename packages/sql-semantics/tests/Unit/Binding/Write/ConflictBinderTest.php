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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\ExpressionEdit::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\ExpressionInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\StatementInvariant::class)]
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
final class ConflictBinderTest extends TestCase
{
    public function testBindSeparatesConditionalConflictUpdate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER,n INTEGER)')))->bind('INSERT INTO t VALUES(1,2) ON CONFLICT(id) WHERE id>0 DO UPDATE SET n=excluded.n WHERE t.n<10 RETURNING id');
        self::assertNull($statement->where);
        self::assertSame('update', $statement->conflicts[0]->action);
        self::assertSame('id', $statement->conflicts[0]->keys[0]->binding?->column->name);
        self::assertSame('>', $statement->conflicts[0]->indexPredicate?->symbol);
        self::assertSame('<', $statement->conflicts[0]->where?->symbol);
        self::assertSame('n', $statement->conflicts[0]->assignments[0]->targets[0]->binding?->column->name);
    }
    public function testActionRetainsDoNothing(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('INSERT INTO t VALUES(1) ON CONFLICT DO NOTHING');
        self::assertSame('nothing', $statement->conflicts[0]->action);
        self::assertSame([], $statement->conflicts[0]->assignments);
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
        self::assertSame('non-boolean-predicate', $statement->diagnostics[0]->reason);
        self::assertSame('integer', $statement->where?->type->name);
    }

    public function testPredicateRetainsCursorRowSelection(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('DELETE FROM t WHERE CURRENT OF cur');
        self::assertNotNull($statement->where);
        self::assertSame('current-row', $statement->where->kind->value);
        self::assertSame(['cur'], $statement->where->reference);
        self::assertSame('boolean', $statement->where->type->name);
    }

    public function testActionRetainsNamedConstraintAndConditionalUpdate(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER,n INTEGER)'));
        $conflict = $binder->bind('INSERT INTO t VALUES(1,2) ON CONFLICT ON CONSTRAINT t_key DO UPDATE SET n=excluded.n WHERE t.n<excluded.n')->conflicts[0];
        self::assertSame('t_key', $conflict->constraint);
        self::assertSame([], $conflict->keys);
        self::assertNull($conflict->indexPredicate);
        self::assertSame('<', $conflict->where?->symbol);
        self::assertSame('n', $conflict->assignments[0]->targets[0]->binding?->column->name);
    }
    public function testActionSeparatesSqliteIndexAndUpdatePredicates(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER,n INTEGER)'));
        $statement = $binder->bind('INSERT INTO t VALUES(1,2) ON CONFLICT(id) WHERE id>0 DO UPDATE SET n=excluded.n WHERE n<3 ON CONFLICT DO NOTHING');
        self::assertSame(['update','nothing'], array_column($statement->conflicts, 'action'));
        self::assertSame('id', $statement->conflicts[0]->keys[0]->binding?->column->name);
        self::assertSame('>', $statement->conflicts[0]->indexPredicate?->symbol);
        self::assertSame('<', $statement->conflicts[0]->where?->symbol);
        self::assertSame('3', $statement->conflicts[0]->where->operands[1]->symbol);
        self::assertSame([], $statement->conflicts[1]->assignments);
        self::assertNull($statement->conflicts[1]->where);
    }
    public function testBindKeepsMysqlDuplicateKeyWritesSeparate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER,n INTEGER)')))->bind('INSERT INTO t VALUES(1,2) ON DUPLICATE KEY UPDATE n=3');
        self::assertSame('update', $statement->conflicts[0]->action);
        self::assertSame('3', $statement->conflicts[0]->assignments[0]->value->symbol);
        self::assertSame([], $statement->writes);
        self::assertNull($statement->where);
    }
}
