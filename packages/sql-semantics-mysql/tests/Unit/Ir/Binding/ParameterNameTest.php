<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Binding\ParameterName;

#[CoversClass(ParameterName::class)]
#[Small]
final class ParameterNameTest extends TestCase
{
    public function testValuePreservesCaseAndNumericNames(): void
    {
        self::assertSame('Arg_1', (new ParameterName('Arg_1'))->value);
        self::assertSame('0', (new ParameterName('0'))->value);
    }

    public function testBindingNameRejectsTheMarkerDelimiter(): void
    {
        $this->expectExceptionMessage('A parameter name requires ASCII letters, digits or underscores.');

        new ParameterName(':value');
    }
}
