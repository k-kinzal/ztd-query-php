<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain;

#[CoversClass(RefusedConnectionExplain::class)]
#[Small]
final class RefusedConnectionExplainTest extends TestCase
{
    public function testMessageDistinguishesAnalyzeFromInto(): void
    {
        self::assertSame('EXPLAIN ANALYZE FOR CONNECTION is not supported.', (new RefusedConnectionExplain(true))->message());
        self::assertSame('EXPLAIN FOR CONNECTION does not support the INTO clause.', (new RefusedConnectionExplain(false))->message());
    }
}
