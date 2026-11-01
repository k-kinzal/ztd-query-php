<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinement;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(WindowRefinement::class)]
#[Small]
final class WindowRefinementTest extends TestCase
{
    public function testMessageDerivesTheConstraintFromItsCompleteWindow(): void
    {
        $window = new M\Call\Window\WindowSpec(new Name('w'), partition: [new M\Query\OrderItem(new M\Literal\NumberLiteral('1.1'))]);
        $problem = new WindowRefinement($window);
        self::assertSame($window, $problem->window);
        self::assertSame('A dependent window cannot define a partition.', $problem->message());
    }
}
