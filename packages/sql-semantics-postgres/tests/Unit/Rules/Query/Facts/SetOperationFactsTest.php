<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Facts;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\SetOperationFacts::class)]
#[Medium]
final class SetOperationFactsTest extends TestCase
{
    public function testDeriveKeepsTheNamesOfTheFirstOperand(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 AS a INTERSECT SELECT 2 AS b');
        self::assertSame('a', $query->field(0)->name?->value);
    }

    public function testCombinedReportsTypesThatCannotBeMatched(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 UNION SELECT TRUE');
        self::assertSame('UNION types integer and boolean cannot be matched.', $query->facts->diagnostics[0]->message());
    }

    public function testReboundBindsTheRecursiveTable(): void
    {
        $context = (new \SqlSemantics\Platform\PostgreSql\Platform())->context(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172), null, [], true);
        $derivation = new \SqlSemantics\Construction\Derivation($context);
        $fact = $derivation->query(new \SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression(new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\WithClause([new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\CommonTableExpression(new \SqlSemantics\Statement\Identifier\Name('r'), new \SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation(new \SqlSemantics\Platform\PostgreSql\Statement\Query\Select([new \SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')), new \SqlSemantics\Statement\Identifier\Name('n'))]), \SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperator::Union, new \SqlSemantics\Platform\PostgreSql\Statement\Query\Select([new \SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget(new \SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName(new \SqlSemantics\Statement\Identifier\Name('+')), new \SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference([new \SqlSemantics\Statement\Identifier\Name('n')]), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'))))], new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('r')))))), [], null, null, null)], true), new \SqlSemantics\Platform\PostgreSql\Statement\Query\Select([new \SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget(new \SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference([new \SqlSemantics\Statement\Identifier\Name('n')]))], new \SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('r')))))), $derivation->environment());
        self::assertSame('integer', ($fact->fields()?->at(0)->type instanceof \SqlSemantics\Statement\Type\Known ? $fact->fields()->at(0)->type->descriptor->name() : null));
    }
}
