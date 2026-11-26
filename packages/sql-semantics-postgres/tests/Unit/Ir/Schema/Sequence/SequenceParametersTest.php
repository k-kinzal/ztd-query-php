<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceParameters;

#[CoversClass(SequenceParameters::class)]
final class SequenceParametersTest extends TestCase
{
    public function testDescendingDefaultsAndSeparateRestart(): void
    {
        $values = new SequenceParameters(IntegerStorage::Standard, new WideIntegerLiteral('-3'), initial: new WideIntegerLiteral('-17'));

        self::assertSame('-2147483648', $values->minimum->decimal);
        self::assertSame('-1', $values->maximum->decimal);
        self::assertSame('-1', $values->start->decimal);
        self::assertSame('-17', $values->initial->decimal);
    }

    public function testRejectsAnOutOfRangeRestart(): void
    {
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('The sequence start and restart must be within its limits.');

        new SequenceParameters(IntegerStorage::Small, initial: new WideIntegerLiteral('32768'));
    }
}
