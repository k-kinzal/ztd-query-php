<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\RowArity;

#[CoversClass(RowArity::class)]
#[Small]
final class RowArityTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('an ARRAY subquery needs 1 column(s) but has 2.', (new RowArity('an ARRAY subquery', 1, 2))->message());
    }
}
