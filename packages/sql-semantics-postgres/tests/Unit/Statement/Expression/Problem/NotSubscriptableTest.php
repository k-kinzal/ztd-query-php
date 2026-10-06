<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NotSubscriptable;

#[CoversClass(NotSubscriptable::class)]
#[Small]
final class NotSubscriptableTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('Cannot subscript type integer because it does not support subscripting.', (new NotSubscriptable('integer'))->message());
    }
}
