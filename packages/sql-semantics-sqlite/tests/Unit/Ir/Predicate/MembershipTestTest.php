<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipItem;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;

#[CoversClass(MembershipTest::class)]
#[Small]
final class MembershipTestTest extends TestCase
{
    public function testDetachesOrderedMembersFromInputReferences(): void
    {
        $operand = new IntegerLiteral('2');
        $first = new MembershipItem($operand);
        $reference = $first;
        $last = new MembershipItem(new IntegerLiteral('3'));
        $test = new MembershipTest($operand, [&$reference, $last, $first], true);
        $reference = $last;

        self::assertSame([$first, $last, $first], $test->items);
        self::assertSame($operand, $test->operand);
        self::assertTrue($test->negated);
    }
}
