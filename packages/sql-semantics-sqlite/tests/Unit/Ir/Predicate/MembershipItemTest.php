<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipItem;

#[CoversClass(MembershipItem::class)]
#[Small]
final class MembershipItemTest extends TestCase
{
    public function testOwnsOneScalarCandidate(): void
    {
        $null = new NullLiteral();

        self::assertSame($null, (new MembershipItem($null))->expression);
    }
}
