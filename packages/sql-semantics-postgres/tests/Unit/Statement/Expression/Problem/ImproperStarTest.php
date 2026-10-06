<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\ImproperStar;

#[CoversClass(ImproperStar::class)]
#[Small]
final class ImproperStarTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('Improper use of "*".', (new ImproperStar())->message());
    }
}
