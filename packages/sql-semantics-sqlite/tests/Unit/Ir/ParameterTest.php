<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;

#[CoversClass(Parameter::class)]
#[Small]
final class ParameterTest extends TestCase
{
    public function testPreservesLabelAndDerivesSlot(): void
    {
        $parameter = new Parameter('00032766');
        self::assertSame('00032766', $parameter->label);
        self::assertSame(32766, $parameter->slot);
    }

    public function testRejectsZero(): void
    {
        $this->expectExceptionMessage('between 1 and 32766');

        new Parameter('000');
    }
}
