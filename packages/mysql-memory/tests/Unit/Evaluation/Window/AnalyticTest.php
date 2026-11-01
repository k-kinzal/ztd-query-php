<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Window;

use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Window\Analytic;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;

#[CoversClass(Analytic::class)]
#[Small]
final class AnalyticTest extends TestCase
{
    public function testKindCountAndArgumentsAreThoseOfTheCall(): void
    {
        $read = new ColumnRead(Domain::integer(), 0);
        $analytic = new Analytic(WindowFunctionKind::Lag, null, [$read], 2, Domain::integer());

        self::assertSame([WindowFunctionKind::Lag, null, [$read], 2], [$analytic->kind, $analytic->accumulation, $analytic->arguments, $analytic->count]);
    }
}
