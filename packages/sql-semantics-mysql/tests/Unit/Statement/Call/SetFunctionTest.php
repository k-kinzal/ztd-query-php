<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\SetFunction;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Identifier\Name;

#[CoversNothing]
#[Small]
final class SetFunctionTest extends TestCase
{
    public function testAggregatesTellsAnAggregateFromItsWindowedForm(): void
    {
        self::assertTrue((new GroupConcat([new NumberLiteral('1')]))->aggregates());
        self::assertFalse((new Aggregate(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Minimum, [new NumberLiteral('1')], over: new Name('w')))->aggregates());
        self::assertContains(SetFunction::class, class_implements(Aggregate::class));
    }

    public function testOverAnswersTheWindowOfAWindowedSetFunction(): void
    {
        $window = new Name('w');
        $windowed = new Aggregate(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Sum, [new NumberLiteral('1')], over: $window);
        $plain = new GroupConcat([new NumberLiteral('1')]);

        self::assertSame($window, $windowed->over());
        self::assertNull($plain->over());
    }
}
