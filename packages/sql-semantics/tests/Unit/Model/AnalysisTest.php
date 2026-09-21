<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\DialectParser::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\StatementList::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\Tree::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Binder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\BoundRelation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\NullFacts::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Scope::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\SelectBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\TableResolver::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\TypeResolution::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\Analysis::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\ColumnBinding::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\Expression::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\Join::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\Ordering::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\OutputColumn::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Model\TableUse::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Schema::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(SchemaBuilder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Type\Nullability::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\ExpressionEdit::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\ExpressionInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\StatementInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
final class AnalysisTest extends TestCase
{
    public function testRetainsUnresolvedInputsAndEveryProjection(): void
    {
        $analysis = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->analyze('SELECT t.id, 1 AS n, t.* FROM missing t WHERE t.id > 0');
        self::assertSame(['unknown-table', 'unknown-column', 'unknown-column'], array_column($analysis->diagnostics, 'reason'));
        self::assertFalse($analysis->statement->relations[0]->declaration->resolved);
        self::assertSame([], $analysis->statement->relations[0]->declaration->columns);
        self::assertSame(['id', 'n', null], array_column($analysis->statement->outputs, 'name'));
        self::assertSame('unresolved-column', $analysis->statement->outputs[0]->expression->kind->value);
        self::assertSame(['t', 'id'], $analysis->statement->outputs[0]->expression->reference);
        self::assertSame('unknown', $analysis->statement->outputs[0]->expression->type->name);
        self::assertSame('unknown', $analysis->statement->outputs[0]->expression->nullability->value);
        self::assertSame('integer', $analysis->statement->outputs[1]->expression->type->name);
        self::assertSame('wildcard', $analysis->statement->outputs[2]->expression->kind->value);
        self::assertSame(['t'], $analysis->statement->outputs[2]->expression->reference);
        self::assertSame('>', $analysis->statement->where?->symbol);
    }

    public function testRetainsKnownBindingsAlongsideUnknownColumns(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL)');
        $analysis = (new Binder($schema))->analyze('SELECT id, missing FROM t');
        self::assertSame(['unknown-column'], array_column($analysis->diagnostics, 'reason'));
        self::assertSame($schema->tables[0]->columns[0], $analysis->statement->outputs[0]->expression->binding?->column);
        self::assertSame('not-null', $analysis->statement->outputs[0]->expression->nullability->value);
        self::assertSame('missing', $analysis->statement->outputs[1]->name);
    }

    public function testRetainsAmbiguityInsteadOfSelectingAnArbitraryColumn(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $analysis = (new Binder($schema))->analyze('SELECT id FROM t a, t b');
        self::assertSame(['ambiguous-column'], array_column($analysis->diagnostics, 'reason'));
        self::assertNull($analysis->statement->outputs[0]->expression->binding);
        self::assertSame(['id'], $analysis->statement->outputs[0]->expression->reference);
        self::assertCount(2, $analysis->statement->relations);
    }

    public function testRetainsOpenCteResultsAndMutationAssignments(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->analyze('WITH q AS (TABLE absent) SELECT * FROM q')->statement;
        self::assertFalse($query->relations[0]->declaration->resolved);
        self::assertSame('wildcard', $query->outputs[0]->expression->kind->value);
        self::assertSame('absent', $query->ctes['q']->relations[0]->declaration->name);
        $write = $binder->analyze('UPDATE absent SET n=n+1 RETURNING n');
        self::assertSame('UPDATE', $write->statement->kind);
        self::assertSame(['n'], array_keys($write->statement->assignments));
        self::assertSame('+', $write->statement->assignments['n']->symbol);
        self::assertSame('unresolved-column', $write->statement->outputs[0]->expression->kind->value);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidSemantics')]
    public function testPreservesInvalidSemanticStructure(string $sql, string $reason): void
    {
        $result = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->analyze($sql);
        self::assertContains($reason, array_column($result->diagnostics, 'reason'));
        self::assertSame($sql, $result->statement->source->toString());
        self::assertNotSame([], $result->statement->outputs);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerInvalidSemantics(): iterable
    {
        yield 'incompatible types' => ['SELECT COALESCE(1, TRUE)', 'incompatible-types'];
        yield 'non boolean predicate' => ['SELECT 1 WHERE 42', 'non-boolean-predicate'];
        yield 'values width' => ['VALUES (1, 2), (3)', 'values-column-count'];
        yield 'compound width' => ['SELECT 1, 2 UNION SELECT 3', 'set-column-count'];
        yield 'order position' => ['SELECT 1 ORDER BY 2', 'invalid-output-position'];
        yield 'ambiguous output' => ['SELECT 1 AS n, 2 AS n ORDER BY n', 'ambiguous-output'];
        yield 'star without relation' => ['SELECT *', 'unknown-relation'];
        yield 'duplicate relation' => ['SELECT 1 FROM t, t', 'duplicate-relation'];
    }
    public function testUnresolvedInputsDoNotAcquireInventedCommonTypes(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->analyze('SELECT COALESCE(missing, 1)')->statement;
        self::assertSame('unknown', $query->outputs[0]->expression->type->name);
        self::assertSame('unresolved-column', $query->outputs[0]->expression->operands[0]->kind->value);
        self::assertSame('integer', $query->outputs[0]->expression->operands[1]->type->name);
    }

}
