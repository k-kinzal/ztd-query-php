<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NoCollation;

#[CoversClass(NoCollation::class)]
#[Small]
final class NoCollationTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('Collations are not supported by type integer.', (new NoCollation('integer'))->message());
    }
}
