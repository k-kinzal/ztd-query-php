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
    public function testErrorRetainsTheImpossibleJsonTarget(): void
    {
        $problem = new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\InvalidJsonCast(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Point);
        $error = (new CallErrors())->error($problem);
        self::assertNotNull($error);
        self::assertSame(4032, $error->getCode());
        self::assertSame('Invalid cast from JSON to POINT.', $error->getMessage());
    }

    public function testErrorReportsTheWindowConstraintWhenNoEnclosingLabelIsAvailable(): void
    {
        $problem = new WindowConstraint(new WindowSpec(order: [new OrderItem(new NumberLiteral('1'))]), true);
        $error = (new CallErrors())->error($problem);
        self::assertNotNull($error);
        self::assertSame(3592, $error->getCode());
        self::assertSame("Window '<unnamed window>': " . $problem->message(), $error->getMessage());
    }
    public function testFrameRetainsTheOwningWindowName(): void
    {
        $window = new WindowSpec(frame: new \SqlSemantics\Platform\MySql\Statement\Call\Window\Frame(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Rows, new \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound(\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::Preceding, new NumberLiteral('1.5'))));
        $problem = new \SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameConstraint($window, \SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameCondition::Offset);
        $error = (new CallErrors())->frame($problem, 'w');
        self::assertSame(3586, $error->getCode());
        self::assertStringStartsWith("Window 'w':", $error->getMessage());
    }

    public function testRefinementPreservesTheIndependentPartitionCondition(): void
    {
        $window = new WindowSpec(new \SqlSemantics\Statement\Identifier\Name('w'), partition: [new OrderItem(new NumberLiteral('1.1'))]);
        $problem = new \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinement($window);
        self::assertSame(\MySqlMemory\Error\Family\QueryError::WindowDependentPartitioning->error()->getMessage(), (new CallErrors())->refinement($problem)->getMessage());
    }
}
