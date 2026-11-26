<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Binding\Parameter;

#[CoversClass(Parameter::class)]
#[Small]
final class ParameterTest extends TestCase
{
    public function testToStringEmitsTheNativeIntegerCast(): void
    {
        $parameter = new Parameter(1);

        self::assertSame(1, $parameter->position);
        self::assertSame('CAST($1 AS pg_catalog.int4)', $parameter->toString());
    }
}
