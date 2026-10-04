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
        self::assertFalse((new GroupConcat([new NumberLiteral('1')], over: new Name('w')))->aggregates());
        self::assertContains(SetFunction::class, class_implements(Aggregate::class));
    }
}
