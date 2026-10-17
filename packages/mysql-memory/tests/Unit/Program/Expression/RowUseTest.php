<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Expression;

use MySqlMemory\Instance;
use MySqlMemory\Program\Expression\RowUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RowUse::class)]
#[Small]
final class RowUseTest extends TestCase
{
    public function testErrorPreservesTheDeferredConversionCondition(): void
    {
        $value = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone(new NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\Text('+00:00'));
        self::assertSame(3998, (new RowUse())->error($value)->getCode());
        self::assertSame(1241, (new RowUse())->error(new Row([new NumberLiteral('1'), new NumberLiteral('2')]))->getCode());
    }

    /**
     * @return iterable<string, array{RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand, int}>
     */
    public static function providerOperands(): iterable
    {
        yield 'literal fields' => [new Row([new NumberLiteral('1'), new NumberLiteral('2')]), 1241];
        yield 'name before width' => [new Row([new ColumnUse(new Name('missing')), new NumberLiteral('2')]), 1054];
        yield 'routine before width' => [new Row([new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new Name('missing_function')), new NumberLiteral('2')]), 1305];
        yield 'routine before later column' => [new Row([new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new Name('missing_function')), new ColumnUse(new Name('missing'))]), 1305];
        yield 'column before later routine' => [new Row([new ColumnUse(new Name('missing')), new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new Name('missing_function'))]), 1054];
        yield 'arithmetic row' => [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus, new NumberLiteral('0'), new Row([new NumberLiteral('1'), new NumberLiteral('2')])), 1241];
        yield 'earlier scalar name' => [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus, new ColumnUse(new Name('missing')), new Row([new NumberLiteral('1'), new NumberLiteral('2')])), 1054];
        yield 'later scalar name' => [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredArithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus, new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new ColumnUse(new Name('missing'))), 1241];
        yield 'membership earlier name' => [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredMembership(new ColumnUse(new Name('missing')), [new Row([new NumberLiteral('1'), new NumberLiteral('2')])]), 1054];
        yield 'membership later name' => [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredMembership(new NumberLiteral('0'), [new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new ColumnUse(new Name('missing'))]), 1241];
        yield 'grouped row' => [new \SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow(new Row([new NumberLiteral('1'), new NumberLiteral('2')])), 1241];
    }

    #[DataProvider('providerOperands')]
    public function testRaiseBindsStoredOperandsBeforeTheirScalarUse(RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $operand, int $code): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->text = 'Unrelated request label';
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode($code);
        (new RowUse())->raise($operand, $session);
    }

    public function testPrepareDoesNotEvaluateTheRetainedAssignments(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $assignment = new \SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment(new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new Name('x')), new NumberLiteral('9'));
        $row = new Row([$assignment, new NumberLiteral('2')]);
        $derivation = new \SqlSemantics\Construction\Derivation($session->semantics()->context([], true, new \SqlSemantics\Contract\SearchPath('d'), $session->resolution()));
        $derivation->scalar($row, $derivation->environment());
        $evaluation = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $evaluation);
        $planner = new \MySqlMemory\Plan\Planner($row, $derivation->facts(), $session->settings(), $connection, $session->instance->dictionary);
        (new RowUse())->prepare($row, $planner->compiler, new \MySqlMemory\Evaluation\Scope());

        self::assertArrayHasKey('x', $session->variables->user);
        self::assertNull($session->variables->user['x']->value);
    }


    public function testMembershipChecksEachShapeBeforeBindingLaterMembers(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $value = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredMembership(new NumberLiteral('0'), [new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new ColumnUse(new Name('later'))]);
        $derivation = new \SqlSemantics\Construction\Derivation($session->semantics()->context([], true));
        $derivation->scalar($value, $derivation->environment());
        $evaluation = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $evaluation);
        $planner = new \MySqlMemory\Plan\Planner($value, $derivation->facts(), $session->settings(), $connection, $session->instance->dictionary);
        $this->expectExceptionCode(1241);
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        (new RowUse())->membership($value, $planner->compiler, new \MySqlMemory\Evaluation\Scope());
    }
}
