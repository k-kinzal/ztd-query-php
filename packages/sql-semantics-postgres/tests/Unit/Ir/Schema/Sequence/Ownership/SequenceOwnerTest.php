<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence\Ownership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Ownership\SequenceOwner;

#[CoversClass(SequenceOwner::class)]
final class SequenceOwnerTest extends TestCase
{
    public function testQuotedDelimitsEveryOwnerCoordinateIndependently(): void
    {
        $owner = new SequenceOwner(new Identifier('a."b"'), new Identifier('sales.棚'), new Identifier('ref'));

        self::assertSame('"a.""b"""."sales.棚"."ref"', $owner->quoted());
    }
}
