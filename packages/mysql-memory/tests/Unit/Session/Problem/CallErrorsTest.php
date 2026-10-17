<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Session\Problem\CallErrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;

#[CoversClass(CallErrors::class)]
#[Small]
final class CallErrorsTest extends TestCase
{
    public function testErrorReportsTheWindowConstraintWhenNoEnclosingLabelIsAvailable(): void
    {
        $problem = new WindowConstraint(new WindowSpec(order: [new OrderItem(new NumberLiteral('1'))]), true);
        $error = (new CallErrors())->error($problem);
        self::assertNotNull($error);
        self::assertSame(3592, $error->getCode());
        self::assertSame("Window '<unnamed window>': " . $problem->message(), $error->getMessage());
    }
}
