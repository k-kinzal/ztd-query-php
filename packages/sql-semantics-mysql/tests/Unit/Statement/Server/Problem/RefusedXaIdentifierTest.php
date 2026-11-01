<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedXaIdentifier;

#[CoversClass(RefusedXaIdentifier::class)]
#[Small]
final class RefusedXaIdentifierTest extends TestCase
{
    public function testMessageDescribesTheIdentifierDomain(): void
    {
        $problem = new RefusedXaIdentifier(12, true);

        self::assertSame(['An XA identifier exceeds its representation limits.', 12, true], [$problem->message(), $problem->offset, $problem->following]);
    }

    public function testValueRefusesNegativePositions(): void
    {
        $this->expectExceptionMessage('An XA refusal has a nonnegative input position.');
        new RefusedXaIdentifier(-1);
    }
}
