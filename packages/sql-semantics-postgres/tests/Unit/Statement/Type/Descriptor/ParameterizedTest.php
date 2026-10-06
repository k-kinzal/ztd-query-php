<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Descriptor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;

#[CoversClass(Parameterized::class)]
#[Small]
final class ParameterizedTest extends TestCase
{
    public function testNamePutsTheModifiersWhereTheServerDisplaysThem(): void
    {
        self::assertSame('numeric(10,2)', (new Parameterized(Builtin::Numeric, 10, 2))->name());
        self::assertSame('timestamp(3) with time zone', (new Parameterized(Builtin::Timestamptz, 3))->name());
        self::assertSame('character varying(5)', (new Parameterized(Builtin::Varchar, 5))->name());
    }

    public function testRejectsNoModifier(): void
    {
        $this->expectExceptionMessage('A parameterized type has at least one modifier.');
        new Parameterized(Builtin::Numeric);
    }
}
