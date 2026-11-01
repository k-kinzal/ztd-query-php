<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\View\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\View\Problem\BodyRestriction;

#[CoversClass(BodyRestriction::class)]
#[Small]
final class BodyRestrictionTest extends TestCase
{
    public function testMessageDistinguishesInputsFromResultDestinations(): void
    {
        self::assertSame(["View's SELECT contains a variable or parameter", "View's SELECT contains a 'INTO' clause"], array_map(static fn (BodyRestriction $problem): string => $problem->message(), BodyRestriction::cases()));
    }
}
