<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Typing\Prepared\ParameterContexts;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(ParameterContexts::class)]
#[Small]
final class ParameterContextsTest extends TestCase
{
    public function testExpressionsRetainsTheContextAndItsOriginalChildren(): void
    {
        $marker = new Parameter();
        $null = new NullLiteral();
        $sum = new Arithmetic(ArithmeticOperator::Plus, $marker, $null);
        $query = new Select([], [new SelectExpression($sum)]);

        self::assertSame([$sum, $null, $marker], (new ParameterContexts())->expressions($query));
    }

    public function testExpressionsDoesNotTreatStoredEventBodyMarkersAsCommandParameters(): void
    {
        $schedule = new Parameter();
        $body = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new Select([], [new SelectExpression(new Parameter())]));
        $event = new \SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('e')), new \SqlSemantics\Platform\MySql\Statement\Routine\Event\OnceSchedule($schedule), $body);

        self::assertSame([$schedule], (new ParameterContexts())->expressions($event));
    }
}
