<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;

#[CoversClass(FoldedMembership::class)]
#[Small]
final class FoldedMembershipTest extends TestCase
{
    public function testKeepsTheOrderedNumberedRegistrations(): void
    {
        $first = new Parameter('02');
        $again = new Parameter('2');
        $value = new FoldedMembership(true, [$first, $again]);

        self::assertTrue($value->matches);
        self::assertSame([$first, $again], $value->bindings);
    }
}
