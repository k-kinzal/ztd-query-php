<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;

#[CoversClass(IntegerMembership::class)]
#[Small]
final class IntegerMembershipTest extends TestCase
{
    public function testAlternativesRetainDuplicatesAndDetachCallerReferences(): void
    {
        $choice = new RangeIntegerLiteral('2147483648');
        $original = $choice;
        $test = new IntegerMembership(new NullLiteral(), [&$choice, $choice], true);
        $choice = new NullLiteral();

        self::assertSame([$original, $original], $test->alternatives);
        self::assertTrue($test->negated);
    }
}
