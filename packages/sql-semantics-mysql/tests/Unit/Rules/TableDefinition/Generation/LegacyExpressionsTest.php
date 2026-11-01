<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacyExpressions;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(LegacyExpressions::class)]
#[Small]
final class LegacyExpressionsTest extends TestCase
{
    public function testContainsDistinguishesStoredFunctionsFromEagerQueryPredicates(): void
    {
        $call = new M\Call\KeywordCall(M\Call\KeywordFunction::User, []);
        $query = new M\Query\Select([], [new M\Query\SelectExpression($call)]);
        self::assertTrue((new LegacyExpressions())->contains($call));
        self::assertFalse((new LegacyExpressions())->contains(new M\Expression\Subquery\InQuery(new M\Literal\NumberLiteral('1'), $query)));
        self::assertFalse((new LegacyExpressions())->contains(new M\Literal\NumberLiteral('1')));
    }

    public function testEagerIdentifiesQueriesAndStoredCalls(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]);
        self::assertTrue((new LegacyExpressions())->eager(new M\Expression\Subquery\ScalarSubquery($query)));
        self::assertFalse((new LegacyExpressions())->eager(new M\Variable\UserVariable(new Name('a'))));
    }

    public function testContainsRetainsCompoundParametersButExcludesABareMarker(): void
    {
        $expression = new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, new M\Literal\Parameter(), new M\Literal\NumberLiteral('1'));
        self::assertTrue((new LegacyExpressions())->contains($expression));
        self::assertFalse((new LegacyExpressions())->contains(new M\Expression\Grouped(new M\Literal\Parameter())));
    }

    public function testCommandsKeepsTheDeclaredColumnRole(): void
    {
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('g')), new M\Table\Column\Generation\DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Call\KeywordCall(M\Call\KeywordFunction::User, [])));
        self::assertTrue((new LegacyExpressions())->commands([new M\Alter\Column\ChangeColumn(null, $column)]));
        self::assertFalse((new LegacyExpressions())->commands([]));
    }
}
