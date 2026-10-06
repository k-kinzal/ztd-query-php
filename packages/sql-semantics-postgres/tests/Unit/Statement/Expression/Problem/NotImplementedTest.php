<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NotImplemented;

#[CoversClass(NotImplemented::class)]
#[Small]
final class NotImplementedTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('UNIQUE predicate is not yet implemented.', (new NotImplemented('UNIQUE predicate'))->message());
    }
}
