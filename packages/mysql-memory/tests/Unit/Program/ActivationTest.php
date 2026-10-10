<?php

declare(strict_types=1);

namespace Tests\Unit\Program;

use MySqlMemory\Program\Activation;
use MySqlMemory\Program\Row;
use MySqlMemory\Program\Variable;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Activation::class)]
#[Small]
final class ActivationTest extends TestCase
{
    public function testRowsAnswersTheNamesAndTypesInScope(): void
    {
        $activation = new Activation('PROCEDURE', 'd.p', false, Collation::known('utf8mb4_0900_ai_ci'));
        $activation->scope[] = new Row(new ParameterList([]), [new Variable('x', Domain::integer())]);

        self::assertSame('x', $activation->rows()[0]->names[0]->value);
    }

    public function testVariableFindsTheInnermostVariableWithoutRegardToCase(): void
    {
        $activation = new Activation('PROCEDURE', 'd.p', false, Collation::known('utf8mb4_0900_ai_ci'));
        $outer = new Variable('x', Domain::integer(), 1);
        $inner = new Variable('X', Domain::integer(), 2);
        $activation->scope = [new Row(new ParameterList([]), [$outer]), new Row(new ParameterList([]), [$inner]), new Row(new ParameterList([]), [new Variable('x', Domain::integer(), 3)], 'NEW')];

        self::assertSame($inner, $activation->variable('x'));
    }

    public function testFindAnswersTheVariableOfTheRowARelationDeclares(): void
    {
        $activation = new Activation('PROCEDURE', 'd.p', false, Collation::known('utf8mb4_0900_ai_ci'));
        $relation = new ParameterList([]);
        $variable = new Variable('a', Domain::integer());
        $activation->scope = [new Row($relation, [$variable]), new Row(new ParameterList([]), [new Variable('a', Domain::integer())])];

        self::assertSame([$variable, null], [$activation->find($relation, 'A'), $activation->find(new ParameterList([]), 'a')]);
    }

    public function testRowFindsTheNewOrOldRow(): void
    {
        $activation = new Activation('TRIGGER', 'd.t', true, Collation::known('utf8mb4_0900_ai_ci'));
        $new = new Row(new ParameterList([]), [], 'NEW', true);
        $activation->scope = [$new];

        self::assertSame([$new, null], [$activation->row('new'), $activation->row('OLD')]);
    }

    public function testMarkCountsWhatIsInScope(): void
    {
        $activation = new Activation('PROCEDURE', 'd.p', false, Collation::known('utf8mb4_0900_ai_ci'));
        $activation->scope[] = new Row(new ParameterList([]), []);

        self::assertSame([1, 0, 0, 0], $activation->mark());
    }

    public function testLeaveForgetsTheDeclarationsMadeSinceAMark(): void
    {
        $activation = new Activation('PROCEDURE', 'd.p', false, Collation::known('utf8mb4_0900_ai_ci'));
        $activation->scope[] = new Row(new ParameterList([]), []);
        $mark = $activation->mark();
        $activation->scope[] = new Row(new ParameterList([]), []);

        $activation->leave($mark);

        self::assertCount(1, $activation->scope);
    }

    public function testDepthCountsTheActivationsOfAProgram(): void
    {
        $collation = Collation::known('utf8mb4_0900_ai_ci');
        $first = new Activation('PROCEDURE', 'd.p', false, $collation);
        $second = new Activation('PROCEDURE', 'd.q', false, $collation, $first);
        $third = new Activation('PROCEDURE', 'D.P', false, $collation, $second);

        self::assertSame([2, 1, 0], [$third->depth('PROCEDURE', 'd.p'), $third->depth('PROCEDURE', 'd.q'), $third->depth('FUNCTION', 'd.p')]);
    }
}
