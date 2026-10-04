<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\AmbiguousOutputName;

#[CoversClass(AmbiguousOutputName::class)]
#[Small]
final class AmbiguousOutputNameTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('Output column reference a is ambiguous.', (new AmbiguousOutputName('a'))->message());
    }
}
