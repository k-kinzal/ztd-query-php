<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;
use Tests\Scenario\AnalysisCase;

#[CoversClass(\SqlSemantics\Analysis\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\FromReader::class)]
#[CoversClass(\SqlSemantics\Analysis\LiteralReader::class)]
#[CoversClass(\SqlSemantics\Analysis\NullFacts::class)]
#[CoversClass(\SqlSemantics\Analysis\ProjectionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\SelectReader::class)]
#[CoversClass(\SqlSemantics\Analysis\SyntaxGuard::class)]
#[CoversClass(\SqlSemantics\Analysis\TailReader::class)]
#[CoversClass(\SqlSemantics\Analysis\TypeResolution::class)]
#[CoversClass(\SqlSemantics\Analyzer::class)]
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
#[CoversClass(\SqlSemantics\Binding\Scope::class)]
#[CoversClass(\SqlSemantics\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Model\ColumnBinding::class)]
#[CoversClass(\SqlSemantics\Model\Expression::class)]
#[CoversClass(\SqlSemantics\Model\Join::class)]
#[CoversClass(\SqlSemantics\Model\Ordering::class)]
#[CoversClass(\SqlSemantics\Model\OutputColumn::class)]
#[CoversClass(\SqlSemantics\Model\SelectQuery::class)]
#[CoversClass(\SqlSemantics\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Schema\Catalog::class)]
#[CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
final class ExpressionRulesTest extends TestCase
{
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testOperatorNullTestsAreNeverNullable(Dialect $dialect): void
    {
        $query = (new AnalysisCase($dialect))->query('SELECT parent_id IS NULL AS absent, parent_id IS NOT NULL AS present FROM users');
        self::assertSame(Nullability::NotNull, $query->outputs[0]->expression->nullability);
        self::assertSame(Nullability::NotNull, $query->outputs[1]->expression->nullability);
    }
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testCallNullIfCanIntroduceNullWithoutAnOuterJoin(Dialect $dialect): void
    {
        $query = (new AnalysisCase($dialect))->query('SELECT NULLIF(score, 0) AS result FROM users');
        self::assertSame(Nullability::MaybeNull, $query->outputs[0]->expression->nullability);
        self::assertSame([], $query->outputs[0]->expression->nullExtendedBy);
    }


    public function testPredicateRejectsNonBooleanPostgresInputs(): void
    {
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('boolean type');
        (new AnalysisCase())->query('SELECT id FROM users WHERE score');
    }

    public function testCoerceRecordsPostgresCommonTypeConversions(): void
    {
        $query = (new AnalysisCase())->query('SELECT COALESCE(id, 2147483648) FROM users');
        $expression = $query->outputs[0]->expression;
        self::assertSame(\SqlSemantics\Model\ExpressionKind::Cast, $expression->operands[0]->kind);
        self::assertSame('bigint', $expression->operands[0]->type->name);
        self::assertSame('integer', $expression->operands[0]->operands[0]->type->name);
        self::assertSame('id', $expression->lineage()[0]->column->name);
    }

    public function testArithmeticHandlesNegativePostgresIntegerBoundaries(): void
    {
        $query = (new AnalysisCase())->query('SELECT -2147483648, -9223372036854775808');
        self::assertSame('integer', $query->outputs[0]->expression->type->name);
        self::assertSame('bigint', $query->outputs[1]->expression->type->name);
    }
}
