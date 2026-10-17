<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedSignalItems;

#[CoversClass(RefusedSignalItems::class)]
#[Small]
final class RefusedSignalItemsTest extends TestCase
{
    public function testMessageKeepsTheOperandRefusal(): void
    {
        $problem = new RefusedSignalItems(new Resignal(null), new RefusedTemporalLiteral('bad'));

        self::assertSame('Incorrect DATE value.', $problem->message());
    }
}
