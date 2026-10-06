<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\From\JoinedInput;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JoinedInput::class)]
#[Small]
final class JoinedInputTest extends TestCase
{
    public function testOfAnswersTheShapeTheStarSelects(): void
    {
        $slot = new OutputSlot(new Name('a'), new Known(new Integral(IntegralKind::Int)), Nullability::NotNull);
        $input = JoinedInput::of([new VisibleRelation(new Dual(), new RowShape([$slot], [new SessionState('x')]))], [[0, 0]]);

        self::assertSame([$slot], $input->fact->shape->slots);
        self::assertFalse($input->fact->shape->complete());
    }

    public function testSlotAnswersTheSlotOfAnEntry(): void
    {
        $slot = new OutputSlot(new Name('a'), new Known(new Integral(IntegralKind::Int)), Nullability::NotNull);

        self::assertSame($slot, JoinedInput::of([new VisibleRelation(new Dual(), new RowShape([$slot]))], [[0, 0]])->slot([0, 0]));
    }

    public function testCompleteTellsWhetherEveryColumnIsKnown(): void
    {
        self::assertTrue(JoinedInput::of([new VisibleRelation(new Dual(), new RowShape([]))], [])->complete());
        self::assertFalse(JoinedInput::of([new VisibleRelation(new Dual(), new RowShape([], [new SessionState('x')]))], [])->complete());
    }

    public function testCompleteTreatsANameThatDependsOnInputsAsUndecided(): void
    {
        $unnamed = new OutputSlot(null, new Known(new Integral(IntegralKind::Int)), Nullability::NotNull, null, null, [new SessionState('character_set_client')]);

        self::assertFalse(JoinedInput::of([new VisibleRelation(new Dual(), new RowShape([$unnamed]))], [[0, 0]])->complete());
    }
}
