<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\Coalescing;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(Coalescing::class)]
#[Small]
final class CoalescingTest extends TestCase
{
    public function testNullabilityIsNotNullWhenAnOperandIsNotNull(): void
    {
        $coalescing = new Coalescing();
        self::assertSame(Nullability::NotNull, $coalescing->nullability([new ScalarFact(new NullOnly(), Nullability::NotNull), new ScalarFact(new Known(Builtin::Int4), Nullability::NotNull)]));
        self::assertSame(Nullability::Nullable, $coalescing->nullability([new ScalarFact(new Known(Builtin::Int4), Nullability::Nullable)]));
        self::assertSame(Nullability::Dependent, $coalescing->nullability([new ScalarFact(new Known(Builtin::Int4), Nullability::Nullable), new ScalarFact(new Known(Builtin::Int4), Nullability::Dependent)]));
    }
}
