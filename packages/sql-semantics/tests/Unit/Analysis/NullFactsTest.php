<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;
use Tests\Scenario\AnalysisCase;

#[CoversClass(\SqlSemantics\Analysis\NullFacts::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Analysis\FromReader::class)]
#[CoversClass(\SqlSemantics\Analysis\LiteralReader::class)]
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
final class NullFactsTest extends TestCase
{
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testCoalesceRequiresANonNullOperandToGuaranteeAValue(Dialect $dialect): void
    {
        $query = (new AnalysisCase($dialect))->query('SELECT COALESCE(parent_id, NULL) AS a, COALESCE(NULL, NULL) AS b, COALESCE(parent_id, score) AS c FROM users');
        self::assertSame(Nullability::MaybeNull, $query->outputs[0]->expression->nullability);
        self::assertSame(Nullability::AlwaysNull, $query->outputs[1]->expression->nullability);
        self::assertSame(Nullability::NotNull, $query->outputs[2]->expression->nullability);
    }
    public function testStrictComparisonPropagatesNullButBooleanOrDoesNot(): void
    {
        $query = (new AnalysisCase())->query('SELECT NULL = 1 AS a, NULL OR TRUE AS b');
        self::assertSame(Nullability::AlwaysNull, $query->outputs[0]->expression->nullability);
        self::assertSame(Nullability::MaybeNull, $query->outputs[1]->expression->nullability);
    }


    public function testExtensionsRetainsAllNullableOperandCauses(): void
    {
        $query = (new AnalysisCase())->query('SELECT a.id+b.id FROM users a FULL JOIN users b ON a.id=b.id');
        self::assertSame(['j0'], $query->outputs[0]->expression->nullExtendedBy);
        self::assertSame(Nullability::MaybeNull, $query->outputs[0]->expression->nullability);
    }

    #[DataProvider('providerFacts')]
    public function testStrictAndCoalesceFacts(Nullability $left, Nullability $right, Nullability $strict, Nullability $coalesce): void
    {
        $type = new \SqlSemantics\Type\TypeDescriptor(Dialect::PostgreSql, 'integer');
        $source = new \SqlParser\Parser\Node('expr', 0, []);
        $operands = [
            new \SqlSemantics\Model\Expression(\SqlSemantics\Model\ExpressionKind::Literal, $type, $left, $source),
            new \SqlSemantics\Model\Expression(\SqlSemantics\Model\ExpressionKind::Literal, $type, $right, $source),
        ];
        self::assertSame($strict, \SqlSemantics\Analysis\NullFacts::strict($operands));
        self::assertSame($coalesce, \SqlSemantics\Analysis\NullFacts::coalesce($operands));
    }

    /**
     * @return iterable<string, array{Nullability, Nullability, Nullability, Nullability}>
     */
    public static function providerFacts(): iterable
    {
        yield 'NotNull-NotNull' => [Nullability::NotNull, Nullability::NotNull, Nullability::NotNull, Nullability::NotNull];
        yield 'NotNull-MaybeNull' => [Nullability::NotNull, Nullability::MaybeNull, Nullability::MaybeNull, Nullability::NotNull];
        yield 'NotNull-AlwaysNull' => [Nullability::NotNull, Nullability::AlwaysNull, Nullability::AlwaysNull, Nullability::NotNull];
        yield 'NotNull-Unknown' => [Nullability::NotNull, Nullability::Unknown, Nullability::Unknown, Nullability::NotNull];
        yield 'MaybeNull-NotNull' => [Nullability::MaybeNull, Nullability::NotNull, Nullability::MaybeNull, Nullability::NotNull];
        yield 'MaybeNull-MaybeNull' => [Nullability::MaybeNull, Nullability::MaybeNull, Nullability::MaybeNull, Nullability::MaybeNull];
        yield 'MaybeNull-AlwaysNull' => [Nullability::MaybeNull, Nullability::AlwaysNull, Nullability::AlwaysNull, Nullability::MaybeNull];
        yield 'MaybeNull-Unknown' => [Nullability::MaybeNull, Nullability::Unknown, Nullability::Unknown, Nullability::Unknown];
        yield 'AlwaysNull-NotNull' => [Nullability::AlwaysNull, Nullability::NotNull, Nullability::AlwaysNull, Nullability::NotNull];
        yield 'AlwaysNull-MaybeNull' => [Nullability::AlwaysNull, Nullability::MaybeNull, Nullability::AlwaysNull, Nullability::MaybeNull];
        yield 'AlwaysNull-AlwaysNull' => [Nullability::AlwaysNull, Nullability::AlwaysNull, Nullability::AlwaysNull, Nullability::AlwaysNull];
        yield 'AlwaysNull-Unknown' => [Nullability::AlwaysNull, Nullability::Unknown, Nullability::AlwaysNull, Nullability::Unknown];
        yield 'Unknown-NotNull' => [Nullability::Unknown, Nullability::NotNull, Nullability::Unknown, Nullability::NotNull];
        yield 'Unknown-MaybeNull' => [Nullability::Unknown, Nullability::MaybeNull, Nullability::Unknown, Nullability::Unknown];
        yield 'Unknown-AlwaysNull' => [Nullability::Unknown, Nullability::AlwaysNull, Nullability::AlwaysNull, Nullability::Unknown];
        yield 'Unknown-Unknown' => [Nullability::Unknown, Nullability::Unknown, Nullability::Unknown, Nullability::Unknown];
    }
}
