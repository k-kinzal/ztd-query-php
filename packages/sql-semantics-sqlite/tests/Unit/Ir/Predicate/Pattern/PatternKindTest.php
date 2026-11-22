<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate\Pattern;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternKind;

#[CoversClass(PatternKind::class)]
#[Small]
final class PatternKindTest extends TestCase
{
    public function testDefinesTheNativeOperators(): void
    {
        self::assertSame('LIKE', PatternKind::AsciiLike->value);
        self::assertSame('GLOB', PatternKind::Glob->value);
    }
}
