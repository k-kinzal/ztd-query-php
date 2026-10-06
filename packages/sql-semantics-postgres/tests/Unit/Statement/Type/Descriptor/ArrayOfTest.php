<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Descriptor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;

#[CoversClass(ArrayOf::class)]
#[Small]
final class ArrayOfTest extends TestCase
{
    public function testNameAppendsBrackets(): void
    {
        self::assertSame('numeric(10,2)[]', (new ArrayOf(new Parameterized(Builtin::Numeric, 10, 2)))->name());
    }
}
