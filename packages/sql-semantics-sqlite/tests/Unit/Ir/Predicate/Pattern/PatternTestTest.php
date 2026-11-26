<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate\Pattern;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternKind;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;

#[CoversClass(PatternTest::class)]
#[Small]
final class PatternTestTest extends TestCase
{
    public function testKeepsTheBuiltInRoles(): void
    {
        $subject = new TextLiteral('a');
        $pattern = new TextLiteral('A%');
        $escape = new TextLiteral('!');
        $test = new PatternTest(PatternKind::AsciiLike, $subject, $pattern, true, $escape);

        self::assertSame($subject, $test->subject);
        self::assertSame($pattern, $test->pattern);
        self::assertSame($escape, $test->escape);
        self::assertTrue($test->negated);
    }
}
