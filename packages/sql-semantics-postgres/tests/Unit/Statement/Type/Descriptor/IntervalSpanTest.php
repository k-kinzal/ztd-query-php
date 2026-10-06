<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Descriptor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\IntervalSpan;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalFields;

#[CoversClass(IntervalSpan::class)]
#[Small]
final class IntervalSpanTest extends TestCase
{
    public function testNameWritesFieldsAndPrecision(): void
    {
        self::assertSame('interval year to month', (new IntervalSpan(IntervalFields::YearToMonth))->name());
        self::assertSame('interval(3)', (new IntervalSpan(null, 3))->name());
        self::assertSame('interval second(0)', (new IntervalSpan(IntervalFields::Second, 0))->name());
    }

    public function testRejectsAPrecisionWithoutSeconds(): void
    {
        $this->expectExceptionMessage('Only an interval with a seconds field has a precision next to its fields.');
        new IntervalSpan(IntervalFields::Day, 3);
    }
}
